import { Component, OnInit, OnDestroy, ChangeDetectorRef } from '@angular/core';
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
  extraFees?: { fee_key: string; fee_label: string; is_per_unit: number; rate: number; amount: number }[];
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
export class Enrollment implements OnInit, OnDestroy {
  private apiUrl        = 'http://localhost/sia-api/enrollment.php';
  private accountingApi = 'http://localhost/sia-api/accounting.php';
  private registrarApi  = 'http://localhost/sia-api/registrar.php';

  
  /** Returns HTTP headers with the auth token. Call this in every API request. */
  private getHeaders() {
    const token = sessionStorage.getItem('token') || localStorage.getItem('token') || '';
    return { headers: { Authorization: `Bearer ${token}` } };
  }

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

  // Payment due dates — loaded from Accounting (sys_config)
  dueDates: { [key: string]: { label: string; date_range: string } } = {
    downpayment: { label: 'Downpayment', date_range: '' },
    prelim:      { label: 'Prelim',      date_range: 'JANUARY 10-16, 2026' },
    midterm:     { label: 'Midterm',     date_range: 'FEBRUARY 9 - 14, 2026' },
    finals:      { label: 'Finals',      date_range: 'MARCH 30 - APRIL 4, 2026' },
  };

  getDueDate(period: string): string {
    const key = period.toLowerCase();
    return this.dueDates[key]?.date_range ?? '';
  }

  paymentInfo = { amount: 0, discountedAmount: 0, status: 'Pending' as 'Pending' | 'Paid', dueDate: '2025-02-28', reference: '' };
  isProcessingPayment = false;
  paymentMethod: 'GCash' | 'Cash' = 'GCash';
  gcashReference = ''; gcashAmount = 0; gcashDate = new Date().toISOString().split('T')[0]; gcashSubmitted = false;

  isApprovalPending = false; approvalMessage = '';
  private pollInterval: any = null;
  private torPollInterval: any = null;

  currentSemester = '';
  studentCategory = '';   // 'SHS' | 'TVET' | '' (College)
  get isSHS():        boolean { return this.studentCategory === 'SHS'; }
  get isTVET():       boolean { return this.studentCategory === 'TVET'; }
  get isCollege():    boolean { return !this.isSHS && !this.isTVET; }
  get isTransferee(): boolean { return (this.student?.studentType ?? '') === 'Transferee'; }
  // Free only for SHS/TVET New & Old students
  get isFreeStudent(): boolean { return (this.isSHS || this.isTVET) && !this.isTransferee; }
  enrolledCourses: StudentCourse[] = [];
  isAutoEnrolling = false;
  ngOnDestroy(): void {
    if (this.pollInterval)    clearInterval(this.pollInterval);
    if (this.torPollInterval) clearInterval(this.torPollInterval);
  }

  enrollmentSummary: {
    enrollmentDate: string; semester: string; program: string; yearLevel: string;
    totalCourses: number; totalCredits: number; courses: StudentCourse[];
    payment: PaymentSummary; termPayments: TermPayment[];
  } | null = null;

  currentView: 'dashboard' | 'enrollment-summary' = 'enrollment-summary';
  showDropModal = false; selectedCourseForDrop: StudentCourse | null = null;
  showEditModal = false; editForm: any = {};
  notifications: EnrollmentNotification[] = [];
  addDropDeadline = '2025-04-15';

  // ═══════════════════════════════════════════════════════════════
  // INIT — restore state first, then sync with DB
  // ═══════════════════════════════════════════════════════════════
  ngOnInit(): void {
    const storedUser = sessionStorage.getItem('currentUser') || localStorage.getItem('currentUser');
    if (!storedUser) { this.router.navigate(['/login']); return; }
    this.userId = JSON.parse(storedUser).id;

    // ── Restore last known step IMMEDIATELY to prevent flash back to 'payment' ──
    // This is purely a visual restore — loadContext() will correct it from DB truth.
    const savedStep = sessionStorage.getItem('enrollmentStep') as typeof this.workflowStep | null;
    if (savedStep) {
      this.workflowStep = savedStep;
    }

    // ── Restore approval polling if student was mid-wait when they reloaded ──
    // Without this, approved students who reload during polling never get redirected.
    if (savedStep === 'approval' || savedStep === 'cash-pending') {
      this.isApprovalPending = true;
      this.startApprovalPolling();
    }

    this.loadDueDates();
    this.loadContext();
  }

  loadDueDates(): void {
    this.http.get<any>(`${this.accountingApi}?action=get_due_dates`).subscribe({
      next: res => { if (res.success) this.dueDates = res.dueDates; }
    });
  }

