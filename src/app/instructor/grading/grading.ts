import { Component, OnInit, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient, HttpHeaders } from '@angular/common/http';
import { ActivatedRoute } from '@angular/router';

type Step = 'courses' | 'students';

interface Course {
  id: number;
  code: string;
  name: string;
  credits: number;
  semester: string;
  program: string;
  yearLevel: string;
  enrolledCount: number;
  gradeCompletion: number;
  submittedCount: number;
}

interface Student {
  enrollmentId: number;
  studentId: number;
  studentNumber: string;
  firstName: string;
  lastName: string;
  yearLevel: string;
  program: string;
  studentCategory: string;
  prelim: number | null;
  midterm: number | null;
  final: number | null;
  overall: number | null;
  remarks: string;
  gradeSubmitted: boolean;
  gradeReleased: boolean;
  editPrelim: string;
  editMidterm: string;
  editFinal: string;
  savingPrelim: boolean;
  savingMidterm: boolean;
  savingFinal: boolean;
}

@Component({
  selector: 'app-instructor-grading',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './grading.html',
  styleUrl: './grading.css',
})
export class InstructorGrading implements OnInit {
  private api = 'http://localhost/sia-api/faculty.php';

  private getHeaders() {
    const token = sessionStorage.getItem('token') || localStorage.getItem('token') || '';
    return { headers: new HttpHeaders({ Authorization: `Bearer ${token}` }) };
  }

  constructor(
    private http: HttpClient,
    private cdr: ChangeDetectorRef,
    private route: ActivatedRoute
  ) {}

  step: Step = 'courses';

  // Courses
  courses: Course[] = [];
  isLoadingCourses = false;
  courseSearch = '';
  selectedCourse: Course | null = null;

  // Students
  students: Student[] = [];
  isLoadingStudents = false;
  isSubmitting = false;

  toast = { show: false, type: 'success' as 'success' | 'error', message: '' };

  ngOnInit(): void {
    this.loadCourses();
    // If navigated from courses page with a courseId, auto-select
    this.route.queryParams.subscribe(params => {
      if (params['courseId']) {
        const id = parseInt(params['courseId']);
        const trySelect = () => {
          const found = this.courses.find(c => c.id === id);
          if (found) this.selectCourse(found);
        };
        setTimeout(trySelect, 800);
      }
    });
  }

  /* ─── Courses ─────────────────────────────────────── */
  loadCourses(): void {
    this.isLoadingCourses = true;
    this.http.get<any>(`${this.api}?action=get_my_courses`, this.getHeaders()).subscribe({
      next: (res) => {
        this.isLoadingCourses = false;
        this.courses = res.success ? (res.courses || []) : [];
        this.cdr.detectChanges();
        // Re-try auto-select if courseId param present
        this.route.queryParams.subscribe(params => {
          if (params['courseId']) {
            const found = this.courses.find(c => c.id === parseInt(params['courseId']));
            if (found && this.step === 'courses') this.selectCourse(found);
          }
        });
      },
      error: () => { this.isLoadingCourses = false; this.cdr.detectChanges(); }
    });
  }

  get filteredCourses(): Course[] {
    const q = this.courseSearch.toLowerCase();
    if (!q) return this.courses;
    return this.courses.filter(c =>
      c.code.toLowerCase().includes(q) ||
      c.name.toLowerCase().includes(q) ||
      c.program.toLowerCase().includes(q)
    );
  }

  selectCourse(course: Course): void {
    this.selectedCourse = course;
    this.step = 'students';
    this.students = [];
    this.isLoadingStudents = true;

    this.http.get<any>(
      `${this.api}?action=get_course_students&course_id=${course.id}`,
      this.getHeaders()
    ).subscribe({
      next: (res) => {
        this.isLoadingStudents = false;
        if (res.success) {
          this.students = (res.students || []).map((s: any) => ({
            ...s,
            editPrelim:   s.prelim  !== null ? String(s.prelim)  : '',
            editMidterm:  s.midterm !== null ? String(s.midterm) : '',
            editFinal:    s.final   !== null ? String(s.final)   : '',
            savingPrelim:  false,
            savingMidterm: false,
            savingFinal:   false,
          }));
        }
        this.cdr.detectChanges();
      },
      error: () => { this.isLoadingStudents = false; this.cdr.detectChanges(); }
    });
  }

