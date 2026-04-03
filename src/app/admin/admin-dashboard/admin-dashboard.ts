import { Component, OnInit, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../environment';

@Component({
  selector: 'app-admin-dashboard',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './admin-dashboard.html',
  styleUrl: './admin-dashboard.css',
})
export class AdminDashboard implements OnInit {
  private adminApi = environment.adminApi;

  isLoading = true;

  // Live stats from API
  totalStudents         = 0;
  enrolledStudents      = 0;
  pendingStudents       = 0;
  inactiveStudents      = 0;
  totalUserAccounts     = 0;
  studentUserAccounts   = 0;
  orphanAccounts        = 0;
  userCountsByRole: Record<string, number> = {};
  byProgram:   { program: string; cnt: number }[] = [];
  byYearLevel: { year_level: string; cnt: number }[] = [];

  // Faculty & courses (loaded separately)
  facultyCount  = 0;
  courseCount   = 0;
  sectionCount  = 0;

  semesterInfo = {
    current: '—',
  };

  constructor(private http: HttpClient, private cdr: ChangeDetectorRef) {}

  ngOnInit(): void {
    this.loadStats();
    this.loadFacultyCount();
    this.loadCourseCounts();
  }

  loadStats(): void {
    this.http.get<any>(`${this.adminApi}?action=get_student_stats`).subscribe({
      next: (res) => {
        if (res.success) {
          this.totalStudents       = res.total        ?? 0;
          this.enrolledStudents    = res.enrolled      ?? 0;
          this.pendingStudents     = res.pending       ?? 0;
          this.inactiveStudents    = res.inactive      ?? 0;
          this.totalUserAccounts   = res.total_user_accounts    ?? 0;
          this.studentUserAccounts = res.student_user_accounts  ?? 0;
          this.orphanAccounts      = res.orphan_accounts        ?? 0;
          this.userCountsByRole    = res.user_counts_by_role    ?? {};
          this.byProgram           = res.byProgram   ?? [];
          this.byYearLevel         = res.byYearLevel ?? [];
        }
        this.isLoading = false;
        this.cdr.detectChanges();
      },
      error: () => { this.isLoading = false; this.cdr.detectChanges(); }
    });
  }

  loadFacultyCount(): void {
    this.http.get<any>(`${this.adminApi}?action=get_faculty`).subscribe({
      next: (res) => {
        if (res.success) this.facultyCount = (res.faculty ?? []).length;
        this.cdr.detectChanges();
      },
      error: () => {}
    });
  }

  loadCourseCounts(): void {
    this.http.get<any>(`${this.adminApi}?action=get_courses`).subscribe({
      next: (res) => {
        if (res.success) this.courseCount = (res.courses ?? []).length;
        this.cdr.detectChanges();
      },
      error: () => {}
    });
  }

  get enrollmentRate(): number {
    if (!this.totalStudents) return 0;
    return Math.round((this.enrolledStudents / this.totalStudents) * 100);
  }

  roleLabel(role: string): string {
    const map: Record<string,string> = {
      student: '👨‍🎓 Students', admin: '🔧 Admins',
      faculty: '👨‍🏫 Faculty', accounting: '💼 Accounting', registrar: '📋 Registrar'
    };
    return map[role] ?? role;
  }
}
