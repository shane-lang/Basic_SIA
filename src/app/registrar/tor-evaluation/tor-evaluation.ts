import { Component, OnInit, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { Router } from '@angular/router';
import { map, catchError } from 'rxjs/operators';
import { of } from 'rxjs';

interface PendingTOR {
  evalId:             number;
  studentId:          number;
  studentNumber:      string;
  firstName:          string;
  lastName:           string;
  program:            string;
  yearLevel:          string;
  lastSchoolAttended: string;
  studentType:        string;
  status:             'Pending' | 'Evaluated' | 'Rejected';
  creditedUnits:      number;
  approvedUnits:      number;
  creditedSubjects:   CreditedSubject[];
  registrarNotes:     string;
  submittedAt:        string;
  programUnits:       number;
  torFileUrl:         string;
}

interface CurriculumCourse {
  courseId:     number;
  code:         string;
  name:         string;
  credits:      number;
  yearLevel:    string;
  semester:     string;
  description:  string;
  isCredited:   boolean;
  selected:     boolean;
  status?:      'Credited' | 'Completed' | 'Enrolled' | 'Pending';
  grade?:       string | null;
  creditedFrom?:string | null;
}

interface CreditedSubject {
  courseId:     number;
  code:         string;
  name:         string;
  credits:      number;
  creditedFrom: string;
}

interface SemesterGroup {
  semester:      string;
  courses:       CurriculumCourse[];
  totalUnits:    number;
  creditedUnits: number;
}
interface YearGroup {
  yearLevel:     string;
  semesters:     SemesterGroup[];
  totalUnits:    number;
  creditedUnits: number;
}

@Component({
  selector: 'app-tor-evaluation',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './tor-evaluation.html',
  styleUrl: './tor-evaluation.css',
})
export class TorEvaluation implements OnInit {
  private registrarApi = 'http://localhost/sia-api/registrar.php';

  
  /** Returns HTTP headers with the auth token. Call this in every API request. */
  private getHeaders() {
    const token = sessionStorage.getItem('token') ?? '';
    return { headers: { Authorization: `Bearer ${token}` } };
  }

  constructor(private http: HttpClient, private router: Router, private cdr: ChangeDetectorRef) {}

  pendingTors:    PendingTOR[] = [];
  evaluatedTors:  PendingTOR[] = [];
  isLoading       = false;
  isEvalLoading   = false;
  activeTab: 'pending' | 'evaluated' = 'pending';
  successMessage  = '';
  errorMessage    = '';

  showModal       = false;
  modalMode:       'evaluate' | 'reject' | 'view' | 'manual' = 'evaluate';
  selectedTor:     PendingTOR | null = null;
  isSubmitting    = false;
  registrarNotes  = '';

  // Manual evaluation entries
  manualSubjects: { code: string; name: string; credits: number }[] = [];
  manualNewCode   = '';
  manualNewName   = '';
  manualNewCredits= 3;

  curriculumCourses: CurriculumCourse[] = [];
  yearGroups:        YearGroup[]        = [];
  isCoursesLoading  = false;
  recomputedFee: any = null;

  get selectedCourses(): CurriculumCourse[] { return this.curriculumCourses.filter(c => c.selected); }
  get totalCreditedUnits(): number { return this.selectedCourses.reduce((s, c) => s + c.credits, 0); }
  get approvedUnits(): number {
    if (!this.selectedTor) return 0;
    return Math.max(0, (this.selectedTor.programUnits || 0) - this.totalCreditedUnits);
  }
  get allSelected(): boolean { return this.curriculumCourses.length > 0 && this.curriculumCourses.every(c => c.selected); }
  get totalProgramUnits(): number { return this.curriculumCourses.reduce((s, c) => s + c.credits, 0); }

  ngOnInit(): void { this.loadPendingTors(); }

  // ── Robust PHP response parser ─────────────────────────────────
  // PHP sometimes prepends notices/warnings before the JSON.
  // This strips everything before the first '{' or '[' so the real
  // JSON can still be parsed. The raw PHP output is shown to the user
  // if parsing fails, making debugging much easier.
  private parsePhpResponse(raw: string): any {
    if (!raw || !raw.trim()) throw new Error('Empty response from server.');
    const firstBrace  = raw.indexOf('{');
    const firstBracket = raw.indexOf('[');
    let start = -1;
    if (firstBrace >= 0 && firstBracket >= 0) start = Math.min(firstBrace, firstBracket);
    else if (firstBrace >= 0) start = firstBrace;
    else if (firstBracket >= 0) start = firstBracket;

    if (start < 0) throw new Error(`No JSON in response. PHP said: ${raw.substring(0, 300)}`);

    const junk = raw.substring(0, start).trim();
    if (junk) console.warn('[TOR] PHP output before JSON (notice/warning):', junk);

    try {
      return JSON.parse(raw.substring(start));
    } catch {
      throw new Error(`JSON parse failed. Raw response: ${raw.substring(0, 400)}`);
    }
  }

  // Safe GET: fetches as text, strips PHP noise, returns parsed object
  private safeGet(url: string) {
    return this.http.get(url, { responseType: 'text' }).pipe(
      map(raw => this.parsePhpResponse(raw)),
      catchError(err => {
        const msg = err?.message || '';
        // Network-level error (XAMPP down, CORS, etc.)
        if (err?.status === 0 || err?.name === 'HttpErrorResponse') {
          return of({ success: false, _networkError: true, message: 'Cannot reach server. Make sure XAMPP is running and Apache is started.' });
        }
        return of({ success: false, _parseError: true, message: msg });
      })
    );
  }

  // Safe POST: same idea but for POST requests
  private safePost(url: string, body: any) {
    return this.http.post(url, body, { responseType: 'text' }).pipe(
      map(raw => this.parsePhpResponse(raw)),
      catchError(err => {
        if (err?.status === 0 || err?.name === 'HttpErrorResponse') {
          return of({ success: false, _networkError: true, message: 'Cannot reach server. Make sure XAMPP is running.' });
        }
        return of({ success: false, _parseError: true, message: err?.message || 'Unknown error' });
      })
    );
  }

  // ── Load pending TOR list ──────────────────────────────────────
  loadPendingTors(): void {
    this.isLoading = true;
    this.errorMessage = '';
    this.safeGet(`${this.registrarApi}?action=get_pending_tor`).subscribe(res => {
      this.isLoading = false;
      if (res.success) {
        this.pendingTors = res.evaluations || [];
      } else {
        this.pendingTors = [];
        // Show the REAL error from PHP, not a generic message
        if (res._networkError) {
          this.errorMessage = res.message;
        } else if (res._parseError) {
          this.errorMessage = `Server error: ${res.message}`;
        } else {
          this.errorMessage = res.message || 'Failed to load TOR evaluations.';
        }
      }
      this.cdr.detectChanges();
    });
  }

  loadEvaluatedTors(): void {
    this.isEvalLoading = true;
    this.safeGet(`${this.registrarApi}?action=get_evaluated_tor`).subscribe(res => {
      this.isEvalLoading = false;
      this.evaluatedTors = res.success ? (res.evaluations || []) : [];
      this.cdr.detectChanges();
    });
  }

  switchTab(tab: 'pending' | 'evaluated'): void {
    this.activeTab = tab;
    if (tab === 'evaluated' && !this.evaluatedTors.length) this.loadEvaluatedTors();
    this.cdr.detectChanges();
  }

  openEvaluate(tor: PendingTOR): void {
    this.selectedTor = tor; this.registrarNotes = ''; this.recomputedFee = null;
    this.curriculumCourses = []; this.yearGroups = [];
    this.modalMode = 'evaluate'; this.showModal = true;
    this.errorMessage = ''; this.successMessage = '';
    this.loadCurriculum(tor.studentId, tor.program);
    this.cdr.detectChanges();
  }

  openReject(tor: PendingTOR): void {
    this.selectedTor = tor; this.registrarNotes = '';
    this.modalMode = 'reject'; this.showModal = true; this.errorMessage = '';
    this.cdr.detectChanges();
  }

  openView(tor: PendingTOR): void {
    this.selectedTor = tor; this.modalMode = 'view'; this.showModal = true;
    this.curriculumCourses = []; this.yearGroups = [];
    this.loadCurriculum(tor.studentId, tor.program);
    this.cdr.detectChanges();
  }

  openManual(tor: PendingTOR): void {
    this.selectedTor = tor; this.registrarNotes = ''; this.recomputedFee = null;
    this.manualSubjects = []; this.manualNewCode = ''; this.manualNewName = ''; this.manualNewCredits = 3;
    this.modalMode = 'manual'; this.showModal = true;
    this.errorMessage = ''; this.successMessage = '';
    this.cdr.detectChanges();
  }

  get manualCreditedUnits(): number { return this.manualSubjects.reduce((s, c) => s + (c.credits || 0), 0); }
  get manualApprovedUnits(): number {
    if (!this.selectedTor) return 0;
    return Math.max(0, (this.selectedTor.programUnits || 0) - this.manualCreditedUnits);
  }

  addManualSubject(): void {
    if (!this.manualNewCode.trim() || !this.manualNewName.trim()) return;
    this.manualSubjects.push({
      code: this.manualNewCode.trim().toUpperCase(),
      name: this.manualNewName.trim(),
      credits: this.manualNewCredits || 3
    });
    this.manualNewCode = ''; this.manualNewName = ''; this.manualNewCredits = 3;
    this.cdr.detectChanges();
  }

  removeManualSubject(i: number): void {
    this.manualSubjects.splice(i, 1); this.cdr.detectChanges();
  }

  submitManualEvaluation(): void {
    if (!this.selectedTor) return;
    if (!this.manualSubjects.length) { this.errorMessage = 'Add at least one subject to credit. Use Reject if none qualify.'; this.cdr.detectChanges(); return; }
    this.isSubmitting = true; this.errorMessage = '';
    const user = JSON.parse(sessionStorage.getItem('currentUser') || '{}');
    const creditedSubjects = this.manualSubjects.map(s => ({
      courseId: 0,
      code: s.code, name: s.name, credits: s.credits,
      creditedFrom: this.selectedTor!.lastSchoolAttended || 'Previous School',
    }));
    this.safePost(`${this.registrarApi}?action=evaluate_tor&eval_id=${this.selectedTor.evalId}&student_id=${this.selectedTor.studentId}`, {
      eval_id: this.selectedTor.evalId, student_id: this.selectedTor.studentId,
      registrar_user_id: user.id || 0, credited_subjects: creditedSubjects,
      registrar_notes: this.registrarNotes, manual: true,
    }).subscribe(res => {
      this.isSubmitting = false;
      if (res.success) {
        this.successMessage = `✅ ${res.creditedUnits} units manually credited. New total: ₱${this.fa(res.newTotal)}.`;
        this.pendingTors = this.pendingTors.filter(t => t.evalId !== this.selectedTor!.evalId);
        this.showModal = false; this.selectedTor = null;
      } else { this.errorMessage = res.message || 'Evaluation failed.'; }
      this.cdr.detectChanges();
    });
  }

  closeModal(): void {
    this.showModal = false; this.selectedTor = null;
    this.curriculumCourses = []; this.yearGroups = [];
    this.manualSubjects = []; this.manualNewCode = ''; this.manualNewName = '';
    this.registrarNotes = ''; this.recomputedFee = null; this.errorMessage = '';
    this.cdr.detectChanges();
  }

  loadCurriculum(studentId: number, program: string): void {
    this.isCoursesLoading = true;
    this.safeGet(
      `${this.registrarApi}?action=get_program_courses&program=${encodeURIComponent(program)}&student_id=${studentId}`
    ).subscribe(res => {
      this.isCoursesLoading = false;
      if (res.success) {
        this.curriculumCourses = (res.courses || []).map((c: any) => ({ ...c, selected: !!c.selected }));
        this.buildYearGroups();
        this.updateFeePreview();
      }
      this.cdr.detectChanges();
    });
  }

  buildYearGroups(): void {
    const yearMap = new Map<string, Map<string, CurriculumCourse[]>>();
    const YEAR_ORDER = ['1st Year','2nd Year','3rd Year','4th Year','5th Year'];
    const SEM_ORDER  = ['1st Semester','2nd Semester','Summer','Midyear'];

    for (const c of this.curriculumCourses) {
      const yr  = c.yearLevel || '1st Year';
      const sem = this.normSem(c.semester);
      if (!yearMap.has(yr))    yearMap.set(yr, new Map());
      const sm = yearMap.get(yr)!;
      if (!sm.has(sem))        sm.set(sem, []);
      sm.get(sem)!.push(c);
    }

    const years = Array.from(yearMap.keys())
      .sort((a, b) => (YEAR_ORDER.indexOf(a) - YEAR_ORDER.indexOf(b)) || a.localeCompare(b));

    this.yearGroups = years.map(yr => {
      const sm = yearMap.get(yr)!;
      const sems = Array.from(sm.keys())
        .sort((a, b) => (SEM_ORDER.indexOf(a) - SEM_ORDER.indexOf(b)) || a.localeCompare(b));

      let yTotal = 0, yCredited = 0;
      const semesters: SemesterGroup[] = sems.map(sem => {
        const courses  = sm.get(sem)!;
        const sTotal   = courses.reduce((s, c) => s + c.credits, 0);
        const sCred    = courses.filter(c => c.selected).reduce((s, c) => s + c.credits, 0);
        yTotal   += sTotal;
        yCredited += sCred;
        return { semester: sem, courses, totalUnits: sTotal, creditedUnits: sCred };
      });
      return { yearLevel: yr, semesters, totalUnits: yTotal, creditedUnits: yCredited };
    });
  }

  normSem(s: string): string {
    if (!s) return '1st Semester';
    const l = s.toLowerCase();
    if (l.includes('summer') || l.includes('mid')) return 'Summer';
    if (l.includes('2nd') || l.includes('second')) return '2nd Semester';
    return '1st Semester';
  }

  toggleAll(checked: boolean): void {
    this.curriculumCourses.forEach(c => c.selected = checked);
    this.buildYearGroups(); this.updateFeePreview(); this.cdr.detectChanges();
  }

  onCourseToggle(): void { this.buildYearGroups(); this.updateFeePreview(); this.cdr.detectChanges(); }

  updateFeePreview(): void {
    if (!this.selectedTor) { this.recomputedFee = null; return; }
    const u = this.approvedUnits > 0 ? this.approvedUnits : (this.selectedTor.programUnits || 0);
    if (!u) { this.recomputedFee = null; return; }
    const tf = u * 650, misc = 6688, reg = 700, lab = u * 1900, energy = u * 21 * 3;
    const sub = tf + misc + reg + lab + energy;
    this.recomputedFee = { units: u, tuitionFee: tf, miscellaneousFee: misc, registrationFee: reg, laboratoryFee: lab, energyFee: energy, subtotal: sub, totalAssessment: sub };
  }

  submitEvaluation(): void {
    if (!this.selectedTor) return;
    if (!this.selectedCourses.length) { this.errorMessage = 'Select at least one subject to credit. Use Reject if none qualify.'; this.cdr.detectChanges(); return; }
    this.isSubmitting = true; this.errorMessage = '';
    const user = JSON.parse(sessionStorage.getItem('currentUser') || '{}');
    const creditedSubjects: CreditedSubject[] = this.selectedCourses.map(c => ({
      courseId: c.courseId, code: c.code, name: c.name, credits: c.credits,
      creditedFrom: this.selectedTor!.lastSchoolAttended || 'Previous School',
    }));
    this.safePost(`${this.registrarApi}?action=evaluate_tor&eval_id=${this.selectedTor.evalId}&student_id=${this.selectedTor.studentId}`, {
      eval_id: this.selectedTor.evalId, student_id: this.selectedTor.studentId,
      registrar_user_id: user.id || 0, credited_subjects: creditedSubjects, registrar_notes: this.registrarNotes,
    }).subscribe(res => {
      this.isSubmitting = false;
      if (res.success) {
        this.successMessage = `✅ ${res.creditedUnits} units credited from ${this.selectedTor!.lastSchoolAttended}. New total: ₱${this.fa(res.newTotal)}.`;
        this.pendingTors = this.pendingTors.filter(t => t.evalId !== this.selectedTor!.evalId);
        this.showModal = false; this.selectedTor = null;
      } else { this.errorMessage = res.message || 'Evaluation failed.'; }
      this.cdr.detectChanges();
    });
  }

  submitRejection(): void {
    if (!this.selectedTor) return;
    if (!this.registrarNotes.trim()) { this.errorMessage = 'Please provide a reason for rejection.'; this.cdr.detectChanges(); return; }
    this.isSubmitting = true; this.errorMessage = '';
    const user = JSON.parse(sessionStorage.getItem('currentUser') || '{}');
    this.safePost(`${this.registrarApi}?action=reject_tor&eval_id=${this.selectedTor.evalId}&student_id=${this.selectedTor.studentId}`, {
      eval_id: this.selectedTor.evalId, student_id: this.selectedTor.studentId,
      registrar_user_id: user.id || 0, registrar_notes: this.registrarNotes,
    }).subscribe(res => {
      this.isSubmitting = false;
      if (res.success) {
        this.successMessage = '⚠️ TOR rejected. Student enrolled in full program.';
        this.pendingTors = this.pendingTors.filter(t => t.evalId !== this.selectedTor!.evalId);
        this.showModal = false; this.selectedTor = null;
      } else { this.errorMessage = res.message || 'Rejection failed.'; }
      this.cdr.detectChanges();
    });
  }

  fd(d: string): string {
    if (!d) return '—';
    return new Date(d).toLocaleDateString('en-PH', { year:'numeric', month:'short', day:'numeric', hour:'2-digit', minute:'2-digit' });
  }
  fa(n: number): string { return (n||0).toLocaleString('en-PH',{minimumFractionDigits:2,maximumFractionDigits:2}); }
}