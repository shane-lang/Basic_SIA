import { Component, OnInit, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient, HttpHeaders } from '@angular/common/http';
import { environment } from '../../environment';

type Step = 'faculty' | 'subjects' | 'students';

interface Faculty {
  id: number;
  faculty_id: string;
  first_name: string;
  last_name: string;
  department: string;
  specialty: string;
  status: string;
  course_count: number;
  subjects_arr: string[];
}

interface FacultySubject {
  courseId: number;
  code: string;
  name: string;
  credits: number;
  semester: string;
  department: string;
  program: string;
  yearLevel: string;
  enrolledCount: number;
  prelimDone: number;
  midtermDone: number;
  finalDone: number;
  gradeCompletion: number;
}

interface CourseStudent {
  enrollmentId: number;
  studentId: number;
  studentNumber: string;
  firstName: string;
  lastName: string;
  yearLevel: string;
  program: string;
  semester: string;
  enrollmentStatus: string;
  prelim: number | null;
  midterm: number | null;
  final: number | null;
  overall: number | null;
  remarks: string;
  gradeReleased: boolean;
  // edit fields
  editPrelim: string;
  editMidterm: string;
  editFinal: string;
  savingPrelim: boolean;
  savingMidterm: boolean;
  savingFinal: boolean;
}

@Component({
  selector: 'app-grading',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './grading.html',
  styleUrl: './grading.css',
})
export class Grading implements OnInit {
  private gradesApi = environment.gradesApi;

  get adminId(): number {
    try {
      const u = sessionStorage.getItem('currentUser') ?? '{}';
      return JSON.parse(u).id ?? 0;
    } catch { return 0; }
  }

  constructor(private http: HttpClient, private cdr: ChangeDetectorRef) {}

  // Navigation
  step: Step = 'faculty';
  selectedFaculty: Faculty | null = null;
  selectedSubject: FacultySubject | null = null;

  // Faculty list
  faculty: Faculty[] = [];
  isLoadingFaculty = false;
  facultySearch = '';

  // Subject list
  subjects: FacultySubject[] = [];
  isLoadingSubjects = false;

  // Student grade list
  students: CourseStudent[] = [];
  isLoadingStudents = false;
  isSubmitting = false;

  toast = { show: false, type: 'success' as 'success' | 'error', message: '' };

  ngOnInit(): void {
    this.loadFaculty();
  }

  /* ─── Faculty ─────────────────────────────────────────── */

  loadFaculty(): void {
    this.isLoadingFaculty = true;
    this.http.get<any>(`${this.gradesApi}?action=admin_get_faculty`).subscribe({
      next: (res) => {
        this.isLoadingFaculty = false;
        this.faculty = res.success ? (res.faculty || []) : [];
        this.cdr.detectChanges();
      },
      error: () => { this.isLoadingFaculty = false; this.cdr.detectChanges(); }
    });
  }

  get filteredFaculty(): Faculty[] {
    const q = this.facultySearch.toLowerCase();
    if (!q) return this.faculty;
    return this.faculty.filter(f =>
      f.first_name.toLowerCase().includes(q) ||
      f.last_name.toLowerCase().includes(q) ||
      f.department.toLowerCase().includes(q) ||
      f.faculty_id.toLowerCase().includes(q)
    );
  }

  selectFaculty(f: Faculty): void {
    this.selectedFaculty = f;
    this.step = 'subjects';
    this.subjects = [];
    this.isLoadingSubjects = true;

    this.http.get<any>(
      `${this.gradesApi}?action=admin_get_faculty_subjects&faculty_id=${f.id}`
    ).subscribe({
      next: (res) => {
        this.isLoadingSubjects = false;
        this.subjects = res.success ? (res.subjects || []) : [];
        this.cdr.detectChanges();
      },
      error: () => { this.isLoadingSubjects = false; this.cdr.detectChanges(); }
    });
  }

  /* ─── Subjects ────────────────────────────────────────── */

  selectSubject(sub: FacultySubject): void {
    this.selectedSubject = sub;
    this.step = 'students';
    this.students = [];
    this.isLoadingStudents = true;

    this.http.get<any>(
      `${this.gradesApi}?action=admin_get_course_students&course_id=${sub.courseId}`
    ).subscribe({
      next: (res) => {
        this.isLoadingStudents = false;
        if (res.success) {
          this.students = (res.students || []).map((s: any) => ({
            ...s,
            editPrelim:  s.prelim  !== null ? String(s.prelim)  : '',
            editMidterm: s.midterm !== null ? String(s.midterm) : '',
            editFinal:   s.final   !== null ? String(s.final)   : '',
            savingPrelim: false, savingMidterm: false, savingFinal: false,
          }));
        }
        this.cdr.detectChanges();
      },
      error: () => { this.isLoadingStudents = false; this.cdr.detectChanges(); }
    });
  }

  /* ─── Grade Saving ────────────────────────────────────── */

