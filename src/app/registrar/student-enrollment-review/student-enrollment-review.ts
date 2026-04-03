import { Component, OnInit, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../environment';

// ── API base URLs (same pattern as the rest of the app) ────────────────────
const REGISTRAR_API  = environment.registrarApi;
const ENROLLMENT_API = environment.enrollApi;

// ── Interfaces matching actual DB columns returned by registrar.php ─────────
interface Student {
  id: number;
  studentNumber: string;
  firstName: string;
  lastName: string;
  middleName: string;
  fullName: string;
  email: string;
  phone: string;
  program: string;
  yearLevel: string;
  semester: string;
  studentType: string;
  studentCategory: string;
  enrollmentStatus: string;
  paymentStatus: string;
  approvalStatus: string;
  isScholar: number;
  scholarType: string;
  enrollmentDate: string;
  // detail fields (loaded on demand)
  courses?: EnrolledCourse[];
  fees?: FeeBreakdown;
  loadingDetail?: boolean;
}

interface EnrolledCourse {
  enrollment_id: number;
  course_id: number;
  code: string;
  name: string;
  credits: number;
  lecUnits?: number;
  labUnits?: number;
  isGeneral?: boolean;
  isLab?: boolean;
  instructor: string;
  day: string;
  time: string;
  room: string;
  semester: string;
  status: string;
}

interface FeeBreakdown {
  tuitionFee: number;
  miscellaneousFee: number;
  registrationFee: number;
  laboratoryFee: number;
  energyFee: number;
  subtotal: number;
  discount: number;
  installmentFee: number;
  totalAssessment: number;
  totalPaid: number;
  balance: number;
  paymentStatus: string;
}

@Component({
  selector: 'app-student-enrollment-review',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './student-enrollment-review.html',
  styleUrl: './student-enrollment-review.css',
})
export class StudentEnrollmentReviewComponent implements OnInit {
  // ── State ─────────────────────────────────────────────────────────────────
  students: Student[]  = [];
  programs: string[]   = [];
  loading              = false;
  error                = '';

  // Filters
  searchQuery   = '';
  filterStatus  = '';
  filterProgram = '';
  filterCategory = '';
  filterPayment = '';

  // Pagination
  page       = 1;
  limit      = 20;
  total      = 0;
  totalPages = 0;

  // Stats (derived from total counts, not current page)
  stats = { total: 0, pending: 0, enrolled: 0, unpaid: 0 };

  // Detail panel
  selectedStudent: Student | null = null;
  showDetail = false;

  // Notifications
  notifications: { id: number; type: string; message: string }[] = [];
  private _notifId = 0;

  constructor(private http: HttpClient, private cdr: ChangeDetectorRef) {}

  ngOnInit(): void {
    this.loadStudents();
    this.loadStats();
  }

  // ── Load student list from registrar.php ──────────────────────────────────
  loadStudents(): void {
    this.loading = true;
    this.error   = '';

    // FIX: removed manual _token — authInterceptor adds it automatically.
    // Double _token in URL caused Apache to reject the OPTIONS preflight (CORS error).
    const params: Record<string, string> = {
      action: 'masterlist_students',
      page:   String(this.page),
      limit:  String(this.limit),
    };

    if (this.searchQuery)    params['q']        = this.searchQuery;
    if (this.filterStatus)   params['status']   = this.filterStatus;
    if (this.filterProgram)  params['program']  = this.filterProgram;
    if (this.filterCategory) params['category'] = this.filterCategory;
    if (this.filterPayment)  params['payment']  = this.filterPayment;

    const qs = new URLSearchParams(params).toString();

    this.http.get<any>(`${REGISTRAR_API}?${qs}`).subscribe({
      next: (res) => {
        this.loading    = false;
        if (res.success) {
          this.students   = res.students ?? [];
          this.total      = res.total    ?? 0;
          this.totalPages = res.totalPages ?? 0;
          this.programs   = res.programs  ?? [];
        } else {
          this.error = res.message || 'Failed to load students';
        }
        this.cdr.detectChanges();
      },
      error: () => {
        this.loading = false;
        this.error = 'Connection error. Make sure XAMPP is running.';
        this.cdr.detectChanges();
      }
    });
  }

  // ── Load stats (separate counts per status) ───────────────────────────────
  loadStats(): void {
    // FIX: removed manual token reads — authInterceptor sends token automatically.

    // Total
    this.http.get<any>(`${REGISTRAR_API}?action=masterlist_students&limit=1&page=1`).subscribe({
      next: (res) => {
        if (res.success) this.stats.total = res.total ?? 0;
      }
    });

    // Pending enrollment
    this.http.get<any>(`${REGISTRAR_API}?action=masterlist_students&status=Pending&limit=1&page=1`).subscribe({
      next: (res) => {
        if (res.success) this.stats.pending = res.total ?? 0;
      }
    });

    // Enrolled
    this.http.get<any>(`${REGISTRAR_API}?action=masterlist_students&status=Enrolled&limit=1&page=1`).subscribe({
      next: (res) => {
        if (res.success) this.stats.enrolled = res.total ?? 0;
      }
    });
  }

  // ── View student detail (load courses + fees) ─────────────────────────────
  viewDetail(student: Student): void {
    this.selectedStudent = { ...student, loadingDetail: true, courses: [], fees: undefined };
    this.showDetail      = true;

    // FIX: removed manual _token — interceptor handles it.

    // 1. Load enrolled courses from enrollment.php
    this.http.get<any>(`${ENROLLMENT_API}?action=get_student_enrollments&student_id=${student.id}`).subscribe({
      next: (res) => {
        if (res.success && this.selectedStudent) {
          this.selectedStudent.courses = (res.enrolled ?? []).map((c: any) => ({
            enrollment_id: c.enrollment_id,
            course_id:     c.course_id,
            code:          c.code,
            name:          c.name,
            credits:       +c.credits,
            lecUnits:      +(c.lecUnits ?? c.lec_units ?? c.credits ?? 0),
            labUnits:      +(c.labUnits ?? c.lab_units ?? 0),
            isGeneral:     !!(c.isGeneral ?? c.is_general),
            isLab:         !!(c.isLab ?? c.is_lab),
            instructor:    c.instructor || '—',
            day:           c.day        || '—',
            time:          c.time       || '—',
            room:          c.room       || '—',
            semester:      c.semester   || '—',
            status:        c.status,
          }));
        }
        if (this.selectedStudent) this.selectedStudent.loadingDetail = false;
        this.cdr.detectChanges();
      },
      error: () => {
        if (this.selectedStudent) this.selectedStudent.loadingDetail = false;
        this.cdr.detectChanges();
      }
    });

    // 2. Load fee breakdown from enrollment.php get_student_context
    this.http.get<any>(`${ENROLLMENT_API}?action=get_student_context&student_id=${student.id}`).subscribe({
      next: (res) => {
        if (res.success && res.fees && this.selectedStudent) {
          this.selectedStudent.fees = res.fees as FeeBreakdown;
          this.cdr.detectChanges();
        }
      }
    });
  }

  closeDetail(): void {
    this.showDetail      = false;
    this.selectedStudent = null;
  }

  // ── Pagination ────────────────────────────────────────────────────────────
  goToPage(p: number): void {
    if (p < 1 || p > this.totalPages) return;
    this.page = p;
    this.loadStudents();
  }

  applyFilters(): void {
    this.page = 1;
    this.loadStudents();
    this.loadStats();
  }

  clearFilters(): void {
    this.searchQuery    = '';
    this.filterStatus   = '';
    this.filterProgram  = '';
    this.filterCategory = '';
    this.filterPayment  = '';
    this.page           = 1;
    this.loadStudents();
    this.loadStats();
  }

  // ── Helpers ───────────────────────────────────────────────────────────────
  get totalCredits(): number {
    return (this.selectedStudent?.courses ?? [])
      .filter(c => c.status === 'Enrolled' || c.status === 'Pending')
      .reduce((s, c) => s + c.credits, 0);
  }

  statusClass(status: string): string {
    const map: Record<string, string> = {
      Enrolled: 'badge-green',
      Pending:  'badge-yellow',
      Dropped:  'badge-gray',
      Approved: 'badge-blue',
      Rejected: 'badge-red',
      Paid:     'badge-green',
      Unpaid:   'badge-red',
      Partial:  'badge-yellow',
    };
    return map[status] ?? 'badge-gray';
  }

  fmt(val: number): string {
    return '₱' + val.toLocaleString('en-PH', { minimumFractionDigits: 2 });
  }

  // ── Notifications ─────────────────────────────────────────────────────────
  notify(type: string, message: string): void {
    const id = this._notifId++;
    this.notifications.push({ id, type, message });
    setTimeout(() => {
      this.notifications = this.notifications.filter(n => n.id !== id);
      this.cdr.detectChanges();
    }, 4000);
  }

  dismiss(id: number): void {
    this.notifications = this.notifications.filter(n => n.id !== id);
  }

  isMinor(code: string): boolean {
    if (!code) return false;
    const upper = code.toUpperCase();
    return upper.startsWith('GE') || upper.startsWith('PE') ||
           upper.startsWith('NSTP') || upper.startsWith('OJT');
  }

}