  loadContext(): void {
    this.isFeeLoading = true;
    this.http.get<any>(`${this.apiUrl}?action=get_student_context&user_id=${this.userId}`, this.getHeaders()).subscribe({
      next: (res) => {
        this.isFeeLoading = false;
        if (!res.success) { this.router.navigate(['/login']); return; }

        this.student         = res.student;
        this.studentDbId     = res.student.dbId;
        this.currentSemester = res.student.semester ?? '';
        this.studentCategory = (res.student.studentCategory ?? '').toUpperCase();
        // Fallback: infer from student number if DB category is still blank
        if (!this.studentCategory) {
          const sNum: string = res.student.id ?? '';
          if (sNum.startsWith('SHS-'))  this.studentCategory = 'SHS';
          else if (sNum.startsWith('TVET-')) this.studentCategory = 'TVET';
        }
        sessionStorage.setItem('studentCategory', this.studentCategory);
        localStorage.setItem('studentCategory', this.studentCategory);
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
        localStorage.setItem('studentDbId', String(this.studentDbId));

        // ── Stop any pending approval poll — DB is now the source of truth ──
        if (this.pollInterval) { clearInterval(this.pollInterval); this.pollInterval = null; }

        // ── ROUTING ─────────────────────────────────────────────
        const s        = res.student;
        const cat      = (s.studentCategory ?? '').toUpperCase();
        const isFree   = (cat === 'SHS' || cat === 'TVET');
        const approved    = s.approvalStatus === 'Approved';
        const torPending  = s.torEvalStatus  === 'Pending';
        const torDone     = s.torEvalStatus  === 'Evaluated';
        const torRejected = s.torEvalStatus  === 'Rejected';
        const paid        = s.paymentStatus  === 'Paid';
        const isCash      = this.paymentMethod === 'Cash';

        // SHS/TVET New/Old are free — go straight to dashboard if approved
        // Transferees must pay ₱20,000 — follow normal payment flow
        const isTransferee = (s.studentType ?? '') === 'Transferee';
        if (isFree && !isTransferee && approved) {
          sessionStorage.removeItem('pendingPaymentMethod');
          sessionStorage.removeItem('pendingPaymentPlan');
          this.isApprovalPending = false;
          this.route('dashboard');
          this.ensureEnrolledThenLoad();

        } else if (approved) {
          sessionStorage.removeItem('pendingPaymentMethod');
          sessionStorage.removeItem('pendingPaymentPlan');
          this.isApprovalPending = false;
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
    // Always load dashboard data first so screen isn't blank while auto-enroll runs
    this.loadDashboard();
    // Then also trigger auto-enroll in case courses are missing (idempotent - safe to re-run)
    this.http.post<any>(`${this.apiUrl}?action=auto_enroll_all`, {
      student_id: this.studentDbId, semester: this.currentSemester,
    }, this.getHeaders()).subscribe({
      // After auto-enroll finishes, reload courses to pick up any newly enrolled subjects
      next:  () => { this.loadEnrolledCourses(); this.loadEnrollmentSummary(); },
      error: () => { /* already loaded above, silently ignore */ },
    });
  }

  loadDashboard(): void {
    // Always reload all dashboard data sources in parallel
    this.loadEnrolledCourses();
    this.loadEnrollmentSummary();
    this.http.get<any>(`${this.apiUrl}?action=get_student_context&user_id=${this.userId}`, this.getHeaders()).subscribe({
      next: (res) => {
        if (res.success) {
          this.fees            = res.fees ?? null;
          this.termBreakdown   = res.termBreakdown ?? [];
          this.paymentReceipts = res.payments ?? [];
          this.paymentPlan     = res.student.paymentPlan === 'installment' ? 'installment' : 'full';
        }
        this.cdr.detectChanges();
      },
      error: () => { this.cdr.detectChanges(); }
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
    }, this.getHeaders()).subscribe({
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
    // Clear any existing interval first to prevent duplicates
    if (this.pollInterval) { clearInterval(this.pollInterval); this.pollInterval = null; }
    // Check immediately on start (covers the reload-and-already-approved case)
    this.checkApprovalStatus();
    this.pollInterval = setInterval(() => {
      if (!this.isApprovalPending) { clearInterval(this.pollInterval); this.pollInterval = null; return; }
      this.checkApprovalStatus();
    }, 10000);
  }

  checkApprovalStatus(): void {
    if (!this.userId) return;
    this.http.get<any>(`${this.apiUrl}?action=get_payment_status&user_id=${this.userId}`, this.getHeaders()).subscribe({
      next: (res) => {
        if (res.success && res.approvalStatus === 'Approved') {
          // Stop polling immediately
          if (this.pollInterval) { clearInterval(this.pollInterval); this.pollInterval = null; }
          sessionStorage.removeItem('pendingPaymentMethod');
          sessionStorage.removeItem('pendingPaymentPlan');
          this.isApprovalPending = false;
          this.approvalMessage = this.paymentMethod === 'Cash'
            ? '💵 Cash payment confirmed by Accounting!'
            : '📱 GCash payment verified by Accounting!';
          this.addNotification('success', 'Payment approved! Loading your dashboard...');
          // Pre-set step to dashboard so loadContext doesn't flash 'approval'
          this.route('dashboard');
          // Reload full context to get enrolled courses and updated fees
          this.loadContext();
        }
      }
    });
  }

  proceedToDashboard(): void {
    this.isAutoEnrolling = true; this.cdr.detectChanges();
    this.http.post<any>(`${this.apiUrl}?action=auto_enroll_all`, {
      student_id: this.studentDbId, semester: this.currentSemester,
    }, this.getHeaders()).subscribe({
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
    if (this.torPollInterval) { clearInterval(this.torPollInterval); this.torPollInterval = null; }
    this.torPollInterval = setInterval(() => this.loadTorEvaluation(), 15000);
  }

  loadTorEvaluation(): void {
    if (!this.studentDbId) return;
    this.isTorLoading = true;
    this.http.get<any>(`${this.registrarApi}?action=get_tor_evaluation&student_id=${this.studentDbId}`, this.getHeaders()).subscribe({
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
    this.http.get<any>(`${this.apiUrl}?action=get_enrollments&user_id=${this.userId}`, this.getHeaders()).subscribe({
      next: (res) => { if (res.success) { this.enrolledCourses = res.enrollments; this.cdr.detectChanges(); } }
    });
  }

  loadEnrollmentSummary(): void {
    this.http.get<any>(`${this.apiUrl}?action=get_enrollment_summary&user_id=${this.userId}`, this.getHeaders()).subscribe({
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
    this.http.put<any>(`${this.apiUrl}?action=drop_course`, { enrollment_id: this.selectedCourseForDrop.id, student_id: this.studentDbId }, this.getHeaders()).subscribe({
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
    }, this.getHeaders()).subscribe({
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

  // Actual amounts paid per term (from payment records)
  get dpPaid(): number {
    const dp = this.termBreakdown.find(t => t.period === 'Downpayment');
    return dp ? (dp.amountPaid ?? 0) : 0;
  }
  get prelimPaid(): number {
    const p = this.termBreakdown.find(t => t.period === 'Prelim');
    return p ? (p.amountPaid ?? 0) : 0;
  }
  get midtermPaid(): number {
    const m = this.termBreakdown.find(t => t.period === 'Midterm');
    return m ? (m.amountPaid ?? 0) : 0;
  }
  get finalsPaid(): number {
    const f = this.termBreakdown.find(t => t.period === 'Finals');
    return f ? (f.amountPaid ?? 0) : 0;
  }

  /**
   * Computes the AMOUNT shown per term in the installment table.
   *
   * Rule: whatever was already paid in a term is SHOWN as-is.
   * The REMAINING balance after each paid term is split EQUALLY
   * among all unpaid terms that follow — so overpaying one term
   * reduces ALL remaining terms, and underpaying increases them.
   *
   * Example — Total: 33,137
   *   No payments    → DP: 8,285 | Prelim: 8,284 | Midterm: 8,284 | Finals: 8,284
   *   DP paid 10,000 → DP: 10,000 | Remaining 23,137÷3 = 7,713 | 7,712 | 7,712
   *   + Prelim 10,000→ DP: 10,000 | Prelim: 10,000 | Remaining 13,137÷2 = 6,569 | 6,568
   *   DP paid 5,000  → DP: 5,000  | Remaining 28,137÷3 = 9,379 each
   */
  get installmentAmounts(): { dpDue: number; prelimDue: number; midtermDue: number; finalsDue: number } {
    const total  = this.fees?.totalAssessment ?? 0;
    if (total <= 0) return { dpDue: 0, prelimDue: 0, midtermDue: 0, finalsDue: 0 };

    const dpPaid  = this.dpPaid;
    const prPaid  = this.prelimPaid;
    const midPaid = this.midtermPaid;
    const finPaid = this.finalsPaid;

    // DP: show scheduled (total/4) if unpaid, actual if paid
    const quarter   = Math.ceil(total / 4);
    const dpShow    = dpPaid > 0 ? dpPaid : quarter;
    const dpCredit  = dpPaid > 0 ? dpPaid : quarter;

    // Remaining after DP → split EQUALLY among all 3 remaining terms
    const rem1   = Math.max(0, total - dpCredit);
    const prShare  = rem1 > 0 ? Math.ceil(rem1 / 3) : 0;
    const prShow   = prPaid > 0 ? prPaid : prShare;
    const prCredit = prPaid > 0 ? prPaid : prShare;

    // Remaining after Prelim → split EQUALLY among Midterm and Finals
    const rem2    = Math.max(0, rem1 - prCredit);
    const midShare = rem2 > 0 ? Math.ceil(rem2 / 2) : 0;
    const midShow  = midPaid > 0 ? midPaid : midShare;
    const midCredit= midPaid > 0 ? midPaid : midShare;

    // Finals = whatever is still left
    const rem3   = Math.max(0, rem2 - midCredit);
    const finShow= finPaid > 0 ? finPaid : rem3;

    return {
      dpDue:      dpShow,
      prelimDue:  prShow,
      midtermDue: midShow,
      finalsDue:  finShow,
    };
  }

  // Convenience getters used by template + print SOA
  get dpAmount(): number      { return this.installmentAmounts.dpDue; }
  get prelimAmount(): number  { return this.installmentAmounts.prelimDue; }
  get midtermAmount(): number { return this.installmentAmounts.midtermDue; }
  get finalsAmount(): number  { return this.installmentAmounts.finalsDue; }

  /**
   * Examination Covered = the latest exam period with a recorded payment.
   * Order: Finals > Midterm > Prelim > Downpayment
   * If nothing paid yet, defaults to 'PRELIM'.
   */
  get currentExamCovered(): string {
    const order = ['Finals', 'Midterm', 'Prelim', 'Downpayment'];
    for (const period of order) {
      if (this.termBreakdown.some(t => t.period === period)) return period.toUpperCase();
    }
    return 'PRELIM';
  }

  // Remaining balance after DP credit (kept for compatibility)
  get remainingAfterDP(): number {
    const total = this.fees?.totalAssessment ?? 0;
    const dp    = this.dpPaid > 0 ? this.dpPaid : this.installmentTermAmount;
    return Math.max(0, total - dp);
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
  viewOR(receipt: any): void {
    const name = `${receipt.lastName || receipt.last_name || ''}, ${receipt.firstName || receipt.first_name || ''}`;
    const amount = receipt.amount || 0;
    const amtWords = this.amountToWords(amount);
    const fmt = (n: number) => n.toLocaleString('en-PH', { minimumFractionDigits: 2 });
    const period = receipt.period || 'Full';
    const isCashPay = receipt.method === 'Cash';
    const payDate = receipt.paymentDate || '';

    const html = `<!DOCTYPE html><html><head><meta charset="utf-8">
<title>Official Receipt ${receipt.orArNumber || ''}</title>
<style>
  *{box-sizing:border-box;margin:0;padding:0;}
  body{font-family:Arial,sans-serif;font-size:10.5px;padding:14px 18px;color:#000;background:#fff;width:720px;}
  .outer{display:grid;grid-template-columns:220px 1fr;gap:0;border:1.5px solid #000;}
  /* LEFT PANEL */
  .left-panel{border-right:1.5px solid #000;display:flex;flex-direction:column;}
  .part-header{background:#000;color:#fff;text-align:center;font-size:9px;font-weight:700;padding:3px;}
  .part-table{width:100%;border-collapse:collapse;font-size:9.5px;}
  .part-table td{border:1px solid #000;padding:2px 4px;height:18px;}
  .part-table td:last-child{width:70px;text-align:right;}
  .part-table .total-row td{font-weight:700;background:#f5f5f5;}
  .subj-area{border-top:1.5px solid #000;padding:4px;font-size:9px;}
  .subj-row{display:grid;grid-template-columns:55px 1fr 25px;border-bottom:1px solid #ddd;padding:1px 0;}
  /* RIGHT PANEL */
  .right-panel{display:flex;flex-direction:column;}
  .school-header{display:flex;align-items:flex-start;gap:8px;padding:6px 8px;border-bottom:1.5px solid #000;}
  .logo-circle{width:52px;height:52px;border:2px solid #000;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:7.5px;text-align:center;flex-shrink:0;font-weight:700;}
  .school-info{flex:1;}
  .school-name{font-size:13px;font-weight:900;text-transform:uppercase;letter-spacing:0.5px;}
  .school-sub{font-size:8px;color:#333;margin-top:1px;}
  .deped-badge{font-size:8px;border:1px solid #999;padding:2px 5px;border-radius:3px;margin-left:auto;white-space:nowrap;align-self:flex-start;}
  .receipt-bar{text-align:center;background:#fff;border-bottom:1.5px solid #000;padding:4px;}
  .receipt-bar-title{font-size:13px;font-weight:900;letter-spacing:1.5px;}
  .receipt-bar-sub{font-size:8.5px;color:#444;}
  .body-pad{padding:6px 10px;}
  .field-row{display:flex;align-items:flex-end;gap:5px;margin-bottom:4px;}
  .flabel{font-size:9px;white-space:nowrap;}
  .fval{border-bottom:1px solid #000;flex:1;font-weight:700;font-size:10px;padding-bottom:1px;min-width:40px;}
  .sum-words{font-style:italic;}
  .pay-method{display:flex;align-items:center;gap:6px;margin-top:5px;flex-wrap:wrap;}
  .cb{display:inline-flex;align-items:center;gap:3px;font-size:9px;}
  .cb-sq{width:11px;height:11px;border:1px solid #000;display:inline-flex;align-items:center;justify-content:center;font-size:9px;}
  .receipt-no-bar{display:flex;justify-content:flex-end;padding:4px 10px;border-top:1px solid #ccc;}
  .receipt-no{font-size:16px;font-weight:900;color:#c00;}
  .sig-row{display:flex;justify-content:space-between;align-items:flex-end;padding:6px 10px 4px;border-top:1px solid #ccc;}
  .sig-block{text-align:center;}
  .sig-line{border-top:1px solid #000;padding-top:3px;font-size:8.5px;margin-top:20px;}
  .footer{font-size:7.5px;color:#444;padding:4px 8px;border-top:1px dashed #aaa;}
  @media print{
    body{padding:6px 10px;}
    @page{margin:6mm;size:A4 landscape;}
    .no-print{display:none!important;}
  }
</style></head><body>

<div class="outer">
  <!-- LEFT: Particulars + Subjects -->
  <div class="left-panel">
    <div class="part-header">In settlement of the following:</div>
    <table class="part-table">
      <tr><td>Particulars</td><td><strong>Amount</strong></td></tr>
      <tr><td>Downpayment</td><td>${period==='Downpayment' ? fmt(amount) : ''}</td></tr>
      <tr><td>1st Payment</td><td>${period==='Prelim' ? fmt(amount) : ''}</td></tr>
      <tr><td>2nd Payment</td><td>${period==='Midterm' ? fmt(amount) : ''}</td></tr>
      <tr><td>3rd Payment</td><td>${period==='Finals' ? fmt(amount) : ''}</td></tr>
      <tr><td>4th Payment</td><td></td></tr>
      <tr><td>5th Payment</td><td></td></tr>
      <tr><td>6th Payment</td><td></td></tr>
      <tr><td>Others</td><td></td></tr>
      <tr class="total-row"><td>Total Due</td><td>${period==='Full' ? fmt(amount) : fmt(amount)}</td></tr>
      <tr><td>Less: Withholding Tax</td><td></td></tr>
      <tr class="total-row"><td>Payment Due</td><td>${fmt(amount)}</td></tr>
    </table>
    <div class="subj-area">
      <div class="subj-row" style="font-weight:700;font-size:9px;border-bottom:1px solid #000;">
        <span>Code</span><span>Subject</span><span>Units</span>
      </div>
      <div class="subj-row"><span>${receipt.program || ''}</span><span>${receipt.semester || ''}</span><span></span></div>
      <div class="subj-row"><span></span><span></span><span></span></div>
      <div class="subj-row"><span></span><span></span><span></span></div>
      <div class="subj-row"><span></span><span></span><span></span></div>
      <div class="subj-row"><span></span><span></span><span></span></div>
    </div>
  </div>

  <!-- RIGHT: Header + Receipt Body -->
  <div class="right-panel">
    <div class="school-header">
      <div class="logo-circle">ST.<br>BENILDE</div>
      <div class="school-info">
        <div class="school-name">ST. BENILDE</div>
        <div class="school-sub">CENTER FOR GLOBAL COMPETENCE, INC.</div>
        <div class="school-sub">Email: stbenilde_olongapo@yahoo.com &nbsp;|&nbsp; Telefax: (047) 223-3031</div>
        <div class="school-sub">Olongapo City, Zambales, Philippines</div>
        <div class="school-sub">NON-VAT Reg. TIN: 006-722-355-00000</div>
      </div>
      <div class="deped-badge">Registered with:<br>CHED · TESDA · DepEd</div>
    </div>

    <div class="receipt-bar">
      <div class="receipt-bar-title">OFFICIAL RECEIPT (EXEMPT)</div>
    </div>

    <div class="body-pad">
      <div class="field-row">
        <span class="flabel">RECEIVED from</span>
        <span class="fval">${name}</span>
        <span class="flabel">with TIN</span>
        <span class="fval" style="max-width:90px;"></span>
      </div>
      <div class="field-row">
        <span class="flabel">business style of</span>
        <span class="fval"></span>
        <span class="flabel">and address at</span>
        <span class="fval">Olongapo City</span>
      </div>
      <div class="field-row">
        <span class="flabel">in partial/full payment for</span>
        <span class="fval">${receipt.program || ''} — ${receipt.semester || ''}</span>
      </div>
      <div class="field-row">
        <span class="flabel">the sum of</span>
        <span class="fval sum-words">( P ${fmt(amount)} ) &nbsp; ${amtWords}</span>
        <span class="flabel">pesos</span>
      </div>

      <div class="pay-method">
        <span class="flabel">Form of Payment:</span>
        <span class="cb"><span class="cb-sq">${isCashPay ? '✓' : ''}</span> Cash</span>
        <span class="cb"><span class="cb-sq">${!isCashPay ? '✓' : ''}</span> Check</span>
        <span class="flabel" style="margin-left:8px;">Bank</span>
        <span class="fval" style="max-width:90px;">${!isCashPay ? (receipt.gcashReference || "" || '') : ''}</span>
        <span class="flabel">Check No.</span>
        <span class="fval" style="max-width:70px;"></span>
        <span class="flabel">Date</span>
        <span class="fval" style="max-width:80px;">${payDate}</span>
      </div>
    </div>

    <div class="receipt-no-bar">
      <span style="font-size:9px;margin-right:8px;">No.</span>
      <span class="receipt-no">${receipt.orArNumber || '—'}</span>
    </div>

    <div class="sig-row">
      <div>
        <div style="font-size:8px;color:#555;"><em>THIS DOCUMENT IS NOT VALID FOR CLAIM OF INPUT TAXES</em></div>
      </div>
      <div class="sig-block">
        <div style="height:24px;font-size:10px;"></div>
        <div class="sig-line">Cashier/Authorized Representative</div>
      </div>
    </div>

    <div class="footer">
      200-Elitre 1503 42501-52500 &nbsp;|&nbsp; BIR Authority to Print No.: OCN: 018AU20220000004994<br>
      Printer's Accreditation No.: 018MP2019000000000001 &nbsp;|&nbsp; Date Issued: 01-09-2019<br>
      Dinamika Printing Intl. Corp. &nbsp;|&nbsp; 75m St. Tapinac, Olongapo City &nbsp;|&nbsp; TIN: 215-213-220-00000 - VAT
    </div>
  </div>
</div>

<div class="no-print" style="text-align:center;margin-top:14px;">
  <button onclick="window.print()" style="background:#1d4ed8;color:white;border:none;padding:10px 32px;font-size:14px;font-weight:700;border-radius:7px;cursor:pointer;margin-right:10px;">🖨️ Print</button>
  <button onclick="window.close()" style="background:#64748b;color:white;border:none;padding:10px 24px;font-size:14px;font-weight:700;border-radius:7px;cursor:pointer;">✕ Close</button>
</div>

</body></html>`;
    const win = window.open('', '_blank', 'width=800,height=700');
    if (!win) return;
    win.document.write(html); win.document.close();
  }

  viewAR(receipt: any): void {
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
    <div class="info-field"><span>Academic Year:</span><span class="info-val">${(()=>{ const m=(s.semester||'').match(/(\d{4})-(\d{4})/); if(m) return m[0]; const y=new Date().getFullYear(); return y+'-'+(y+1); })()}</span></div>
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

<div class="no-print" style="text-align:center;margin-top:16px;padding:12px;background:#f8fafc;border-top:1px solid #e2e8f0;">
      <button onclick="window.print()" style="background:#7c3aed;color:white;border:none;padding:10px 32px;font-size:14px;font-weight:700;border-radius:7px;cursor:pointer;margin-right:10px;">🖨️ Print</button>
      <button onclick="window.close()" style="background:#64748b;color:white;border:none;padding:10px 24px;font-size:14px;font-weight:700;border-radius:7px;cursor:pointer;">✕ Close</button>
    </div>
</body></html>`;

    const win = window.open('', '_blank', 'width=760,height=860');
    if (!win) return;
    win.document.write(html);
    win.document.close();
  }

  // ── View Statement of Account ─────────────────────────────────────────────
  viewSOA(): void {
    const s    = this.student;
    const f    = this.fees;
    if (!f) return;
    const name = `${(s.lastName || s.last_name || '').toUpperCase()}, ${(s.firstName || s.first_name || '').toUpperCase()} ${(s.middleName || s.middle_name || '').toUpperCase()}`.trim();
    const fmt  = (n: number) => (+n || 0).toLocaleString('en-PH', { minimumFractionDigits: 2 });
    const isInstallment = this.paymentPlan === 'installment';
    const td   = this.termBreakdown;
    const getTerm = (p: string) => td.find(t => t.period === p);
    const dp  = getTerm('Downpayment');
    const pr  = getTerm('Prelim');
    const mid = getTerm('Midterm');
    const fin = getTerm('Finals');
    const dpAmt  = this.dpAmount;
    const prAmt  = this.prelimAmount;
    const midAmt = this.midtermAmount;
    const finAmt = this.finalsAmount;
    const totalAmountToBePaid = this.fees?.totalAssessment ?? 0;

    // Due dates — loaded from Accounting (sys_config via get_due_dates)
    const dueDates: any = {
      Downpayment: this.getDueDate('downpayment'),
      Prelim:      this.getDueDate('prelim'),
      Midterm:     this.getDueDate('midterm'),
      Finals:      this.getDueDate('finals'),
    };

    const scheduleRow = (label: string, period: string, amount: number, paid: any) => {
      const highlight = !paid && period === 'Prelim' ? 'style="color:#c00;font-weight:700;"' : '';
      const dueRange = dueDates[period] || '';
      const paidDateStr = paid?.paymentDate
        ? new Date(paid.paymentDate).toLocaleDateString('en-PH', { month:'2-digit', day:'2-digit', year:'2-digit' })
        : '';
      const dateCell = dueRange
        ? `${dueRange}${paidDateStr ? `<br><span style="color:#166534;font-size:9px;">Paid: ${paidDateStr}</span>` : ''}`
        : (paidDateStr || '');
      return `<tr>
        <td style="padding:3px 6px;">${label}</td>
        <td style="padding:3px 6px;" ${highlight}>${dateCell}</td>
        <td style="padding:3px 6px;text-align:right;">${paid ? fmt(paid.amountPaid) : ''}</td>
        <td style="padding:3px 6px;text-align:center;">${paid ? (paid.orArNumber || '') : ''}</td>
      </tr>`;
    };

    const html = `<!DOCTYPE html><html><head><meta charset="utf-8">
<title>Statement of Account — ${name}</title>
<style>
  *{box-sizing:border-box;margin:0;padding:0;}
  body{font-family:Arial,sans-serif;font-size:10px;padding:18px 22px;color:#000;width:780px;}
  /* Header */
  .top-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;}
  .logo-area{display:flex;align-items:center;gap:10px;}
  .logo-circle{width:70px;height:70px;border:2px solid #000;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:9px;text-align:center;font-weight:900;}
  .school-info-center{text-align:center;flex:1;}
  .school-name-big{font-size:18px;font-weight:900;text-transform:uppercase;letter-spacing:1px;color:#1a1a6e;}
  .school-name-sub{font-size:9px;text-transform:uppercase;letter-spacing:0.5px;}
  .school-addr{font-size:8.5px;color:#444;margin-top:1px;}
  .badges-right{display:flex;gap:8px;align-items:center;}
  .badge-img{width:45px;height:45px;border:1px solid #ccc;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:7px;text-align:center;}
  /* SOA Title */
  .soa-title{text-align:center;font-size:11px;font-weight:900;text-transform:uppercase;border-top:2px solid #000;border-bottom:1px solid #000;padding:3px;margin:6px 0;}
  /* Student Info Bar */
  .info-bar{display:grid;grid-template-columns:1fr auto auto;gap:0;border:1px solid #000;margin-bottom:4px;}
  .info-cell{padding:3px 8px;border-right:1px solid #000;font-size:10px;}
  .info-cell:last-child{border-right:none;}
  .info-label{font-size:8.5px;color:#555;}
  .info-val{font-weight:700;font-size:11px;}
  .info-bar2{display:grid;grid-template-columns:1fr 1fr 1fr;border:1px solid #000;border-top:none;margin-bottom:8px;}
  /* Two column layout */
  .main-grid{display:grid;grid-template-columns:300px 1fr;gap:16px;}
  /* Assessment table */
  .assess-table{width:100%;border-collapse:collapse;font-size:10px;}
  .assess-table td{padding:2px 4px;border-bottom:1px dotted #ddd;}
  .assess-table td:last-child{text-align:right;min-width:70px;}
  .assess-section-title{text-align:center;font-size:10px;font-weight:700;background:#f0f0f0;border:1px solid #ccc;padding:3px;margin-bottom:2px;}
  .subtotal-row td{font-weight:700;border-top:1px solid #000;padding-top:3px;}
  .total-row td{font-weight:900;font-size:11px;background:#d0d0d0;padding:3px 4px;}
  .final-row td{font-weight:900;font-size:12px;background:#4040a0;color:#fff;padding:4px;}
  /* Schedule table */
  .sched-table{width:100%;border-collapse:collapse;font-size:10px;}
  .sched-table th{background:#4040a0;color:#fff;padding:3px 6px;text-align:left;font-size:9.5px;}
  .sched-table td{border:1px solid #ccc;padding:2px 6px;}
  .total-balance-box{border:2px solid #000;padding:5px 10px;text-align:center;margin:10px 0;}
  .total-balance-label{font-size:11px;font-weight:700;}
  .total-balance-amt{font-size:16px;font-weight:900;color:#c00;}
  /* Installment schedule */
  .install-table{width:100%;border-collapse:collapse;font-size:10px;margin-top:8px;}
  .install-table th{background:#f0c040;color:#000;font-weight:700;padding:3px 6px;border:1px solid #999;}
  .install-table td{border:1px solid #ccc;padding:3px 6px;}
  /* Withdrawal policies */
  .policies{font-size:8px;margin-top:10px;color:#333;}
  .policies p{margin-bottom:2px;}
  /* Signature */
  .sig-area{display:grid;grid-template-columns:1fr 1fr;gap:30px;margin-top:16px;}
  .sig-block{text-align:center;}
  .sig-name{font-size:11px;font-weight:900;border-bottom:1.5px solid #000;padding-bottom:2px;margin-bottom:2px;}
  .sig-title{font-size:9px;}
  .sig-date{font-size:9px;margin-top:8px;}
  @media print{body{padding:8px 12px;}@page{margin:8mm;size:A4;} .no-print{display:none!important;}}
</style></head><body>

<!-- HEADER -->
<div class="top-header">
  <div class="logo-area">
    <div class="logo-circle">ST.<br>BENILDE</div>
  </div>
  <div class="school-info-center">
    <div class="school-name-big">ST. BENILDE</div>
    <div class="school-name-sub">Center for Global Competence, Inc.</div>
    <div class="school-addr">2647 RIZAL AVENUE, WEST BAJAC-BAJAC, OLONGAPO CITY &nbsp;|&nbsp; TELEFAX: (047) 223 - 9031</div>
  </div>
  <div class="badges-right">
    <div class="badge-img">CHED<br>Reg.</div>
    <div class="badge-img">DepEd<br>Reg.</div>
    <div class="badge-img">TESDA<br>Reg.</div>
  </div>
</div>

<div class="soa-title">STATEMENT OF ACCOUNT &nbsp; ${s.semester || ''}</div>

<!-- Student Info -->
<div class="info-bar">
  <div class="info-cell">
    <div class="info-label">Name:</div>
    <div class="info-val">${name}</div>
  </div>
  <div class="info-cell">
    <div class="info-label">Course:</div>
    <div class="info-val">${s.program || ''}</div>
  </div>
  <div class="info-cell">
    <div class="info-label">Department:</div>
    <div class="info-val">${s.department || 'ICTD'}</div>
  </div>
</div>
<div class="info-bar2">
  <div class="info-cell">
    <div class="info-label">EXAMINATION COVERED</div>
    <div class="info-val">${this.currentExamCovered}</div>
  </div>
  <div class="info-cell">
    <div class="info-label">Semester:</div>
    <div class="info-val">${s.semester || ''}</div>
  </div>
  <div class="info-cell">
    <div class="info-label">Student No.:</div>
    <div class="info-val">${s.studentNumber || s.student_number || ''}</div>
  </div>
</div>

<!-- Main 2-col layout -->
<div class="main-grid">

  <!-- LEFT: Assessment -->
  <div>
    <div class="assess-section-title">ASSESSMENT FOR THE CURRENT SEMESTER</div>
    <table class="assess-table">
      <tr><td>No. of Units</td><td>${f.units || ''}</td></tr>
      <tr><td>Tuition Fee</td><td>${fmt(f.tuitionFee)}</td></tr>
      <tr><td>Miscellaneous Fee</td><td>${fmt(f.miscellaneousFee)}</td></tr>
      <tr><td>Registration Fee</td><td>${fmt(f.registrationFee)}</td></tr>
      <tr><td>NSTP Fee</td><td></td></tr>
      <tr><td>ENERGY FEE</td><td>${f.energyFee ? fmt(f.energyFee) : ''}</td></tr>
      ${(f.extraFees && f.extraFees.length > 0) ? f.extraFees.map((ef: any) => `<tr><td>${ef.fee_label}${ef.is_per_unit ? ` (${f.units} units × ${fmt(ef.rate)})` : ''}</td><td>${fmt(ef.amount)}</td></tr>`).join('') : ''}
      <tr><td>Supervision Fee</td><td></td></tr>
      <tr><td style="padding-top:4px;"># of laboratory</td><td></td></tr>
      <tr><td>Laboratory Fees:</td><td>${f.laboratoryFee ? fmt(f.laboratoryFee) : ''}</td></tr>
      <tr><td style="padding-left:12px;">Computer Lab.</td><td></td></tr>
      <tr><td style="padding-left:12px;">Kitchen Lab.</td><td></td></tr>
      <tr><td style="padding-left:12px;">Bartending Lab.</td><td></td></tr>
      <tr><td style="padding-left:12px;">F&amp;B Lab.</td><td></td></tr>
      <tr><td style="padding-left:12px;">Housekeeping Lab.</td><td></td></tr>
      <tr><td>Penalty Late Payment</td><td></td></tr>
      <tr><td>&nbsp;</td><td></td></tr>
      <tr class="subtotal-row"><td>Subtotal</td><td>${fmt(f.subtotal || (f.tuitionFee + f.miscellaneousFee + f.registrationFee + f.laboratoryFee + f.energyFee))}</td></tr>
      <tr><td>Discount of TF</td><td>${f.discount ? fmt(f.discount) : '- -'}</td></tr>
      <tr><td>Installment Fee</td><td>${isInstallment ? fmt(f.installmentFee || 0) : ''}</td></tr>
      <tr class="total-row"><td>TOTAL ASSESSMENT</td><td>${fmt(f.totalAssessment)}</td></tr>
    </table>
    <table class="assess-table" style="margin-top:4px;">
      <tr class="final-row"><td>FINAL ASSESSMENT</td><td>${fmt(f.totalAssessment)}</td></tr>
    </table>
  </div>

  <!-- RIGHT: Schedule of Payment -->
  <div>
    <table class="sched-table">
      <thead>
        <tr>
          <th>SCHEDULE OF PAYMENT</th>
          <th>DATE OF PAYMENTS</th>
          <th style="text-align:right;">PAYMENTS</th>
          <th style="text-align:center;">O.R. NUMBER</th>
        </tr>
      </thead>
      <tbody>
        ${scheduleRow('Downpayment', 'Downpayment', dpAmt, dp)}
        <tr><td colspan="4" style="padding:1px;"></td></tr>
        ${scheduleRow('PRELIM', 'Prelim', prAmt, pr)}
        <tr><td colspan="4" style="padding:1px;"></td></tr>
        ${scheduleRow('MIDTERM', 'Midterm', midAmt, mid)}
        <tr><td colspan="4" style="padding:1px;"></td></tr>
        ${scheduleRow('PREFINAL', 'Prefinal', 0, null)}
        <tr><td colspan="4" style="padding:1px;"></td></tr>
        ${scheduleRow('FINAL', 'Finals', finAmt, fin)}
      </tbody>
    </table>

    <div class="total-balance-box">
      <span class="total-balance-label">Total Balance &nbsp;&nbsp;</span>
      <span class="total-balance-amt">${fmt(f.balance ?? f.totalAssessment - f.totalPaid)}</span>
    </div>

    <!-- Installment breakdown -->
    <table class="install-table">
      <thead>
        <tr>
          <th>INSTALLMENT PAYMENT</th>
          <th>DUE DATES</th>
          <th style="text-align:right;">AMOUNT</th>
        </tr>
      </thead>
      <tbody>
        <tr><td>Downpayment :</td><td></td><td style="text-align:right;">${fmt(dpAmt)}</td></tr>
        <tr><td style="color:#c00;font-weight:700;">Prelim :</td><td style="color:#c00;font-weight:700;">${dueDates['Prelim']}</td><td style="text-align:right;">${fmt(prAmt)}</td></tr>
        <tr><td>Midterm:</td><td>${dueDates['Midterm']}</td><td style="text-align:right;">${fmt(midAmt)}</td></tr>
        <tr><td>Final:</td><td>${dueDates['Finals']}</td><td style="text-align:right;">${fmt(finAmt)}</td></tr>
      </tbody>
    </table>
    <div style="text-align:right;font-size:11px;font-weight:700;margin-top:6px;padding-right:4px;">
      Total amount to be paid: &nbsp; <span style="background:#add8e6;padding:2px 8px;">${fmt(totalAmountToBePaid)}</span>
    </div>
  </div>
</div>

<!-- Withdrawal Policies -->
<div class="policies">
  <strong>Withdrawal Policies</strong>
  <p>1. In case of withdrawal of enrollment, the amount of Php7,388.00 (Registration and Miscellaneous fees) shall be retained at all times.</p>
  <p>2. In case withdrawal is filed during the first week of classes, 50% of other fees shall be paid in addition to Php7,388.00.</p>
  <p>3. In case withdrawal is filed during the second week of classes, 50% of other fees shall be paid in addition to Php7,388.00.</p>
  <p>4. In case withdrawal is filed during the third week of classes, 100% of the total assessment shall be paid.</p>
  <p>5. No document shall be released to any withdrawing student without complete payment of financial obligation.</p>
</div>

<!-- Signatures -->
<div class="sig-area">
  <div class="sig-block">
    <div class="sig-name">Jhomer M. Onoya</div>
    <div class="sig-title">Account Management Officer</div>
    <div class="sig-date">DATE &nbsp;&nbsp;&nbsp;&nbsp; ${new Date().toLocaleDateString('en-US', {weekday:'long', year:'numeric', month:'long', day:'numeric'})}</div>
  </div>
  <div class="sig-block">
    <div class="sig-name">${name}</div>
    <div class="sig-title">Signature Over Printed Name</div>
    <div style="margin-top:8px;">
      <span style="font-size:9px;">Acknowledged by:</span>
    </div>
  </div>
</div>

<div class="no-print" style="text-align:center;margin-top:16px;">
  <button onclick="window.print()" style="background:#1a1a6e;color:white;border:none;padding:10px 32px;font-size:14px;font-weight:700;border-radius:7px;cursor:pointer;margin-right:10px;">🖨️ Print SOA</button>
  <button onclick="window.close()" style="background:#64748b;color:white;border:none;padding:10px 24px;font-size:14px;font-weight:700;border-radius:7px;cursor:pointer;">✕ Close</button>
</div>

</body></html>`;
    const win = window.open('', '_blank', 'width=860,height=900');
    if (!win) return;
    win.document.write(html); win.document.close();
  }
}