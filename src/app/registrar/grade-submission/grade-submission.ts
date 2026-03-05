import { Component, OnInit, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient, HttpHeaders } from '@angular/common/http';

type ViewMode = 'thumbnail' | 'list';
type TabMode  = 'by-student' | 'by-course' | 'pending-release';

interface GradeStudent {
  id: number;
  studentNumber: string;
  firstName: string;
  lastName: string;
  fullName: string;
  program: string;
  yearLevel: string;
  semester: string;
  status: string;
  totalSubjects: number;
  prelimDone: number;
  midtermDone: number;
  finalDone: number;
  gradeCompletion: number;
  initials: string;
}

interface GradeCourse {
  id: number;
  code: string;
  name: string;
  instructor: string;
  program: string;
  credits: number;
  enrolledCount: number;
  prelimDone: number;
  midtermDone: number;
  finalDone: number;
  gradeCompletion: number;
}

interface PendingCourse {
  courseId: number;
  code: string;
  name: string;
  facultyName: string;
  semester: string;
  department: string;
  submittedCount: number;
  releasedCount: number;
  pendingRelease: number;
  isReleasing?: boolean;
}

interface Subject {
  enrollmentId: number; courseId: number;
  code: string; name: string; credits: number;
  instructor: string; semester: string;
  prelim: number | null; midterm: number | null; final: number | null;
  overall: number | null; remarks: string;
  prelimAt: string | null; midtermAt: string | null; finalAt: string | null;
}

@Component({
  selector: 'app-grade-submission',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './grade-submission.html',
  styleUrl: './grade-submission.css',
})
export class GradeSubmission implements OnInit {
  private gradesApi    = 'http://localhost/sia-api/grades.php';  // used for release only
  private registrarApi = 'http://localhost/sia-api/registrar.php';

  private getHeaders(): { headers: HttpHeaders } {
    const token = sessionStorage.getItem('token') || localStorage.getItem('token') || '';
    return { headers: new HttpHeaders({ Authorization: `Bearer ${token}` }) };
  }
  constructor(private http: HttpClient, private cdr: ChangeDetectorRef) {}

  // View state
  viewMode: ViewMode = 'thumbnail';
  tabMode:  TabMode  = 'pending-release';

  // Student list
  students: GradeStudent[] = [];
  isLoadingStudents = false;
  searchQuery     = '';
  filterProgram   = '';
  filterYearLevel = '';
  filterSemester  = '';
  filterCategory  = '';   // 'College' | 'SHS' | 'TVET' | ''
  currentPage   = 1;
  totalPages    = 1;
  totalStudents = 0;
  programs: string[] = [];
  searchTimeout: any;

  // Course list
  courses: GradeCourse[] = [];
  isLoadingCourses = false;
  filterCourseCategory = '';  // 'College' | 'SHS' | 'TVET' | ''

  // Pending release
  pendingCourses: PendingCourse[] = [];
  isLoadingPending = false;

  // Grade entry (student detail pane)
  selectedStudent: GradeStudent | null = null;
  selectedCourse:  GradeCourse | null  = null;
  subjects: Subject[] = [];
  courseStudents: any[] = [];
  isLoadingSubjects = false;
  showDetailPane    = false;

  toast = { show: false, type: 'success' as 'success' | 'error', message: '' };

  ngOnInit(): void {
    this.loadPendingRelease();
    this.loadStudents();
    this.loadCourses();
  }

  /* ─── Pending Release Tab ────────────────────────────── */

  loadPendingRelease(): void {
    this.isLoadingPending = true;
    this.http.get<any>(`${this.gradesApi}?action=registrar_pending_grades`, this.getHeaders()).subscribe({
      next: (res) => {
        this.isLoadingPending = false;
        this.pendingCourses = res.success ? (res.courses || []) : [];
        this.cdr.detectChanges();
      },
      error: () => { this.isLoadingPending = false; this.cdr.detectChanges(); }
    });
  }

  viewCourse(course: PendingCourse): void {
    const fakeCourse: GradeCourse = {
      id: course.courseId, code: course.code, name: course.name,
      instructor: course.facultyName, program: course.department,
      credits: 0, enrolledCount: course.submittedCount,
      prelimDone: 0, midtermDone: 0, finalDone: 0, gradeCompletion: 0,
    };
    this.selectCourse(fakeCourse);
  }

  releaseCourse(course: PendingCourse): void {
    if (course.pendingRelease === 0) {
      this.showToast('error', 'All grades in this course are already released.'); return;
    }
    course.isReleasing = true;
    this.http.post<any>(`${this.gradesApi}?action=registrar_release_grades`, {
      course_id: course.courseId,
    }, this.getHeaders()).subscribe({
      next: (res) => {
        course.isReleasing = false;
        if (res.success) {
          this.showToast('success', `✅ ${res.message}`);
          course.releasedCount  += res.affected;
          course.pendingRelease -= res.affected;
        } else {
          this.showToast('error', res.message || 'Release failed');
        }
        this.cdr.detectChanges();
      },
      error: () => {
        course.isReleasing = false;
        this.showToast('error', 'Server error during release.');
        this.cdr.detectChanges();
      }
    });
  }

