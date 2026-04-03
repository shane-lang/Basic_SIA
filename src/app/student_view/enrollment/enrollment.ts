import { Component, OnInit, OnDestroy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { Router } from '@angular/router';
import { environment } from '../../environment';

interface StudentCourse {
  id: number; courseId: number; code: string; name: string; credits: number;
  lecUnits?: number; labUnits?: number; isGeneral?: boolean; isLab?: boolean;
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
  private apiUrl        = environment.enrollApi;
  private accountingApi = environment.accountingApi;
  private registrarApi  = environment.registrarApi;
  constructor(private http: HttpClient, public router: Router, private cdr: ChangeDetectorRef) {}

  workflowStep: 'payment' | 'cash-pending' | 'approval' | 'dashboard' | 'tor-pending' | 're-enroll' | 'graduated' | 'pending-approval' = 'payment';
  // Re-enrollment state
  needsReEnroll      = false;
  nextSemester       = '';
  nextYearLevel      = '';
  isReEnrolling      = false;
  needsPlanSelection = false;
  // Graduation state
  isGraduated      = false;
  graduatedProgram = '';
  graduatedYear    = '';
  graduatedSemester = '';
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
  // Resolved plan for the SOA currently on screen — may differ from paymentPlan
  // (current semester) when the user is viewing a past semester. Always use this
  // inside currentExamCovered and SOA-related getters.
  soaPaymentPlan: 'full' | 'installment' = 'full';
  termBreakdown: { period: string; amountPaid: number; orArNumber: string; orArType: string; paymentDate: string; paymentMethod: string }[] = [];

  // ── Scholarship declaration (student-side) ────────────────────────────────
  isScholar        = false;
  scholarType      = '';
  scholarGrantor   = '';
  scholarshipAmount = 0;
  scholarFullTuition = false;   // true = full coverage, no payment needed
  scholarPending   = false;     // true after submission, waiting for accounting approval
  scholarApproved  = false;     // true if accounting already approved

  scholarTypes = [
    'CHED Scholarship',
    'Government Scholarship',
    'LGU Scholarship',
    'Athletic Scholarship',
    'Academic Excellence Scholarship',
    'SHS Voucher Program',
    'TESDA PRISAA',
    'Full Scholarship',
    'Partial Scholarship',
    'Financial Assistance',
    'Others',
  ];
  paymentReceipts: any[] = [];

  // ── SOA Semester History ──────────────────────────────────────────────────
  // Populated on dashboard load — lets students browse SOA from past terms.
  soaSemesters:       string[]  = [];
  selectedSoaSemester = '';          // '' = current (default, no filter)
  soaDisplaySemester  = '';          // semester label shown in SOA header + print
  // BUG-SOA-DUES-01 FIX: per-term scheduled dues returned by PHP for the selected
  // semester — populated in selectSoaSemester() so installmentAmounts uses the
  // correct stored figures instead of recalculating from totalAssessment.
  storedInstDues: { dpDue: number; prelimDue: number; midtermDue: number; finalsDue: number } | null = null;
  isSoaHistoryLoading = false;

  // Payment due dates — loaded from Accounting (sys_config)
  dueDates: { [key: string]: { label: string; date_range: string } } = {
    downpayment: { label: 'Downpayment', date_range: '' },
    prelim:      { label: 'Prelim',      date_range: '' },
    midterm:     { label: 'Midterm',     date_range: '' },
    finals:      { label: 'Finals',      date_range: '' },
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
    const storedUser = sessionStorage.getItem('currentUser');
    if (!storedUser) { this.router.navigate(['/login']); return; }
    this.userId = JSON.parse(storedUser).id;

    // ── Restore last known step IMMEDIATELY to prevent flash back to 'payment' ──
    // This is purely a visual restore — loadContext() will correct it from DB truth.
    // Restore graduation state if the student already graduated in a previous session
    if (sessionStorage.getItem('enrollmentStep') === 'graduated') {
      this.isGraduated = true;
    }
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

    // FIX DUE-DATE-FE-01: Do NOT call loadDueDates() here — studentDbId is 0
    // at this point because loadContext() hasn't finished yet. loadDueDates() is
    // now called inside loadDashboard() which runs AFTER studentDbId is set.
    this.loadContext();
  }

  loadDueDates(semester?: string): void {
    // Pass student_id so the backend resolves the correct semester-scoped due dates
    // for the student's current term.
    // When viewing a past SOA semester, pass that semester string explicitly so the
    // backend tries the scoped key for that term first, then falls back to global.
    const sid = this.studentDbId || 0;
    let url = `${this.accountingApi}?action=get_due_dates`;
    if (semester) {
      // semester may be a full string like "1st Semester, AY 2025-2026" —
      // send it as-is; the PHP backend (FIX DUE-DATE-GET-01) parses it correctly.
      url += `&semester=${encodeURIComponent(semester)}`;
      const ayMatch = semester.match(/(\d{4}-\d{4})/);
      if (ayMatch) url += `&school_year=${encodeURIComponent(ayMatch[1])}`;
    } else if (sid > 0) {
      url += `&student_id=${sid}`;
    }
    this.http.get<any>(url).subscribe({
      next: res => {
        if (res.success && res.dueDates) {
          // Always replace all keys so switching to a different semester shows
          // that term's dates (not a mix with the previous term's non-empty values).
          const blank = { downpayment: { label: 'Downpayment', date_range: '' },
                          prelim:      { label: 'Prelim',      date_range: '' },
                          midterm:     { label: 'Midterm',     date_range: '' },
                          finals:      { label: 'Finals',      date_range: '' } };
          this.dueDates = { ...blank, ...res.dueDates };
          this.cdr.detectChanges();
        }
      }
    });
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
        this.studentCategory = (res.student.studentCategory ?? '').toUpperCase();
        // Fallback: infer from student number if DB category is still blank
        if (!this.studentCategory) {
          const sNum: string = res.student.id ?? '';
          if (sNum.startsWith('SHS-'))  this.studentCategory = 'SHS';
          else if (sNum.startsWith('TVET-')) this.studentCategory = 'TVET';
        }
        sessionStorage.setItem('studentCategory', this.studentCategory);
        this.paymentMethod      = res.student.paymentMethod === 'Cash' ? 'Cash' : 'GCash';
        // FIX FE-PLAN-01: payment_plan is NULL after re-enroll — backend sends
        // needsPlanSelection:true so we show the plan selector instead of jumping to GCash.
        this.needsPlanSelection = res.needsPlanSelection === true;
        this.paymentPlan        = (res.paymentPlan ?? res.student?.paymentPlan) === 'installment' ? 'installment' : 'full';
        this.soaPaymentPlan     = this.paymentPlan; // init SOA plan = current semester
        this.fees               = res.fees ?? null;
        this.termBreakdown      = res.termBreakdown ?? [];
        this.paymentReceipts    = res.payments ?? [];

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
        sessionStorage.setItem('studentDbId', String(this.studentDbId));

        // ── Stop any pending approval poll — DB is now the source of truth ──
        if (this.pollInterval) { clearInterval(this.pollInterval); this.pollInterval = null; }

        // ── RE-ENROLLMENT CHECK ───────────────────────────────────
        // Must be checked FIRST — takes priority over all other routing
        if (res.needsReEnroll) {
          this.needsReEnroll = true;
          this.nextSemester  = res.nextSemester  ?? '';
          this.nextYearLevel = res.nextYearLevel ?? '';
          this.route('re-enroll');
          this.cdr.detectChanges();
          return;
        }
        this.needsReEnroll = false;

        // FIX FE-PLAN-02: After re-enroll, needsPlanSelection=true means student
        // must pick full/installment before paying. Route to payment so plan selector shows.
        if (this.needsPlanSelection) {
          sessionStorage.removeItem('enrollmentStep');
          this.route('payment');
          this.cdr.detectChanges();
          return;
        }

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
          // Transferee TOR done — proceed to payment step
          this.route(isCash ? 'cash-pending' : 'payment');
          if (isCash) { this.isApprovalPending = true; this.startApprovalPolling(); }

        } else if (torRejected) {
          this.route('payment');

        } else if (paid && !isCash) {
          // Paid via GCash — waiting for Registrar to approve enrollment
          this.route('approval');
          this.isApprovalPending = true;
          this.startApprovalPolling();

        } else if (isCash) {
          // Cash student — waiting for Accounting to verify payment
          this.route('cash-pending');
          this.isApprovalPending = true;
          this.startApprovalPolling();

        } else if (!isCash && s.paymentStatus === 'Submitted') {
          // GCash submitted but not yet verified by Accounting — show waiting screen
          this.gcashSubmitted = true;
          this.route('approval');
          this.isApprovalPending = true;
          this.startApprovalPolling();

        } else if (s.approvalStatus === 'Pending' && s.paymentStatus === 'Pending') {
          // Student just registered — route by payment method:
          // • GCash → show payment form so student can enter their GCash reference number.
          //           Card only appears in Accounting AFTER they submit the reference here.
          // • Cash  → show pending-approval (they walk in to Accounting directly)
          if (isCash) {
            this.route('pending-approval');
            this.isApprovalPending = true;
            this.startApprovalPolling();
          } else {
            // GCash student: must submit reference number first
            this.route('payment');
          }

        } else {
          this.route('payment');
        }

        // FIX DUE-DATE-FE-04: Always load due dates after context resolves —
        // every routing path (approval, cash-pending, payment, dashboard) needs
        // them. currentSemester is set above so the scoped key will be correct.
        this.loadDueDates(this.currentSemester || undefined);
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

  // ── RE-ENROLLMENT ────────────────────────────────────────────
  startReEnroll(): void {
    if (this.isReEnrolling) return;
    this.isReEnrolling = true;
    this.http.post<any>(`${this.apiUrl}?action=re_enroll`,
      { student_id: this.studentDbId }
    ).subscribe({
      next: (res) => {
        this.isReEnrolling = false;
        if (res.success) {
          if (res.isGraduated) {
            // Student has completed their program — show graduation screen
            this.isGraduated       = true;
            this.graduatedProgram  = res.program  ?? this.student.program ?? '';
            this.graduatedYear     = res.yearLevel ?? this.student.yearLevel ?? '';
            this.graduatedSemester = res.semester  ?? this.student.semester ?? '';
            this.needsReEnroll     = false;
            sessionStorage.setItem('enrollmentStep', 'graduated');
            this.route('graduated');
            this.addNotification('success', '🎓 Congratulations! You have completed your program.');
          } else {
            // Normal re-enrollment — clear all cached state so the payment plan
            // selector always shows fresh, then reload from DB.
            this.student.yearLevel  = res.newYearLevel;
            this.student.semester   = res.newSemester;
            this.currentSemester    = res.newSemester;
            this.needsReEnroll      = false;
            this.needsPlanSelection = true;
            // FIX FE-PLAN-04: Clear sessionStorage step so the restored step from
            // the old semester does not skip the payment plan selector on reload.
            sessionStorage.removeItem('enrollmentStep');
            sessionStorage.removeItem('pendingPaymentPlan');
            sessionStorage.removeItem('pendingPaymentMethod');
            this.addNotification('success', `Re-enrollment started for ${res.newSemester}. Please select a payment plan.`);
            this.loadContext();
          }
        } else {
          this.addNotification('error', res.message || 'Re-enrollment failed.');
        }
        this.cdr.detectChanges();
      },
      error: () => {
        this.isReEnrolling = false;
        this.addNotification('error', 'Connection error. Make sure XAMPP is running.');
        this.cdr.detectChanges();
      }
    });
  }

  // ── SELECT PAYMENT PLAN (re-enroll flow) ────────────────────
  // Called when student picks full/installment on the payment step after re-enroll.
  // Saves choice to DB via update_payment_plan, then clears needsPlanSelection
  // so the GCash form renders on the next loadContext().
  selectPaymentPlan(plan: 'full' | 'installment', method: 'GCash' | 'Cash'): void {
    this.paymentPlan   = plan;
    this.paymentMethod = method;
    this.http.post<any>(`${this.apiUrl}?action=update_payment_plan`, {
      student_id: this.studentDbId,
      payment_plan: plan,
      payment_method: method,
    }).subscribe({
      next: (res) => {
        if (res.success) {
          this.needsPlanSelection = false;
          // Recompute GCash amount now that we know the plan
          if (this.fees) {
            this.gcashAmount        = plan === 'installment' ? this.dpAmount : this.fees.totalAssessment;
            this.paymentInfo.amount = this.gcashAmount;
          }
          this.addNotification('success', `Payment plan set to ${plan}.`);
          this.cdr.detectChanges();
        } else {
          this.addNotification('error', res.message || 'Could not save payment plan.');
        }
      },
      error: () => { this.addNotification('error', 'Connection error saving payment plan.'); }
    });
  }

  ensureEnrolledThenLoad(): void {
    // Always load dashboard data first so screen isn't blank while auto-enroll runs
    this.loadDashboard();
    // Then also trigger auto-enroll in case courses are missing (idempotent - safe to re-run)
    this.http.post<any>(`${this.apiUrl}?action=auto_enroll_all`, {
      student_id: this.studentDbId, semester: this.currentSemester,
    }).subscribe({
      // After auto-enroll finishes, reload courses to pick up any newly enrolled subjects
      next:  () => { this.loadEnrolledCourses(); this.loadEnrollmentSummary(); },
      error: () => { /* already loaded above, silently ignore */ },
    });
  }

  loadDashboard(): void {
    // Always reload all dashboard data sources in parallel
    this.loadEnrolledCourses();
    this.loadEnrollmentSummary();
    // FIX DUE-DATE-FE-03: Load due dates here where studentDbId is already set.
    // Passing currentSemester lets the backend resolve the scoped key directly;
    // fallback to student_id lookup happens inside loadDueDates() when no semester.
    this.loadDueDates(this.currentSemester || undefined);
    this.http.get<any>(`${this.apiUrl}?action=get_student_context&user_id=${this.userId}`).subscribe({
      next: (res) => {
        if (res.success) {
          this.fees            = res.fees ?? null;
          this.termBreakdown   = res.termBreakdown ?? [];
          this.paymentReceipts = res.payments ?? [];
          this.paymentPlan     = (res.paymentPlan ?? res.student?.paymentPlan) === 'installment' ? 'installment' : 'full';
          // Keep soaPaymentPlan in sync when viewing the current semester
          if (!this.selectedSoaSemester || this.selectedSoaSemester === this.soaSemesters[0]) {
            this.soaPaymentPlan = this.paymentPlan;
          }
        }
        this.cdr.detectChanges();
      },
      error: () => { this.cdr.detectChanges(); }
    });
    // Load semester list so the SOA history dropdown is ready
    this.loadSoaSemesters();
  }

  // ── Load list of semesters for which this student has SOA records ─────────
  loadSoaSemesters(): void {
    if (!this.studentDbId) return;
    this.http.get<any>(`${this.accountingApi}?action=get_soa_semesters&student_id=${this.studentDbId}`).subscribe({
      next: (res) => {
        if (res.success && res.semesters?.length) {
          this.soaSemesters = res.semesters;
          // Default to current semester (first in list = newest)
          if (!this.selectedSoaSemester) {
            this.selectedSoaSemester = res.semesters[0];
            this.soaDisplaySemester  = res.semesters[0];
          }
        }
        this.cdr.detectChanges();
      }
    });
  }

  // ── Called when student picks a different semester from the dropdown ───────
  selectSoaSemester(sem: string): void {
    // BUG-SOA-SWITCH-01 FIX: Removed the `if (selectedSoaSemester === sem) return` early-exit.
    // That guard blocked re-fetching when the user clicked back to the current semester tab
    // after visiting an old one — paymentReceipts stayed overwritten with the old semester's
    // data and was never restored. Now we always re-fetch regardless of whether the semester
    // matches the previously selected value.
    const isCurrentSemester = (sem === this.soaSemesters[0]);
    this.selectedSoaSemester = sem;
    this.soaDisplaySemester  = sem;   // FIX SOA-01: update header label immediately
    this.isSoaHistoryLoading = true;

    // FIX SOA-FE-01: Clear stale term data IMMEDIATELY before the API call.
    this.termBreakdown   = [];
    this.paymentReceipts = [];

    // BUG-SOA-SWITCH-01 FIX: When switching back to the current semester, re-fetch
    // from get_student_context (the authoritative source for current-semester data)
    // instead of get_student_payment_history, which only has historical records.
    // Must restore ALL derived state — fees, paymentPlan, termBreakdown, paymentReceipts,
    // and enrollmentSummary.semester — otherwise installmentAmounts/dpAmount/etc still
    // read stale values left over from the old semester's context.
    if (isCurrentSemester) {
      this.loadDueDates(sem);
      // BUG-SOA-DUES-01 FIX: clear stored dues so installmentAmounts recalculates live
      this.storedInstDues = null;
      this.http.get<any>(`${this.apiUrl}?action=get_student_context&user_id=${this.userId}`).subscribe({
        next: (res) => {
          this.isSoaHistoryLoading = false;
          if (res.success) {
            this.fees            = res.fees ?? null;
            this.paymentPlan     = (res.paymentPlan ?? res.student?.paymentPlan) === 'installment' ? 'installment' : 'full';
            this.soaPaymentPlan  = this.paymentPlan; // sync SOA plan to current semester
            this.termBreakdown   = res.termBreakdown ?? [];
            this.paymentReceipts = res.payments ?? [];
            if (this.enrollmentSummary && res.student?.semester) {
              this.enrollmentSummary = { ...this.enrollmentSummary, semester: res.student.semester };
            }
          }
          this.cdr.detectChanges();
        },
        error: () => { this.isSoaHistoryLoading = false; this.cdr.detectChanges(); }
      });
      return;
    }

    this.loadDueDates(sem);

    // FIX-SOA-SNAPSHOT: Use get_soa_snapshot (frozen per-semester record written at
    // re-enrollment) instead of get_student_payment_history (reads live tuition_fees
    // which has NULL-semester rows that bleed across semesters). The snapshot is the
    // only guaranteed-correct, immutable source for a past semester's SOA data.
    this.http.get<any>(
      `${this.apiUrl}?action=get_soa_snapshot&student_id=${this.studentDbId}&semester=${encodeURIComponent(sem)}`
    ).subscribe({
      next: (res) => {
        this.isSoaHistoryLoading = false;
        const snap = res.snapshot;

        if (res.success && snap) {
          // ── Restore frozen fee breakdown ────────────────────────────────
          const resolvedPlan: 'full' | 'installment' =
            snap.payment_plan === 'installment' ? 'installment' : 'full';
          this.soaPaymentPlan = resolvedPlan;

          this.fees = {
            ...(this.fees ?? {} as any),
            units:            snap.units            ?? 0,
            tuitionFee:       snap.tuition_fee       ?? 0,
            miscellaneousFee: snap.miscellaneous_fee ?? 0,
            registrationFee:  snap.registration_fee  ?? 0,
            laboratoryFee:    snap.laboratory_fee    ?? 0,
            energyFee:        snap.energy_fee        ?? 0,
            subtotal:         snap.subtotal          ?? 0,
            discount:         snap.discount          ?? 0,
            installmentFee:   snap.installment_fee   ?? 0,
            totalAssessment:  snap.total_assessment  ?? 0,
            totalPaid:        snap.total_paid        ?? 0,
            balance:          snap.balance           ?? 0,
            paymentStatus:    snap.payment_status    ?? 'Unpaid',
          };

          // ── Restore frozen payment records → termBreakdown + paymentReceipts ──
          // snapshot.payments is the JSON array frozen at re-enrollment time —
          // it contains every OR/AR for that semester exactly as issued.
          const frozenPayments: any[] = snap.payments ?? [];

          this.paymentReceipts = frozenPayments.map((p: any) => ({
            orArNumber:  p.or_ar_number  ?? p.orArNumber  ?? '',
            orArType:    p.or_ar_type    ?? p.orArType    ?? 'AR',
            amount:      p.amount        ?? 0,
            paymentDate: p.payment_date  ?? p.paymentDate ?? '',
            period:      p.exam_period   ?? p.examPeriod  ?? '',
            method:      p.payment_method ?? p.method     ?? '',
            semester:    p.semester      ?? sem,
          }));

          // Rebuild termBreakdown: sum per period from frozen payments
          const periodMap: { [k: string]: { amountPaid: number; orArNumber: string; orArType: string; paymentDate: string; paymentMethod: string } } = {};
          for (const p of frozenPayments) {
            const period = p.exam_period ?? p.examPeriod ?? '';
            if (!period) continue;
            if (!periodMap[period]) {
              periodMap[period] = {
                amountPaid:    0,
                orArNumber:    p.or_ar_number  ?? p.orArNumber  ?? '',
                orArType:      p.or_ar_type    ?? p.orArType    ?? 'AR',
                paymentDate:   p.payment_date  ?? p.paymentDate ?? '',
                paymentMethod: p.payment_method ?? p.method     ?? '',
              };
            }
            periodMap[period].amountPaid    += +(p.amount ?? 0);
            periodMap[period].orArNumber     = p.or_ar_number  ?? p.orArNumber  ?? periodMap[period].orArNumber;
            periodMap[period].paymentDate    = p.payment_date  ?? p.paymentDate ?? periodMap[period].paymentDate;
          }
          this.termBreakdown = Object.entries(periodMap).map(([period, v]) => ({
            period,
            amountPaid:    v.amountPaid,
            orArNumber:    v.orArNumber,
            orArType:      v.orArType,
            paymentDate:   v.paymentDate,
            paymentMethod: v.paymentMethod,
          }));

          // ── Stored installment dues from snapshot (total/4 split) ──────
          if (resolvedPlan === 'installment') {
            const total   = snap.total_assessment ?? 0;
            const dpPaid  = periodMap['Downpayment']?.amountPaid ?? 0;
            const dpCredit = dpPaid > 0 ? dpPaid : Math.ceil(total / 4);
            const rem      = Math.max(0, total - dpCredit);
            const pd       = rem > 0 ? Math.ceil(rem / 3) : 0;
            this.storedInstDues = {
              dpDue:      dpPaid > 0 ? dpPaid : Math.ceil(total / 4),
              prelimDue:  pd,
              midtermDue: pd,
              finalsDue:  rem > 0 ? Math.max(0, rem - pd * 2) : 0,
            };
          } else {
            this.storedInstDues = null;
          }

          if (this.enrollmentSummary) {
            this.enrollmentSummary = { ...this.enrollmentSummary, semester: sem };
          }

        } else {
          // No snapshot yet — fall back to live payment history for this semester
          // (happens when the student hasn't re-enrolled yet after this semester)
          this.storedInstDues = null;
          this.http.get<any>(
            `${this.accountingApi}?action=get_student_payment_history&student_id=${this.studentDbId}&semester=${encodeURIComponent(sem)}`
          ).subscribe({
            next: (hRes) => {
              if (hRes.success && hRes.semFees) {
                const sf = hRes.semFees;
                const resolvedPlan: 'full' | 'installment' =
                  sf.paymentPlan === 'installment' ? 'installment' : 'full';
                this.soaPaymentPlan = resolvedPlan;
                this.fees = {
                  ...(this.fees ?? {} as any),
                  units: sf.units ?? 0, tuitionFee: sf.tuitionFee ?? 0,
                  miscellaneousFee: sf.miscellaneousFee ?? 0,
                  registrationFee: sf.registrationFee ?? 0,
                  laboratoryFee: sf.laboratoryFee ?? 0,
                  energyFee: sf.energyFee ?? 0,
                  subtotal: sf.subtotal ?? 0, discount: sf.discount ?? 0,
                  installmentFee: sf.installmentFee ?? 0,
                  totalAssessment: sf.totalAssessment ?? 0,
                  totalPaid: sf.totalPaid ?? 0, balance: sf.balance ?? 0,
                  paymentStatus: sf.paymentStatus ?? 'Unpaid',
                };
                if (resolvedPlan === 'installment' && sf.dpDue != null) {
                  this.storedInstDues = { dpDue: sf.dpDue ?? 0, prelimDue: sf.prelimDue ?? 0, midtermDue: sf.midtermDue ?? 0, finalsDue: sf.finalsDue ?? 0 };
                }
                // Use an explicitly-typed map so Object.entries gives typed values
                type PeriodEntry = { amountPaid: number; orArNumber: string; orArType: string; paymentDate: string; paymentMethod: string };
                const pMap: Record<string, PeriodEntry> = {};
                for (const h of (hRes.history ?? [])) {
                  const p2 = h.examPeriod as string;
                  if (!pMap[p2]) pMap[p2] = { amountPaid: 0, orArNumber: h.orArNumber, orArType: h.orArType, paymentDate: h.paymentDate, paymentMethod: h.paymentMethod };
                  pMap[p2].amountPaid += h.amount; pMap[p2].orArNumber = h.orArNumber; pMap[p2].paymentDate = h.paymentDate;
                }
                this.termBreakdown = Object.entries(pMap).map(([period, v]) => ({ period, amountPaid: v.amountPaid, orArNumber: v.orArNumber, orArType: v.orArType, paymentDate: v.paymentDate, paymentMethod: v.paymentMethod }));
                this.paymentReceipts = (hRes.history ?? []).map((h: any) => ({ orArNumber: h.orArNumber, orArType: h.orArType, amount: h.amount, paymentDate: h.paymentDate, period: h.examPeriod, method: h.paymentMethod, semester: h.semester }));
                if (this.enrollmentSummary) this.enrollmentSummary = { ...this.enrollmentSummary, semester: sem };
              }
              this.cdr.detectChanges();
            },
            error: () => this.cdr.detectChanges()
          });
          return;
        }
        this.cdr.detectChanges();
      },
      error: () => { this.isSoaHistoryLoading = false; this.cdr.detectChanges(); }
    });
  }

  processPayment(): void {
    if (!this.studentDbId) { this.addNotification('error', 'Student ID missing.'); return; }
    // FIX FE-PLAN-03: Block payment if student hasn't chosen a plan yet (re-enroll flow)
    if (this.needsPlanSelection) { this.addNotification('warning', 'Please select a payment plan first.'); return; }

    // ── FIX FE-CASH-01: Branch on payment method ──────────────────────────
    if (this.paymentMethod === 'Cash') {
      this._processCashPayment();
      return;
    }

    // GCash path (original logic)
    if (!this.gcashReference.trim()) { this.addNotification('error', 'Enter your GCash Reference Number.'); return; }
    this.isProcessingPayment = true;
    const txnId = 'TXN-' + Date.now() + '-' + Math.random().toString(36).substring(2,7).toUpperCase();
    this.http.post<any>(`${this.accountingApi}?action=submit_gcash`, {
      student_id: this.studentDbId, gcash_reference: this.gcashReference.trim(),
      gcash_amount: this.gcashAmount, gcash_date: this.gcashDate,
      transaction_id: txnId, semester: this.currentSemester,
      // ── Scholarship declaration ────────────────────────────────────────
      is_scholar:        this.isScholar ? 1 : 0,
      scholar_type:      this.scholarType,
      scholar_grantor:   this.scholarGrantor,
      scholarship_amount: this.scholarFullTuition ? 0 : this.scholarshipAmount,
      scholar_full_tuition: this.scholarFullTuition ? 1 : 0,
    }).subscribe({
      next: (res) => {
        this.isProcessingPayment = false;
        if (res.success) {
          this.gcashSubmitted = true; this.paymentInfo.reference = txnId;
          this.route('approval'); this.isApprovalPending = true;
          this.addNotification('success', '✅ Payment submitted! Awaiting Accounting verification.');
          this.startApprovalPolling();
        } else if (res.locked) {
          // Period not yet unlocked by Accounting — show a clear notice
          this.addNotification('warning',
            '🔒 ' + (res.message || 'This payment period is not yet open. Please wait for a notice from Accounting.'));
        } else {
          this.addNotification('error', res.message || 'Submission failed.');
        }
        this.cdr.detectChanges();
      },
      error: () => { this.isProcessingPayment = false; this.addNotification('error', 'Cannot connect.'); this.cdr.detectChanges(); }
    });
  }

  /** FIX FE-CASH-01: Cash payment path — notifies backend then routes to cash-pending. */
  private _processCashPayment(): void {
    this.isProcessingPayment = true;
    this.http.post<any>(`${this.accountingApi}?action=notify_cash_pending`, {
      student_id: this.studentDbId,
      semester:   this.currentSemester,
    }).subscribe({
      next: (res) => {
        this.isProcessingPayment = false;
        if (res.success) {
          this.route('cash-pending');
          this.isApprovalPending = true;
          this.addNotification('success', '💵 Please proceed to the Accounting Office to pay.');
          this.startApprovalPolling();
        } else {
          this.addNotification('error', res.message || 'Could not register cash payment.');
        }
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
    this.http.get<any>(`${this.apiUrl}?action=get_payment_status&user_id=${this.userId}`).subscribe({
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
        } else if (res.success && this.workflowStep === 'pending-approval') {
          // Student is on the pending-approval screen — check if payment status changed
          // so we can redirect them to payment screen when Accounting sets them up
          if (res.paymentStatus === 'Paid') {
            // Fully approved — reload context to get proper routing
            this.loadContext();
          }
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
    if (this.torPollInterval) { clearInterval(this.torPollInterval); this.torPollInterval = null; }
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
      guardianEmail: this.editForm.guardianEmail, guardianRelationship: this.editForm.guardianRelationship,
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

    // BUG-SOA-DUES-01 FIX: When viewing a past semester, PHP returns the stored
    // per-term scheduled dues in semFees (dpDue/prelimDue/midtermDue/finalsDue).
    // Use those directly so past-semester installment schedules show correct figures
    // instead of recalculating which gives wrong results when DP ≠ exactly total/4.
    if (this.storedInstDues) {
      return this.storedInstDues;
    }

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
    const balance   = this.fees?.balance ?? this.feeBreakdown?.balance ?? 0;
    const totalPaid = this.fees?.totalPaid ?? 0;
    const fullyPaid = balance <= 0 && totalPaid > 0;
    // Use soaPaymentPlan — stable for the semester currently on screen,
    // immune to loadDashboard race-overwriting paymentPlan mid-render.
    const plan = this.soaPaymentPlan;

    // FULL-payment plan: any payment = entire semester covered.
    if (plan === 'full') {
      if (this.termBreakdown.length > 0 || fullyPaid) return 'FULL';
    }

    // INSTALLMENT plan:
    // Rule 1 — fully paid (balance = 0) = all exams covered.
    if (plan === 'installment' && fullyPaid) return 'FINALS';

    // Rule 2 — walk from highest posted installment period down.
    // 'Full' entries = GCash not yet posted to installment_payments = Downpayment only.
    const order = ['Finals', 'Midterm', 'Prelim', 'Downpayment'];
    for (const period of order) {
      if (this.termBreakdown.some(t => t.period === period)) return period.toUpperCase();
    }
    if (this.termBreakdown.some(t => t.period === 'Full')) return 'DOWNPAYMENT';

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

  // True when accounting issued a single Full OR that clears the entire balance
  // (installment student who paid all at once — exam_period = 'Full')
  get fullPaymentEntry(): { amountPaid: number; orNo: string; paymentDate: string } | null {
    // FIX EXAM-COV-03: A 'Full' period entry on an INSTALLMENT student is a GCash
    // payment that was logged before being posted to installment_payments. It is NOT
    // a true "Full Payment OR" — returning it here would suppress the installment
    // schedule rows and show a single "Full Payment" row instead. Only resolve for
    // full-plan students so the installment SOA table always renders correctly.
    if (this.soaPaymentPlan === 'installment') return null;
    const e = this.termBreakdown.find(tb => tb.period === 'Full');
    if (!e) return null;
    return { amountPaid: e.amountPaid, orNo: `${e.orArType}: ${e.orArNumber}`, paymentDate: e.paymentDate };
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
    // FIX SOA-02: Use the currently displayed semester (soaDisplaySemester) so that
    // printing a past-term SOA shows that term's semester label, not the current one.
    const soaSem = this.soaDisplaySemester || s.semester || this.currentSemester;
    const name = `${(s.lastName || s.last_name || '').toUpperCase()}, ${(s.firstName || s.first_name || '').toUpperCase()} ${(s.middleName || s.middle_name || '').toUpperCase()}`.trim();
    const fmt  = (n: number) => (+n || 0).toLocaleString('en-PH', { minimumFractionDigits: 2 });
    const isInstallment = this.soaPaymentPlan === 'installment';
    const td   = this.termBreakdown;
    // FIX SOA-SCHED-01: Use installmentSchedule getter (pure per-term data) and
    // fullPaymentEntry for the Full OR case — avoids blank rows in the printed SOA.
    const fullPaid = this.fullPaymentEntry;
    const sched = this.installmentSchedule;
    const [dp, pr, mid, fin] = ['Downpayment','Prelim','Midterm','Finals'].map(
      term => { const t = sched.find(s => s.term === term); return t?.paid ? { amountPaid: t.amountPaid, orArNumber: t.orNo.split(': ')[1] ?? t.orNo, paymentDate: t.paymentDate } : null; }
    );
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

<div class="soa-title">STATEMENT OF ACCOUNT &nbsp; ${soaSem}</div>

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
    <div class="info-val">${soaSem}</div>
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
        ${isInstallment
          ? `${scheduleRow('Downpayment', 'Downpayment', dpAmt, dp)}
             <tr><td colspan="4" style="padding:1px;"></td></tr>
             ${scheduleRow('PRELIM', 'Prelim', prAmt, pr)}
             <tr><td colspan="4" style="padding:1px;"></td></tr>
             ${scheduleRow('MIDTERM', 'Midterm', midAmt, mid)}
             <tr><td colspan="4" style="padding:1px;"></td></tr>
             ${scheduleRow('FINAL', 'Finals', finAmt, fin)}`
          : (() => {
              // Full plan — show single Full Payment row
              const fp = this.termBreakdown.find(t => t.period === 'Full');
              const fpReceipt = fp ? { amountPaid: fp.amountPaid, orArNumber: fp.orArNumber, paymentDate: fp.paymentDate } : (this.paymentReceipts[0] ?? null);
              const paidDate  = fpReceipt?.paymentDate
                ? new Date(fpReceipt.paymentDate).toLocaleDateString('en-PH', { month:'2-digit', day:'2-digit', year:'2-digit' })
                : '';
              return `<tr>
                <td style="padding:3px 6px;">Full Payment</td>
                <td style="padding:3px 6px;">${paidDate}</td>
                <td style="padding:3px 6px;text-align:right;">${fpReceipt ? fmt(fpReceipt.amountPaid ?? f.totalPaid) : ''}</td>
                <td style="padding:3px 6px;text-align:center;">${fpReceipt?.orArNumber ?? ''}</td>
              </tr>`;
            })()
        }
      </tbody>
    </table>

    <div class="total-balance-box">
      <span class="total-balance-label">Total Balance &nbsp;&nbsp;</span>
      <span class="total-balance-amt">${fmt(f.balance ?? f.totalAssessment - f.totalPaid)}</span>
    </div>

    <!-- Installment breakdown — only for installment plan with remaining balance -->
    ${isInstallment && (f.balance ?? 0) > 0 ? `
    <table class="install-table">
      <thead>
        <tr>
          <th>INSTALLMENT PAYMENT</th>
          <th>DUE DATES</th>
          <th style="text-align:right;">AMOUNT</th>
        </tr>
      </thead>
      <tbody>
        <tr><td>Downpayment :</td><td>${dueDates['Downpayment'] || ''}</td><td style="text-align:right;">${fmt(dpAmt)}</td></tr>
        <tr><td style="color:#c00;font-weight:700;">Prelim :</td><td style="color:#c00;font-weight:700;">${dueDates['Prelim']}</td><td style="text-align:right;">${fmt(prAmt)}</td></tr>
        <tr><td>Midterm:</td><td>${dueDates['Midterm']}</td><td style="text-align:right;">${fmt(midAmt)}</td></tr>
        <tr><td>Final:</td><td>${dueDates['Finals']}</td><td style="text-align:right;">${fmt(finAmt)}</td></tr>
      </tbody>
    </table>
    <div style="text-align:right;font-size:11px;font-weight:700;margin-top:6px;padding-right:4px;">
      Total amount to be paid: &nbsp; <span style="background:#add8e6;padding:2px 8px;">${fmt(totalAmountToBePaid)}</span>
    </div>` : ''}
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

  // True when the student is browsing a past semester's SOA (not the current one)
  get isViewingPastSemester(): boolean {
    return this.soaSemesters.length > 0 && this.soaDisplaySemester !== '' &&
           this.soaDisplaySemester !== this.soaSemesters[0];
  }

  // Classify subject as Minor/GE based on course code prefix
  // GE, PE, NSTP, OJT prefixes = Minor/General Education
  isMinor(code: string): boolean {
    if (!code) return false;
    const upper = code.toUpperCase();
    return upper.startsWith('GE') ||
           upper.startsWith('PE') ||
           upper.startsWith('NSTP') ||
           upper.startsWith('OJT');
  }

}