  goToCourses(): void {
    this.step = 'courses';
    this.selectedCourse = null;
    this.students = [];
    this.cdr.detectChanges();
  }

  /* ─── Grade Saving ────────────────────────────────── */
  saveGrade(student: Student, term: 'Prelim' | 'Midterm' | 'Final'): void {
    const editKey   = `edit${term}` as keyof Student;
    const savingKey = `saving${term}` as keyof Student;
    const raw = String((student as any)[editKey]).trim();

    if (raw === '') return;
    const grade = parseFloat(raw);
    if (isNaN(grade) || grade < 1 || grade > 5) {
      this.showToast('error', 'Grade must be between 1.00 and 5.00');
      return;
    }

    (student as any)[savingKey] = true;
    this.cdr.detectChanges();

    this.http.post<any>(`${this.api}?action=save_grade`, {
      enrollment_id: student.enrollmentId,
      student_id:    student.studentId,
      course_id:     this.selectedCourse!.id,
      term,
      grade,
    }, this.getHeaders()).subscribe({
      next: (res) => {
        (student as any)[savingKey] = false;
        if (res.success) {
          if (term === 'Prelim')   student.prelim  = grade;
          if (term === 'Midterm')  student.midterm = grade;
          if (term === 'Final')    student.final   = grade;
          // Recalc overall
          const vals = [student.prelim, student.midterm, student.final].filter(v => v !== null) as number[];
          student.overall  = vals.length ? parseFloat((vals.reduce((a,b)=>a+b,0)/vals.length).toFixed(2)) : null;
          student.remarks  = student.final !== null ? (student.overall! <= 3.0 ? 'Passed' : 'Failed') : 'In Progress';
        } else {
          this.showToast('error', res.message || 'Save failed');
        }
        this.cdr.detectChanges();
      },
      error: () => { (student as any)[savingKey] = false; this.showToast('error','Server error'); this.cdr.detectChanges(); }
    });
  }

  /* ─── Submit to Registrar ─────────────────────────── */
  submitToRegistrar(): void {
    if (!this.selectedCourse) return;
    const hasGrades = this.students.some(s => s.prelim !== null || s.midterm !== null || s.final !== null);
    if (!hasGrades) { this.showToast('error','No grades to submit yet.'); return; }

    this.isSubmitting = true;
    this.http.post<any>(`${this.api}?action=submit_to_registrar`,
      { course_id: this.selectedCourse.id },
      this.getHeaders()
    ).subscribe({
      next: (res) => {
        this.isSubmitting = false;
        if (res.success) {
          this.students.forEach(s => { if (s.prelim !== null || s.midterm !== null || s.final !== null) s.gradeSubmitted = true; });
          this.showToast('success', res.message || 'Grades submitted to Registrar!');
        } else {
          this.showToast('error', res.message || 'Submit failed.');
        }
        this.cdr.detectChanges();
      },
      error: () => { this.isSubmitting = false; this.showToast('error','Server error'); this.cdr.detectChanges(); }
    });
  }

  get hasUnsubmitted(): boolean {
    return this.students.some(s => !s.gradeSubmitted && !s.gradeReleased && (s.prelim !== null || s.midterm !== null || s.final !== null));
  }

  gradeClass(g: number | null): string {
    if (g === null) return '';
    if (g <= 1.5) return 'grade-excellent';
    if (g <= 2.5) return 'grade-good';
    if (g <= 3.0) return 'grade-pass';
    return 'grade-fail';
  }

  initials(first: string, last: string): string {
    return ((first[0] ?? '') + (last[0] ?? '')).toUpperCase();
  }

  showToast(type: 'success' | 'error', message: string): void {
    this.toast = { show:true, type, message };
    setTimeout(() => { this.toast.show = false; this.cdr.detectChanges(); }, 4000);
  }

  completionColor(pct: number): string {
    if (pct === 100) return '#16a34a';
    if (pct >= 50)  return '#d97706';
    return '#dc2626';
  }
}