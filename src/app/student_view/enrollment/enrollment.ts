import { Component, OnInit, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { Router } from '@angular/router';

interface Course {
  id: number;
  code: string;
  name: string;
  credits: number;
  instructor: string;
  schedule: string;
  day: string;
  time: string;
  room: string;
  capacity: number;
  enrolled: number;
  available: number;
  semester: string;
  description: string;
  department: string;
}

interface StudentCourse {
  id: number;
  courseId: number;
  code: string;
  name: string;
  credits: number;
  instructor: string;
  schedule: string;
  day: string;
  time: string;
  room: string;
  enrollmentDate: string;
  status: 'Pending' | 'Enrolled' | 'Completed' | 'Dropped';
  grade?: string;
}

interface EnrollmentNotification {
  id: string;
  type: 'success' | 'warning' | 'error' | 'info';
  message: string;
  timestamp: Date;
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

  constructor(private http: HttpClient, private router: Router, private cdr: ChangeDetectorRef) {}

  // ── Workflow ──────────────────────────────────────────────
  workflowStep: 'payment' | 'cash-pending' | 'approval' | 'dashboard' = 'payment';

  // ── Student identity ──────────────────────────────────────
  userId:      number = 0;
  studentDbId: number = 0;

  // ── Programs ──────────────────────────────────────────────
  programs = [
    { id: 'IT',  name: 'BS Information Technology', code: 'IT',  description: 'Learn programming, web development, and IT systems' },
    { id: 'CS',  name: 'BS Computer Science',        code: 'CS',  description: 'Advanced computer science and algorithms' },
    { id: 'BIS', name: 'BS Information Systems',     code: 'BIS', description: 'Business and IT integration' },
    { id: 'CE',  name: 'BS Civil Engineering',        code: 'CE',  description: 'Infrastructure and construction' }
  ];

  // ── Student profile ───────────────────────────────────────
  student: any = {};

  // ── Payment ───────────────────────────────────────────────
  paymentInfo = {
    amount:    25000,
    status:    'Pending' as 'Pending' | 'Paid',
    dueDate:   '2025-02-28',
    paidDate:  '',
    reference: ''
  };
  isProcessingPayment = false;
  paymentMethod: 'GCash' | 'Cash' = 'GCash';
  gcashReference = '';
  gcashAmount    = 25000;
  gcashDate      = new Date().toISOString().split('T')[0];
  gcashSubmitted = false;

  // ── Approval ─────────────────────────────────────────────
  isApprovalPending = false;
  approvalMessage   = '';
  private pollInterval: any = null;

  // ── Courses ───────────────────────────────────────────────
  currentSemester  = '1st Semester, AY 2024-2025';
  availableCourses: Course[]        = [];
  enrolledCourses:  StudentCourse[] = [];

  // ── UI state ─────────────────────────────────────────────
  currentView: 'dashboard' | 'enroll' | 'manage' = 'dashboard';
  showEnrollModal       = false;
  showDropModal         = false;
  showConfirmationModal = false;
  selectedCourseForEnroll: Course | null        = null;
  selectedCourseForDrop:   StudentCourse | null = null;
  lastEnlistedCourse: Course | null = null;

  searchQuery      = '';
  filterDepartment: string | null = null;
  notifications:   EnrollmentNotification[] = [];
  enrollmentForm   = { courseId: 0, notes: '', priority: 'Medium' as 'Low' | 'Medium' | 'High' };

  enrollmentDeadline    = '2025-03-31';
  addDropDeadline       = '2025-04-15';
  maxCredits            = 24;
  minCredits            = 15;
  daysUntilDeadline     = 0;
  isDeadlineApproaching = false;
  creditWarning         = '';


  // ── ngOnInit ─────────────────────────────────────────────
  ngOnInit(): void {
    const storedUser = localStorage.getItem('currentUser');
    if (!storedUser) return;
    const user  = JSON.parse(storedUser);
    this.userId = user.id;

    // Restore workflowStep immediately so navigating away+back doesn't flash payment screen
    const savedStep = localStorage.getItem('enrollmentStep') as typeof this.workflowStep | null;
    if (savedStep) {
      this.workflowStep = savedStep;
      if (savedStep === 'dashboard') {
        this.loadEnrolledCourses();
        this.loadAvailableCourses();
      }
    }

    this.http.get<any>(`${this.apiUrl}?action=get_profile&user_id=${this.userId}`).subscribe({
      next: (res) => {
        if (res.success) {
          this.student     = res.student;
          this.studentDbId = res.student.dbId;
          // Save studentDbId so schedule component can use it directly
          localStorage.setItem('studentDbId', String(res.student.dbId));

          const storedPm = localStorage.getItem('pendingPaymentMethod');
          if (storedPm === 'Cash' || storedPm === 'GCash') {
            this.paymentMethod         = storedPm;
            this.student.paymentMethod = storedPm;
          } else {
            this.paymentMethod = (res.student.paymentMethod === 'Cash') ? 'Cash' : 'GCash';
          }

          this.paymentInfo.status = res.student.paymentStatus === 'Paid' ? 'Paid' : 'Pending';

          // DB is source of truth — always re-derive step from latest status
          if (res.student.approvalStatus === 'Approved' || res.student.enrollmentStatus === 'Enrolled') {
            localStorage.removeItem('pendingPaymentMethod');
            this.setStep('dashboard');
            this.loadEnrolledCourses();
            this.loadAvailableCourses();
          } else if (res.student.paymentStatus === 'Paid') {
            this.setStep('approval');
            this.isApprovalPending = true;
            this.startApprovalPolling();
          } else {
            if (this.paymentMethod === 'Cash') {
              this.setStep('cash-pending');
              this.isApprovalPending = true;
              this.startApprovalPolling();
            } else {
              this.setStep('payment');
            }
          }
        }
        this.cdr.detectChanges();
      },
      error: () => { this.cdr.detectChanges(); }
    });
    this.calculateDeadlineInfo();
  }

  // Save workflowStep to localStorage so it survives navigation
  setStep(step: typeof this.workflowStep): void {
    this.workflowStep = step;
    localStorage.setItem('enrollmentStep', step);
  }

  // ── Load data ─────────────────────────────────────────────
  loadAvailableCourses(): void {
    this.http.get<any>(`${this.apiUrl}?action=get_courses&user_id=${this.userId}&semester=${encodeURIComponent(this.currentSemester)}`)
      .subscribe({ next: (res) => { if (res.success) { this.availableCourses = res.courses; this.cdr.detectChanges(); } } });
  }

  loadEnrolledCourses(): void {
    this.http.get<any>(`${this.apiUrl}?action=get_enrollments&user_id=${this.userId}`)
      .subscribe({
        next: (res) => {
          if (res.success) { this.enrolledCourses = res.enrollments; this.checkCreditWarning(); this.cdr.detectChanges(); }
        }
      });
  }

  // ── GCash submit ──────────────────────────────────────────
  processPayment(): void {
    if (!this.studentDbId) {
      const stored = localStorage.getItem('currentUser');
      if (!stored) { this.addNotification('error', 'Not logged in.'); return; }
      const u = JSON.parse(stored);
      this.http.get<any>(`${this.apiUrl}?action=get_profile&user_id=${u.id}`).subscribe({
        next: (res) => {
          if (res.success) { this.student = res.student; this.studentDbId = res.student.dbId; this.cdr.detectChanges(); this.processPayment(); }
          else { this.addNotification('error', 'Student profile not found.'); }
        }
      });
      return;
    }

    if (!this.gcashReference.trim()) {
      this.addNotification('error', 'Please enter your GCash Reference Number.');
      return;
    }

    this.isProcessingPayment = true;
    this.cdr.detectChanges();

    const txnId = 'TXN-' + Date.now() + '-' + Math.random().toString(36).substring(2, 7).toUpperCase();

    this.http.post<any>(`${this.accountingApi}?action=submit_gcash`, {
      student_id:      this.studentDbId,
      gcash_reference: this.gcashReference.trim(),
      gcash_amount:    this.gcashAmount,
      gcash_date:      this.gcashDate,
      transaction_id:  txnId,
      semester:        this.currentSemester
    }).subscribe({
      next: (res) => {
        this.isProcessingPayment = false;
        if (res.success) {
          this.gcashSubmitted        = true;
          this.paymentInfo.reference = txnId;
          this.workflowStep          = 'approval';
          this.isApprovalPending     = true;
          this.addNotification('success', '✅ GCash payment submitted! Waiting for Accounting verification.');
          this.startApprovalPolling();
        } else {
          this.addNotification('error', res.message || 'Submission failed. Please try again.');
        }
        this.cdr.detectChanges();
      },
      error: () => {
        this.isProcessingPayment = false;
        this.addNotification('error', 'Cannot connect to server.');
        this.cdr.detectChanges();
      }
    });
  }

  // ── Approval polling ──────────────────────────────────────
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
          this.http.get<any>(`${this.apiUrl}?action=get_profile&user_id=${this.userId}`).subscribe({
            next: (pRes) => {
              if (pRes.success) { this.student = pRes.student; this.studentDbId = pRes.student.dbId; }
              clearInterval(this.pollInterval);
              localStorage.removeItem('pendingPaymentMethod');
              this.isApprovalPending = false;
              this.approvalMessage   = this.paymentMethod === 'Cash'
                ? '💵 Cash payment confirmed by the Accounting Office!'
                : '📱 GCash payment verified by the Accounting Office!';
              this.addNotification('success',
                this.paymentMethod === 'Cash'
                  ? 'Cash payment confirmed! You can now enlist subjects.'
                  : 'Payment verified! You can now enlist subjects.'
              );
              this.cdr.detectChanges();
            }
          });
        }
      }
    });
  }

  proceedToDashboard(): void {
    this.setStep('dashboard');
    this.loadAvailableCourses();
    this.loadEnrolledCourses();
    this.cdr.detectChanges();
  }

  // ── Enroll / Drop ─────────────────────────────────────────
  openEnrollModal(course: Course): void {
    this.selectedCourseForEnroll = course;
    this.enrollmentForm = { courseId: course.id, notes: '', priority: 'Medium' };
    this.showEnrollModal = true;
    this.cdr.detectChanges();
  }

  closeEnrollModal(): void {
    this.showEnrollModal         = false;
    this.selectedCourseForEnroll = null;
    this.cdr.detectChanges();
  }

  confirmEnrollment(): void {
    if (!this.selectedCourseForEnroll) return;

    if (this.totalEnrolledCredits + this.selectedCourseForEnroll.credits > this.maxCredits) {
      this.addNotification('error', `Cannot exceed ${this.maxCredits} credits.`);
      this.closeEnrollModal();
      return;
    }

    const courseToEnlist = this.selectedCourseForEnroll;
    this.http.post<any>(`${this.apiUrl}?action=enroll_course`, {
      student_id: this.studentDbId,
      course_id:  courseToEnlist.id,
      notes:      this.enrollmentForm.notes,
      semester:   this.currentSemester
    }).subscribe({
      next: (res) => {
        this.closeEnrollModal();
        if (res.success) {
          this.lastEnlistedCourse   = courseToEnlist;
          this.showConfirmationModal = true;
          this.addNotification('success', `✅ ${courseToEnlist.code} enlisted successfully!`);
          this.loadEnrolledCourses();
          this.loadAvailableCourses();
        } else {
          this.addNotification('error', res.message || 'Enlistment failed.');
        }
        this.cdr.detectChanges();
      },
      error: () => {
        this.addNotification('error', 'Server error. Please try again.');
        this.closeEnrollModal();
        this.cdr.detectChanges();
      }
    });
  }

  openDropModal(course: StudentCourse): void {
    this.selectedCourseForDrop = course;
    this.showDropModal = true;
    this.cdr.detectChanges();
  }

  closeDropModal(): void {
    this.showDropModal         = false;
    this.selectedCourseForDrop = null;
    this.cdr.detectChanges();
  }

  confirmDrop(): void {
    if (!this.selectedCourseForDrop) return;
    this.http.put<any>(`${this.apiUrl}?action=drop_course`, {
      enrollment_id: this.selectedCourseForDrop.id,
      student_id:    this.studentDbId
    }).subscribe({
      next: (res) => {
        if (res.success) {
          this.addNotification('success', `${this.selectedCourseForDrop!.code} dropped.`);
          this.loadEnrolledCourses();
          this.loadAvailableCourses();
        } else {
          this.addNotification('error', res.message);
        }
        this.closeDropModal();
        this.cdr.detectChanges();
      }
    });
  }

  closeConfirmationModal(): void {
    this.showConfirmationModal = false;
    this.cdr.detectChanges();
  }

  // ── Schedule helper ───────────────────────────────────────
  getCoursesForDay(day: string): StudentCourse[] {
    return this.enrolledCourses.filter(c =>
      c.status === 'Enrolled' &&
      c.day &&
      c.day.split(',').map((d: string) => d.trim()).includes(day)
    );
  }

  // ── Computed ──────────────────────────────────────────────
  get totalEnrolledCredits(): number {
    return this.enrolledCourses
      .filter(c => c.status === 'Enrolled' || c.status === 'Pending')
      .reduce((sum, c) => sum + c.credits, 0);
  }

  get pendingCourses():  StudentCourse[] { return this.enrolledCourses.filter(c => c.status === 'Pending'); }
  get approvedCourses(): StudentCourse[] { return this.enrolledCourses.filter(c => c.status === 'Enrolled'); }

  get availableCoursesFiltered(): Course[] {
    return this.availableCourses.filter(c => {
      const s = this.searchQuery.toLowerCase();
      return (c.name.toLowerCase().includes(s) || c.code.toLowerCase().includes(s)) &&
             (!this.filterDepartment || c.department === this.filterDepartment);
    });
  }

  get departments(): string[] {
    return [...new Set(this.availableCourses.map(c => c.department))];
  }

  canEnroll(c: Course):      boolean { return c.available == null || c.available > 0; }
  isCourseAlmostFull(c: Course): boolean { return c.available < 5 && c.available > 0; }
  getAvailableSeats(c: Course):  number  { return c.available; }
  canAddCourse():  boolean { return this.totalEnrolledCredits < this.maxCredits; }
  canDropCourse(): boolean { return new Date() <= new Date(this.addDropDeadline); }

  calculateDeadlineInfo(): void {
    const daysLeft = Math.ceil((new Date(this.enrollmentDeadline).getTime() - new Date().getTime()) / 86400000);
    this.daysUntilDeadline     = daysLeft;
    this.isDeadlineApproaching = daysLeft <= 7 && daysLeft > 0;
  }

  checkCreditWarning(): void {
    const t = this.totalEnrolledCredits;
    this.creditWarning = t > this.maxCredits ? `Exceeds maximum ${this.maxCredits} credits.`
                       : (t > 0 && t < this.minCredits) ? `Minimum ${this.minCredits} credits required. Current: ${t}`
                       : '';
  }

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

  // Stubs
  selectStudentType(_: string): void {}
  backToProgram(): void {}
  selectProgram(_: string): void {}
  submitRegistration(): void {}
  approveEnrollment(): void {}
  approveAllPendingCourses(): void {}
}