  get totalPendingRelease(): number {
    return this.pendingCourses.reduce((n, c) => n + c.pendingRelease, 0);
  }

  /* ─── Student Tab ─────────────────────────────────────── */

  loadStudents(page = 1): void {
    this.isLoadingStudents = true;
    this.currentPage = page;
    const p = new URLSearchParams({
      action: 'get_grade_students',
      page: String(page), limit: '24',
      ...(this.searchQuery       && { q: this.searchQuery }),
      ...(this.filterProgram     && { program: this.filterProgram }),
      ...(this.filterYearLevel   && { year_level: this.filterYearLevel }),
      ...(this.filterSemester    && { semester: this.filterSemester }),
      ...(this.filterCategory    && { category: this.filterCategory }),
    });

    this.http.get<any>(`${this.registrarApi}?${p}`, this.getHeaders()).subscribe({
      next: (res) => {
        this.isLoadingStudents = false;
        if (res.success) {
          this.students     = res.students || [];
          this.totalPages   = res.totalPages || 1;
          this.totalStudents = res.total || 0;
          const progs = new Set(this.students.map((s: GradeStudent) => s.program).filter(Boolean));
          if (this.programs.length === 0) this.programs = [...progs];
        }
        this.cdr.detectChanges();
      },
      error: () => { this.isLoadingStudents = false; this.cdr.detectChanges(); }
    });
  }

  onSearch(): void {
    clearTimeout(this.searchTimeout);
    this.searchTimeout = setTimeout(() => this.loadStudents(1), 350);
  }

  clearFilters(): void {
    this.searchQuery = ''; this.filterProgram = '';
    this.filterYearLevel = ''; this.filterSemester = ''; this.filterCategory = '';
    this.loadStudents(1);
  }

  selectStudent(s: GradeStudent): void {
    this.selectedStudent  = s;
    this.selectedCourse   = null;
    this.showDetailPane   = true;
    this.isLoadingSubjects = true;
    this.subjects = [];

    this.http.get<any>(
      `${this.registrarApi}?action=get_grade_student_detail&student_id=${s.id}`,
      this.getHeaders()
    ).subscribe({
      next: (res) => {
        this.isLoadingSubjects = false;
        if (res.success) {
          this.subjects = (res.subjects || []).map((sub: any) => ({
            ...sub,
          }));
        }
        this.cdr.detectChanges();
      },
      error: () => { this.isLoadingSubjects = false; this.cdr.detectChanges(); }
    });
  }

  /* ─── Course Tab ──────────────────────────────────────── */

  loadCourses(): void {
    this.isLoadingCourses = true;
    const p = new URLSearchParams({ action: 'get_grade_courses' });
    if (this.filterCourseCategory) p.set('category', this.filterCourseCategory);
    this.http.get<any>(`${this.registrarApi}?${p}`, this.getHeaders()).subscribe({
      next: (res) => {
        this.isLoadingCourses = false;
        this.courses = res.success ? (res.courses || []) : [];
        this.cdr.detectChanges();
      },
      error: () => { this.isLoadingCourses = false; this.cdr.detectChanges(); }
    });
  }

  selectCourse(c: GradeCourse): void {
    this.selectedCourse  = c;
    this.selectedStudent = null;
    this.showDetailPane  = true;
    this.isLoadingSubjects = true;
    this.courseStudents = [];

    this.http.get<any>(
      `${this.registrarApi}?action=get_course_students&course_id=${c.id}`,
      this.getHeaders()
    ).subscribe({
      next: (res) => {
        this.isLoadingSubjects = false;
        if (res.success) this.courseStudents = res.students || [];
        this.cdr.detectChanges();
      },
      error: () => { this.isLoadingSubjects = false; this.cdr.detectChanges(); }
    });
  }

  closeDetailPane(): void {
    this.showDetailPane  = false;
    this.selectedStudent = null;
    this.selectedCourse  = null;
    this.subjects = []; this.courseStudents = [];
    this.loadStudents(this.currentPage);
    this.loadCourses();
    this.loadPendingRelease();
  }

  /* ─── Helpers ─────────────────────────────────────────── */

  prevPage(): void { if (this.currentPage > 1) this.loadStudents(this.currentPage - 1); }
  nextPage(): void { if (this.currentPage < this.totalPages) this.loadStudents(this.currentPage + 1); }

  completionClass(pct: number): string {
    if (pct >= 100) return 'cp-full';
    if (pct >= 60)  return 'cp-mid';
    if (pct > 0)    return 'cp-low';
    return 'cp-none';
  }

  gradeClass(g: number | null): string {
    if (g === null) return '';
    if (g <= 1.5) return 'g-excel';
    if (g <= 2.0) return 'g-good';
    if (g <= 3.0) return 'g-pass';
    return 'g-fail';
  }

  fmtGrade(g: number | null): string { return g !== null ? g.toFixed(2) : '—'; }

  get gradedSubjectCount(): number {
    return this.subjects.filter(s => s.prelim !== null || s.midterm !== null || s.final !== null).length;
  }

  showToast(type: 'success' | 'error', message: string): void {
    this.toast = { show: true, type, message };
    setTimeout(() => { this.toast.show = false; this.cdr.detectChanges(); }, 3500);
  }
}