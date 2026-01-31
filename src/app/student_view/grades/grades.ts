import { Component, OnInit, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';

interface GradeEntry {
  enrollmentId: number;
  code: string; name: string; credits: number;
  instructor: string; semester: string; status: string;
  prelim: number|null; midterm: number|null;
  final: number|null; overall: number|null;
  remarks: string; description: string;
}
interface SemesterGWA { semester: string; gwa: number|null; credits: number; }

@Component({
  selector: 'app-grades',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './grades.html',
  styleUrls: ['./grades.css']
})
export class Grades implements OnInit {
  private apiUrl = 'http://localhost/sia-api/grades.php';
  private param  = '';

  constructor(private http: HttpClient, private cdr: ChangeDetectorRef) {}

  isLoading = true; error = '';
  grades: GradeEntry[] = []; semesters: string[] = [];
  semesterGWA: SemesterGWA[] = [];
  selectedSemester = '';
  currentGWA: number|null = null; overallGWA: number|null = null;
  academicStatus = 'No grades yet'; totalCredits = 0;

  ngOnInit(): void {
    const stored = localStorage.getItem('currentUser');
    if (!stored) { this.error = 'Not logged in.'; this.isLoading = false; return; }
    const dbId = localStorage.getItem('studentDbId');
    const user = JSON.parse(stored);
    this.param = dbId ? `student_id=${dbId}` : `user_id=${user.id}`;
    this.loadSemesters();
    this.loadSummary();
  }

  loadSemesters(): void {
    this.http.get<any>(`${this.apiUrl}?action=get_semesters&${this.param}`).subscribe({
      next: (res) => {
        if (res.success) {
          this.semesters = res.semesters;
          this.selectedSemester = res.semesters[0] ?? '';
          this.loadGrades();
        } else { this.isLoading = false; this.error = res.message; }
        this.cdr.detectChanges();
      },
      error: () => { this.isLoading = false; this.error = 'Cannot connect to server.'; this.cdr.detectChanges(); }
    });
  }

  loadGrades(): void {
    this.isLoading = true;
    const sp = this.selectedSemester ? `&semester=${encodeURIComponent(this.selectedSemester)}` : '';
    this.http.get<any>(`${this.apiUrl}?action=get_grades&${this.param}${sp}`).subscribe({
      next: (res) => {
        this.isLoading = false;
        if (res.success) { this.grades = res.grades ?? []; this.currentGWA = res.gwa ?? null; this.totalCredits = res.totalCredits ?? 0; }
        else { this.error = res.message; }
        this.cdr.detectChanges();
      },
      error: () => { this.isLoading = false; this.error = 'Cannot connect to server.'; this.cdr.detectChanges(); }
    });
  }

  loadSummary(): void {
    this.http.get<any>(`${this.apiUrl}?action=get_grade_summary&${this.param}`).subscribe({
      next: (res) => {
        if (res.success) { this.overallGWA = res.overallGWA ?? null; this.academicStatus = res.academicStatus ?? 'No grades yet'; this.semesterGWA = res.semesterGWA ?? []; }
        this.cdr.detectChanges();
      }
    });
  }

  onSemesterChange(): void { this.loadGrades(); }

  gradeClass(g: number|null): string {
    if (g===null) return 'g-none';
    if (g<=1.5)   return 'g-excel';
    if (g<=2.0)   return 'g-good';
    if (g<=3.0)   return 'g-pass';
    return 'g-fail';
  }
  gradeColor(g: number|null): string {
    if (g===null) return '#a0aec0';
    if (g<=1.5)   return '#16a34a';
    if (g<=2.0)   return '#2563eb';
    if (g<=3.0)   return '#d97706';
    return '#dc2626';
  }
  fmtGrade(g: number|null): string { return g!==null ? g.toFixed(2) : '—'; }
  statusClass(gwa: number|null): string {
    if (!gwa) return '';
    if (gwa<=1.5) return 'st-excel';
    if (gwa<=2.0) return 'st-good';
    if (gwa<=3.0) return 'st-pass';
    return 'st-fail';
  }
  barWidth(gwa: number|null): number {
    if (gwa===null) return 0;
    return Math.min(Math.max(((5-gwa)/4)*100, 0), 100);
  }
  get passedCount():     number { return this.grades.filter(g=>g.remarks==='Passed').length; }
  get inProgressCount(): number { return this.grades.filter(g=>g.remarks==='In Progress').length; }
  get failedCount():     number { return this.grades.filter(g=>g.remarks==='Failed').length; }
}