import { Component, OnInit, OnDestroy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';

interface Schedule {
  payment_type: string;
  total_assessment: number;
  prelim_due: number;  midterm_due: number;  finals_due: number;
  prelim_paid: number; midterm_paid: number; finals_paid: number;
  prelim_status: string; midterm_status: string; finals_status: string;
  downpayment_paid: number;
  total_paid?: number;  // server-side sum of all installment_payments — always accurate
}

interface Notice {
  exam_period: string; amount_due: number; due_date: string;
  message: string; sent_at: string; is_read: number;
}

interface Permit {
  id: number; exam_period: string; school_year: string;
  semester: string; status: string; requested_at: string;
  approved_at: string; remarks: string;
  // populated by get_permit_details
  student_number?: string; first_name?: string; last_name?: string;
  program?: string; year_level?: string;
  approved_by_first?: string; approved_by_last?: string;
  courses?: { code: string; name: string; instructor: string }[];
}

@Component({
  selector: 'app-payment-schedule',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './payment-schedule.html',
  styleUrl: './payment-schedule.css'
})
export class PaymentSchedule implements OnInit, OnDestroy {
  private apiUrl      = 'http://localhost/sia-api/accounting.php';
  private enrollApi   = 'http://localhost/sia-api/enrollment.php';

  // BUG FIX: use students-table PK (studentDbId), NOT user.id
  studentId   = 0;
  studentInfo: any = {};

  schedule: Schedule | null = null;
  notices: Record<string, Notice> = {};
  permits: Permit[] = [];
  isLoading    = true;
  studentCategory = '';   // 'SHS' | 'TVET' | '' (College)
  studentType     = '';   // 'New' | 'Old' | 'Transferee'
  // Free only when SHS/TVET AND not a Transferee (Transferees pay ₱20k)
  get isFreeStudent(): boolean {
    const isSHSTVET = this.studentCategory === 'SHS' || this.studentCategory === 'TVET';
    return isSHSTVET && this.studentType !== 'Transferee';
  }
  isRequesting = false;
  msg = ''; msgType: 'ok'|'err' = 'ok';

  periods = ['Prelim','Midterm','Finals'] as const;

  // ── Payment modal ────────────────────────────────────────────────────────
  showPayModal   = false;
  payPeriod      = '';
  payAmount      = 0;
  payMethod: 'Cash'|'GCash' = 'Cash';
  payGcashRef    = '';
  payNote        = '';
  payDate        = '';
  isSubmitting   = false;
  payMsg         = '';
  payMsgType: 'ok'|'err' = 'ok';
  // After submitting — waiting for accounting approval
  paySubmitted   = false;
  payOrArNumber  = '';
  isPollingApproval = false;
  private pollTimer: any = null;

  // ── Payment due dates (exam schedule) ───────────────────────────────────
  dueDates: Record<string, { label: string; date_range: string }> = {};

  // ── Permit viewer ────────────────────────────────────────────────────────
  showPermitViewer  = false;
  viewingPermit: Permit | null = null;
  isLoadingPermit   = false;

  
  /** Returns HTTP headers with the auth token. Call this in every API request. */
  private getHeaders() {
    const token = sessionStorage.getItem('token') ?? '';
    return { headers: { Authorization: `Bearer ${token}` } };
  }

  constructor(private http: HttpClient, private cdr: ChangeDetectorRef) {}

  ngOnInit(): void {
    const s = sessionStorage.getItem('currentUser');
    if (s) { const u = JSON.parse(s); this.studentId = u.id; this.studentInfo = u; }

    const dbId = sessionStorage.getItem('studentDbId');
    if (dbId) this.studentId = parseInt(dbId, 10);

    this.payDate = new Date().toISOString().split('T')[0];

    // ── Restore payment-submitted state so reload doesn't clear "awaiting approval" screen ──
    const pendingKey = `paySchedulePending_${this.studentId}`;
    const pendingRaw = sessionStorage.getItem(pendingKey);
    if (pendingRaw) {
      try {
        const pending = JSON.parse(pendingRaw);
        this.paySubmitted      = true;
        this.payPeriod         = pending.period   || '';
        this.payOrArNumber     = pending.orArNumber || '';
        this.payAmount         = pending.amount   || 0;
        this.showPayModal      = true;
        this.isPollingApproval = true;
        // Restart polling — if already approved since last load, poll will close modal
        this.startApprovalPolling();
      } catch { sessionStorage.removeItem(pendingKey); }
    }

    // Always fetch fresh from API — do not rely on sessionStorage cache
    // load() is called inside the callback so category is set before rendering
    this.http.get<any>(`${this.enrollApi}?action=get_student_context&student_id=${this.studentId}`, this.getHeaders()).subscribe({
      next: (res) => {
        if (res.success) {
          const cat = (res.student?.studentCategory ?? '').toUpperCase();
          this.studentType = res.student?.studentType ?? '';
          // Fallback: infer from student number if DB category is blank
          const studentNum: string = res.student?.id ?? '';
          if (cat) {
            this.studentCategory = cat;
          } else if (studentNum.startsWith('SHS-')) {
            this.studentCategory = 'SHS';
          } else if (studentNum.startsWith('TVET-')) {
            this.studentCategory = 'TVET';
          }
          sessionStorage.setItem('studentCategory', this.studentCategory);
        }
        this.load();
        this.cdr.detectChanges();
      },
      error: () => {
        // Fallback: try sessionStorage then just load
        const cached = sessionStorage.getItem('studentCategory');
        if (cached) this.studentCategory = cached.toUpperCase();
        this.load();
        this.cdr.detectChanges();
      }
    });
  }

  ngOnDestroy(): void {
    if (this.pollTimer) clearInterval(this.pollTimer);
  }

  load(): void {
    this.isLoading = true;
    this.http.get<any>(`${this.apiUrl}?action=get_payment_schedule&student_id=${this.studentId}`, this.getHeaders()).subscribe({
      next: (res) => {
        this.schedule = res.success ? res.schedule : null;
        this.notices  = res.notices  || {};
        this.isLoading = false;
        this.loadPermits();
        this.loadDueDates();
        this.cdr.detectChanges();
      },
      error: () => { this.isLoading = false; this.cdr.detectChanges(); }
    });
  }

  loadPermits(): void {
    this.http.get<any>(`${this.apiUrl}?action=get_student_permit_status&student_id=${this.studentId}`, this.getHeaders()).subscribe({
      next: (res) => { this.permits = res.success ? res.permits : []; this.cdr.detectChanges(); }
    });
  }

  loadDueDates(): void {
    this.http.get<any>(`${this.apiUrl}?action=get_due_dates`).subscribe({
      next: (res) => { if (res.success && res.dueDates) { this.dueDates = res.dueDates; this.cdr.detectChanges(); } }
    });
  }

  getStatus(period: string): string {
    const p = period.toLowerCase() as any;
    return this.schedule ? (this.schedule as any)[p+'_status'] : 'locked';
  }

  getDue(period: string):  number { const p=period.toLowerCase() as any; return this.schedule?(this.schedule as any)[p+'_due']:0; }
  getPaid(period: string): number { const p=period.toLowerCase() as any; return this.schedule?(this.schedule as any)[p+'_paid']:0; }
  getBalance(period: string): number { return Math.max(0, this.getDue(period) - this.getPaid(period)); }

  get totalPaid(): number {
    if (!this.schedule) return 0;
    // Use server-computed total_paid (sum of all installment_payments) — accurate even
    // when a student overpays a term or pays into a not-yet-unlocked period.
    if (this.schedule.total_paid !== undefined && this.schedule.total_paid > 0) {
      return this.schedule.total_paid;
    }
    // Fallback: sum period fields (less accurate but safe)
    return (this.schedule.downpayment_paid || 0)
         + (this.schedule.prelim_paid  || 0)
         + (this.schedule.midterm_paid || 0)
         + (this.schedule.finals_paid  || 0);
  }

  get remainingBalance(): number {
    if (!this.schedule) return 0;
    return Math.max(0, (this.schedule.total_assessment || 0) - this.totalPaid);
  }

  getNotice(period: string): Notice | null { return this.notices[period] || null; }
  getPermit(period: string): Permit | undefined { return this.permits.find(p => p.exam_period === period); }

  canRequest(period: string): boolean {
    const status = this.getStatus(period);
    // Allow permit request if paid OR partial (any amount paid counts)
    if (status !== 'paid' && status !== 'partial') return false;
    const permit = this.getPermit(period);
    if (permit && (permit.status === 'pending' || permit.status === 'approved')) return false;
    return true;
  }

  requestPermit(period: string): void {
    this.isRequesting = true;
    this.http.post<any>(`${this.apiUrl}?action=request_exam_permit`, {
      student_id:  this.studentId,
      exam_period: period,
      school_year: this.studentInfo.school_year || '2025-2026',
      semester:    this.studentInfo.semester    || '2nd Semester'
    }, this.getHeaders()).subscribe({
      next: (res) => {
        this.isRequesting = false;
        this.msg     = res.message;
        this.msgType = res.success ? 'ok' : 'err';
        if (res.success) this.loadPermits();
        this.cdr.detectChanges();
        setTimeout(() => { this.msg = ''; this.cdr.detectChanges(); }, 5000);
      },
      error: () => { this.isRequesting = false; this.cdr.detectChanges(); }
    });
  }

  // ── Payment modal ─────────────────────────────────────────────────────────
  openPayModal(period: string): void {
    this.payPeriod    = period;
    this.payAmount    = 0;          // student enters their own amount — no minimum enforced
    this.payMethod    = 'Cash';
    this.payGcashRef  = '';
    this.payNote      = '';
    this.payDate      = new Date().toISOString().split('T')[0];
    this.payMsg       = '';
    this.paySubmitted = false;
    this.payOrArNumber = '';
    if (this.pollTimer) { clearInterval(this.pollTimer); this.pollTimer = null; }
    this.isPollingApproval = false;
    this.showPayModal = true;
    this.cdr.detectChanges();
  }

  closePayModal(): void {
    if (this.isSubmitting) return;
    if (this.pollTimer) { clearInterval(this.pollTimer); this.pollTimer = null; }
    // Clear persisted pending state when user manually closes the modal
    sessionStorage.removeItem(`paySchedulePending_${this.studentId}`);
    this.showPayModal = false;
    this.paySubmitted = false;
    this.isPollingApproval = false;
    this.cdr.detectChanges();
  }

  submitPayment(): void {
    if (!this.payAmount || this.payAmount <= 0) {
      this.payMsg = 'Please enter a valid amount.'; this.payMsgType = 'err';
      this.cdr.detectChanges(); return;
    }
    if (this.payMethod === 'GCash' && !this.payGcashRef.trim()) {
      this.payMsg = 'GCash reference number is required.'; this.payMsgType = 'err';
      this.cdr.detectChanges(); return;
    }

    this.isSubmitting = true;
    this.payMsg = '';

    // Submit payment — goes to Accounting for approval (same as enrollment flow)
    this.http.post<any>(`${this.apiUrl}?action=submit_installment_payment`, {
      student_id:         this.studentId,
      amount:             this.payAmount,
      payment_date:       this.payDate,
      payment_method:     this.payMethod,
      gcash_reference:    this.payGcashRef.trim(),
      exam_period:        this.payPeriod,
      notes:              this.payNote.trim(),
    }, this.getHeaders()).subscribe({
      next: (res) => {
        this.isSubmitting = false;
        if (res.success) {
          this.paySubmitted  = true;
          this.payOrArNumber = res.orArNumber || '';
          this.payMsg        = '';
          // Persist state so reload doesn't clear "awaiting approval" screen
          const pendingKey = `paySchedulePending_${this.studentId}`;
          sessionStorage.setItem(pendingKey, JSON.stringify({
            period:      this.payPeriod,
            orArNumber:  this.payOrArNumber,
            amount:      this.payAmount,
          }));
          // Start polling for accounting approval
          this.startApprovalPolling();
        } else {
          this.payMsg     = res.message || 'Failed to submit payment.';
          this.payMsgType = 'err';
        }
        this.cdr.detectChanges();
      },
      error: () => {
        this.isSubmitting = false;
        this.payMsg     = 'Cannot connect to server.';
        this.payMsgType = 'err';
        this.cdr.detectChanges();
      }
    });
  }

  // Poll accounting API every 5s to check if payment was approved
  startApprovalPolling(): void {
    // Prevent duplicate intervals
    if (this.pollTimer) { clearInterval(this.pollTimer); this.pollTimer = null; }
    this.isPollingApproval = true;
    // Check immediately on start
    this.checkApprovalPoll();
    this.pollTimer = setInterval(() => this.checkApprovalPoll(), 5000);
  }

  private checkApprovalPoll(): void {
    this.http.get<any>(`${this.apiUrl}?action=get_payment_schedule&student_id=${this.studentId}`, this.getHeaders()).subscribe({
      next: (res) => {
        if (!res.success) return;
        const sched = res.schedule;
        const p = this.payPeriod.toLowerCase();
        const newStatus = sched ? sched[p + '_status'] : null;
        // If status changed from unpaid → paid or partial, accounting approved it
        if (newStatus === 'paid' || newStatus === 'partial') {
          clearInterval(this.pollTimer);
          this.pollTimer = null;
          this.isPollingApproval = false;
          this.schedule = sched;
          this.notices  = res.notices || {};
          // Clear persisted pending state
          sessionStorage.removeItem(`paySchedulePending_${this.studentId}`);
          this.loadPermits();
          this.closePayModal();
          this.msg     = `✅ Payment approved by Accounting!`;
          this.msgType = 'ok';
          setTimeout(() => { this.msg = ''; this.cdr.detectChanges(); }, 6000);
          this.cdr.detectChanges();
        }
      }
    });
  }

  // ── Permit viewer ─────────────────────────────────────────────────────────
  openPermitViewer(permit: Permit): void {
    this.isLoadingPermit  = true;
    this.viewingPermit    = permit;
    this.showPermitViewer = true;
    this.cdr.detectChanges();

    // Fetch full permit details including courses + approver name
    this.http.get<any>(`${this.apiUrl}?action=get_permit_details&permit_id=${permit.id}&student_id=${this.studentId}`, this.getHeaders()).subscribe({
      next: (res) => {
        this.isLoadingPermit = false;
        if (res.success) {
          this.viewingPermit = { ...permit, ...res.permit };
        }
        this.cdr.detectChanges();
      },
      error: () => { this.isLoadingPermit = false; this.cdr.detectChanges(); }
    });
  }

  closePermitViewer(): void {
    this.showPermitViewer = false;
    this.viewingPermit = null;
    this.cdr.detectChanges();
  }

  printPermit(): void {
    const p = this.viewingPermit;
    if (!p) return;
    const examDate = this.dueDates[p.exam_period.toLowerCase()]?.date_range || '';
    const courses = (p.courses || []).map(c =>
      `<tr><td>${c.code} — ${c.name}</td><td></td></tr>`
    ).join('');
    const extraRows = Math.max(0, 8 - (p.courses?.length || 0));
    const blankRows = Array(extraRows).fill('<tr><td>&nbsp;</td><td></td></tr>').join('');

    const html = `<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>${p.exam_period} Examination Permit</title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: Arial, sans-serif; padding: 28px 32px; font-size: 12px; color: #000; }

    /* Header */
    .header { display: flex; align-items: center; gap: 14px; padding-bottom: 8px; border-bottom: 2.5px solid #000; margin-bottom: 8px; }
    .logo-circle { width: 62px; height: 62px; border: 2px solid #888; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 9px; text-align: center; color: #555; font-style: italic; }
    .school-info { flex: 1; }
    .school-name { font-size: 16px; font-weight: 900; text-transform: uppercase; letter-spacing: 1px; }
    .school-sub  { font-size: 9px; color: #444; margin-top: 1px; }
    .school-addr { font-size: 9px; color: #444; }
    .reg-badges  { display: flex; flex-direction: column; gap: 4px; align-items: flex-end; font-size: 8px; color: #555; }
    .reg-badge   { border: 1px solid #aaa; padding: 2px 6px; border-radius: 3px; white-space: nowrap; }

    /* Blue title bar */
    .permit-bar { background: #1a4fa0; color: white; text-align: center; padding: 7px 10px; margin: 8px 0; }
    .permit-bar-title { font-size: 14px; font-weight: 900; letter-spacing: 2px; }
    .permit-bar-sub   { font-size: 10px; margin-top: 2px; }
    .permit-bar-date  { font-size: 10px; margin-top: 1px; font-style: italic; }

    /* Student info */
    .info-section { display: grid; grid-template-columns: 1fr 1fr; gap: 0; margin: 8px 0; }
    .info-row { display: flex; gap: 4px; padding: 4px 0; border-bottom: 1px solid #ccc; }
    .info-row:nth-child(odd) { padding-right: 12px; }
    .info-label { font-size: 10px; color: #555; white-space: nowrap; }
    .info-value { font-size: 12px; font-weight: 700; border-bottom: 1px solid #000; flex: 1; padding-bottom: 1px; }

    /* Highlight box */
    .highlight-box { background: #1a4fa0; color: white; padding: 4px 10px; font-size: 11px; font-weight: 700; margin: 6px 0; display: inline-block; }

    /* Note */
    .note { color: #c00; font-weight: 700; font-size: 10px; margin: 6px 0; text-align: center; }

    /* Subject table */
    table { width: 100%; border-collapse: collapse; margin-top: 6px; }
    th    { background: #f0f0f0; font-size: 11px; font-weight: 700; padding: 5px 8px; border: 1px solid #333; text-align: left; }
    td    { padding: 5px 8px; border: 1px solid #555; font-size: 11px; height: 22px; }

    /* Signature */
    .sig-area { display: flex; justify-content: flex-start; margin-top: 28px; }
    .sig-block { text-align: center; min-width: 220px; }
    .sig-name  { font-size: 13px; font-weight: 900; color: #1a4fa0; letter-spacing: 0.5px; text-transform: uppercase; }
    .sig-line  { border-top: 1.5px solid #000; margin-top: 6px; padding-top: 4px; font-size: 10px; }

    @media print {
      body { padding: 14px 18px; }
      @page { margin: 10mm; }
    }
  </style>
</head>
<body>

  <!-- HEADER -->
  <div class="header">
    <div class="logo-circle">ST.<br>BENILDE<br>LOGO</div>
    <div class="school-info">
      <div class="school-name">ST. BENILDE</div>
      <div class="school-sub">CENTER FOR GLOBAL COMPETENCE, INC.</div>
      <div class="school-addr">#247 Rizal Avenue, West Bajac-Bajac, Olongapo City &nbsp;|&nbsp; Tel/Fax: (047) 223-3031</div>
    </div>
    <div class="reg-badges">
      <div class="reg-badge">Registered with:<br>CHED · TESDA · DepEd</div>
    </div>
  </div>

  <!-- PERMIT TITLE BAR -->
  <div class="permit-bar">
    <div class="permit-bar-title">${p.exam_period.toUpperCase()} EXAMINATION PERMIT</div>
    <div class="permit-bar-sub">${p.semester} &nbsp;&nbsp; A.Y. ${p.school_year}</div>
  </div>

  <!-- STUDENT INFO -->
  <div class="info-section">
    <div class="info-row">
      <span class="info-label">Student No.:</span>
      <span class="info-value">${p.student_number || ''}</span>
    </div>
    <div class="info-row">
      <span class="info-label">Date of Exam:</span>
      <span class="info-value">${examDate}</span>
    </div>
    <div class="info-row" style="grid-column:1/-1;">
      <span class="info-label">Name:</span>
      <span class="info-value" style="text-transform:uppercase;">${p.last_name || ''}, ${p.first_name || ''}</span>
    </div>
    <div class="info-row" style="grid-column:1/-1;">
      <span class="info-label">Course/Major:</span>
      <span class="info-value">
        <span class="highlight-box">${p.program || ''}</span>
      </span>
    </div>
  </div>

  <!-- NOTE -->
  <div class="note">NOTE: ANY ERASURE WILL INVALIDATE THIS ${p.exam_period.toUpperCase()} PERMIT</div>

  <!-- SUBJECT TABLE -->
  <table>
    <thead>
      <tr>
        <th style="width:65%">Subject</th>
        <th style="width:35%">Instructor's Signature</th>
      </tr>
    </thead>
    <tbody>
      ${courses}
      ${blankRows}
    </tbody>
  </table>

  <!-- SIGNATURE -->
  <div class="sig-area">
    <div class="sig-block">
      <div class="sig-name">${(p.approved_by_first || '') + ' ' + (p.approved_by_last || '')}</div>
      <div class="sig-line">Account Management Officer / Cashier</div>
    </div>
  </div>

  <script>window.onload = () => { window.print(); }<\/script>
</body>
</html>`;

    const win = window.open('', '_blank', 'width=850,height=900');
    if (!win) return;
    win.document.write(html);
    win.document.close();
  }

  // Expose Math to template
  Math = Math;

  blankRows(courseCount: number): any[] {
    return Array(Math.max(0, 8 - courseCount));
  }

  statusLabel(s: string): string {
    return { locked:'🔒 Locked', unpaid:'Unpaid', partial:'Partial', paid:'✅ Paid' }[s] || s;
  }

  fmt(n: number): string { return (n||0).toLocaleString('en-PH',{minimumFractionDigits:2}); }
  fmtDate(d: string): string { return d ? new Date(d).toLocaleDateString('en-PH',{year:'numeric',month:'short',day:'numeric'}) : '—'; }
  fmtDatetime(d: string): string { return d ? new Date(d).toLocaleString('en-PH',{year:'numeric',month:'short',day:'numeric',hour:'2-digit',minute:'2-digit'}) : '—'; }
}