  saveGrade(student: CourseStudent, term: 'Prelim' | 'Midterm' | 'Final'): void {
    const rawVal = term === 'Prelim' ? student.editPrelim
                 : term === 'Midterm' ? student.editMidterm : student.editFinal;
    const grade = rawVal !== '' ? parseFloat(rawVal) : null;

    if (grade !== null && (isNaN(grade) || grade < 1.0 || grade > 5.0)) {
      this.showToast('error', 'Grade must be between 1.00 and 5.00'); return;
    }

    const savingKey = ('saving' + term) as 'savingPrelim' | 'savingMidterm' | 'savingFinal';
    student[savingKey] = true;

    this.http.post<any>(`${this.gradesApi}?action=admin_save_grade`, {
      enrollment_id: student.enrollmentId,
      student_id:    student.studentId,
      course_id:     this.selectedSubject!.courseId,
      term, grade,
      submitted_by: this.adminId,
    }).subscribe({
      next: (res) => {
        student[savingKey] = false;
        if (res.success) {
          if (term === 'Prelim')  student.prelim  = grade;
          if (term === 'Midterm') student.midterm = grade;
          if (term === 'Final')   student.final   = grade;
          this._recompute(student);
          this._updateSubjectCompletion();
          this.showToast('success', `${student.lastName}, ${student.firstName} — ${term} saved ✓`);
        } else {
          this.showToast('error', res.message || 'Save failed');
        }
        this.cdr.detectChanges();
      },
      error: () => {
        student[savingKey] = false;
        this.showToast('error', 'Server error. Please try again.');
        this.cdr.detectChanges();
      }
    });
  }

  /* ─── Submit to Registrar ─────────────────────────────── */

  submitToRegistrar(): void {
    if (!this.selectedSubject) return;
    const hasGrades = this.students.some(s =>
      s.prelim !== null || s.midterm !== null || s.final !== null
    );
    if (!hasGrades) {
      this.showToast('error', 'No grades to submit. Enter at least one grade first.');
      return;
    }

    this.isSubmitting = true;
    this.http.post<any>(`${this.gradesApi}?action=admin_submit_to_registrar`, {
      course_id: this.selectedSubject.courseId,
      submitted_by: this.adminId,
    }).subscribe({
      next: (res) => {
        this.isSubmitting = false;
        if (res.success) {
          this.showToast('success', `✅ ${res.message}`);
          // reload students to reflect released status
          this.selectSubject(this.selectedSubject!);
        } else {
          this.showToast('error', res.message || 'Submission failed');
        }
        this.cdr.detectChanges();
      },
      error: () => {
        this.isSubmitting = false;
        this.showToast('error', 'Server error during submission.');
        this.cdr.detectChanges();
      }
    });
  }

  /* ─── Navigation ──────────────────────────────────────── */

  goToFaculty(): void {
    this.step = 'faculty';
    this.selectedFaculty = null;
    this.selectedSubject = null;
    this.subjects = [];
    this.students = [];
    this.loadFaculty();
  }

  goToSubjects(): void {
    if (!this.selectedFaculty) return;
    this.step = 'subjects';
    this.selectedSubject = null;
    this.students = [];
    this.selectFaculty(this.selectedFaculty);
  }

  /* ─── Helpers ─────────────────────────────────────────── */

  private _recompute(s: CourseStudent): void {
    const vals = [s.prelim, s.midterm, s.final].filter(v => v !== null) as number[];
    s.overall  = vals.length ? Math.round(vals.reduce((a, b) => a + b, 0) / vals.length * 100) / 100 : null;
    s.remarks  = s.final !== null ? (s.overall !== null && s.overall <= 3.0 ? 'Passed' : 'Failed') : 'In Progress';
  }

  private _updateSubjectCompletion(): void {
    if (!this.selectedSubject) return;
    const total = this.students.length;
    if (!total) return;
    this.selectedSubject.prelimDone  = this.students.filter(s => s.prelim  !== null).length;
    this.selectedSubject.midtermDone = this.students.filter(s => s.midterm !== null).length;
    this.selectedSubject.finalDone   = this.students.filter(s => s.final   !== null).length;
    const done = this.selectedSubject.prelimDone + this.selectedSubject.midtermDone + this.selectedSubject.finalDone;
    this.selectedSubject.gradeCompletion = Math.round(done / (total * 3) * 100);
  }

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

  initials(f: string, l: string): string {
    return `${f.charAt(0)}${l.charAt(0)}`.toUpperCase();
  }

  get gradedCount(): number {
    return this.students.filter(s => s.prelim !== null || s.midterm !== null || s.final !== null).length;
  }

  get allFinalGraded(): boolean {
    return this.students.length > 0 && this.students.every(s => s.final !== null);
  }

  showToast(type: 'success' | 'error', message: string): void {
    this.toast = { show: true, type, message };
    setTimeout(() => { this.toast.show = false; this.cdr.detectChanges(); }, 3500);
  }
}