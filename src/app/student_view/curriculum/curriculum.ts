import { Component, OnInit, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../environment';

interface Course {
  courseId: number;
  code: string;
  name: string;
  credits: number;
  lecUnits: number;
  labUnits: number;
  isGeneral: boolean;
  isLab: boolean;
  semester: string;
  yearLevel: string;
  description: string;
  status: 'enrolled' | 'completed' | 'failed' | 'not_taken' | 'credited';
  grade: number | null;
  prerequisiteId:   number | null;
  prerequisiteCode: string | null;
  prerequisiteName: string | null;
}

interface SemesterGroup {
  semester: string;
  courses: Course[];
  totalUnits: number;
  completedUnits: number;
}

interface YearGroup {
  yearLevel: string;
  semesters: SemesterGroup[];
  totalUnits: number;
  completedUnits: number;
}

@Component({
  selector: 'app-curriculum',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './curriculum.html',
  styleUrl: './curriculum.css'
})
export class Curriculum implements OnInit {
  private enrollApi = environment.enrollApi;

  studentId   = 0;
  program     = '';
  yearLevel   = '';
  isLoading   = true;
  error       = '';

  yearGroups: YearGroup[] = [];

  get totalUnits()     { return this.yearGroups.reduce((s, y) => s + y.totalUnits, 0); }
  get completedUnits() { return this.yearGroups.reduce((s, y) => s + y.completedUnits, 0); }
  get remainingUnits() { return Math.max(0, this.totalUnits - this.completedUnits); }
  get progressPct()    { return this.totalUnits > 0 ? Math.round((this.completedUnits / this.totalUnits) * 100) : 0; }

  get totalSubjects()     { return this.yearGroups.flatMap(y => y.semesters.flatMap(s => s.courses)).length; }
  get completedSubjects() { return this.yearGroups.flatMap(y => y.semesters.flatMap(s => s.courses)).filter(c => c.status === 'completed').length; }
  constructor(private http: HttpClient, private cdr: ChangeDetectorRef) {}

  ngOnInit(): void {
    // BUG-CURRICULUM-01 FIX: studentDbId is written to sessionStorage by
    // dashboard.ts and payment-schedule.ts after get_student_context resolves.
    // If the student navigates directly to /curriculum before those run,
    // studentDbId will be 0 and the API returns student_id=0 → no curriculum.
    // Fix: also try user_id from currentUser as a fallback so the PHP side
    // can resolve students.id via the user_id FK lookup.
    const storedDbId = sessionStorage.getItem('studentDbId');
    this.studentId   = storedDbId ? parseInt(storedDbId, 10) : 0;

    const userRaw = sessionStorage.getItem('currentUser');
    if (userRaw) {
      const u = JSON.parse(userRaw);
      this.program   = u.program    ?? '';
      this.yearLevel = u.year_level ?? '';
      // Store userId as fallback in case studentDbId is not yet available
      if (!this.studentId && u.id) {
        this.userId = u.id;
      }
    }

    this.load();
  }

  private userId = 0; // fallback when studentDbId is not yet in sessionStorage

  load(): void {
    this.isLoading = true;

    // Use student_id (DB PK) if available, else fall back to user_id so
    // enrollment.php can resolve it via students.user_id (same lookup as profile).
    const apiParam = this.studentId > 0
      ? `student_id=${this.studentId}`
      : `user_id=${this.userId}`;

    this.http.get<any>(
      `${this.enrollApi}?action=get_curriculum&${apiParam}`
    ).subscribe({
      next: (res) => {
        this.isLoading = false;
        if (res.success) {
          this.program    = res.program    || this.program;
          this.yearGroups = res.yearGroups || [];
          // Cache studentDbId if it came back via user_id lookup
          if (!this.studentId && res.studentDbId) {
            this.studentId = res.studentDbId;
            sessionStorage.setItem('studentDbId', String(res.studentDbId));
          }
        } else {
          this.error = res.message || 'Failed to load curriculum.';
        }
        this.cdr.detectChanges();
      },
      error: () => {
        this.isLoading = false;
        this.error = 'Cannot connect to server.';
        this.cdr.detectChanges();
      }
    });
  }

  statusLabel(s: string): string {
    return { completed: 'Completed', enrolled: 'Enrolled', failed: 'Failed', not_taken: 'Not Yet Taken', credited: '✓ Credited' }[s] || s;
  }

  // Returns true when the prerequisite course has been completed (passed)
  isPrereqPassed(course: Course): boolean {
    if (!course.prerequisiteId) return true;
    const allCourses = this.yearGroups.flatMap(y => y.semesters.flatMap(s => s.courses));
    const prereq = allCourses.find(c => c.courseId === course.prerequisiteId);
    return prereq?.status === 'completed';
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