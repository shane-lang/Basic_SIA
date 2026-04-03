import { Component, OnInit, OnChanges, SimpleChanges, ChangeDetectorRef, Input } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { Router } from '@angular/router';
import { environment } from '../../environment';

interface SemesterHistory {
  semester: string;
  subjects: SubjectRecord[];
  total_units: number;
  graded_units: number;
  gpa: number | null;
  subject_count: number;
  passed: number;
  failed: number;
  dropped: number;
}

interface SubjectRecord {
  enrollment_id: number;
  enrollment_status: string;
  enrollment_date: string;
  semester: string;
  course_id: number;
  course_code: string;
  course_name: string;
  units: number;
  lec_units: number;
  lab_units: number;
  is_lab: number;
  department: string;
  final_grade: number | string | null;
  term: string;
}

interface FeeLogEntry {
  id: number;
  course_code: string;
  course_name: string;
  action: string;
  subject_type: string;
  course_category: string;
  units: number;
  total_impact: number;
  added_by_email: string;
  reason: string;
  created_at: string;
}

interface ScholarshipRecord {
  id: number;
  scholar_type: string;
  grantor: string;
  scholarship_amount: number;
  semester: string;
  is_active: number;
  granted_by_email: string;
  revoked_at: string | null;
  revoke_reason: string | null;
  created_at: string;
}

@Component({
  selector: 'app-enrollment-history',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './enrollment-history.html',
  styleUrl: './enrollment-history.css',
})
export class EnrollmentHistoryComponent implements OnInit, OnChanges {
  @Input() studentId = 0;
  @Input() embedded  = false;  // true = used inside masterlist, hides own back button

  private registrarApi = environment.registrarApi;

  isLoading   = false;
  errorMsg    = '';
  student:    any = null;
  history:    SemesterHistory[] = [];
  feeLog:     FeeLogEntry[] = [];
  scholarships: ScholarshipRecord[] = [];

  semesterCount  = 0;
  totalSubjects  = 0;

  expandedSemesters = new Set<string>();
  activeTab: 'history' | 'feelog' | 'scholarships' = 'history';

  constructor(
    private http: HttpClient,
    private cdr: ChangeDetectorRef,
    private router: Router,
  ) {}

  ngOnChanges(changes: SimpleChanges): void {
    // When used embedded inside masterlist, studentId can change when
    // the user clicks "History" on a different student — reload data.
    if (changes['studentId'] && !changes['studentId'].firstChange) {
      const newId = changes['studentId'].currentValue;
      if (newId && newId !== 0) {
        this.student      = null;
        this.history      = [];
        this.feeLog       = [];
        this.scholarships = [];
        this.expandedSemesters.clear();
        this.activeTab    = 'history';
        this.errorMsg     = '';
        this.load();
      }
    }
  }

  ngOnInit(): void {
    if (!this.studentId) {
      const raw = sessionStorage.getItem('selectedStudent');
      if (raw) {
        try { this.studentId = JSON.parse(raw).id ?? JSON.parse(raw).studentId ?? 0; }
        catch { }
      }
    }
    if (this.studentId) {
      this.load();
    } else {
      // No student in context — redirect back to masterlist instead of blank error page
      this.router.navigate(['/registrar/masterlist']);
    }
  }

  load(): void {
    this.isLoading = true;
    this.errorMsg  = '';
    this.http.get<any>(`${this.registrarApi}?action=get_enrollment_history&student_id=${this.studentId}`).subscribe({
      next: (res) => {
        this.isLoading = false;
        if (res.success) {
          this.student      = res.student     ?? null;
          this.history      = res.history     ?? [];
          this.feeLog       = res.fee_log     ?? [];
          this.scholarships = res.scholarships ?? [];
          this.semesterCount = res.semester_count ?? this.history.length;
          this.totalSubjects = res.total_subjects ?? 0;
          // Auto-expand current/latest semester
          if (this.history.length > 0) {
            this.expandedSemesters.add(this.history[0].semester);
          }
        } else {
          this.errorMsg = res.message || 'Failed to load enrollment history.';
        }
        this.cdr.detectChanges();
      },
      error: () => {
        this.isLoading = false;
        this.errorMsg  = 'Server error loading enrollment history.';
        this.cdr.detectChanges();
      }
    });
  }

  toggleSemester(sem: string): void {
    if (this.expandedSemesters.has(sem)) this.expandedSemesters.delete(sem);
    else this.expandedSemesters.add(sem);
    this.cdr.detectChanges();
  }

  isExpanded(sem: string): boolean {
    return this.expandedSemesters.has(sem);
  }

  // FIX HISTORY-05: MySQL DECIMAL fields can arrive as strings even when the
  // PHP layer casts them. Coerce to number here so .toFixed() never throws.
  private toNum(g: number | string | null | undefined): number | null {
    if (g === null || g === undefined || g === '') return null;
    const n = typeof g === 'number' ? g : parseFloat(g as string);
    return isNaN(n) ? null : n;
  }

  gradeColor(g: number | string | null): string {
    const n = this.toNum(g);
    if (n === null) return '#6b7280';
    if (n <= 3.0)  return '#16a34a';
    if (n >= 5.0)  return '#dc2626';
    return '#f59e0b';
  }

  gradeLabel(g: number | string | null): string {
    const n = this.toNum(g);
    if (n === null) return 'N/A';
    if (n <= 3.0)  return n.toFixed(2) + ' ✓';
    if (n >= 5.0)  return n.toFixed(2) + ' ✗';
    if (n === 4.0) return '4.0 INC';
    return n.toFixed(2);
  }

  statusColor(s: string): string {
    const m: Record<string,string> = {
      Enrolled:  '#16a34a',
      Pending:   '#f59e0b',
      Dropped:   '#dc2626',
      Completed: '#6366f1',
      Failed:    '#dc2626',
    };
    return m[s] ?? '#6b7280';
  }

  fmt(n: number): string {
    return (n ?? 0).toLocaleString('en-PH', { minimumFractionDigits: 2 });
  }

  fmtDate(d: string | null): string {
    if (!d) return '—';
    return new Date(d).toLocaleDateString('en-PH', { year:'numeric', month:'short', day:'numeric' });
  }

  goBack(): void {
    this.router.navigate(['/registrar/masterlist']);
  }

  get totalUnitsAllSems(): number {
    return this.history.reduce((a, s) => a + s.total_units, 0);
  }

  get overallGPA(): number | null {
    const graded = this.history.filter(s => s.gpa !== null);
    if (!graded.length) return null;
    return +(graded.reduce((a, s) => a + (s.gpa ?? 0), 0) / graded.length).toFixed(4);
  }
}