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
}