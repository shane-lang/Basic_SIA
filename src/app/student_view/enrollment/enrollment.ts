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

  userId:      number = 0;
  studentDbId: number = 0;
  student: any = {};

  // TOR hard copy notice (shown before payment for transferees)
  showTorHardCopyNotice = false;

  // TOR Evaluation state (for transferees)
  torEvaluation: {
    status: 'Pending' | 'Evaluated' | 'Rejected';
    creditedUnits: number;
    approvedUnits: number;
    creditedSubjects: { code: string; name: string; credits: number }[];
    registrarNotes: string;
    evaluatedAt: string;
  } | null = null;
  isTorLoading = false;

  // Computed fee breakdown (loaded from accounting API)
  feeBreakdown: {
    units: number; tuitionFee: number; miscellaneousFee: number;
    registrationFee: number; laboratoryFee: number; energyFee: number;
    subtotal: number; discount: number; installmentFee: number;
    totalAssessment: number; totalPaid: number; balance: number; paymentStatus: string;
  } | null = null;
  isFeeLoading = false;
  paymentPlan: 'full' | 'installment' = 'full';
  termBreakdown: { period: string; amountPaid: number; orArNumber: string; orArType: string; paymentDate: string; paymentMethod: string }[] = [];

  // Payment receipts
  paymentReceipts: any[] = [];
  isReceiptsLoading = false;

  paymentInfo = { amount: 0, discountedAmount: 0, status: 'Pending' as 'Pending' | 'Paid', dueDate: '2025-02-28', reference: '' };
  isProcessingPayment = false;
  paymentMethod: 'GCash' | 'Cash' = 'GCash';
  gcashReference = ''; gcashAmount = 0; gcashDate = new Date().toISOString().split('T')[0]; gcashSubmitted = false;

  // Fee preview (shown BEFORE payment — computed from program units)
  feePreview: {
    units: number; tuitionFee: number; miscellaneousFee: number;
    registrationFee: number; laboratoryFee: number; energyFee: number;
    subtotal: number; discount: number; installmentFee: number; totalAssessment: number;
  } | null = null;
  isFeePreviewLoading = false;

  isApprovalPending = false; approvalMessage = '';
  private pollInterval: any = null;

  currentSemester = ''; // loaded from DB via student profile
  enrolledCourses: StudentCourse[] = [];

  enrollmentSummary: {
    enrollmentDate: string; semester: string; program: string; yearLevel: string;
    totalCourses: number; totalCredits: number;
    courses: StudentCourse[];
    payment: PaymentSummary; termPayments: TermPayment[];
  } | null = null;

  currentView: 'dashboard' | 'enrollment-summary' = 'dashboard';
  showDropModal         = false;
  selectedCourseForDrop: StudentCourse | null = null;
  showEditModal         = false; editForm: any = {};
  isAutoEnrolling       = false;
  notifications: EnrollmentNotification[] = [];
  addDropDeadline = '2025-04-15';

  ngOnInit(): void {
    const storedUser = localStorage.getItem('currentUser');
    if (!storedUser) { this.router.navigate(['/login']); return; }
    const user = JSON.parse(storedUser);
    this.userId = user.id;

    const storedPm = localStorage.getItem('pendingPaymentMethod');
    if (storedPm === 'Cash' || storedPm === 'GCash') this.paymentMethod = storedPm as 'Cash' | 'GCash';

    const storedPlan = localStorage.getItem('pendingPaymentPlan');
    if (storedPlan === 'installment') this.paymentPlan = 'installment';

    this.http.get<any>(`${this.apiUrl}?action=get_profile&user_id=${this.userId}`).subscribe({
      next: (res) => {
        if (res.success) {
          this.student     = res.student;
          this.studentDbId = res.student.dbId;
          localStorage.setItem('studentDbId', String(res.student.dbId));
          if (!storedPm) this.paymentMethod = res.student.paymentMethod === 'Cash' ? 'Cash' : 'GCash';
          if (res.student.paymentPlan) this.paymentPlan = res.student.paymentPlan;

          if (res.student.approvalStatus === 'Approved' || res.student.enrollmentStatus === 'Enrolled') {
            localStorage.removeItem('pendingPaymentMethod');
            this.currentSemester = res.student.semester ?? '';
            this.setStep('dashboard');
            this.ensureEnrolledThenLoad();
          } else if (res.student.torEvalStatus === 'Pending') {
            // Transferee waiting for registrar to evaluate TOR
            this.setStep('tor-pending');
            this.startTorPolling();
          } else if (res.student.torEvalStatus === 'Evaluated') {
            // Registrar done — load new fee and show hard copy reminder first
            this.loadFeePreview(res.student.program);
            this.loadTorEvaluation();
            const hardCopyDismissed = localStorage.getItem('torHardCopyDismissed_' + this.studentDbId);
            if (!hardCopyDismissed) {
              this.showTorHardCopyNotice = true;
            }
            if (this.paymentMethod === 'Cash') {
              this.setStep('cash-pending'); this.isApprovalPending = true; this.startApprovalPolling();
            } else {
              this.setStep('payment');
            }
          } else if (res.student.torEvalStatus === 'Rejected') {
            // TOR rejected — show payment step but with rejection notice
            this.loadFeePreview(res.student.program);
            this.loadTorEvaluation();
            this.setStep('payment');
          } else if (res.student.paymentStatus === 'Paid') {
            this.setStep('approval'); this.isApprovalPending = true; this.startApprovalPolling();
          } else if (this.paymentMethod === 'Cash') {
            this.loadFeePreview(res.student.program);
            this.setStep('cash-pending'); this.isApprovalPending = true; this.startApprovalPolling();
          } else {
            this.loadFeePreview(res.student.program);
            this.setStep('payment');
          }
        } else { this.router.navigate(['/login']); }
        this.cdr.detectChanges();
      },
      error: () => { this.addNotification('error', 'Cannot load profile. Check XAMPP is running.'); this.cdr.detectChanges(); }
    });
  }

  setStep(step: typeof this.workflowStep): void {
    this.workflowStep = step;
    localStorage.setItem('enrollmentStep', step);
  }

  // FIX: On dashboard load, auto_enroll silently (returns early if already enrolled)
  ensureEnrolledThenLoad(): void {
    this.http.post<any>(`${this.apiUrl}?action=auto_enroll_all`, {
      student_id: this.studentDbId, semester: this.currentSemester,
    }).subscribe({
      next: () => { this.loadEnrolledCourses(); this.loadEnrollmentSummary(); this.loadFeeBreakdown(); this.cdr.detectChanges(); },
      error: () => { this.loadEnrolledCourses(); this.loadEnrollmentSummary(); this.loadFeeBreakdown(); this.cdr.detectChanges(); }
    });
  }

  processPayment(): void {
    if (!this.studentDbId) { this.addNotification('error', 'Student ID missing. Please restart.'); return; }
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
          this.setStep('approval'); this.isApprovalPending = true;
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

  // ── TOR Polling — checks every 15s if registrar has evaluated ──
  private torPollInterval: any = null;

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
            evaluatedAt:      res.evaluation.evaluatedAt     || '',
          };
          // If evaluated → stop polling, reload fee, go to payment
          if (res.evaluation.status === 'Evaluated') {
            if (this.torPollInterval) clearInterval(this.torPollInterval);
            this.loadFeePreview(this.student.program);
            if (this.paymentMethod === 'Cash') {
              this.setStep('cash-pending'); this.isApprovalPending = true; this.startApprovalPolling();
            } else {
              this.setStep('payment');
            }
          } else if (res.evaluation.status === 'Rejected') {
            if (this.torPollInterval) clearInterval(this.torPollInterval);
            this.loadFeePreview(this.student.program);
            this.setStep('payment');
          }
        }
        this.cdr.detectChanges();
      },
      error: () => { this.isTorLoading = false; this.cdr.detectChanges(); }
    });
  }

  checkApprovalStatus(): void {
    if (!this.userId) return;
    this.http.get<any>(`${this.apiUrl}?action=get_payment_status&user_id=${this.userId}`).subscribe({
      next: (res) => {
        if (res.success && res.approvalStatus === 'Approved') {
          clearInterval(this.pollInterval);
          localStorage.removeItem('pendingPaymentMethod');
          this.isApprovalPending = false;
          this.approvalMessage = this.paymentMethod === 'Cash' ? '💵 Cash payment confirmed by Accounting!' : '📱 GCash payment verified by Accounting!';
          this.addNotification('success', 'Payment approved!');
          // FIX: Reload full profile so currentSemester is set before proceeding to dashboard
          this.http.get<any>(`${this.apiUrl}?action=get_profile&user_id=${this.userId}`).subscribe({
            next: (pRes) => {
              if (pRes.success) {
                this.student       = pRes.student;
                this.studentDbId   = pRes.student.dbId;
                this.currentSemester = pRes.student.semester ?? this.currentSemester;
              }
              this.cdr.detectChanges();
            }
          });
        }
      }
    });
  }

  proceedToDashboard(): void {
    this.isAutoEnrolling = true; this.cdr.detectChanges();

    // FIX: If currentSemester is still empty (can happen if payment was approved
    // before profile fully refreshed), reload profile first to get it
    const doEnroll = () => {
      this.http.post<any>(`${this.apiUrl}?action=auto_enroll_all`, {
        student_id: this.studentDbId, semester: this.currentSemester,
      }).subscribe({
        next: (res) => {
          this.isAutoEnrolling = false;
          if (res.success && res.enrolled > 0) this.addNotification('success', `✅ ${res.enrolled} subject(s) auto-enrolled for your program!`);
          this.setStep('dashboard'); this.loadEnrolledCourses(); this.loadEnrollmentSummary(); this.loadFeeBreakdown(); this.cdr.detectChanges();
        },
        error: () => { this.isAutoEnrolling = false; this.setStep('dashboard'); this.loadEnrolledCourses(); this.loadEnrollmentSummary(); this.cdr.detectChanges(); }
      });
    };

    if (!this.currentSemester) {
      this.http.get<any>(`${this.apiUrl}?action=get_profile&user_id=${this.userId}`).subscribe({
        next: (pRes) => {
          if (pRes.success) {
            this.student         = pRes.student;
            this.studentDbId     = pRes.student.dbId;
            this.currentSemester = pRes.student.semester ?? '';
          }
          doEnroll();
        },
        error: () => doEnroll()
      });
    } else {
      doEnroll();
    }
  }

  loadFeePreview(programName: string): void {
    if (!programName) return;
    this.isFeePreviewLoading = true;
    this.cdr.detectChanges();
    // Fetch fee preview using program name — backend will sum course credits for that program
    this.http.get<any>(`${this.accountingApi}?action=get_fee_preview&program=${encodeURIComponent(programName)}&student_id=${this.studentDbId}`).subscribe({
      next: (res) => {
        this.isFeePreviewLoading = false;
        if (res.success && res.fees) {
          this.feePreview = res.fees;
          this.gcashAmount = res.fees.totalAssessment;
          this.paymentInfo.amount = res.fees.totalAssessment;
        }
        this.cdr.detectChanges();
      },
      error: () => { this.isFeePreviewLoading = false; this.cdr.detectChanges(); }
    });
  }

  loadFeeBreakdown(): void {
    if (!this.studentDbId) return;
    this.isFeeLoading = true;
    this.http.get<any>(`${this.accountingApi}?action=get_student_receipts&student_id=${this.studentDbId}`).subscribe({
      next: (res) => {
        this.isFeeLoading = false;
        if (res.success) {
          this.feeBreakdown   = res.fees ? { ...res.fees, totalPaid: res.totalPaid, balance: res.balance, paymentStatus: res.paymentStatus } : null;
          this.paymentReceipts = res.payments || [];
          this.termBreakdown   = res.termBreakdown || [];
          if (res.paymentPlan) this.paymentPlan = res.paymentPlan;
        }
        this.cdr.detectChanges();
      },
      error: () => { this.isFeeLoading = false; this.cdr.detectChanges(); }
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
            courses: res.courses,
            payment: res.payment, termPayments: res.termPayments,
          };
          this.cdr.detectChanges();
        }
      }
    });
  }

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
      emergencyContact: this.editForm.emergencyContact, emergencyPhone: this.editForm.emergencyPhone, dateOfBirth: this.editForm.dateOfBirth,
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
    localStorage.setItem('torHardCopyDismissed_' + this.studentDbId, '1');
    this.showTorHardCopyNotice = false;
    this.cdr.detectChanges();
  }

  get approvedCourses(): StudentCourse[] { return this.enrolledCourses.filter(c => c.status === 'Enrolled'); }
  get totalCredits(): number { return this.approvedCourses.reduce((s, c) => s + c.credits, 0); }
  canDropCourse(): boolean { return new Date() <= new Date(this.addDropDeadline); }

  /** Returns true if the given term (Downpayment/Prelim/Midterm/Finals) has not been paid yet */
  isTermUnpaid(term: string): boolean {
    return !this.termBreakdown.some(t => t.period === term);
  }

  /** Amount due per installment term (total / 4, Finals gets remainder) */
  get installmentTermAmount(): number {
    if (!this.feeBreakdown) return 0;
    return Math.ceil(this.feeBreakdown.totalAssessment / 4);
  }

  get installmentFinalsAmount(): number {
    if (!this.feeBreakdown) return 0;
    return this.feeBreakdown.totalAssessment - (this.installmentTermAmount * 3);
  }

  /** All 4 installment terms with status and expected amount */
  get installmentSchedule(): { term: string; label: string; amount: number; paid: boolean; amountPaid: number; orNo: string; paymentDate: string }[] {
    const terms = [
      { term: 'Downpayment', label: '1st — Downpayment', amount: this.installmentTermAmount },
      { term: 'Prelim',      label: '2nd — Prelim',      amount: this.installmentTermAmount },
      { term: 'Midterm',     label: '3rd — Midterm',     amount: this.installmentTermAmount },
      { term: 'Finals',      label: '4th — Finals',      amount: this.installmentFinalsAmount },
    ];
    return terms.map(t => {
      const paid = this.termBreakdown.find(tb => tb.period === t.term);
      return {
        term:        t.term,
        label:       t.label,
        amount:      t.amount,
        paid:        !!paid,
        amountPaid:  paid ? paid.amountPaid : 0,
        orNo:        paid ? `${paid.orArType}: ${paid.orArNumber}` : '',
        paymentDate: paid ? paid.paymentDate : '',
      };
    });
  }

  getTermIcon(status: string): string { return status === 'Paid' ? '✅' : status === 'Partial' ? '🔶' : '❌'; }
  getTermClass(status: string): string { return status === 'Paid' ? 'term-paid' : status === 'Partial' ? 'term-partial' : 'term-unpaid'; }
  getPayStatusClass(status: string): string { return status === 'Paid' ? 'pay-paid' : status === 'Partial' ? 'pay-partial' : 'pay-unpaid'; }

  addNotification(type: EnrollmentNotification['type'], message: string): void {
    const n: EnrollmentNotification = { id: 'n-' + Date.now(), type, message, timestamp: new Date() };
    this.notifications.push(n);
    setTimeout(() => { const i = this.notifications.findIndex(x => x.id === n.id); if (i !== -1) { this.notifications.splice(i, 1); this.cdr.detectChanges(); } }, 5000);
  }
  dismissNotification(id: string): void {
    const i = this.notifications.findIndex(n => n.id === id);
    if (i !== -1) { this.notifications.splice(i, 1); this.cdr.detectChanges(); }
  }
}