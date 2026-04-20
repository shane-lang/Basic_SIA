import { Component, OnInit, OnDestroy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../environment';
import { PasswordGateService } from '../password-gate/password-gate.service';

interface GradeEntry {
  enrollmentId: number;
  code: string; name: string; credits: number;
  lecUnits: number; labUnits: number; isGeneral: boolean; isLab: boolean;
  instructor: string; semester: string; status: string;
  prelim: number|null; midterm: number|null;
  final: number|null; overall: number|null;
  remarks: string; description: string;
  isReleased: boolean;
}
interface SemesterGWA { semester: string; gwa: number|null; credits: number; }

@Component({
  selector: 'app-grades',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './grades.html',
  styleUrls: ['./grades.css']
})
export class Grades implements OnInit, OnDestroy {
  private apiUrl = environment.gradesApi;
  private param  = '';
  constructor(private http: HttpClient, private cdr: ChangeDetectorRef, private gate: PasswordGateService) {}

  isLoading = true; error = '';
  // ── Password gate inactivity lock (5 min) ─────────────────────────────────
  _locked          = true;
  private _lockTimer: any = null;
  private readonly _LOCK_MS = 300000;  // 5 minutes

  _startLockTimer(): void {
    this._clearLockTimer();
    this._lockTimer = setTimeout(() => {
      this._locked = true;
      // Clear the password-gate cache so re-navigating here after inactivity
      // requires the student to re-enter their password.
      sessionStorage.removeItem('pgv_ts_grades');
      this.cdr.detectChanges();
    }, this._LOCK_MS);
  }

  _clearLockTimer(): void {
    if (this._lockTimer) { clearTimeout(this._lockTimer); this._lockTimer = null; }
  }

  resetLockTimer(): void {
    if (!this._locked) this._startLockTimer();
  }


  grades: GradeEntry[] = []; semesters: string[] = [];
  semesterGWA: SemesterGWA[] = [];
  selectedSemester = '';
  currentGWA: number|null = null; overallGWA: number|null = null;
  academicStatus = 'No grades yet'; totalCredits = 0;

  // SHS students use school-year terms, not college semesters.
  // Sourced from sessionStorage (set by enrollment.ts after student context loads).
  get isSHS(): boolean {
    return (sessionStorage.getItem('studentCategory') ?? '').toUpperCase() === 'SHS';
  }

  async ngOnInit(): Promise<void> {
    const stored = sessionStorage.getItem('currentUser');
    if (!stored) { this.error = 'Not logged in.'; this.isLoading = false; return; }

    // ── Password gate: student must confirm identity before grades are shown ──
    // NOTE: Do NOT clear pgv_ts_grades here — if the student already verified
    // within the last 5 minutes (inactivity window), they should not be asked again.
    // The cache is only cleared when the inactivity timer fires (_startLockTimer).
    const verified = await this.gate.requirePassword('Grades');
    if (!verified) {
      this.error = 'Password verification is required to view your grades.';
      this.isLoading = false;
      this.cdr.detectChanges();
      return;
    }
    this._locked = false;
    this._startLockTimer();

    const dbId = sessionStorage.getItem('studentDbId');
    const user = JSON.parse(stored);
    this.param = dbId ? `student_id=${dbId}` : `user_id=${user.id}`;
    this.loadSemesters();
    this.loadSummary();
  }

  ngOnDestroy(): void {
    this._clearLockTimer();  // stop JS timer; lock state intentionally preserved across tabs
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
    this.http.get<any>(`${this.apiUrl}?action=get_released_grades&${this.param}${sp}`).subscribe({
      next: (res) => {
        this.isLoading = false;
        if (res.success) {
          this.grades = (res.grades ?? []).map((g: any) => ({
            ...g,
            isReleased: true, // get_released_grades only returns released grades
          }));
          this.currentGWA = res.gwa ?? null;
          this.totalCredits = res.totalCredits ?? 0;
        }
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