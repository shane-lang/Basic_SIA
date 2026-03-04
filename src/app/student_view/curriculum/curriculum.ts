import { Component, OnInit, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { HttpClient } from '@angular/common/http';

interface Course {
  courseId: number;
  code: string;
  name: string;
  credits: number;
  lecUnits: number;
  labUnits: number;
  semester: string;
  yearLevel: string;
  description: string;
  status: 'enrolled' | 'completed' | 'failed' | 'not_taken' | 'credited';
  grade: string | null;
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
  private enrollApi = 'http://localhost/sia-api/enrollment.php';

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

  private getHeaders() {
    const token = sessionStorage.getItem('token') ?? '';
    return { headers: { Authorization: `Bearer ${token}` } };
  }

  constructor(private http: HttpClient, private cdr: ChangeDetectorRef) {}

  ngOnInit(): void {
    const dbId = sessionStorage.getItem('studentDbId');
    if (dbId) this.studentId = parseInt(dbId, 10);

    const user = sessionStorage.getItem('currentUser');
    if (user) {
      const u = JSON.parse(user);
      this.program   = u.program   ?? '';
      this.yearLevel = u.year_level ?? '';
    }

    this.load();
  }

  load(): void {
    this.isLoading = true;
    this.http.get<any>(
      `${this.enrollApi}?action=get_curriculum&student_id=${this.studentId}`,
      this.getHeaders()
    ).subscribe({
      next: (res) => {
        this.isLoading = false;
        if (res.success) {
          this.program   = res.program   || this.program;
          this.yearGroups = res.yearGroups || [];
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
}