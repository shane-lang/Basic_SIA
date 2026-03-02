import { Component, OnInit, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { Router } from '@angular/router';

interface StudentCourse {
  id: number; courseId: number; code: string; name: string; credits: number;
  instructor: string; schedule: string; day: string; time: string; room: string;
  enrollmentDate: string; semester: string;
  status: 'Pending' | 'Enrolled' | 'Completed' | 'Dropped'; grade?: string;
}
interface TermPayment {
  term: 'Prelim' | 'Midterm' | 'Finals';
  amountDue: number; amountPaid: number;
  paymentDate: string | null; status: 'Unpaid' | 'Partial' | 'Paid';
}
interface PaymentSummary {
  totalFee: number; scholarDiscount: number; amountDue: number;
  amountPaid: number; balance: number;
  status: 'Paid' | 'Partial' | 'Unpaid'; method: string; paymentDate: string | null;
}
interface EnrollmentNotification {
  id: string; type: 'success' | 'warning' | 'error' | 'info'; message: string; timestamp: Date;
}
interface FeeData {
  units: number;
  tuitionFee: number; miscellaneousFee: number; registrationFee: number;
  laboratoryFee: number; energyFee: number;
  subtotal: number; discount: number; installmentFee: number;
  totalAssessment: number; totalPaid: number; balance: number; paymentStatus: string;
}

@Component({
  selector: 'app-enrollment',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './enrollment.html',
  styleUrl: './enrollment.css',
})
export class Enrollment implements OnInit {
  private apiUrl        = 'http://localhost/sia-api/enrollment.php';
  private accountingApi = 'http://localhost/sia-api/accounting.php';
  private registrarApi  = 'http://localhost/sia-api/registrar.php';

  constructor(private http: HttpClient, private router: Router, private cdr: ChangeDetectorRef) {}

  workflowStep: 'payment' | 'cash-pending' | 'approval' | 'dashboard' | 'tor-pending' = 'payment';
  userId = 0; studentDbId = 0; student: any = {};
  showTorHardCopyNotice = false;

  torEvaluation: {
    status: 'Pending' | 'Evaluated' | 'Rejected';
    creditedUnits: number; approvedUnits: number;
    creditedSubjects: { code: string; name: string; credits: number }[];
    registrarNotes: string; evaluatedAt: string;
  } | null = null;
  isTorLoading = false;

  // ── SINGLE source of truth for fees ──────────────────────────
  fees: FeeData | null = null;
  isFeeLoading = false;
  get isFeePreviewLoading(): boolean { return this.isFeeLoading; }
  // Aliases so existing HTML still compiles unchanged
  get feePreview(): FeeData | null   { return this.fees; }
  get feeBreakdown(): FeeData | null { return this.fees; }

  paymentPlan: 'full' | 'installment' = 'full';
  termBreakdown: { period: string; amountPaid: number; orArNumber: string; orArType: string; paymentDate: string; paymentMethod: string }[] = [];
  paymentReceipts: any[] = [];

  paymentInfo = { amount: 0, discountedAmount: 0, status: 'Pending' as 'Pending' | 'Paid', dueDate: '2025-02-28', reference: '' };
  isProcessingPayment = false;
  paymentMethod: 'GCash' | 'Cash' = 'GCash';
  gcashReference = ''; gcashAmount = 0; gcashDate = new Date().toISOString().split('T')[0]; gcashSubmitted = false;

  isApprovalPending = false; approvalMessage = '';
  private pollInterval: any = null;
  private torPollInterval: any = null;

  currentSemester = '';
  enrolledCourses: StudentCourse[] = [];
  isAutoEnrolling = false;

  enrollmentSummary: {
    enrollmentDate: string; semester: string; program: string; yearLevel: string;
    totalCourses: number; totalCredits: number; courses: StudentCourse[];
    payment: PaymentSummary; termPayments: TermPayment[];
  } | null = null;

  currentView: 'dashboard' | 'enrollment-summary' = 'dashboard';
  showDropModal = false; selectedCourseForDrop: StudentCourse | null = null;
  showEditModal = false; editForm: any = {};
  notifications: EnrollmentNotification[] = [];
  addDropDeadline = '2025-04-15';

  // ═══════════════════════════════════════════════════════════════
  // INIT — one call, one source of truth
  // ═══════════════════════════════════════════════════════════════
  ngOnInit(): void {
    const storedUser = sessionStorage.getItem('currentUser');
    if (!storedUser) { this.router.navigate(['/login']); return; }
    this.userId = JSON.parse(storedUser).id;
    this.loadContext();
  }

  loadContext(): void {
    this.isFeeLoading = true;
    this.http.get<any>(`${this.apiUrl}?action=get_student_context&user_id=${this.userId}`).subscribe({
      next: (res) => {
        this.isFeeLoading = false;
        if (!res.success) { this.router.navigate(['/login']); return; }

        this.student         = res.student;
        this.studentDbId     = res.student.dbId;
        this.currentSemester = res.student.semester ?? '';
        this.paymentMethod   = res.student.paymentMethod === 'Cash' ? 'Cash' : 'GCash';
        this.paymentPlan     = res.student.paymentPlan  === 'installment' ? 'installment' : 'full';
        this.fees            = res.fees ?? null;
        this.termBreakdown   = res.termBreakdown ?? [];
        this.paymentReceipts = res.payments ?? [];

        if (res.torEvaluation) {
          this.torEvaluation = {
            status:           res.torEvaluation.status,
            creditedUnits:    res.torEvaluation.creditedUnits,
            approvedUnits:    res.torEvaluation.approvedUnits,
            creditedSubjects: res.torEvaluation.creditedSubjects || [],
            registrarNotes:   res.torEvaluation.registrarNotes  || '',
            evaluatedAt:      res.torEvaluation.evaluatedAt      || '',
          };
        }

        if (this.fees) {
          this.gcashAmount        = this.paymentPlan === 'installment' ? this.dpAmount : this.fees.totalAssessment;
          this.paymentInfo.amount = this.gcashAmount;
        }

        sessionStorage.setItem('studentDbId', String(this.studentDbId));

        // ── ROUTING ─────────────────────────────────────────────
        const s        = res.student;
        const approved    = s.approvalStatus === 'Approved';
        const torPending  = s.torEvalStatus  === 'Pending';
        const torDone     = s.torEvalStatus  === 'Evaluated';
        const torRejected = s.torEvalStatus  === 'Rejected';
        const paid        = s.paymentStatus  === 'Paid';
        const isCash      = this.paymentMethod === 'Cash';

        if (approved) {
          sessionStorage.removeItem('pendingPaymentMethod');
          sessionStorage.removeItem('pendingPaymentPlan');
          this.route('dashboard');
          this.ensureEnrolledThenLoad();

        } else if (torPending) {
          this.route('tor-pending');
          this.startTorPolling();

        } else if (torDone) {
          if (!sessionStorage.getItem('torHardCopyDismissed_' + this.studentDbId)) {
            this.showTorHardCopyNotice = true;
          }
          this.route(isCash ? 'cash-pending' : 'payment');
          if (isCash) { this.isApprovalPending = true; this.startApprovalPolling(); }

        } else if (torRejected) {
          this.route('payment');

        } else if (paid && !isCash) {
          this.route('approval');
          this.isApprovalPending = true;
          this.startApprovalPolling();

        } else if (isCash) {
          this.route('cash-pending');
          this.isApprovalPending = true;
          this.startApprovalPolling();

        } else {
          this.route('payment');
        }

        this.cdr.detectChanges();
      },
      error: () => {
        this.isFeeLoading = false;
        this.addNotification('error', 'Cannot load profile. Check XAMPP is running.');
        this.cdr.detectChanges();
      }
    });
  }

  route(step: typeof this.workflowStep): void {
    this.workflowStep = step;
    sessionStorage.setItem('enrollmentStep', step);
  }
  setStep(step: typeof this.workflowStep): void { this.route(step); }

  ensureEnrolledThenLoad(): void {
    this.http.post<any>(`${this.apiUrl}?action=auto_enroll_all`, {
      student_id: this.studentDbId, semester: this.currentSemester,
    }).subscribe({
      next:  () => this.loadDashboard(),
      error: () => this.loadDashboard(),
    });
  }

  loadDashboard(): void {
    this.loadEnrolledCourses();
    this.loadEnrollmentSummary();
    this.http.get<any>(`${this.apiUrl}?action=get_student_context&user_id=${this.userId}`).subscribe({
      next: (res) => {
        if (res.success) {
          this.fees            = res.fees ?? null;
          this.termBreakdown   = res.termBreakdown ?? [];
          this.paymentReceipts = res.payments ?? [];
          this.paymentPlan     = res.student.paymentPlan === 'installment' ? 'installment' : 'full';
        }
        this.cdr.detectChanges();
      }
    });
  }

  processPayment(): void {
    if (!this.studentDbId) { this.addNotification('error', 'Student ID missing.'); return; }
    if (!this.gcashReference.trim()) { this.addNotification('error', 'Enter your GCash Reference Number.'); return; }
    this.isProcessingPayment = true;
    const txnId = 'TXN-' + Date.now() + '-' + Math.random().toString(36).substring(2,7).toUpperCase();
    this.http.post<any>(`${this.accountingApi}?action=submit_gcash`, {
      student_id: this.studentDbId, gcash_reference: this.gcashReference.trim(),
      gcash_amount: this.gcashAmount, gcash_date: this.gcashDate,
      transaction_id: txnId, semester: this.currentSemester
    }).subscribe({
      next: (res) => {
        this.isProcessingPayment = false;
        if (res.success) {
          this.gcashSubmitted = true; this.paymentInfo.reference = txnId;
          this.route('approval'); this.isApprovalPending = true;
          this.addNotification('success', '✅ Payment submitted! Awaiting Accounting verification.');
          this.startApprovalPolling();
        } else { this.addNotification('error', res.message || 'Submission failed.'); }
        this.cdr.detectChanges();
      },
      error: () => { this.isProcessingPayment = false; this.addNotification('error', 'Cannot connect.'); this.cdr.detectChanges(); }
    });
  }

  startApprovalPolling(): void {
    if (this.pollInterval) clearInterval(this.pollInterval);
    this.pollInterval = setInterval(() => {
      if (!this.isApprovalPending) { clearInterval(this.pollInterval); return; }
      this.checkApprovalStatus();
    }, 10000);
  }

  checkApprovalStatus(): void {
    if (!this.userId) return;
    this.http.get<any>(`${this.apiUrl}?action=get_payment_status&user_id=${this.userId}`).subscribe({
      next: (res) => {
        if (res.success && res.approvalStatus === 'Approved') {
          clearInterval(this.pollInterval);
          sessionStorage.removeItem('pendingPaymentMethod');
          sessionStorage.removeItem('pendingPaymentPlan');
          this.isApprovalPending = false;
          this.approvalMessage = this.paymentMethod === 'Cash'
            ? '💵 Cash payment confirmed by Accounting!'
            : '📱 GCash payment verified by Accounting!';
          this.addNotification('success', 'Payment approved!');
          this.loadContext();
        }
      }
    });
  }

  proceedToDashboard(): void {
    this.isAutoEnrolling = true; this.cdr.detectChanges();
    this.http.post<any>(`${this.apiUrl}?action=auto_enroll_all`, {
      student_id: this.studentDbId, semester: this.currentSemester,
    }).subscribe({
      next: (res) => {
        this.isAutoEnrolling = false;
        if (res.success && res.enrolled > 0)
          this.addNotification('success', `✅ ${res.enrolled} subject(s) auto-enrolled!`);
        this.route('dashboard');
        this.loadDashboard();
        this.cdr.detectChanges();
      },
      error: () => { this.isAutoEnrolling = false; this.route('dashboard'); this.loadDashboard(); this.cdr.detectChanges(); }
    });
  }

  startTorPolling(): void {
    this.loadTorEvaluation();
    if (this.torPollInterval) clearInterval(this.torPollInterval);
    this.torPollInterval = setInterval(() => this.loadTorEvaluation(), 15000);
  }

  loadTorEvaluation(): void {
    if (!this.studentDbId) return;
    this.isTorLoading = true;
    this.http.get<any>(`${this.registrarApi}?action=get_tor_evaluation&student_id=${this.studentDbId}`).subscribe({
      next: (res) => {
        this.isTorLoading = false;
        if (res.success && res.evaluation) {
          this.torEvaluation = {
            status:           res.evaluation.status,
            creditedUnits:    res.evaluation.creditedUnits,
            approvedUnits:    res.evaluation.approvedUnits,
            creditedSubjects: res.evaluation.creditedSubjects || [],
            registrarNotes:   res.evaluation.registrarNotes  || '',
            evaluatedAt:      res.evaluation.evaluatedAt      || '',
          };
          if (res.evaluation.status === 'Evaluated' || res.evaluation.status === 'Rejected') {
            if (this.torPollInterval) clearInterval(this.torPollInterval);
            this.loadContext();
          }
        }
        this.cdr.detectChanges();
      },
      error: () => { this.isTorLoading = false; this.cdr.detectChanges(); }
    });
  }

  loadEnrolledCourses(): void {
    this.http.get<any>(`${this.apiUrl}?action=get_enrollments&user_id=${this.userId}`).subscribe({
      next: (res) => { if (res.success) { this.enrolledCourses = res.enrollments; this.cdr.detectChanges(); } }
    });
  }

  loadEnrollmentSummary(): void {
    this.http.get<any>(`${this.apiUrl}?action=get_enrollment_summary&user_id=${this.userId}`).subscribe({
      next: (res) => {
        if (res.success) {
          this.enrollmentSummary = {
            enrollmentDate: res.enrollmentDate, semester: res.semester,
            program: res.program, yearLevel: res.yearLevel,
            totalCourses: res.totalCourses, totalCredits: res.totalCredits,
            courses: res.courses, payment: res.payment, termPayments: res.termPayments,
          };
          this.cdr.detectChanges();
        }
      }
    });
  }

  // No-ops — kept so templates compile
  loadFeePreview(_?: string): void {}
  loadFeeBreakdown(): void {}

  openDropModal(c: StudentCourse): void { this.selectedCourseForDrop = c; this.showDropModal = true; this.cdr.detectChanges(); }
  closeDropModal(): void { this.showDropModal = false; this.selectedCourseForDrop = null; this.cdr.detectChanges(); }
  confirmDrop(): void {
    if (!this.selectedCourseForDrop) return;
    this.http.put<any>(`${this.apiUrl}?action=drop_course`, { enrollment_id: this.selectedCourseForDrop.id, student_id: this.studentDbId }).subscribe({
      next: (res) => {
        if (res.success) { this.addNotification('success', `${this.selectedCourseForDrop!.code} dropped.`); this.loadEnrolledCourses(); this.loadEnrollmentSummary(); }
        else { this.addNotification('error', res.message); }
        this.closeDropModal(); this.cdr.detectChanges();
      }
    });
  }

  openEditModal(): void { this.editForm = { ...this.student }; this.showEditModal = true; this.cdr.detectChanges(); }
  closeEditModal(): void { this.showEditModal = false; this.cdr.detectChanges(); }
  saveEditChanges(): void {
    if (!this.studentDbId) return;
    this.http.post<any>(`${this.apiUrl}?action=update_profile`, {
      student_id: this.studentDbId, phone: this.editForm.phone, address: this.editForm.address,
      emergencyContact: this.editForm.emergencyContact, emergencyPhone: this.editForm.emergencyPhone,
      dateOfBirth: this.editForm.dateOfBirth,
    }).subscribe({
      next: (res) => {
        if (res.success) { Object.assign(this.student, this.editForm); this.addNotification('success', 'Profile updated!'); this.closeEditModal(); }
        else { this.addNotification('error', res.message || 'Update failed.'); }
        this.cdr.detectChanges();
      },
      error: () => { this.addNotification('error', 'Server error.'); this.cdr.detectChanges(); }
    });
  }

  dismissTorHardCopyNotice(): void {
    sessionStorage.setItem('torHardCopyDismissed_' + this.studentDbId, '1');
    this.showTorHardCopyNotice = false;
    this.cdr.detectChanges();
  }

  get approvedCourses(): StudentCourse[] { return this.enrolledCourses.filter(c => c.status === 'Enrolled'); }
  get totalCredits(): number { return this.approvedCourses.reduce((s, c) => s + c.credits, 0); }
  canDropCourse(): boolean { return new Date() <= new Date(this.addDropDeadline); }

  isTermUnpaid(term: string): boolean {
    return !this.termBreakdown.some(t => t.period === term);
  }

  get installmentTermAmount(): number {
    const total = this.fees?.totalAssessment ?? 0;
    return total > 0 ? Math.ceil(total / 4) : 0;
  }
  get dpAmount(): number      { return this.installmentTermAmount; }
  get prelimAmount(): number  { return this.installmentTermAmount; }
  get midtermAmount(): number { return this.installmentTermAmount; }
  get finalsAmount(): number  {
    const total = this.fees?.totalAssessment ?? 0;
    return total > 0 ? total - (this.installmentTermAmount * 3) : 0;
  }

  get installmentSchedule(): { term: string; label: string; amount: number; paid: boolean; amountPaid: number; orNo: string; paymentDate: string }[] {
    const terms = [
      { term: 'Downpayment', label: '1st — Downpayment (DP)', amount: this.dpAmount },
      { term: 'Prelim',      label: '2nd — Prelim',           amount: this.prelimAmount },
      { term: 'Midterm',     label: '3rd — Midterm',          amount: this.midtermAmount },
      { term: 'Finals',      label: '4th — Finals',           amount: this.finalsAmount },
    ];
    return terms.map(t => {
      const paid = this.termBreakdown.find(tb => tb.period === t.term);
      return {
        term: t.term, label: t.label, amount: t.amount,
        paid: !!paid, amountPaid: paid ? paid.amountPaid : 0,
        orNo: paid ? `${paid.orArType}: ${paid.orArNumber}` : '',
        paymentDate: paid ? paid.paymentDate : '',
      };
    });
  }

  getTermIcon(s: string): string { return s === 'Paid' ? '✅' : s === 'Partial' ? '🔶' : '❌'; }
  getTermClass(s: string): string { return s === 'Paid' ? 'term-paid' : s === 'Partial' ? 'term-partial' : 'term-unpaid'; }
  getPayStatusClass(s: string): string { return s === 'Paid' ? 'pay-paid' : s === 'Partial' ? 'pay-partial' : 'pay-unpaid'; }

  addNotification(type: EnrollmentNotification['type'], message: string): void {
    const n: EnrollmentNotification = { id: 'n-' + Date.now(), type, message, timestamp: new Date() };
    this.notifications.push(n);
    setTimeout(() => {
      const i = this.notifications.findIndex(x => x.id === n.id);
      if (i !== -1) { this.notifications.splice(i, 1); this.cdr.detectChanges(); }
    }, 5000);
  }
  dismissNotification(id: string): void {
    const i = this.notifications.findIndex(n => n.id === id);
    if (i !== -1) { this.notifications.splice(i, 1); this.cdr.detectChanges(); }
  }

  // ── Number to words (for receipt) ────────────────────────────────────────
  private amountToWords(amount: number): string {
    const ones = ['','One','Two','Three','Four','Five','Six','Seven','Eight','Nine',
      'Ten','Eleven','Twelve','Thirteen','Fourteen','Fifteen','Sixteen','Seventeen','Eighteen','Nineteen'];
    const tens = ['','','Twenty','Thirty','Forty','Fifty','Sixty','Seventy','Eighty','Ninety'];
    const toWords = (n: number): string => {
      if (n === 0) return '';
      if (n < 20) return ones[n] + ' ';
      if (n < 100) return tens[Math.floor(n/10)] + (n%10 ? ' ' + ones[n%10] : '') + ' ';
      return ones[Math.floor(n/100)] + ' Hundred ' + toWords(n%100);
    };
    const pesos = Math.floor(amount);
    const centavos = Math.round((amount - pesos) * 100);
    let result = toWords(pesos).trim() + ' Pesos';
    if (centavos > 0) result += ' and ' + toWords(centavos).trim() + '/100 Centavos';
    return result;
  }

  // ── Print OR (Official Receipt) — for Full Payment ───────────────────────
  printOR(receipt: any): void {
    const s = this.student;
    const name = `${s.lastName || s.last_name || ''}, ${s.firstName || s.first_name || ''}`;
    const amount = receipt.amount || 0;
    const amtWords = this.amountToWords(amount);
    const fmt = (n: number) => n.toLocaleString('en-PH', { minimumFractionDigits: 2 });
    const courses = (this.enrolledCourses || []);

    const subjectRows = courses.length > 0
      ? courses.map((c: any) => `<tr><td>${c.code}</td><td>${c.name}</td><td>${c.credits || ''}</td></tr>`).join('')
      : '<tr><td colspan="3">&nbsp;</td></tr><tr><td colspan="3">&nbsp;</td></tr>';

    const html = `<!DOCTYPE html><html><head><meta charset="utf-8">
<title>Official Receipt ${receipt.orArNumber}</title>
<style>
  *{box-sizing:border-box;margin:0;padding:0;}
  body{font-family:Arial,sans-serif;font-size:11px;padding:20px 24px;color:#000;}
  .header{display:flex;align-items:flex-start;gap:12px;border-bottom:2px solid #000;padding-bottom:8px;margin-bottom:6px;}
  .logo{width:60px;height:60px;border:2px solid #000;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:8px;text-align:center;flex-shrink:0;}
  .school-name{font-size:15px;font-weight:900;text-transform:uppercase;}
  .school-sub{font-size:9px;}
  .school-addr{font-size:9px;margin-top:2px;}
  .badges{font-size:8px;text-align:right;margin-left:auto;}
  .receipt-title{text-align:center;font-size:14px;font-weight:900;letter-spacing:1px;border:2px solid #000;padding:4px;margin:6px 0;}
  .receipt-no{text-align:right;font-size:13px;font-weight:900;color:#c00;margin-bottom:4px;}
  .body-section{border:1px solid #000;padding:8px 10px;margin:6px 0;font-size:11px;}
  .field-line{display:flex;align-items:flex-end;gap:6px;margin-bottom:5px;}
  .field-label{white-space:nowrap;font-size:10px;}
  .field-val{border-bottom:1px solid #000;flex:1;min-width:80px;font-weight:700;padding-bottom:1px;}
  .amount-words{font-style:italic;font-size:11px;}
  .two-col{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin:6px 0;}
  .particulars-table{width:100%;border-collapse:collapse;font-size:10px;}
  .particulars-table th,.particulars-table td{border:1px solid #000;padding:3px 5px;}
  .particulars-table th{background:#eee;text-align:left;}
  .subjects-table{width:100%;border-collapse:collapse;font-size:10px;}
  .subjects-table th,.subjects-table td{border:1px solid #000;padding:3px 5px;}
  .subjects-table th{background:#eee;}
  .payment-section{display:flex;gap:16px;align-items:center;margin:4px 0;}
  .pay-label{font-size:10px;}
  .pay-box{border:1px solid #000;padding:2px 8px;font-size:10px;margin-right:4px;}
  .checked{font-weight:900;}
  .sig-area{display:flex;justify-content:space-between;margin-top:20px;}
  .sig-block{text-align:center;min-width:160px;}
  .sig-line{border-top:1.5px solid #000;padding-top:3px;font-size:9px;}
  .footer-text{font-size:8px;color:#555;margin-top:8px;border-top:1px solid #ccc;padding-top:4px;}
  @media print{body{padding:10px 14px;}@page{margin:8mm;}}
</style></head><body>

<div class="header">
  <div class="logo">ST.<br>BENILDE</div>
  <div>
    <div class="school-name">St. Benilde Center for Global Competence, Inc.</div>
    <div class="school-sub">NON-VAT Reg. TIN: 006-722-355-00000</div>
    <div class="school-addr">2647 Rizal Avenue, West Bajac-Bajac, Olongapo City &nbsp;|&nbsp; Tel/Fax: (047) 223-3031</div>
  </div>
  <div class="badges">Registered with:<br>CHED · TESDA<br>DepEd</div>
</div>

<div class="receipt-title">OFFICIAL RECEIPT (EXEMPT)</div>
<div class="receipt-no">No. &nbsp; ${receipt.orArNumber}</div>

<div class="body-section">
  <div class="field-line">
    <span class="field-label">RECEIVED from</span>
    <span class="field-val">${name}</span>
    <span class="field-label">with TIN</span>
    <span class="field-val" style="max-width:120px;"></span>
  </div>
  <div class="field-line">
    <span class="field-label">business style of</span>
    <span class="field-val"></span>
    <span class="field-label">and address at</span>
    <span class="field-val">Olongapo City</span>
  </div>
  <div class="field-line">
    <span class="field-label">in partial/full payment for</span>
    <span class="field-val">${receipt.period || 'Full Payment'} — ${s.program || ''} ${s.semester || ''}</span>
  </div>
  <div class="field-line">
    <span class="field-label">the sum of</span>
    <span class="field-val amount-words">( P &nbsp;${fmt(amount)} ) &nbsp; ${amtWords}</span>
    <span class="field-label">pesos</span>
  </div>

  <div class="payment-section" style="margin-top:8px;">
    <span class="field-label">Form of Payment:</span>
    <span class="pay-box ${receipt.method === 'Cash' ? 'checked' : ''}">
      ${receipt.method === 'Cash' ? '☑' : '☐'} Cash
    </span>
    <span class="pay-box ${receipt.method !== 'Cash' ? 'checked' : ''}">
      ${receipt.method !== 'Cash' ? '☑' : '☐'} Check
    </span>
    <span class="field-label" style="margin-left:12px;">Bank</span>
    <span class="field-val" style="max-width:120px;">${receipt.method !== 'Cash' ? (receipt.gcashReference || '') : ''}</span>
    <span class="field-label">Check No.</span>
    <span class="field-val" style="max-width:100px;"></span>
    <span class="field-label">Date</span>
    <span class="field-val" style="max-width:100px;">${receipt.paymentDate || ''}</span>
  </div>
</div>

<div class="two-col">
  <div>
    <table class="particulars-table">
      <thead><tr><th colspan="2">In settlement of the following:</th></tr>
        <tr><th>Particulars</th><th>Amount</th></tr>
      </thead>
      <tbody>
        <tr><td>Downpayment</td><td style="text-align:right;">${receipt.period==='Downpayment' ? 'P '+fmt(amount) : ''}</td></tr>
        <tr><td>1st Payment</td><td style="text-align:right;">${receipt.period==='Prelim' ? 'P '+fmt(amount) : ''}</td></tr>
        <tr><td>2nd Payment</td><td style="text-align:right;">${receipt.period==='Midterm' ? 'P '+fmt(amount) : ''}</td></tr>
        <tr><td>3rd Payment</td><td style="text-align:right;">${receipt.period==='Finals' ? 'P '+fmt(amount) : ''}</td></tr>
        <tr><td>4th Payment</td><td style="text-align:right;"></td></tr>
        <tr><td>Others</td><td style="text-align:right;"></td></tr>
        <tr><td><strong>Total Due</strong></td><td style="text-align:right;"><strong>P ${fmt(amount)}</strong></td></tr>
        <tr><td>Less: Withholding Tax</td><td></td></tr>
        <tr><td><strong>Payment Due</strong></td><td style="text-align:right;"><strong>P ${fmt(amount)}</strong></td></tr>
      </tbody>
    </table>
  </div>
  <div>
    <table class="subjects-table">
      <thead><tr><th>Code</th><th>Subject</th><th>Units</th></tr></thead>
      <tbody>${subjectRows}</tbody>
    </table>
  </div>
</div>

<div class="sig-area">
  <div class="sig-block">
    <div style="height:30px;"></div>
    <div class="sig-line">Cashier / Authorized Representative</div>
  </div>
  <div style="font-size:9px;text-align:right;color:#555;">
    Date: ${receipt.paymentDate || new Date().toLocaleDateString('en-PH')}<br>
    <em>THIS DOCUMENT IS NOT VALID FOR CLAIM OF INPUT TAXES</em>
  </div>
</div>

<div class="footer-text">
  Printer's Accreditation No.: 018MP2019000000000001 &nbsp;|&nbsp; Date Issued: 01-09-2019<br>
  BIR Authority to Print No.: OCN: 018AU20220000004994
</div>

<script>window.onload=()=>{window.print();}<\/script>
</body></html>`;

    const win = window.open('', '_blank', 'width=820,height=900');
    if (!win) return;
    win.document.write(html);
    win.document.close();
  }

  // ── Print AR (Acknowledgement Receipt) — for Installment Payments ────────
  printAR(receipt: any): void {
    const s = this.student;
    const name = `${s.lastName || s.last_name || ''}, ${s.firstName || s.first_name || ''}`;
    const amount = receipt.amount || 0;
    const fmt = (n: number) => n.toLocaleString('en-PH', { minimumFractionDigits: 2 });
    const courses = (this.enrolledCourses || []);

    const subjectRows = courses.length > 0
      ? courses.map((c: any) => `<tr>
          <td style="font-size:10px;">${c.code}</td>
          <td style="font-size:10px;">${c.name}</td>
          <td style="text-align:center;">${c.credits || ''}</td>
          <td style="text-align:center;">&nbsp;</td>
        </tr>`).join('')
      : '<tr><td colspan="4">&nbsp;</td></tr><tr><td colspan="4">&nbsp;</td></tr>';

    const periodLabel: any = {
      'Downpayment': 'Downpayment / Enrollment',
      'Prelim': '1st Term (Prelim) Installment',
      'Midterm': '2nd Term (Midterm) Installment',
      'Finals': '3rd Term (Finals) Installment',
    };

    const html = `<!DOCTYPE html><html><head><meta charset="utf-8">
<title>Acknowledgement Receipt ${receipt.orArNumber}</title>
<style>
  *{box-sizing:border-box;margin:0;padding:0;}
  body{font-family:Arial,sans-serif;font-size:11px;padding:20px 24px;color:#000;}
  .header{text-align:center;border-bottom:2px solid #000;padding-bottom:8px;margin-bottom:8px;}
  .school-name{font-size:16px;font-weight:900;text-transform:uppercase;letter-spacing:1px;}
  .school-addr{font-size:9px;margin-top:3px;}
  .form-label{font-size:9px;color:#555;margin-top:2px;}
  .receipt-title{text-align:center;font-size:14px;font-weight:900;letter-spacing:1px;border:2px solid #000;padding:5px;margin:6px 0;}
  .receipt-no-row{display:flex;justify-content:space-between;margin-bottom:6px;}
  .receipt-no{font-size:12px;font-weight:900;color:#c00;}
  .receipt-date{font-size:11px;}
  .body-section{border:1px solid #000;padding:8px 10px;margin:6px 0;}
  .field-line{display:flex;align-items:flex-end;gap:6px;margin-bottom:5px;}
  .field-label{white-space:nowrap;font-size:10px;}
  .field-val{border-bottom:1px solid #000;flex:1;font-weight:700;padding-bottom:1px;}
  .checkbox-grid{display:grid;grid-template-columns:1fr 1fr;gap:4px 16px;margin:8px 0 4px 0;font-size:10px;}
  .cb-item{display:flex;align-items:center;gap:5px;}
  .cb-box{width:13px;height:13px;border:1px solid #000;display:inline-block;text-align:center;font-size:10px;line-height:13px;}
  .other-line{display:flex;align-items:flex-end;gap:6px;margin-top:4px;font-size:10px;}
  .info-row{display:flex;gap:12px;margin-top:8px;font-size:10px;}
  .info-field{display:flex;align-items:flex-end;gap:4px;}
  .info-val{border-bottom:1px solid #000;min-width:80px;font-weight:700;padding-bottom:1px;}
  .subjects-table{width:100%;border-collapse:collapse;margin-top:8px;font-size:10px;}
  .subjects-table th,.subjects-table td{border:1px solid #000;padding:3px 5px;}
  .subjects-table th{background:#eee;text-align:center;}
  .sig-area{display:flex;justify-content:flex-end;margin-top:20px;}
  .sig-block{text-align:center;min-width:140px;}
  .sig-line{border-top:1.5px solid #000;padding-top:3px;font-size:9px;font-weight:700;}
  @media print{body{padding:10px 14px;}@page{margin:8mm;}}
</style></head><body>

<div class="header">
  <div class="school-name">St. Benilde Center for Global Competence, Inc.</div>
  <div class="school-addr">2647 Rizal Avenue, West Bajac-Bajac, Olongapo City</div>
  <div class="form-label">ADMIN FORM 09</div>
</div>

<div class="receipt-title">ACKNOWLEDGEMENT RECEIPT</div>
<div class="receipt-no-row">
  <span class="receipt-no">No. &nbsp; ${receipt.orArNumber}</span>
  <span class="receipt-date">DATE: &nbsp; ${receipt.paymentDate || new Date().toLocaleDateString('en-PH')}</span>
</div>

<div class="body-section">
  <div class="field-line">
    <span class="field-label">This is to acknowledge the receipt of payment from</span>
    <span class="field-val">${name}</span>
  </div>
  <div class="field-line">
    <span class="field-label">amounting to,</span>
    <span class="field-val">( P &nbsp;${fmt(amount)} )</span>
  </div>
  <div style="margin-top:8px;font-size:10px;font-weight:700;">As partial/Full payment for:</div>

  <div class="checkbox-grid">
    <div class="cb-item"><span class="cb-box">&nbsp;</span> School Uniform</div>
    <div class="cb-item"><span class="cb-box">&nbsp;</span> Graduation</div>
    <div class="cb-item"><span class="cb-box">&nbsp;</span> ID Lace</div>
    <div class="cb-item"><span class="cb-box">&nbsp;</span> Sports Fest</div>
    <div class="cb-item"><span class="cb-box">&nbsp;</span> P.E. Uniform</div>
    <div class="cb-item"><span class="cb-box">☑</span> <strong>Tuition / ${periodLabel[receipt.period] || receipt.period}</strong></div>
    <div class="cb-item"><span class="cb-box">&nbsp;</span> Books/Workbooks</div>
  </div>
  <div class="other-line">
    <span>Other student trainings &amp; activities (please specify):</span>
    <span class="field-val"></span>
  </div>

  <div class="info-row">
    <div class="info-field"><span>Course:</span><span class="info-val">${s.program || ''}</span></div>
    <div class="info-field"><span>Semester:</span><span class="info-val">${s.semester || ''}</span></div>
    <div class="info-field"><span>Academic Year:</span><span class="info-val">${new Date().getFullYear()}-${new Date().getFullYear()+1}</span></div>
  </div>
</div>

<table class="subjects-table">
  <thead>
    <tr>
      <th style="width:12%;">Code</th>
      <th>Subject / Description</th>
      <th style="width:8%;">Units</th>
      <th style="width:10%;">Grade</th>
    </tr>
  </thead>
  <tbody>${subjectRows}</tbody>
</table>

<div class="sig-area">
  <div class="sig-block">
    <div style="height:32px;"></div>
    <div class="sig-line">CASHIER</div>
  </div>
</div>

<script>window.onload=()=>{window.print();}<\/script>
</body></html>`;

    const win = window.open('', '_blank', 'width=760,height=860');
    if (!win) return;
    win.document.write(html);
    win.document.close();
  }
}