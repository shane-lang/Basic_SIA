import { Component, OnInit, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient, HttpHeaders } from '@angular/common/http';

type ViewMode = 'thumbnail' | 'list';
type TabMode  = 'by-student' | 'by-course';

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

interface Subject {
  enrollmentId: number; courseId: number;
  code: string; name: string; credits: number;
  instructor: string; semester: string;
  prelim: number | null; midterm: number | null; final: number | null;
  overall: number | null; remarks: string;
  prelimAt: string | null; midtermAt: string | null; finalAt: string | null;
  editPrelim: string; editMidterm: string; editFinal: string;
  savingPrelim: boolean; savingMidterm: boolean; savingFinal: boolean;
}

@Component({
  selector: 'app-grade-submission',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './grade-submission.html',
  styleUrl: './grade-submission.css',
})
export class GradeSubmission implements OnInit {
  private gradesApi   = 'http://localhost/sia-api/grades.php';
  private registrarApi = 'http://localhost/sia-api/registrar.php';

  private getHeaders(): { headers: HttpHeaders } {
    const token = sessionStorage.getItem('token') || localStorage.getItem('token') || '';
    return { headers: new HttpHeaders({ Authorization: `Bearer ${token}` }) };
  }
  get registrarId(): number {
    const u = sessionStorage.getItem('currentUser') || localStorage.getItem('currentUser') || '{}';
    return JSON.parse(u).id ?? 0;
  }

  constructor(private http: HttpClient, private cdr: ChangeDetectorRef) {}

  // View state
  viewMode: ViewMode = 'thumbnail';
  tabMode:  TabMode  = 'by-student';

  // Student list
  students: GradeStudent[] = [];
  isLoadingStudents = false;
  searchQuery   = '';
  filterProgram = '';
  filterYearLevel = '';
  filterSemester  = '';
  currentPage   = 1;
  totalPages    = 1;
  totalStudents = 0;
  programs: string[] = [];
  searchTimeout: any;

  // Course list
  courses: GradeCourse[]  = [];
  isLoadingCourses = false;

  // Grade entry (student detail pane)
  selectedStudent: GradeStudent | null = null;
  selectedCourse:  GradeCourse | null  = null;
  subjects: Subject[] = [];
  courseStudents: any[] = [];
  isLoadingSubjects = false;
  showDetailPane    = false;

  toast = { show: false, type: 'success' as 'success' | 'error', message: '' };

  ngOnInit(): void {
    this.loadStudents();
    this.loadCourses();
  }

  /* ─── Student Tab ─────────────────────────────────────── */

  loadStudents(page = 1): void {
    this.isLoadingStudents = true;
    this.currentPage = page;
    const p = new URLSearchParams({
      action: 'get_grade_students',
      page: String(page), limit: '24',
      ...(this.searchQuery    && { q: this.searchQuery }),
      ...(this.filterProgram  && { program: this.filterProgram }),
      ...(this.filterYearLevel && { year_level: this.filterYearLevel }),
      ...(this.filterSemester  && { semester: this.filterSemester }),
    });

    this.http.get<any>(`${this.registrarApi}?${p}`, this.getHeaders()).subscribe({
      next: (res) => {
        this.isLoadingStudents = false;
        if (res.success) {
          this.students     = res.students || [];
          this.totalPages   = res.totalPages || 1;
          this.totalStudents = res.total || 0;
          // collect programs for filter
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
    this.filterYearLevel = ''; this.filterSemester = '';
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
            editPrelim:  sub.prelim  !== null ? String(sub.prelim)  : '',
            editMidterm: sub.midterm !== null ? String(sub.midterm) : '',
            editFinal:   sub.final   !== null ? String(sub.final)   : '',
            savingPrelim: false, savingMidterm: false, savingFinal: false,
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
    this.http.get<any>(`${this.registrarApi}?action=get_grade_courses`, this.getHeaders()).subscribe({
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

  /* ─── Grade Saving ────────────────────────────────────── */

  saveGrade(subject: Subject, term: 'Prelim' | 'Midterm' | 'Final'): void {
    const rawVal = term === 'Prelim' ? subject.editPrelim
                : term === 'Midterm' ? subject.editMidterm : subject.editFinal;
    const grade  = rawVal !== '' ? parseFloat(rawVal) : null;
    if (grade !== null && (isNaN(grade) || grade < 1.0 || grade > 5.0)) {
      this.showToast('error', 'Grade must be 1.00–5.00'); return;
    }
    const savingKey = ('saving' + term) as keyof Subject;
    (subject as any)[savingKey] = true;

    this.http.post<any>(`${this.gradesApi}?action=save_grade`, {
      enrollment_id: subject.enrollmentId,
      student_id:    this.selectedStudent?.id,
      course_id:     subject.courseId,
      term, grade, submitted_by: this.registrarId,
    }, this.getHeaders()).subscribe({
      next: (res) => {
        (subject as any)[savingKey] = false;
        if (res.success) {
          if (term === 'Prelim')  { subject.prelim  = grade; subject.prelimAt  = new Date().toISOString(); }
          if (term === 'Midterm') { subject.midterm = grade; subject.midtermAt = new Date().toISOString(); }
          if (term === 'Final')   { subject.final   = grade; subject.finalAt   = new Date().toISOString(); }
          this._recomputeOverall(subject);
          this.showToast('success', `${subject.code} ${term} saved ✓`);
          // Update student card completion
          if (this.selectedStudent) this.selectedStudent.gradeCompletion = this._calcCompletion();
        } else { this.showToast('error', res.message || 'Save failed'); }
        this.cdr.detectChanges();
      },
      error: () => { (subject as any)[savingKey] = false; this.showToast('error', 'Server error'); this.cdr.detectChanges(); }
    });
  }

  private _recomputeOverall(s: Subject): void {
    const vals = [s.prelim, s.midterm, s.final].filter(v => v !== null) as number[];
    s.overall  = vals.length ? Math.round(vals.reduce((a, b) => a + b, 0) / vals.length * 100) / 100 : null;
    s.remarks  = s.final !== null ? (s.overall !== null && s.overall <= 3.0 ? 'Passed' : 'Failed') : 'In Progress';
  }

  private _calcCompletion(): number {
    const total = this.subjects.length * 3;
    if (!total) return 0;
    const done = this.subjects.reduce((n, s) =>
      n + (s.prelim !== null ? 1 : 0) + (s.midterm !== null ? 1 : 0) + (s.final !== null ? 1 : 0), 0);
    return Math.round(done / total * 100);
  }

  closeDetailPane(): void {
    this.showDetailPane  = false;
    this.selectedStudent = null;
    this.selectedCourse  = null;
    this.subjects = []; this.courseStudents = [];
    this.loadStudents(this.currentPage);
    this.loadCourses();
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