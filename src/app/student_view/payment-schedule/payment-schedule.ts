import { Component, OnInit, OnDestroy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../environment';
import { PasswordGateService } from '../password-gate/password-gate.service';

interface Schedule {
  payment_type: string;
  total_assessment: number;
  prelim_due: number;  midterm_due: number;  finals_due: number;
  prelim_paid: number; midterm_paid: number; finals_paid: number;
  prelim_status: string; midterm_status: string; finals_status: string;
  downpayment_paid: number;
  total_paid?: number;
  approved_permits?: Record<string, { permit_identifier: string; approved_at: string }>;
}

interface Notice {
  exam_period: string; amount_due: number; due_date: string;
  message: string; sent_at: string; is_read: number;
}

interface Permit {
  id: number; exam_period: string; school_year: string;
  semester: string; status: string; requested_at: string;
  approved_at: string; remarks: string;
  permit_identifier?: string;
  student_number?: string; first_name?: string; last_name?: string;
  program?: string; year_level?: string;
  approved_by_first?: string; approved_by_last?: string;
  courses?: { code: string; name: string; instructor: string }[];
}

interface PaymentRecord {
  id: number;
  orArNumber: string;
  orArType: string;
  amount: number;
  paymentDate: string;
  paymentMethod: string;
  gcashRef: string;
  examPeriod: string;
  notes: string;
  createdAt: string;
  verifiedBy: string;
}

@Component({
  selector: 'app-payment-schedule',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './payment-schedule.html',
  styleUrl: './payment-schedule.css'
})
export class PaymentSchedule implements OnInit, OnDestroy {
  private apiUrl      = environment.accountingApi;
  private enrollApi   = environment.enrollApi;

  studentId   = 0;
  studentInfo: any = {};

  schedule: Schedule | null = null;
  notices: Record<string, Notice> = {};
  permits: Permit[] = [];
  isLoading    = true;

  // ── Password gate inactivity lock (5 min) ─────────────────────────────────
  _locked          = true;
  private _lockTimer: any = null;
  private readonly _LOCK_MS = 300000;

  _startLockTimer(): void {
    this._clearLockTimer();
    this._lockTimer = setTimeout(() => {
      this._locked = true;
      sessionStorage.removeItem('pgv_ts_soa___receipts');
      this.cdr.detectChanges();
    }, this._LOCK_MS);
  }

  _clearLockTimer(): void {
    if (this._lockTimer) { clearTimeout(this._lockTimer); this._lockTimer = null; }
  }

  resetLockTimer(): void {
    if (!this._locked) this._startLockTimer();
  }

  studentCategory = '';
  studentType     = '';

  get isFreeStudent(): boolean {
    const isSHSTVET = this.studentCategory === 'SHS' || this.studentCategory === 'TVET';
    return isSHSTVET && this.studentType.toLowerCase() !== 'transferee';
  }

  isRequesting = false;
  msg = ''; msgType: 'ok'|'err' = 'ok';
  paymentPlan   = 'full';
  paymentStatus = '';

  // ── Scholarship fields ────────────────────────────────────────────────────
  isScholar       = false;
  isFullScholar   = false;
  scholarPending  = false;
  scholarApproved = false;
  scholarType     = '';
  scholarGrantor  = '';
  scholarshipAmount = 0;

  get displayPeriods(): string[] {
    return ['Prelim', 'Midterm', 'Finals'];
  }

  readonly scholarPeriods = ['Prelim', 'Midterm', 'Finals'] as const;

  // ── Tab state ─────────────────────────────────────────────────────────────
  activeTab: 'schedule' | 'history' = 'schedule';

  // ── Payment History ───────────────────────────────────────────────────────
  paymentHistory: PaymentRecord[] = [];
  historyTotalPaid  = 0;
  isLoadingHistory  = false;
  historyLoaded     = false;

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
  paySubmitted   = false;
  payOrArNumber  = '';
  isPollingApproval = false;
  private pollTimer: any = null;

  // ── Pending payment guard ─────────────────────────────────────────────────
  pendingTerms: Set<string> = new Set();

  loadPendingTerms(): void {
    this.http.get<any>(`${this.apiUrl}?action=get_pending_payments`).subscribe({
      next: (res) => {
        this.pendingTerms = new Set();
        if (res.success && res.payments) {
          (res.payments as any[])
            .filter(p => p.studentId === this.studentId && p.status === 'Pending')
            .forEach(p => { if (p.examPeriod) this.pendingTerms.add(p.examPeriod); });
        }
        this.cdr.detectChanges();
      }
    });
  }

  hasPendingPayment(period: string): boolean {
    return this.pendingTerms.has(period);
  }

  dueDates: Record<string, { label: string; date_range: string }> = {};

  // ── Permit viewer ────────────────────────────────────────────────────────
  showPermitViewer  = false;
  viewingPermit: Permit | null = null;
  isLoadingPermit   = false;

  // ── QR Code ──────────────────────────────────────────────────────────────
  // Encodes permit owner info. When scanned: shows student number, name, program, permit ID.
  permitQrDataUrl   = '';

  constructor(private http: HttpClient, private cdr: ChangeDetectorRef, private gate: PasswordGateService) {}

  async ngOnInit(): Promise<void> {
    const verified = await this.gate.requirePassword('SOA & Receipts');
    if (!verified) {
      this.isLoading = false;
      this.cdr.detectChanges();
      return;
    }
    this._locked = false;
    this._startLockTimer();

    const s = sessionStorage.getItem('currentUser');
    if (s) { const u = JSON.parse(s); this.studentId = parseInt(String(u.id), 10) || 0; this.studentInfo = u; }

    const dbId = sessionStorage.getItem('studentDbId');
    if (dbId && parseInt(dbId, 10) > 0) this.studentId = parseInt(dbId, 10);

    this.payDate = new Date().toISOString().split('T')[0];

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
        this.startApprovalPolling();
      } catch { sessionStorage.removeItem(pendingKey); }
    }

    this.http.get<any>(`${this.enrollApi}?action=get_student_context&student_id=${this.studentId}`).subscribe({
      next: (res) => {
        if (res.success) {
          const cat = (res.student?.studentCategory ?? '').toUpperCase();
          this.studentType   = res.student?.studentType   ?? '';
          this.paymentPlan   = res.student?.paymentPlan   === 'installment' ? 'installment' : 'full';
          this.paymentStatus = res.student?.paymentStatus ?? '';

          this.isScholar        = res.student?.isScholar        ?? false;
          this.isFullScholar    = res.student?.isFullScholar     ?? false;
          this.scholarPending   = res.student?.scholarPending    ?? false;
          this.scholarApproved  = res.student?.scholarApproved   ?? false;
          this.scholarType      = res.student?.scholarType       ?? '';
          this.scholarGrantor   = res.student?.scholarGrantor    ?? '';
          this.scholarshipAmount = res.student?.scholarshipAmount ?? 0;

          if (!this.isFullScholar && this.isScholar && this.scholarshipAmount > 0
              && (res.student?.paymentStatus === 'Paid'
                  || res.student?.approvalStatus === 'Approved')) {
            this.isFullScholar = true;
          }

          if (this.isFullScholar) this.paymentPlan = 'full';

          const studentNum: string = res.student?.id ?? '';
          if (cat) {
            this.studentCategory = cat;
          } else if (studentNum.startsWith('SHS-')) {
            this.studentCategory = 'SHS';
          } else if (studentNum.startsWith('TVET-')) {
            this.studentCategory = 'TVET';
          }
          sessionStorage.setItem('studentCategory', this.studentCategory);

          const resolvedDbId = res.student?.dbId ?? 0;
          if (resolvedDbId > 0) {
            this.studentId = resolvedDbId;
            sessionStorage.setItem('studentDbId', String(resolvedDbId));
          }
        }
        this.load();
        this.cdr.detectChanges();
      },
      error: () => {
        const cached = sessionStorage.getItem('studentCategory');
        if (cached) this.studentCategory = cached.toUpperCase();
        this.load();
        this.cdr.detectChanges();
      }
    });
  }

  ngOnDestroy(): void {
    this._clearLockTimer();
    if (this.pollTimer) clearInterval(this.pollTimer);
  }

  load(): void {
    this.isLoading = true;
    this.http.get<any>(`${this.apiUrl}?action=get_payment_schedule&student_id=${this.studentId}`).subscribe({
      next: (res) => {
        this.schedule = res.success ? res.schedule : null;
        this.notices  = res.notices  || {};
        this.isLoading = false;
        if (!this.isFullScholar && this.isScholar
            && this.schedule && (this.schedule.total_assessment ?? 1) <= 0) {
          this.isFullScholar = true;
          this.paymentPlan   = 'full';
        }
        this.loadPermits();
        this.loadDueDates();
        this.loadPendingTerms();
        this.cdr.detectChanges();
      },
      error: () => { this.isLoading = false; this.cdr.detectChanges(); }
    });
  }

  loadPermits(): void {
    this.http.get<any>(`${this.apiUrl}?action=get_student_permit_status&student_id=${this.studentId}`).subscribe({
      next: (res) => { this.permits = res.success ? res.permits : []; this.cdr.detectChanges(); }
    });
  }

  loadDueDates(): void {
    const sid = this.studentId || 0;
    const url = sid > 0
      ? `${this.apiUrl}?action=get_due_dates&student_id=${sid}`
      : `${this.apiUrl}?action=get_due_dates`;
    this.http.get<any>(url).subscribe({
      next: (res) => {
        if (res.success && res.dueDates) {
          const blank: Record<string, { label: string; date_range: string }> = {
            downpayment: { label: 'Downpayment', date_range: '' },
            prelim:      { label: 'Prelim',      date_range: '' },
            midterm:     { label: 'Midterm',     date_range: '' },
            finals:      { label: 'Finals',      date_range: '' },
          };
          this.dueDates = { ...blank, ...res.dueDates };
          this.cdr.detectChanges();
        }
      }
    });
  }

  // ── Payment History ───────────────────────────────────────────────────────

  switchTab(tab: 'schedule' | 'history'): void {
    this.activeTab = tab;
    if (tab === 'history' && !this.historyLoaded) {
      this.loadPaymentHistory();
    }
    this.cdr.detectChanges();
  }

  loadPaymentHistory(): void {
    this.isLoadingHistory = true;
    this.cdr.detectChanges();
    const sem = encodeURIComponent(this.studentInfo?.semester || '');
    this.http.get<any>(`${this.apiUrl}?action=get_student_payment_history&student_id=${this.studentId}&semester=${sem}`).subscribe({
      next: (res) => {
        this.isLoadingHistory = false;
        this.historyLoaded    = true;
        if (res.success) {
          this.paymentHistory  = res.history  || [];
          this.historyTotalPaid = res.totalPaid || 0;
        } else {
          this.paymentHistory  = [];
          this.historyTotalPaid = 0;
        }
        this.cdr.detectChanges();
      },
      error: () => {
        this.isLoadingHistory = false;
        this.historyLoaded    = true;
        this.paymentHistory   = [];
        this.cdr.detectChanges();
      }
    });
  }

  // ─────────────────────────────────────────────────────────────────────────

  get totalAssessmentDisplay(): number {
    return this.schedule?.total_assessment ?? 0;
  }

  getStatus(period: string): string {
    if (period !== 'Full' && this.isPermitCleared(period)) return 'paid';

    if (period === 'Full') {
      if (this.isFullScholar) {
        return ['Prelim','Midterm','Finals'].some(p => this.getNotice(p)) ? 'paid' : 'locked';
      }
      if (this.paymentPlan === 'full') {
        const hasNotice = ['Prelim','Midterm','Finals'].some(p => this.getNotice(p));
        const hasUnlock = this.schedule && (
          (this.schedule as any)['prelim_status']  !== 'locked' ||
          (this.schedule as any)['midterm_status'] !== 'locked' ||
          (this.schedule as any)['finals_status']  !== 'locked'
        );
        if (!(hasNotice || hasUnlock)) return 'locked';
        return this.remainingBalance <= 0 ? 'paid' : 'unpaid';
      }
      return 'locked';
    }

    const p = period.toLowerCase() as any;
    const schedStatus = this.schedule ? (this.schedule as any)[p+'_status'] : 'locked';

    if (this.isFullScholar) {
      if (this.schedule && schedStatus !== 'locked') return 'paid';
      return this.getNotice(period) ? 'paid' : 'locked';
    }

    if (this.paymentPlan === 'full') {
      const hasNotice  = !!this.getNotice(period);
      const isUnlocked = schedStatus !== 'locked';
      if (!(hasNotice || isUnlocked)) return 'locked';
      return this.remainingBalance <= 0 ? 'paid' : 'unpaid';
    }

    return schedStatus;
  }

  getDue(period: string): number {
    if (period === 'Full') return this.schedule ? (this.schedule.total_assessment || 0) : 0;
    if (this.paymentPlan === 'full') {
      return this.schedule ? (this.schedule.total_assessment || 0) : 0;
    }
    const p = period.toLowerCase() as any;
    return this.schedule ? ((this.schedule as any)[p+'_due'] || 0) : 0;
  }

  getPaid(period: string): number {
    if (period === 'Full') return this.totalPaid;
    if (this.paymentPlan === 'full') return this.totalPaid;
    const p = period.toLowerCase() as any;
    return this.schedule ? ((this.schedule as any)[p+'_paid'] || 0) : 0;
  }

  getBalance(period: string): number {
    if (this.isPermitCleared(period)) return 0;
    return Math.max(0, this.getDue(period) - this.getPaid(period));
  }

  get totalPaid(): number {
    if (!this.schedule) return 0;
    if (this.schedule.total_paid !== undefined && this.schedule.total_paid > 0) {
      return this.schedule.total_paid;
    }
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

  isPermitCleared(period: string): boolean {
    if (this.permits.some(p => p.exam_period === period && p.status === 'approved')) return true;
    return !!(this.schedule?.approved_permits?.[period]);
  }

  getPermit(period: string): Permit | undefined {
    if (period === 'Full') {
      const all = ['Prelim','Midterm','Finals'];
      return (
        this.permits.find(p => all.includes(p.exam_period) && p.status === 'approved') ||
        this.permits.find(p => all.includes(p.exam_period) && p.status === 'pending')  ||
        this.permits.find(p => all.includes(p.exam_period))
      );
    }
    return this.permits.find(p => p.exam_period === period);
  }

  canRequest(period: string): boolean {
    if (period === 'Full') {
      const allDone = ['Prelim','Midterm','Finals'].every(p => {
        const permit = this.permits.find(x => x.exam_period === p);
        return permit && (permit.status === 'pending' || permit.status === 'approved');
      });
      if (allDone) return false;
      if (this.getStatus('Full') === 'locked') return false;
      return this.paymentPlan === 'full' || this.isFullScholar;
    }

    const permit = this.getPermit(period);
    if (permit && (permit.status === 'pending' || permit.status === 'approved')) return false;

    const status = this.getStatus(period);
    if (status === 'locked') return false;
    if (this.isFullScholar) return true;
    if (this.paymentPlan === 'full') return true;
    return status === 'paid' || status === 'partial';
  }

  requestPermit(period: string): void {
    this.isRequesting = true;
    this.http.post<any>(`${this.apiUrl}?action=request_exam_permit`, {
      student_id:  this.studentId,
      exam_period: period,
      school_year: this.studentInfo.school_year || '2025-2026',
      semester:    this.studentInfo.semester    || '2nd Semester'
    }).subscribe({
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
    this.payAmount    = 0;
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
    sessionStorage.removeItem(`paySchedulePending_${this.studentId}`);
    this.showPayModal = false;
    this.paySubmitted = false;
    this.isPollingApproval = false;
    this.cdr.detectChanges();
  }

  submitPayment(): void {
    if (this.payMethod === 'Cash') {
      this.payAmount = this.getBalance(this.payPeriod) || 0;
    }

    if (this.payMethod === 'GCash' && (!this.payAmount || this.payAmount <= 0)) {
      this.payMsg = 'Please enter a valid amount.'; this.payMsgType = 'err';
      this.cdr.detectChanges(); return;
    }
    if (this.payMethod === 'GCash' && !this.payGcashRef.trim()) {
      this.payMsg = 'GCash reference number is required.'; this.payMsgType = 'err';
      this.cdr.detectChanges(); return;
    }

    this.isSubmitting = true;
    this.payMsg = '';

    this.http.post<any>(`${this.apiUrl}?action=submit_installment_payment`, {
      student_id:         this.studentId,
      amount:             this.payAmount,
      payment_date:       this.payDate,
      payment_method:     this.payMethod,
      gcash_reference:    this.payGcashRef.trim(),
      exam_period:        this.payPeriod,
      notes:              this.payNote.trim(),
    }).subscribe({
      next: (res) => {
        this.isSubmitting = false;
        if (res.success) {
          this.paySubmitted  = true;
          this.payOrArNumber = res.orArNumber || '';
          this.payMsg        = '';
          if (this.payMethod === 'GCash') {
            const pendingKey = `paySchedulePending_${this.studentId}`;
            sessionStorage.setItem(pendingKey, JSON.stringify({
              period:      this.payPeriod,
              orArNumber:  this.payOrArNumber,
              amount:      this.payAmount,
            }));
            this.startApprovalPolling();
          }
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

  startApprovalPolling(): void {
    if (this.pollTimer) { clearInterval(this.pollTimer); this.pollTimer = null; }
    this.isPollingApproval = true;
    this.checkApprovalPoll();
    this.pollTimer = setInterval(() => this.checkApprovalPoll(), 5000);
  }

  private checkApprovalPoll(): void {
    this.http.get<any>(`${this.apiUrl}?action=get_payment_schedule&student_id=${this.studentId}`).subscribe({
      next: (res) => {
        if (!res.success) return;
        const sched = res.schedule;
        const p = this.payPeriod.toLowerCase();
        const newStatus = sched ? sched[p + '_status'] : null;
        if (newStatus === 'paid' || newStatus === 'partial') {
          clearInterval(this.pollTimer);
          this.pollTimer = null;
          this.isPollingApproval = false;
          this.schedule = sched;
          this.notices  = res.notices || {};
          sessionStorage.removeItem(`paySchedulePending_${this.studentId}`);
          this.pendingTerms.delete(this.payPeriod);
          this.loadPermits();
          this.loadPendingTerms();
          this.historyLoaded = false;
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
    this.permitQrDataUrl  = '';
    this.cdr.detectChanges();

    this.http.get<any>(`${this.apiUrl}?action=get_permit_details&permit_id=${permit.id}&student_id=${this.studentId}`).subscribe({
      next: (res) => {
        this.isLoadingPermit = false;
        if (res.success) {
          this.viewingPermit = { ...permit, ...res.permit };
        }
        // Generate QR after details are loaded
        this.generatePermitQr(this.viewingPermit!);
        this.cdr.detectChanges();
      },
      error: () => {
        this.isLoadingPermit = false;
        if (this.viewingPermit) this.generatePermitQr(this.viewingPermit);
        this.cdr.detectChanges();
      }
    });
  }

  closePermitViewer(): void {
    this.showPermitViewer = false;
    this.viewingPermit = null;
    this.permitQrDataUrl = '';
    this.cdr.detectChanges();
  }

  // ── QR Code Generation ────────────────────────────────────────────────────
  // Builds a QR code URL using the free QRServer API (no npm package needed).
  // The encoded payload is a verification URL. When scanned, opens the permit
  // verification page in the student's browser automatically.
  generatePermitQr(permit: Permit): void {
    if (!permit) return;
    const verifyUrl = `https://steelblue-marten-571548.hostingersite.com/sia-api/Accounting.php?action=verify_permit&id=${encodeURIComponent(permit.permit_identifier || permit.id)}`;

    this.permitQrDataUrl =
      `https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=${encodeURIComponent(verifyUrl)}&ecc=M&margin=4`;
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

    const qrPayload = `https://steelblue-marten-571548.hostingersite.com/sia-api/Accounting.php?action=verify_permit&id=${encodeURIComponent(p.permit_identifier || p.id)}`;
    const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=110x110&data=${encodeURIComponent(qrPayload)}&ecc=M&margin=4`;

    const html = `<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>${p.exam_period} Examination Permit</title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: Arial, sans-serif; padding: 28px 32px; font-size: 12px; color: #000; }
    .header { display: flex; align-items: center; gap: 14px; padding-bottom: 8px; border-bottom: 2.5px solid #000; margin-bottom: 8px; }
    .logo-circle { width: 62px; height: 62px; border: 2px solid #888; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 22px; }
    .school-info { flex: 1; }
    .school-name { font-size: 16px; font-weight: 900; text-transform: uppercase; letter-spacing: 1px; }
    .school-sub  { font-size: 9px; color: #444; margin-top: 1px; }
    .school-addr { font-size: 9px; color: #444; }
    .qr-corner { display: flex; flex-direction: column; align-items: center; gap: 3px; flex-shrink: 0; }
    .qr-corner img { width: 90px; height: 90px; border: 1px solid #ccc; border-radius: 4px; }
    .qr-label { font-size: 7px; color: #666; text-align: center; }
    .permit-bar { background: #1a4fa0; color: white; text-align: center; padding: 7px 10px; margin: 8px 0; }
    .permit-bar-title { font-size: 14px; font-weight: 900; letter-spacing: 2px; }
    .permit-bar-sub   { font-size: 10px; margin-top: 2px; }
    .permit-bar-date  { font-size: 10px; margin-top: 1px; font-style: italic; }
    .info-section { display: grid; grid-template-columns: 1fr 1fr; gap: 0; margin: 8px 0; }
    .info-row { display: flex; gap: 4px; padding: 4px 0; border-bottom: 1px solid #ccc; }
    .info-label { font-size: 10px; color: #555; white-space: nowrap; }
    .info-value { font-size: 12px; font-weight: 700; border-bottom: 1px solid #000; flex: 1; padding-bottom: 1px; }
    .highlight-box { background: #1a4fa0; color: white; padding: 4px 10px; font-size: 11px; font-weight: 700; display: inline-block; }
    .note { color: #c00; font-weight: 700; font-size: 10px; margin: 6px 0; text-align: center; }
    table { width: 100%; border-collapse: collapse; margin-top: 6px; }
    th    { background: #f0f0f0; font-size: 11px; font-weight: 700; padding: 5px 8px; border: 1px solid #333; text-align: left; }
    td    { padding: 5px 8px; border: 1px solid #555; font-size: 11px; height: 22px; }
    .sig-area { display: flex; justify-content: flex-start; margin-top: 28px; }
    .sig-block { text-align: center; min-width: 220px; }
    .sig-name  { font-size: 13px; font-weight: 900; color: #1a4fa0; letter-spacing: 0.5px; text-transform: uppercase; }
    .sig-line  { border-top: 1.5px solid #000; margin-top: 6px; padding-top: 4px; font-size: 10px; }
    @media print { body { padding: 14px 18px; } @page { margin: 10mm; } }
  </style>
</head>
<body>
  <div class="header">
    <div class="logo-circle">🏫</div>
    <div class="school-info">
      <div class="school-name">ST. BENILDE</div>
      <div class="school-sub">CENTER FOR GLOBAL COMPETENCE, INC.</div>
      <div class="school-addr">#247 Rizal Avenue, West Bajac-Bajac, Olongapo City &nbsp;|&nbsp; Tel/Fax: (047) 223-3031</div>
    </div>
    <div class="qr-corner">
      <img src="${qrUrl}" alt="Permit QR Code" />
      <div class="qr-label">Scan to verify permit owner</div>
    </div>
  </div>
  <div class="permit-bar">
    <div class="permit-bar-title">${p.exam_period.toUpperCase()} EXAMINATION PERMIT</div>
    <div class="permit-bar-sub">${p.semester} &nbsp;&nbsp; A.Y. ${p.school_year}</div>
    <div class="permit-bar-date">Permit No.: <strong>${p.permit_identifier || '—'}</strong></div>
  </div>
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
      <span class="info-value"><span class="highlight-box">${p.program || ''}</span></span>
    </div>
  </div>
  <div class="note">NOTE: ANY ERASURE WILL INVALIDATE THIS ${p.exam_period.toUpperCase()} PERMIT</div>
  <table>
    <thead><tr><th style="width:65%">Subject</th><th style="width:35%">Instructor's Signature</th></tr></thead>
    <tbody>${courses}${blankRows}</tbody>
  </table>
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