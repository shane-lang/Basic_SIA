import { Component, OnInit, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient, HttpHeaders } from '@angular/common/http';
import { environment } from '../../environment';

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
  yearLevel: string;
  courseSemester: string;
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
  program: string;
  submittedCount: number;
  releasedCount: number;
  pendingRelease: number;
  isReleasing?: boolean;
}

interface Subject {
  lecUnits?: number; labUnits?: number; isGeneral?: boolean; isLab?: boolean;
  enrollmentId: number; courseId: number;
  code: string; name: string; credits: number;
  instructor: string; semester: string;
  yearLevel: string; courseSemester: string;
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
  private gradesApi    = environment.gradesApi;  // used for release only
  private registrarApi = environment.registrarApi;
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
  collapsedPrograms = new Set<string>();
  collapsedPending         = new Set<string>();   // key: dept
  collapsedPendingProgram  = new Set<string>();   // key: dept::program
  collapsedStudentPrograms = new Set<string>();

  toggleProgram(program: string): void {
    if (this.collapsedPrograms.has(program)) {
      this.collapsedPrograms.delete(program);
    } else {
      this.collapsedPrograms.add(program);
    }
  }

  isProgramCollapsed(program: string): boolean {
    return this.collapsedPrograms.has(program);
  }

  togglePending(dept: string): void {
    if (this.collapsedPending.has(dept)) this.collapsedPending.delete(dept);
    else this.collapsedPending.add(dept);
  }
  isPendingCollapsed(dept: string): boolean {
    return this.collapsedPending.has(dept);
  }

  togglePendingProgram(dept: string, program: string): void {
    const key = `${dept}::${program}`;
    if (this.collapsedPendingProgram.has(key)) this.collapsedPendingProgram.delete(key);
    else this.collapsedPendingProgram.add(key);
  }
  isPendingProgramCollapsed(dept: string, program: string): boolean {
    return this.collapsedPendingProgram.has(`${dept}::${program}`);
  }

  toggleStudentProgram(program: string): void {
    if (this.collapsedStudentPrograms.has(program)) {
      this.collapsedStudentPrograms.delete(program);
    } else {
      this.collapsedStudentPrograms.add(program);
    }
  }

  isStudentProgramCollapsed(program: string): boolean {
    return this.collapsedStudentPrograms.has(program);
  }

  get groupedPendingCourses(): { dept: string; programs: { program: string; courses: PendingCourse[] }[] }[] {
    const deptMap = new Map<string, Map<string, PendingCourse[]>>();
    for (const c of this.pendingCourses) {
      const dept = c.department?.trim() || 'Other';
      const prog = c.program?.trim()    || 'General';
      if (!deptMap.has(dept)) deptMap.set(dept, new Map());
      const progMap = deptMap.get(dept)!;
      if (!progMap.has(prog)) progMap.set(prog, []);
      progMap.get(prog)!.push(c);
    }
    return Array.from(deptMap.entries())
      .sort(([a], [b]) => a.localeCompare(b))
      .map(([dept, progMap]) => ({
        dept,
        programs: Array.from(progMap.entries())
          .sort(([a], [b]) => a.localeCompare(b))
          .map(([program, courses]) => ({ program, courses })),
      }));
  }

  get groupedStudents(): { program: string; students: GradeStudent[] }[] {
    const map = new Map<string, GradeStudent[]>();
    for (const s of this.students) {
      const prog = s.program?.trim() || 'Other';
      if (!map.has(prog)) map.set(prog, []);
      map.get(prog)!.push(s);
    }
    return Array.from(map.entries())
      .sort(([a], [b]) => a.localeCompare(b))
      .map(([program, students]) => ({ program, students }));
  }

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
    this.http.get<any>(`${this.gradesApi}?action=registrar_pending_grades`).subscribe({
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
      yearLevel: '', courseSemester: course.semester ?? '',
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
    }).subscribe({
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

    this.http.get<any>(`${this.registrarApi}?${p}`).subscribe({
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
      `${this.registrarApi}?action=get_grade_student_detail&student_id=${s.id}`
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
    this.http.get<any>(`${this.registrarApi}?${p}`).subscribe({
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
      `${this.registrarApi}?action=get_course_students&course_id=${c.id}`
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

  // ── Grouping helpers ──────────────────────────────────────
  private _normYear(yr: string): string {
    const l = (yr || '').toLowerCase();
    if (l.includes('5') || l.includes('fifth'))  return '5th Year';
    if (l.includes('4') || l.includes('fourth')) return '4th Year';
    if (l.includes('3') || l.includes('third'))  return '3rd Year';
    if (l.includes('2') || l.includes('second')) return '2nd Year';
    if (l.includes('1') || l.includes('first'))  return '1st Year';
    return yr || 'Other';
  }

  private _normSem(s: string): string {
    const l = (s || '').toLowerCase();
    if (l.includes('summer') || l.includes('mid')) return 'Summer';
    if (l.includes('2nd') || l.includes('second')) return '2nd Semester';
    if (l.includes('1st') || l.includes('first'))  return '1st Semester';
    return s || 'Other';
  }
  /** By Course tab: courses grouped by program */
  get groupedCourses(): { program: string; courses: GradeCourse[] }[] {
    const progMap = new Map<string, GradeCourse[]>();
    for (const c of this.courses) {
      const prog = c.program?.trim() || 'Other';
      if (!progMap.has(prog)) progMap.set(prog, []);
      progMap.get(prog)!.push(c);
    }
    return Array.from(progMap.entries())
      .sort(([a], [b]) => a.localeCompare(b))
      .map(([program, courses]) => ({ program, courses }));
  }



  /** Student detail view: subjects grouped by year level then semester */
  get groupedSubjects(): { yearLevel: string; semesters: { semester: string; subjects: Subject[]; totalUnits: number }[] }[] {
    const YEAR_ORDER = ['1st Year','2nd Year','3rd Year','4th Year','5th Year','Other'];
    const SEM_ORDER  = ['1st Semester','2nd Semester','Summer','Other'];
    const yearMap    = new Map<string, Map<string, Subject[]>>();

    for (const s of this.subjects) {
      const yr  = s.yearLevel      ? this._normYear(s.yearLevel)      : 'Other';
      const sem = s.courseSemester ? this._normSem(s.courseSemester)  : 'Other';
      if (!yearMap.has(yr))  yearMap.set(yr, new Map());
      const sm = yearMap.get(yr)!;
      if (!sm.has(sem)) sm.set(sem, []);
      sm.get(sem)!.push(s);
    }

    return Array.from(yearMap.entries())
      .sort(([a],[b]) => {
        const ia = YEAR_ORDER.indexOf(a), ib = YEAR_ORDER.indexOf(b);
        return ia !== -1 && ib !== -1 ? ia - ib : a.localeCompare(b);
      })
      .map(([yearLevel, semMap]) => ({
        yearLevel,
        semesters: Array.from(semMap.entries())
          .sort(([a],[b]) => {
            const ia = SEM_ORDER.indexOf(a), ib = SEM_ORDER.indexOf(b);
            return ia !== -1 && ib !== -1 ? ia - ib : a.localeCompare(b);
          })
          .map(([semester, subjects]) => ({
            semester,
            subjects,
            totalUnits: subjects.reduce((n, s) => n + (s.credits || 0), 0),
          })),
      }));
  }

}