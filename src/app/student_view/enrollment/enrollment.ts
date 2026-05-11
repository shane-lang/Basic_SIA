import { Component, OnInit, OnDestroy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { Router, ActivatedRoute } from '@angular/router';
import { environment } from '../../environment';
import { PasswordGateService } from '../password-gate/password-gate.service';
import { AuthService } from '../../services/auth';

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
  private receiptApi    = environment.receiptApi;
  soaVerified = false;   // true once student passes the password gate on this page
  private soaLockTimer: any = null;
  private readonly SOA_TIMEOUT_MS = 5 * 60 * 1000;  // 5 minutes inactivity

  /** Called when student clicks a locked SOA/Invoice card — re-opens the gate modal. */
  async unlockSoa(): Promise<void> {
    const ok = await this.gate.requirePassword('SOA & Receipts');
    this.soaVerified = ok;
    if (ok) this.startSoaLockTimer();
    this.cdr.detectChanges();
  }

  private startSoaLockTimer(): void {
    this.clearSoaLockTimer();
    this.soaLockTimer = setTimeout(() => {
      this.soaVerified = false;
      sessionStorage.removeItem('pgv_ts_soa___receipts');
      this.cdr.detectChanges();
    }, this.SOA_TIMEOUT_MS);
  }

  private clearSoaLockTimer(): void {
    if (this.soaLockTimer) {
      clearTimeout(this.soaLockTimer);
      this.soaLockTimer = null;
    }
  }

  /** Reset the inactivity timer on any user interaction within the SOA section. */
  resetSoaTimer(): void {
    if (this.soaVerified) this.startSoaLockTimer();
  }

  constructor(private http: HttpClient, public router: Router, private cdr: ChangeDetectorRef, private activatedRoute: ActivatedRoute, public gate: PasswordGateService, private auth: AuthService) {}

  workflowStep: 'payment' | 'cash-pending' | 'approval' | 'dashboard' | 'tor-pending' | 're-enroll' | 'graduated' | 'pending-approval' | 'subject-selection' | 'subject-waiting' = 'payment';

  // ── Subject Selection (post-login resubmission after registrar rejection) ─
  subjectSelectionStatus: 'Pending' | 'Submitted' | 'Approved' | 'Rejected' = 'Pending';
  wasRejectedSubjectSelection = false;
  subjectSelectionRejectionNote: string | null = null;
  subjectSelectionCourses: { id: number; code: string; name: string; credits: number; yearLevel: string; semester: string; selected: boolean }[] = [];
  isSubjectSelectionLoading = false;
  subjectSelectionError = '';
  isSubjectSubmitting = false;
  private subjectReselectionPollInterval: any = null;

  get selectedSubjects() { return this.subjectSelectionCourses.filter(s => s.selected); }
  get selectedSubjectUnits() { return this.selectedSubjects.reduce((t, s) => t + s.credits, 0); }

  toggleSubject(c: { selected: boolean }): void { c.selected = !c.selected; this.cdr.detectChanges(); }
  selectAllSubjects(): void { this.subjectSelectionCourses.forEach(c => c.selected = true); this.cdr.detectChanges(); }

  loadSubjectSelectionCourses(): void {
    if (!this.student?.program) return;
    this.isSubjectSelectionLoading = true;
    this.subjectSelectionError = '';
    this.subjectSelectionCourses = [];
    this.cdr.detectChanges();
    const program = this.student.program;
    this.http.get<any>(`${this.registrarApi}?action=get_program_courses&program=${encodeURIComponent(program)}`).subscribe({
      next: (res) => {
        this.isSubjectSelectionLoading = false;
        if (res.success && res.courses) {
          const yearLevel = (this.student.yearLevel || '1st Year').trim();
          const semTerm   = (this.student.semester || '').split(',')[0].trim();
          const all = res.courses.map((c: any) => ({
            id:        +(c.courseId ?? c.id ?? 0),
            code:      c.code,
            name:      c.name,
            credits:   +(c.credits ?? 0),
            yearLevel: (c.yearLevel ?? c.year_level ?? '').trim(),
            semester:  (c.semester  ?? '').trim(),
            selected:  false,
          }));
          const filtered = all.filter((c: any) => {
            const ylMatch  = !c.yearLevel || c.yearLevel === yearLevel;
            const semMatch = !c.semester  || c.semester  === semTerm || c.semester === this.student.semester;
            return ylMatch && semMatch;
          });
          this.subjectSelectionCourses = filtered.length > 0 ? filtered : all;
          // Pre-check previously rejected selection (wasRejected) courses
          if (this.wasRejectedSubjectSelection) {
            this.loadAndPreFillRejectedSelection();
          } else {
            // Fresh: select all by default
            this.subjectSelectionCourses.forEach(c => c.selected = true);
          }
        } else {
          this.subjectSelectionError = 'Could not load subjects for your program.';
        }
        this.cdr.detectChanges();
      },
      error: () => {
        this.isSubjectSelectionLoading = false;
        this.subjectSelectionError = 'Could not connect to server.';
        this.cdr.detectChanges();
      }
    });
  }

  private loadAndPreFillRejectedSelection(): void {
    // Pre-fill the courses the student previously submitted (before rejection)
    this.http.get<any>(`${this.apiUrl}?action=get_subject_selection&student_id=${this.studentDbId}`).subscribe({
      next: (res) => {
        const prevCourses: any[] = res.selection?.requested_courses ?? [];
        const prevIds = new Set(prevCourses.map((c: any) => c.id));
        const prevCodes = new Set(prevCourses.map((c: any) => c.code));
        this.subjectSelectionCourses.forEach(c => {
          c.selected = prevIds.has(c.id) || prevCodes.has(c.code);
        });
        // If no match at all, select all as fallback
        if (!this.subjectSelectionCourses.some(c => c.selected)) {
          this.subjectSelectionCourses.forEach(c => c.selected = true);
        }
        this.cdr.detectChanges();
      },
      error: () => {
        // On error, default to select all
        this.subjectSelectionCourses.forEach(c => c.selected = true);
        this.cdr.detectChanges();
      }
    });
  }

  submitReselectedSubjects(): void {
    const selected = this.selectedSubjects;
    if (selected.length === 0) {
      this.addNotification('error', 'Please select at least one subject before submitting.');
      return;
    }
    // FIX BUG-SUBJSEL-CODES-01: Backend only accepts course_ids (integers).
    // course_codes fallback was silently rejected by the backend. Now we surface
    // a clear error if IDs are missing so the student knows to refresh.
    const courseIds = selected.map(s => s.id).filter(id => id > 0);
    if (courseIds.length === 0) {
      this.addNotification('error', 'Could not resolve subject IDs. Please refresh the page and try again.');
      return;
    }
    this.isSubjectSubmitting = true;
    this.subjectSelectionError = '';
    this.cdr.detectChanges();
    const payload: any = { student_id: this.studentDbId, course_ids: courseIds, notes: '' };
    this.http.post<any>(`${this.apiUrl}?action=submit_subject_selection`, payload).subscribe({
      next: (res) => {
        this.isSubjectSubmitting = false;
        if (res.success) {
          this.subjectSelectionStatus         = 'Submitted';
          this.wasRejectedSubjectSelection     = false;
          this.subjectSelectionRejectionNote   = null;
          this.route('subject-waiting');
          this.addNotification('success', '✅ Subject selection submitted! Waiting for Registrar approval.');
          this.startSubjectReselectionPoll();
        } else {
          this.subjectSelectionError = res.message || 'Could not submit subject selection.';
          this.addNotification('error', this.subjectSelectionError);
        }
        this.cdr.detectChanges();
      },
      error: () => {
        this.isSubjectSubmitting = false;
        this.subjectSelectionError = 'Could not connect to server.';
        this.addNotification('error', 'Cannot connect to server. Please try again.');
        this.cdr.detectChanges();
      }
    });
  }

  private startSubjectReselectionPoll(): void {
    this._stopSubjectReselectionPoll();
    const check = () => {
      if (!this.studentDbId) return;
      this.http.get<any>(`${this.apiUrl}?action=get_subject_selection&student_id=${this.studentDbId}`).subscribe({
        next: (res) => {
          const status = res.status ?? '';
          if (status === 'Approved') {
            this._stopSubjectReselectionPoll();
            this.addNotification('success', '🎉 Your subject selection was approved! Redirecting to payment...');
            setTimeout(() => this.loadContext(), 1500);
          } else if (res.wasRejected) {
            this._stopSubjectReselectionPoll();
            this.wasRejectedSubjectSelection   = true;
            this.subjectSelectionRejectionNote = res.rejectionNote ?? null;
            this.route('subject-selection');
            this.addNotification('error', '❌ Your subject selection was rejected again. Please review and resubmit.');
            this.cdr.detectChanges();
          // FIX SUBJSEL-POLL-REJECT-01: Detect full registration rejection.
          // When the Registrar rejects the entire registration (not just the subject
          // selection), enrollment_status='Rejected' is set but subject_selections
          // is not updated — so wasRejected is always false on the poll. The student
          // was stuck on "Waiting for Registrar Approval" forever with no feedback.
          // Now we detect registrationRejected=true and immediately re-route to
          // subject-selection so the student can reselect and resubmit.
          } else if (res.registrationRejected) {
            this._stopSubjectReselectionPoll();
            this.wasRejectedSubjectSelection   = true;
            this.subjectSelectionRejectionNote = res.registrationRejectedReason ?? null;
            this.route('subject-selection');
            this.loadSubjectSelectionCourses();
            this.addNotification('error', '❌ Your registration was rejected by the Registrar. Please review your subject selection and resubmit.');
            this.cdr.detectChanges();
          }
        },
        error: () => {}
      });
    };
    check();
    this.subjectReselectionPollInterval = setInterval(check, 8000);
  }

  private _stopSubjectReselectionPoll(): void {
    if (this.subjectReselectionPollInterval) {
      clearInterval(this.subjectReselectionPollInterval);
      this.subjectReselectionPollInterval = null;
    }
  }
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
  paymentMethod: 'GCash' | 'Cash' = 'Cash'; // FIX FE-PM-NULL-01: default Cash — safer than GCash when method is unknown
  gcashReference = ''; gcashAmount = 0; gcashDate = new Date().toISOString().split('T')[0]; gcashSubmitted = false;

  isApprovalPending = false; approvalMessage = '';
  // FIX REJECT-NOTES-FE-01: Stores the accounting rejection reason returned by
  // get_payment_status so the enrollment payment step can display it as a banner.
  // Cleared when the student successfully resubmits a new payment.
  rejectedNote: string | null = null;
  // FIX APPROVAL-LOOP-01: Guards re-entrant approval handling.
  // Once checkApprovalStatus() fires the "Payment approved!" toast and calls
  // loadContext(), this flag is set so that any concurrent in-flight poll
  // responses (HTTP is async — clearInterval does NOT cancel already-sent requests)
  // are ignored. Reset to false only at the start of a fresh payment cycle.
  private approvalProcessed = false;
  private pollInterval: any = null;
  private torPollInterval: any = null;

  currentSemester = '';
  studentCategory = '';   // 'SHS' | 'TVET' | '' (College)
  get isSHS():        boolean { return this.studentCategory === 'SHS'; }
  get isTVET():       boolean { return this.studentCategory === 'TVET'; }
  get isCollege():    boolean { return !this.isSHS && !this.isTVET; }
  // FIX TRANSFEREE-CASE-FE-01: Use case-insensitive comparison. Backend may store
  // 'transferee', 'Transferee', or 'TRANSFEREE'. Strict === 'Transferee' caused
  // lowercase-stored transferees to be treated as non-transferees, routing them
  // directly to dashboard + auto_enroll_all without waiting for Registrar.
  get isTransferee(): boolean { return (this.student?.studentType ?? '').toLowerCase() === 'transferee'; }
  // Free only for SHS New & Old students (K-12 Gov voucher)
  // TVET now pays like College — FIX TVET-COLLEGE-FLOW-01
  // BUG-TVET-FLOW-02 FIX: TVET non-transferees are FREE (TESDA/PESFA/STEP gov scholarship),
  // same as SHS non-transferees (K-12 DepEd). The old comment "TVET now pays like College"
  // was leftover from a reverted experiment. The backend auto-approves TVET non-transferees
  // with approval_status='Approved' and ₱0 fees — this getter must match so payment-gated
  // UI elements (payment form, balance display, etc.) hide correctly for free TVET students.
  get isFreeStudent(): boolean { return (this.isSHS || this.isTVET) && !this.isTransferee; }
  // BUG-SOA-DEPT-OVERFLOW-01 FIX: Department values stored in the programs table
  // use the full name format: "Business Management Department (BMD)".
  // The SOA header's 1fr grid cell is too narrow for the full string — it overflows
  // silently and appears blank. Extract the parenthetical abbreviation when present
  // (e.g. "BMD"), otherwise return the full string (TVET/SHS overrides are already
  // short: "Technical-Vocational Education..." falls back to the full string with CSS
  // word-wrap as the safety net).
  get departmentShort(): string {
    const dept = this.student?.department ?? '';
    const match = dept.match(/\(([^)]+)\)\s*$/);
    return match ? match[1] : dept;
  }
  enrolledCourses: StudentCourse[] = [];
  isAutoEnrolling = false;
  ngOnDestroy(): void {
    this.clearSoaLockTimer();  // stop JS timer only; lock state preserved across tabs
    if (this.pollInterval)    clearInterval(this.pollInterval);
    if (this.torPollInterval) clearInterval(this.torPollInterval);
    this._stopSubjectReselectionPoll();
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
    // ── SOA Password gate: restore verified state from cache if still valid ───
    // soaVerified starts false (card shows locked). But if the student verified
    // within the last 5 minutes on this page, restore it so they don't have to
    // click and re-enter their password again just because they navigated away.
    const pgvTs = Number(sessionStorage.getItem('pgv_ts_soa___receipts') ?? 0);
    if (pgvTs > 0 && (Date.now() - pgvTs) < 5 * 60 * 1000) {
      this.soaVerified = true;
      this.startSoaLockTimer();
    }

    // FIX AUTH-REHYDRATE-01: AuthService.getCurrentUser() has a localStorage
    // fallback that works immediately on page refresh, unlike raw sessionStorage
    // which is empty until the first HTTP call triggers getToken() rehydration.
    // Old code: sessionStorage.getItem('currentUser') -> null on refresh -> /login.
    const storedUser = this.auth.getCurrentUser();
    if (!storedUser) { this.router.navigate(['/login']); return; }
    this.userId = (storedUser as any).id;

    // ── Restore last known step IMMEDIATELY to prevent flash back to 'payment' ──
    // This is purely a visual restore — loadContext() will correct it from DB truth.
    // Restore graduation state if the student already graduated in a previous session
    if (sessionStorage.getItem('enrollmentStep') === 'graduated') {
      this.isGraduated = true;
    }
    const savedStep = sessionStorage.getItem('enrollmentStep') as typeof this.workflowStep | null;

    // FIX TRANSFEREE-ROUTE-02: Never restore 'dashboard' from sessionStorage as the
    // initial step. 'dashboard' is only valid AFTER loadContext() confirms the student
    // is Approved+Enrolled+Paid. If a transferee's TOR was just evaluated, their
    // sessionStorage may still hold 'dashboard' from a previous session/routing path,
    // causing the payment step to be skipped entirely on every subsequent page load.
    // loadContext() is authoritative — it always corrects the step from DB truth.
    const safeToRestore = savedStep && savedStep !== 'dashboard';
    if (safeToRestore) {
      this.workflowStep = savedStep;
    }

    // ── Restore approval polling if student was mid-wait when they reloaded ──
    // Without this, approved students who reload during polling never get redirected.
    // FIX APPROVAL-LOOP-01: Only restart polling when savedStep is 'approval' or
    // 'cash-pending'. If enrollmentStep was cleared by checkApprovalStatus() (the fix),
    // savedStep will be null and this block is skipped — preventing the loop where
    // an already-approved student restarts polling on every page load.
    if (savedStep === 'approval' || savedStep === 'cash-pending') {
      this.isApprovalPending = true;
      this.startApprovalPolling();
    }

    // FIX SUBJSEL-POLL-RELOAD-01: Restart subject reselection poll on page reload.
    // Without this, if the registrar rejects while the tab is closed/refreshed,
    // the student is stuck on subject-waiting forever with no rejection feedback.
    // loadContext() will also call startSubjectReselectionPoll() if still Submitted,
    // so no duplicate intervals (startSubjectReselectionPoll stops the previous one).
    if (savedStep === 'subject-waiting') {
      this.startSubjectReselectionPoll();
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
    // FIX RACE-NUCLEAR: Read _pm/_pp query params injected by finishTorReview() navigation.
    // These are the highest-priority source — set at the moment the user clicked the button,
    // survive Angular routing, and are independent of DB write timing or sessionStorage order.
    const _qp        = this.activatedRoute.snapshot.queryParams;
    const _qpMethod  = _qp['_pm'];   // 'Cash' | 'GCash' | undefined
    const _qpPlan    = _qp['_pp'];   // 'installment' | 'full' | undefined
    // Also read sessionStorage as secondary fallback (set by finishTorReview before navigate)
    const _hintPlan   = _qpPlan   || sessionStorage.getItem('pendingPaymentPlan')   || '';
    const _hintMethod = _qpMethod || sessionStorage.getItem('pendingPaymentMethod') || '';
    const _hintQs = _hintPlan ? `&hint_payment_plan=${encodeURIComponent(_hintPlan)}` : '';
    this.http.get<any>(`${this.apiUrl}?action=get_student_context&user_id=${this.userId}${_hintQs}`).subscribe({
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
        // Persist studentType so student-layout can compute isFreeStudent
        // without having to re-fetch student data (layout reads sessionStorage only).
        sessionStorage.setItem('studentType', res.student.studentType ?? '');
        // FIX FE-PM-NULL-01: Resolve payment method using a strict priority chain.
        // Priority:
        //   1. Query param _pm (set by finishTorReview navigate — most reliable)
        //   2. sessionStorage pendingPaymentMethod (written by finishTorReview before navigate)
        //   3. DB value from res.student.paymentMethod — only if it is a known valid value
        //   4. 'Cash' as last-resort default — safer than 'GCash':
        //      Cash students who go to the cashier are never blocked by the system,
        //      but showing GCash UI to a Cash student causes a dead-end (no reference to enter).
        // NEVER default an unknown/empty method to 'GCash'.
        if (_hintMethod === 'Cash' || _hintMethod === 'GCash') {
          this.paymentMethod = _hintMethod;
          sessionStorage.removeItem('pendingPaymentMethod');
        } else if (res.student.paymentMethod === 'Cash' || res.student.paymentMethod === 'GCash') {
          this.paymentMethod = res.student.paymentMethod;
        } else {
          // Unknown/empty — default to Cash (safer: Cash students walk in, never blocked)
          this.paymentMethod = 'Cash';
        }

        // BUG-FE-SCHOLAR-02: Restore scholar declaration state from DB so that
        // page reload does not wipe out the pending scholarship notice.
        // students.is_scholar=1 means the student declared (or was granted) a scholarship.
        // scholarPending=true when is_active=0 (declared but not yet approved by accounting).
        if (res.student.isScholar) {
          this.isScholar         = true;
          this.scholarType       = res.student.scholarType       ?? this.scholarType;
          this.scholarGrantor    = res.student.scholarGrantor    ?? this.scholarGrantor;
          this.scholarshipAmount = res.student.scholarshipAmount ?? this.scholarshipAmount;
          // scholarPending = declared but no active approved scholarship yet
          this.scholarPending    = !res.student.scholarshipApproved;
          this.scholarApproved   = !!res.student.scholarshipApproved;
        }
        // FIX FE-PLAN-01: payment_plan is NULL after re-enroll — backend sends
        // needsPlanSelection:true so we show the plan selector instead of jumping to GCash.
        this.needsPlanSelection = res.needsPlanSelection === true;
        // FIX RACE-01 / FE-PLAN-REVERT-01: Resolve payment plan with a 4-level priority chain.
        // The old 2-level chain (hint → DB → default 'full') lost the plan whenever:
        //   • checkApprovalStatus() wiped sessionStorage before calling loadContext()
        //   • query params were already cleared from URL on re-entry
        //   • DB write hadn't committed yet (race window)
        // New priority: hint → DB → current component value → default 'full'
        // This means once the student picks installment, this.paymentPlan='installment'
        // acts as a memory barrier and is NEVER overwritten by a stale DB 'full' response.
        const _pendingPlan = _hintPlan;
        const _dbPlan = (res.paymentPlan ?? res.student?.paymentPlan);
        if (_pendingPlan === 'installment' || _pendingPlan === 'full') {
          // Level 1: sessionStorage / query param hint — most authoritative during race window
          this.paymentPlan = _pendingPlan;
          sessionStorage.removeItem('pendingPaymentPlan');
        } else if (_dbPlan === 'installment' || _dbPlan === 'full') {
          // Level 2: DB value — authoritative once the write has committed
          this.paymentPlan = _dbPlan;
        } else if (this.paymentPlan === 'installment') {
          // Level 3: keep existing component value — student already confirmed installment
          // this session; don't downgrade to 'full' just because DB returned null/empty
          // (DB write still in-flight or needsPlanSelection=true re-enroll path)
        } else {
          // Level 4: true unknown — show plan selector (needsPlanSelection handles this)
          this.paymentPlan = 'full';
        }
        // Suppress needsPlanSelection if we already know the plan from levels 1-3
        if (this.needsPlanSelection && this.paymentPlan === 'installment') {
          this.needsPlanSelection = false;
        }
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
        // Clean up _pm/_pp query params from URL — they've been read, no need to show them
        if (_qpMethod || _qpPlan) {
          this.router.navigate([], { queryParams: {}, replaceUrl: true });
        }

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

        // ── SUBJECT SELECTION ROUTING (FIX REJECT-RESELECT-01) ───────────────
        // Check BEFORE payment routing. If registrar rejected or student hasn't
        // submitted yet, route to subject-selection step first.
        // subjectSelectionStatus values from getStudentContext:
        //   'Pending'   → hasn't submitted yet OR was rejected (wasRejectedSubjectSelection distinguishes)
        //   'Submitted' → waiting for registrar review
        //   'Approved'  → proceed normally to payment routing below
        //
        // Free SHS/TVET non-transferees are always 'Approved' (backend forces this).
        // Transferees skip subject selection (TOR flow handles their subjects).
        // FIX BUG-SUBJSEL-LOGIN-01: Default to 'Pending' (not 'Approved') when the field
        // is missing from the response. Using 'Approved' as default caused students whose
        // selection was rejected to bypass the subject-selection step entirely on next login,
        // routing them straight to payment/dashboard without ever seeing the reselection form.
        //
        // FIX REG-REJECT-ROUTING-01: If enrollment_status='Rejected', the registrar rejected
        // the whole registration (not just the subject selection). Force subjectSelectionStatus
        // back to 'Pending' here so the subject-selection block below fires correctly — even if
        // the DB value wasn't reset yet (race condition on first login after rejection).
        const _selStatus    = (res.subjectSelectionStatus as string) ?? 'Pending';
        const _wasRejected  = res.wasRejectedSubjectSelection === true;
        const _rejNote      = res.subjectSelectionRejectionNote ?? null;
        const _isTransfereeCtx = (res.student?.studentType ?? '').toLowerCase() === 'transferee';
        const _catCtx = (this.studentCategory || (res.student?.studentCategory ?? '')).toUpperCase();
        const _isFreeCtx  = (_catCtx === 'SHS' || _catCtx === 'TVET') && !_isTransfereeCtx;

        // FIX REG-REJECT-ROUTING-01: Registrar rejected the registration.
        // Treat as Pending so the block below routes to subject-selection.
        // The backend also resets subject_selection_status='Pending' in rejectRegistration(),
        // but we guard here too in case the student reloads before the DB write propagates.
        const _enrollRejected = (res.student?.enrollmentStatus === 'Rejected');
        const _effectiveSelStatus = (_enrollRejected && !_isFreeCtx && !_isTransfereeCtx)
          ? 'Pending'
          : _selStatus;

        if (!_isFreeCtx && !_isTransfereeCtx && _effectiveSelStatus !== 'Approved') {
          this.wasRejectedSubjectSelection   = _wasRejected || _enrollRejected;
          this.subjectSelectionRejectionNote = _rejNote;
          this.subjectSelectionStatus        = _effectiveSelStatus as any;
          // FIX REG-REJECT-ROUTING-02: Use _effectiveSelStatus (not _selStatus) so that
          // when enrollment_status='Rejected', the effective status is 'Pending' and we
          // route to subject-selection — not subject-waiting.  Without this fix, a student
          // whose full registration was rejected but whose subject_selection_status is still
          // 'Submitted' in the DB would be stuck on the waiting screen with no way to
          // reselect subjects, because _effectiveSelStatus was computed as 'Pending' but the
          // Submitted branch checked the raw _selStatus instead.
          if (_effectiveSelStatus === 'Submitted') {
            // Already submitted — show waiting screen and poll
            this.route('subject-waiting');
            this.startSubjectReselectionPoll();
          } else {
            // Pending or rejected — show the subject selection form
            this.route('subject-selection');
            this.loadSubjectSelectionCourses();
          }
          this.cdr.detectChanges();
          return;
        }

        // FIX FE-PLAN-02: After re-enroll, needsPlanSelection=true means student
        // must pick full/installment before paying. Route to payment so plan selector shows.
        // FIX PLAN-STUCK-01: Do NOT show plan selector if student is already Approved+Paid.
        // When payment_plan is NULL in DB (reEnroll() reset it but updatePaymentPlan() not yet
        // called), backend returns needsPlanSelection=true even for students who already paid.
        // This caused the routing logic below (approved → dashboard) to never be reached.
        const _alreadyApproved = (res.student?.approvalStatus === 'Approved');
        const _alreadyPaid     = (res.student?.paymentStatus  === 'Paid');
        if (this.needsPlanSelection && !_alreadyApproved && !_alreadyPaid) {
          sessionStorage.removeItem('enrollmentStep');
          this.route('payment');
          this.cdr.detectChanges();
          return;
        }
        if (this.needsPlanSelection && (_alreadyApproved || _alreadyPaid)) {
          // Suppress — student is already done; proceed to routing logic below.
          this.needsPlanSelection = false;
        }

        // ── ROUTING ─────────────────────────────────────────────
        const s        = res.student;
        // FIX CTX-CATEGORY-FE-01: Use students.student_category directly from the DB row
        // for routing decisions. res.student.studentCategory was previously set from
        // programs.level_type (via ctxLevelType in PHP), which could mislabel a College
        // student as 'TVET' if their program had the wrong level_type — causing isFree=true
        // and the TVET branch to fire, routing them to the payment step forever after approval.
        // this.studentCategory (set at line ~293 from the same field) is the source of truth.
        // Fall back to s.studentCategory for safety if this.studentCategory is blank.
        const cat      = (this.studentCategory || (s.studentCategory ?? '')).toUpperCase();
        // isTransferee declared first — isFree references it below.
        // SHS and TVET non-transferees are free (gov subsidy — K-12 / TESDA/PESFA/STEP).
        // Transferees must pay flat rate — follow normal payment flow regardless of category.
        // FIX TRANSFEREE-CASE-FE-01: Use case-insensitive comparison — see isTransferee getter above.
        const isTransferee = (s.studentType ?? '').toLowerCase() === 'transferee';
        // BUG-TVET-FLOW-02 FIX: TVET non-transferees are auto-approved as FREE by the
        // backend (TESDA/PESFA/STEP gov scholarship). Including only SHS here caused TVET
        // non-transferees to fall through to the generic `else if (approved)` branch below,
        // bypassing the isFree-specific session cleanup and routing. Both branches happen
        // to do the same cleanup today, but keeping isFree accurate prevents future drift
        // and makes the routing intent explicit. Must match backend: (SHS || TVET) && !transferee.
        const isFree      = (cat === 'SHS' || cat === 'TVET') && !isTransferee;
        const approved    = s.approvalStatus === 'Approved';
        // FIX TOR-NULL-PENDING-01: null torEvalStatus for transferees = no TOR record yet = treat as Pending
        const torPending  = s.torEvalStatus === 'Pending' || (isTransferee && !s.torEvalStatus);
        const torDone     = s.torEvalStatus  === 'Evaluated';
        const torRejected = s.torEvalStatus  === 'Rejected';
        const paid        = s.paymentStatus  === 'Paid';
        // FIX TRANSFEREE-PARTIAL-01: Installment transferees who paid their Downpayment
        // have paymentStatus='Partial' (not 'Paid') and approvalStatus='Approved'.
        // The old `!paid` guard treated Partial as unpaid → re-routed to cash-pending forever.
        // A transferee should be considered "paid enough to proceed" when:
        //   • paymentStatus is 'Paid' (full payment), OR
        //   • paymentStatus is 'Partial' AND approvalStatus is 'Approved' (installment DP done)
        const paidOrApprovedPartial = paid || (s.paymentStatus === 'Partial' && approved);
        const isCash      = this.paymentMethod === 'Cash';

        // FIX TRANSFEREE-ROUTE-01: Check transferee TOR state BEFORE the generic
        // `approved` guard. When the Registrar evaluates a TOR, approval_status may
        // already be 'Approved' (set by an earlier code path), which caused the
        // student to land directly on the dashboard instead of the payment step.
        // Transferees with a freshly-evaluated TOR who have NOT yet paid must always
        // be routed to payment — regardless of approval_status.
        if (isTransferee && torDone && !paidOrApprovedPartial) {
          if (!sessionStorage.getItem('torHardCopyDismissed_' + this.studentDbId)) {
            this.showTorHardCopyNotice = true;
          }
          // Transferee TOR evaluated — route to cash-pending (installment+Cash set by backend)
          this.route(isCash ? 'cash-pending' : 'payment');
          if (isCash) { this.isApprovalPending = true; this.startApprovalPolling(); }

        } else if (isTransferee && paidOrApprovedPartial && s.enrollmentStatus === 'Enrolled') {
          // FIX TRANSFEREE-REGISTRAR-WAIT-01 (paid + Registrar confirmed):
          // Registrar has set enrollment_status='Enrolled' — subjects are auto-enrolled.
          // Go straight to dashboard and load enrolled subjects.
          sessionStorage.removeItem('pendingPaymentMethod');
          sessionStorage.removeItem('pendingPaymentPlan');
          this.isApprovalPending = false;
          this.route('dashboard');
          this.ensureEnrolledThenLoad();

        } else if (isTransferee && paidOrApprovedPartial) {
          // FIX TRANSFEREE-REGISTRAR-WAIT-01 (paid + awaiting Registrar):
          // Accounting verified payment (approvalStatus='Approved', paymentStatus='Partial'/'Paid')
          // BUT enrollment_status is still 'Confirmed' — Registrar has NOT yet confirmed.
          // Transferees MUST wait for Registrar approval before subjects are enrolled.
          // Previously this fell through to `else if (approved)` → route('dashboard') +
          // ensureEnrolledThenLoad(), which showed the dashboard prematurely and called
          // auto_enroll_all (blocked server-side, but UI already showed whatever
          // enrollment rows existed from earlier bugs or manual entries).
          this.isApprovalPending = true;
          this.route('approval');
          this.startApprovalPolling();

        } else if (isTransferee && torPending) {
          this.route('tor-pending');
          this.startTorPolling();

        } else if (isTransferee && torRejected) {
          this.route('payment');

        } else if (isFree && !isTransferee && approved) {
          // FIX TVET-WIZARD-FE-01: SHS non-transferees go straight to dashboard (no payment
          // step needed — ₱0, no subject enlistment wizard). TVET non-transferees follow the
          // same wizard as College: show the payment step (which displays ₱0 / Free label)
          // so the student sees payment instructions, then subjects load via ensureEnrolledThenLoad.
          // The distinction: SHS has no add/drop and no curriculum enlistment; TVET does.
          sessionStorage.removeItem('pendingPaymentMethod');
          sessionStorage.removeItem('pendingPaymentPlan');
          this.isApprovalPending = false;
          if (cat === 'SHS') {
            // SHS: skip straight to dashboard — no subject enlistment wizard
            this.route('dashboard');
            this.ensureEnrolledThenLoad();
          } else {
            // TVET: show payment step so student sees ₱0 fee info, then subjects load
            // Only skip to dashboard if subjects are already enrolled (wizard already done)
            const hasEnrollments = (res.courses?.length ?? 0) > 0 ||
                                   (res.enrollments?.length ?? 0) > 0;
            if (hasEnrollments) {
              this.route('dashboard');
              this.ensureEnrolledThenLoad();
            } else {
              // First time through wizard — show payment instructions (₱0) then subjects
              this.route('payment');
            }
          }

        } else if (approved) {
          // Non-transferee, non-SHS/TVET College student who is Approved by Accounting.
          // POLICY: Must still wait for Registrar confirmation (enrollmentStatus='Enrolled')
          // before subjects are enrolled and the dashboard is shown.
          // If enrollmentStatus is still 'Confirmed' (Accounting approved, Registrar pending),
          // stay on the waiting/approval screen and poll for Registrar confirmation.
          sessionStorage.removeItem('pendingPaymentMethod');
          sessionStorage.removeItem('pendingPaymentPlan');
          if (s.enrollmentStatus === 'Enrolled') {
            // Registrar already confirmed — go to dashboard
            this.isApprovalPending = false;
            this.route('dashboard');
            this.ensureEnrolledThenLoad();
          } else {
            // Accounting approved but Registrar has not yet confirmed
            this.isApprovalPending = true;
            this.route('approval');
            this.startApprovalPolling();
          }

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
          // FIX REJECT-NOTES-FE-01: Also restore the rejection note from context so
          // that the banner shows immediately on page reload after a payment rejection,
          // without having to wait for the next poll cycle.
          if ((res.student as any)?.rejectionReason) {
            this.rejectedNote = (res.student as any).rejectionReason;
          }
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
    // FIX FE-PLAN-REVERT-01 (Bug 2): Write plan to sessionStorage immediately so
    // any subsequent loadContext() call — including those triggered by approval
    // polling — will always find the hint even after query params are gone.
    // This is the same mechanism finishTorReview() uses (pendingPaymentPlan).
    sessionStorage.setItem('pendingPaymentPlan',   plan);
    sessionStorage.setItem('pendingPaymentMethod', method);
    this.http.post<any>(`${this.apiUrl}?action=update_payment_plan`, {
      student_id: this.studentDbId,
      payment_plan: plan,
      payment_method: method,
    }).subscribe({
      next: (res) => {
        if (res.success) {
          this.needsPlanSelection = false;
          // FIX FE-PLAN-REFRESH-01: After saving the plan, the backend has recomputed
          // tuition_fees and soa_snapshots with the correct installment_fee and
          // total_assessment. We MUST reload context to pick up the updated fees —
          // otherwise this.fees still holds the pre-plan values (e.g. installmentFee=0,
          // totalAssessment=20000) and the cash-pending screen shows the wrong total.
          this.loadContext();
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
    // FIX BUG-INST-RACE-01 (loadDashboard): Pass current plan as hint so backend
    // _buildFees() never uses 'full' fallback during the race window where
    // students.payment_plan may not be committed yet. Without the hint, _buildFees
    // writes installment_fee=0, wiping the charge for the rest of the session.
    const _dashHint = this.paymentPlan === 'installment' ? '&hint_payment_plan=installment' : '';
    this.http.get<any>(`${this.apiUrl}?action=get_student_context&user_id=${this.userId}${_dashHint}`).subscribe({
      next: (res) => {
        if (res.success) {
          this.fees            = res.fees ?? null;
          this.termBreakdown   = res.termBreakdown ?? [];
          this.paymentReceipts = res.payments ?? [];
          // FIX BUG-INST-RACE-01 (loadDashboard): Never let a stale DB 'full'
          // response overwrite a confirmed 'installment' already set by loadContext().
          // Accept 'installment' unconditionally; only accept 'full' when we are not
          // already locked in to installment.
          const _dbPlan1 = (res.paymentPlan ?? res.student?.paymentPlan);
          if (_dbPlan1 === 'installment') {
            this.paymentPlan = 'installment';
          } else if (_dbPlan1 === 'full' && this.paymentPlan !== 'installment') {
            this.paymentPlan = 'full';
          }
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
      // FIX PLAN-NULL-02: Pass hint so getStudentContext resolves NULL payment_plan
      // correctly. Without the hint, a NULL DB value returns paymentPlan:'full'
      // which then clobbers a correctly-set 'installment' in this component.
      const _soaHint = this.paymentPlan === 'installment' ? '&hint_payment_plan=installment' : '';
      this.http.get<any>(`${this.apiUrl}?action=get_student_context&user_id=${this.userId}${_soaHint}`).subscribe({
        next: (res) => {
          this.isSoaHistoryLoading = false;
          if (res.success) {
            this.fees            = res.fees ?? null;
            // FIX PLAN-NULL-02: Never let a 'full' response (which may be the NULL
            // fallback from the backend) overwrite a confirmed 'installment' plan.
            // Only accept 'full' when the current plan is not already installment.
            const _dbPlan2 = (res.paymentPlan ?? res.student?.paymentPlan);
            if (_dbPlan2 === 'installment') {
              this.paymentPlan = 'installment';
            } else if (_dbPlan2 === 'full' && this.paymentPlan !== 'installment') {
              this.paymentPlan = 'full';
            }
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
            extraFees:        snap.extra_fees        ?? [],
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
                  extraFees: sf.extraFees ?? [],
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
      // BUG-FE-SCHOLAR-03: Scholar declaration is now handled separately via
      // declareFullTuitionScholarship() or declare_scholarship — not bundled
      // with submit_gcash. Removed to prevent accidental double-declaration.
    }).subscribe({
      next: (res) => {
        this.isProcessingPayment = false;
        if (res.success) {
          this.gcashSubmitted = true; this.paymentInfo.reference = txnId;
          this.rejectedNote = null; // FIX REJECT-NOTES-FE-01: clear stale rejection banner
          this.route('approval'); this.isApprovalPending = true;
          this.addNotification('success', '✅ Payment submitted! Awaiting Accounting verification.');
          this.approvalProcessed = false; // FIX APPROVAL-LOOP-01: fresh payment cycle
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

  // BUG-FE-SCHOLAR-01: Dedicated method for full-tuition scholarship declaration.
  // When scholarFullTuition=true all payment buttons are hidden, so students had
  // no way to submit their scholarship to the backend. This method calls
  // declare_scholarship directly and routes to the approval waiting screen.
  declareFullTuitionScholarship(): void {
    if (!this.scholarType.trim() || !this.scholarGrantor.trim()) {
      this.addNotification('error', 'Please fill in Scholarship Type and Grantor/Source.');
      return;
    }
    this.isProcessingPayment = true;
    this.http.post<any>(`${this.apiUrl}?action=declare_scholarship`, {
      student_id:         this.studentDbId,
      scholar_type:       this.scholarType,
      grantor:            this.scholarGrantor,
      scholarship_amount: 0,   // full tuition — amount resolved by accounting
    }).subscribe({
      next: (res) => {
        this.isProcessingPayment = false;
        if (res.success) {
          this.scholarPending = true;
          this.addNotification('success', '🎓 Full scholarship application submitted! Awaiting Accounting approval.');
          // Route to approval screen so student isn't left on payment step
          this.route('pending-approval');
          this.isApprovalPending = true;
          this.approvalProcessed = false; // FIX APPROVAL-LOOP-01: fresh payment cycle
          this.startApprovalPolling();
        } else {
          this.addNotification('error', res.message || 'Could not submit scholarship application.');
        }
        this.cdr.detectChanges();
      },
      error: () => {
        this.isProcessingPayment = false;
        this.addNotification('error', 'Cannot connect. Please try again.');
        this.cdr.detectChanges();
      }
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
          this.rejectedNote = null; // FIX REJECT-NOTES-FE-01: clear stale rejection banner
          this.isApprovalPending = true;
          this.addNotification('success', '💵 Please proceed to the Accounting Office to pay.');
          this.approvalProcessed = false; // FIX APPROVAL-LOOP-01: fresh payment cycle
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
    // FIX APPROVAL-LOOP-01: Do NOT reset approvalProcessed here.
    // startApprovalPolling() is called by loadContext() routing branches AND by
    // ngOnInit restore — if approvalProcessed is already true (approval was handled
    // this session), resetting it here would allow checkApprovalStatus() to fire
    // another toast + loadContext() chain, restarting the loop.
    // approvalProcessed is only reset in submitCashPayment(), submitGCashPayment(),
    // and submitScholarship() — i.e. genuine new payment submissions.
    if (this.approvalProcessed) {
      // Already approved this session — clear stale sessionStorage so page reloads
      // don't restore 'approval'/'cash-pending' and restart the loop again.
      sessionStorage.removeItem('enrollmentStep');
      return;
    }
    // Check immediately on start (covers the reload-and-already-approved case)
    this.checkApprovalStatus();
    this.pollInterval = setInterval(() => {
      if (!this.isApprovalPending) { clearInterval(this.pollInterval); this.pollInterval = null; return; }
      this.checkApprovalStatus();
    }, 10000);
  }

  checkApprovalStatus(): void {
    if (!this.userId) return;
    // FIX APPROVAL-LOOP-01: Bail immediately if approval was already processed,
    // OR if we're already on the dashboard (approval is complete by definition).
    // Without these guards, concurrent in-flight HTTP responses each see
    // approvalStatus=Approved and each fire addNotification() + loadContext(),
    // producing N "Payment approved!" toasts — the visible loop.
    if (this.approvalProcessed || this.workflowStep === 'dashboard') return;
    this.http.get<any>(`${this.apiUrl}?action=get_payment_status&user_id=${this.userId}`).subscribe({
      next: (res) => {
        if (res.success && res.approvalStatus === 'Approved') {
          // FIX APPROVAL-LOOP-01: Set the guard FIRST, before any async side-effects,
          // so any concurrent response that lands while loadContext() is in-flight
          // is dropped immediately on the guard above.
          if (this.approvalProcessed) return;
          this.approvalProcessed = true;
          // Stop polling immediately
          if (this.pollInterval) { clearInterval(this.pollInterval); this.pollInterval = null; }
          // FIX APPROVAL-LOOP-01: Clear enrollmentStep so a page reload of an
          // already-approved student does NOT restore 'approval'/'cash-pending'
          // and call startApprovalPolling() → checkApprovalStatus() → toast again.
          sessionStorage.removeItem('enrollmentStep');
          sessionStorage.removeItem('pendingPaymentMethod');
          // sessionStorage.removeItem('pendingPaymentPlan'); ← intentionally NOT removed here
          // Keep pendingPaymentPlan so loadContext() can pass it as hint_payment_plan.
          // FIX FE-PLAN-REVERT-01 (Bug 1): Removing it before loadContext() would
          // cause the backend to return 'full' if students.payment_plan isn't committed
          // yet, wiping the installment breakdown. Cleared naturally by loadContext().
          this.isApprovalPending = false;
          this.approvalMessage = this.paymentMethod === 'Cash'
            ? '💵 Cash payment confirmed by Accounting!'
            : '📱 GCash payment verified by Accounting!';

          // POLICY: ALL students must wait for Registrar confirmation before
          // subjects are enrolled. Accounting approval only sets enrollmentStatus
          // = 'Confirmed'. Subjects are enrolled only when Registrar sets it to
          // 'Enrolled' via confirm_registration.
          // Previously only transferees were held here — non-transferees were
          // routed directly to dashboard + ensureEnrolledThenLoad() which fired
          // auto_enroll_all immediately after Accounting approved, bypassing the
          // Registrar step for all regular (non-transferee) College students.
          if (res.enrollmentStatus !== 'Enrolled') {
            this.addNotification('success', '✅ Payment confirmed! Waiting for Registrar to finalize your enrollment.');
            this.isApprovalPending = true;
            this.route('approval');
            // Continue polling every 10s until Registrar sets enrollmentStatus = 'Enrolled'
            if (this.pollInterval) { clearInterval(this.pollInterval); this.pollInterval = null; }
            this.pollInterval = setInterval(() => {
              this.http.get<any>(`${this.apiUrl}?action=get_payment_status&user_id=${this.userId}`).subscribe({
                next: (poll) => {
                  if (poll.enrollmentStatus === 'Enrolled') {
                    clearInterval(this.pollInterval!); this.pollInterval = null;
                    this.isApprovalPending = false;
                    sessionStorage.removeItem('enrollmentStep');
                    this.addNotification('success', '🎉 Registrar confirmed! Loading your enrolled subjects...');
                    this.route('dashboard');
                    this.loadContext();
                  }
                }
              });
            }, 10000);
          } else {
            // enrollmentStatus is already 'Enrolled' (e.g. Registrar confirmed
            // before this poll fired, or re-enrollment path)
            this.addNotification('success', 'Payment approved! Loading your dashboard...');
            this.route('dashboard');
            this.loadContext();
          }
        } else if (res.success && (this.workflowStep === 'pending-approval' || this.workflowStep === 'cash-pending')) {
          // FIX CASH-POLL-01: Include cash-pending in the fallback branch so that a Cash
          // student whose payment was just verified by Accounting gets rescued here too.
          // Previously only 'pending-approval' was checked, leaving cash-pending students
          // stuck on the waiting screen even after Accounting approved their payment.
          if (res.paymentStatus === 'Paid' || res.approvalStatus === 'Approved') {
            // Status changed — reload context to get proper routing
            this.loadContext();
          }
          // FIX REJECT-NOTES-FE-01: Detect payment rejection while student is on the
          // approval/cash-pending waiting screen and route them back to the payment step
          // with the accounting note displayed so they know exactly what to fix.
          if (res.paymentStatus === 'Pending' && res.approvalStatus === 'Pending' && res.rejectedNote) {
            this.rejectedNote = res.rejectedNote;
            if (this.pollInterval) { clearInterval(this.pollInterval); this.pollInterval = null; }
            this.isApprovalPending = false;
            this.route('payment');
            this.addNotification('error', '❌ Payment rejected by Accounting. Please check the reason below and resubmit.');
            this.cdr.detectChanges();
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

  // ── OR/AR — all use Service Invoice format ──────────────────────────────────
  viewOR(receipt: any): void   { this.openServiceInvoice(receipt); }
  printOR(receipt: any): void  { this.openServiceInvoice(receipt); }
  viewAR(receipt: any): void   { this.openServiceInvoice(receipt); }
  printAR(receipt: any): void  { this.openServiceInvoice(receipt); }

    // ── Service Invoice (online/browser view — NOT an official receipt) ─────────
  async openServiceInvoice(receipt: any): Promise<void> {
    const s = this.student;
    const name = `${s.lastName || s.last_name || ''}, ${s.firstName || s.first_name || ''}`;
    const stNum = s.studentNumber || s.student_number || '';
    const amount = receipt.amount || 0;
    const fmt = (n: number) => n.toLocaleString('en-PH', { minimumFractionDigits: 2 });
    const period = receipt.period || receipt.examPeriod || '';
    const method = receipt.method || receipt.paymentMethod || '';
    const gcashRef = receipt.gcashReference || '';
    const orNo = receipt.orArNumber || receipt.or_ar_number || '';
    const payDate = receipt.paymentDate || receipt.payment_date || '';
    // totalAssessment from component fees (always accurate for current/past sem)
    const totalAssess = (this.fees?.totalAssessment ?? 0) || receipt.totalAssessment || receipt.total_assessment || 0;

    // Compute cumulative paid UP TO AND INCLUDING this specific receipt
    // so each invoice shows a different "Total Paid to Date" that reflects
    // exactly how much had been paid when this invoice was issued.
    const receiptIndex = this.paymentReceipts.findIndex(
      (r: any) => r.orArNumber === orNo
    );
    let cumulativePaid = 0;
    if (receiptIndex >= 0) {
      for (let i = 0; i <= receiptIndex; i++) {
        cumulativePaid += (this.paymentReceipts[i]?.amount || 0);
      }
    } else {
      // Fallback: use component total if receipt not found in array
      cumulativePaid = (this.fees?.totalPaid ?? 0) || amount;
    }
    const totalPaid = cumulativePaid;
    const balance = Math.max(0, totalAssess - totalPaid);

    let statusClass = 'status-unpaid'; let statusLabel = 'UNPAID';
    if (balance <= 0 && totalPaid > 0) { statusClass = 'status-paid'; statusLabel = 'FULLY PAID'; }
    else if (totalPaid > 0) { statusClass = 'status-partial'; statusLabel = 'PARTIALLY PAID'; }

    const html = `<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Service Invoice — ${orNo}</title>
<style>
  @import url('https://fonts.googleapis.com/css2?family=Source+Sans+3:wght@400;600;700&display=swap');
  *,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
  body{font-family:'Source Sans 3',Arial,sans-serif;background:#eee;padding:20px;font-size:12px;color:#111;}
  .page{background:white;width:148mm;margin:0 auto;padding:10mm 12mm 8mm;box-shadow:0 2px 12px rgba(0,0,0,.15);}
  .school-header{display:flex;align-items:center;gap:10px;border-bottom:2px solid #1a3c6e;padding-bottom:8px;margin-bottom:8px;}
  .logo-circle{width:52px;height:52px;background:#1a3c6e;border-radius:50%;flex-shrink:0;display:flex;align-items:center;justify-content:center;color:white;font-weight:700;font-size:16px;}
  .school-name{font-size:15px;font-weight:700;color:#1a3c6e;line-height:1.2;}
  .school-addr{font-size:10px;color:#555;}
  .doc-type{margin-top:12px;text-align:center;}
  .doc-type h2{font-size:14px;letter-spacing:2px;color:#1a3c6e;text-transform:uppercase;font-weight:700;}
  .doc-number{font-size:18px;font-weight:700;color:#c8352a;letter-spacing:1px;margin-top:2px;}
  .meta-row{display:flex;justify-content:space-between;font-size:10px;color:#666;margin-top:4px;}
  .badge-row{text-align:center;margin-top:6px;}
  .status-badge{display:inline-block;border-radius:4px;font-size:9px;font-weight:700;letter-spacing:1px;padding:2px 8px;text-transform:uppercase;}
  .status-paid{background:#d1e7dd;color:#0a6640;border:1px solid #a3cfbb;}
  .status-partial{background:#fff3cd;color:#856404;border:1px solid #ffc107;}
  .status-unpaid{background:#f8d7da;color:#842029;border:1px solid #f5c2c7;}
  .not-receipt-badge{display:inline-block;background:#fff3cd;color:#856404;border:1px solid #ffc107;border-radius:4px;font-size:9px;font-weight:700;letter-spacing:1px;padding:2px 8px;text-transform:uppercase;margin-left:6px;}
  .divider{border:none;border-top:1px dashed #ccc;margin:8px 0;}
  .section-title{font-size:9px;text-transform:uppercase;letter-spacing:1px;color:#888;font-weight:600;margin-bottom:4px;}
  .info-grid{display:grid;grid-template-columns:1fr 1fr;gap:2px 16px;margin-bottom:8px;}
  .field label{font-size:9px;color:#888;text-transform:uppercase;display:block;}
  .field span{font-size:11px;font-weight:600;}
  .box{background:#f8f9fb;border:1px solid #dde;border-radius:4px;padding:8px 10px;margin-bottom:8px;}
  .amt-center{text-align:center;margin:4px 0 8px;}
  .amt-label{font-size:9px;color:#888;text-transform:uppercase;letter-spacing:1px;}
  .amt-value{font-size:26px;font-weight:700;color:#1a3c6e;}
  table.bk{width:100%;font-size:10px;border-collapse:collapse;}
  table.bk td{padding:2px 4px;}
  table.bk td:last-child{text-align:right;font-weight:600;}
  .total-row td{border-top:1px solid #ccc;padding-top:4px;font-weight:700;font-size:11px;}
  .bal-row td{color:#c8352a;}
  .bal-row.paid td{color:#1a7a3c;}
  .footer-note{text-align:center;font-size:9px;color:#bbb;margin-top:8px;letter-spacing:1px;border-top:1px solid #eee;padding-top:6px;}
  .sig-row{display:flex;justify-content:space-between;margin-top:10px;}
  .sig{text-align:center;}
  .sig .line{border-top:1px solid #333;width:100px;margin:22px auto 2px;}
  .sig .sig-name{font-size:10px;font-weight:600;}
  .sig .sig-role{font-size:9px;color:#888;}
  @media print{body{background:white;padding:0;}.page{box-shadow:none;}.no-print{display:none;}}
</style></head><body>
<div class="no-print" style="text-align:center;margin-bottom:12px;">
  <button onclick="window.print()" style="background:#1a3c6e;color:white;border:none;padding:8px 20px;border-radius:4px;cursor:pointer;font-size:13px;">🖨️ Print Invoice</button>
  <button onclick="window.close()" style="margin-left:8px;background:#eee;border:none;padding:8px 16px;border-radius:4px;cursor:pointer;font-size:13px;">Close</button>
</div>
<div class="page">
  <div class="school-header">
    <div class="logo-circle">S</div>
    <div>
      <div class="school-name">St. Benilde Center for Global Competence, Inc.</div>
      <div class="school-addr">#2647 Rizal Avenue, West Bajac-Bajac, Olongapo City | Tel/Fax: (047) 223-9031</div>
    </div>
  </div>
  <div class="doc-type">
    <h2>Service Invoice</h2>
    <div class="doc-number">Ref. No. ${orNo}</div>
    <div class="meta-row"><span>Date: ${payDate}</span></div>
    <div class="badge-row">
      <span class="status-badge ${statusClass}">${statusLabel}</span>
    </div>
  </div>
  <hr class="divider">
  <div class="section-title">Student Information</div>
  <div class="info-grid">
    <div class="field"><label>Name</label><span>${name}</span></div>
    <div class="field"><label>Student No.</label><span>${stNum}</span></div>
    <div class="field"><label>Program</label><span>${s.program || ''}</span></div>
    <div class="field"><label>Year Level</label><span>${s.yearLevel || s.year_level || ''}</span></div>
    <div class="field"><label>Semester</label><span>${s.semester || ''}</span></div>
    <div class="field"><label>Payment Plan</label><span>${s.paymentPlan || s.payment_plan || ''}</span></div>
  </div>
  <hr class="divider">
  <div class="section-title">This Invoice</div>
  <div class="box" style="margin-bottom:10px;">
    <div class="amt-center">
      <div class="amt-label">Amount — ${period}</div>
      <div class="amt-value">₱${fmt(amount)}</div>
    </div>
    <table class="bk">
      <tr><td>Payment Method</td><td>${method}</td></tr>
      ${gcashRef ? `<tr><td>GCash Ref No.</td><td>${gcashRef}</td></tr>` : ''}
    </table>
  </div>
  <hr class="divider">
  <div class="section-title">Payment History</div>
  <table class="bk" style="margin-bottom:10px;border:1px solid #e2e8f0;border-radius:4px;overflow:hidden;">
    <thead>
      <tr style="background:#1a3c6e;color:white;">
        <th style="padding:5px 8px;text-align:left;font-size:9px;font-weight:700;">Period</th>
        <th style="padding:5px 8px;text-align:left;font-size:9px;font-weight:700;">Ref No.</th>
        <th style="padding:5px 8px;text-align:left;font-size:9px;font-weight:700;">Method</th>
        <th style="padding:5px 8px;text-align:left;font-size:9px;font-weight:700;">Date</th>
        <th style="padding:5px 8px;text-align:right;font-size:9px;font-weight:700;">Amount</th>
        <th style="padding:5px 8px;text-align:center;font-size:9px;font-weight:700;">Status</th>
      </tr>
    </thead>
    <tbody>
      ${(() => {
        if (receiptIndex < 0) return '<tr><td colspan="6" style="padding:6px 8px;color:#888;">No history available.</td></tr>';
        return this.paymentReceipts.slice(0, receiptIndex + 1).map((r: any, i: number) => {
          const isCurrent = r.orArNumber === orNo;
          const rowBg = isCurrent ? 'background:#eff6ff;font-weight:700;' : '';
          const marker = isCurrent ? ' ◀ This Invoice' : '✓ Paid';
          const markerColor = isCurrent ? '#1a3c6e' : '#166534';
          return `<tr style="${rowBg}">
            <td style="padding:4px 8px;font-size:10px;">${r.period || ''}</td>
            <td style="padding:4px 8px;font-size:10px;">${r.orArNumber || ''}</td>
            <td style="padding:4px 8px;font-size:10px;">${r.method || ''}</td>
            <td style="padding:4px 8px;font-size:10px;">${r.paymentDate ? new Date(r.paymentDate).toLocaleDateString('en-PH',{month:'short',day:'numeric',year:'numeric'}) : ''}</td>
            <td style="padding:4px 8px;text-align:right;font-size:10px;">₱${(r.amount||0).toLocaleString('en-PH',{minimumFractionDigits:2})}</td>
            <td style="padding:4px 8px;text-align:center;font-size:9px;color:${markerColor};font-weight:700;">${marker}</td>
          </tr>`;
        }).join('');
      })()}
    </tbody>
  </table>
  <table class="bk" style="margin-bottom:10px;">
    <tr class="total-row"><td>Total Assessment</td><td>₱${fmt(totalAssess)}</td></tr>
    <tr><td>Total Paid to Date</td><td>₱${fmt(totalPaid)}</td></tr>
    <tr class="bal-row ${balance <= 0 ? 'paid' : ''}"><td>Remaining Balance</td><td>₱${fmt(balance)}</td></tr>
  </table>
  <div class="sig-row">
    <div class="sig"><div class="line"></div><div class="sig-name">Accounting Office</div><div class="sig-role">Accounting Staff</div></div>
    <div class="sig"><div class="line"></div><div class="sig-name">${name}</div><div class="sig-role">Student / Representative</div></div>
  </div>
  <div class="footer-note"> SERVICE INVOICE</div>
</div>
</body></html>`;
    const win = window.open('', '_blank', 'width=760,height=860');
    if (!win) return;
    win.document.write(html);
    win.document.close();
  }

  // ── View Statement of Account ─────────────────────────────────────────────
  async viewSOA(): Promise<void> {
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
    <div class="info-val">${(s.department?.match(/\(([^)]+)\)\s*$/) ?? [])[1] || s.department || ''}</div>
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