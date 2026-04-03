import { Component, OnInit, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { Router } from '@angular/router';
import { map, catchError } from 'rxjs/operators';
import { of } from 'rxjs';
import { environment } from '../../environment';

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
  lecUnits?:    number;
  labUnits?:    number;
  isGeneral?:   boolean;
  isLab?:       boolean;
  courseId:     number;
  code:         string;
  name:         string;
  credits:      number;
  yearLevel:    string;
  semester:     string;
  description:  string;
  isCredited:   boolean;
  selected:     boolean;
  status?:      'Credited' | 'Completed' | 'Enrolled' | 'Pending' | 'Failed';
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

// ── NEW: Student TOR interfaces ─────────────────────────────────
interface StudentTorEntry {
  id:               number;
  studentNumber:    string;
  firstName:        string;
  lastName:         string;
  program:          string;
  yearLevel:        string;
  semester:         string;
  studentType:      string | null | undefined;
  studentCategory:  string;
  enrollmentStatus: string | null | undefined;
  torEvalStatus:    string;
  torFileUrl:       string | null;
  torFile:          string | null;
}

@Component({
  selector: 'app-tor-evaluation',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './tor-evaluation.html',
  styleUrl: './tor-evaluation.css',
})
export class TorEvaluation implements OnInit {
  private registrarApi  = environment.registrarApi;
  private accountingApi = environment.accountingApi;

  private feeRates = { tuition: 650, misc: 6688, reg: 700, labPerRoom: 1900, energy: 63, labRooms: 4 };
  private extraFeeConfig: { fee_key: string; fee_label: string; value: number; is_per_unit: number }[] = [];

  constructor(private http: HttpClient, private router: Router, private cdr: ChangeDetectorRef) {}

  pendingTors:    PendingTOR[] = [];
  evaluatedTors:  PendingTOR[] = [];
  isLoading       = false;
  isEvalLoading   = false;
  activeTab: 'pending' | 'evaluated' | 'student-tor' = 'pending';
  successMessage  = '';
  errorMessage    = '';

  showModal       = false;
  modalMode:       'evaluate' | 'reject' | 'view' | 'manual' | 'student-tor-view' = 'evaluate';
  selectedTor:     PendingTOR | null = null;
  isSubmitting    = false;
  registrarNotes  = '';

  manualSubjects: { code: string; name: string; credits: number }[] = [];
  manualNewCode   = '';
  manualNewName   = '';
  manualNewCredits= 3;

  curriculumCourses: CurriculumCourse[] = [];
  yearGroups:        YearGroup[]        = [];
  isCoursesLoading  = false;
  recomputedFee: any = null;

  // ── Student TOR tab state ───────────────────────────────────────
  studentTorList:     StudentTorEntry[]  = [];
  studentTorLoading   = false;
  studentTorSearch    = '';
  studentTorFilter:   'all' | 'transferee' | 'with-tor' | 'no-tor' = 'all';
  selectedStudentTor: StudentTorEntry | null = null;
  studentTorDetail:   any = null;      // full enrollment history
  studentCurriculum:  any = null;      // curriculum with credited subjects
  studentTorDetailLoading = false;
  studentTorActiveInner: 'tor-file' | 'curriculum' | 'history' = 'tor-file';

  get filteredStudentTors(): StudentTorEntry[] {
    let list = this.studentTorList;
    const q = this.studentTorSearch.trim().toLowerCase();
    if (q) {
      list = list.filter(s =>
        s.firstName.toLowerCase().includes(q) ||
        s.lastName.toLowerCase().includes(q)  ||
        s.studentNumber.toLowerCase().includes(q) ||
        s.program.toLowerCase().includes(q)
      );
    }
    if (this.studentTorFilter === 'transferee') list = list.filter(s => s.studentType?.toLowerCase().includes('transfer'));
    if (this.studentTorFilter === 'with-tor')   list = list.filter(s => !!s.torFile);
    if (this.studentTorFilter === 'no-tor')     list = list.filter(s => !s.torFile);
    return list;
  }

  get selectedCourses(): CurriculumCourse[] { return this.curriculumCourses.filter(c => c.selected); }
  get totalCreditedUnits(): number { return this.selectedCourses.reduce((s, c) => s + c.credits, 0); }
  get approvedUnits(): number {
    if (!this.selectedTor) return 0;
    return Math.max(0, (this.selectedTor.programUnits || 0) - this.totalCreditedUnits);
  }
  get allSelected(): boolean { return this.curriculumCourses.length > 0 && this.curriculumCourses.every(c => c.selected); }
  get totalProgramUnits(): number { return this.curriculumCourses.reduce((s, c) => s + c.credits, 0); }

  ngOnInit(): void {
    this.loadPendingTors();
    this.loadFeeRates();
  }

  loadFeeRates(): void {
    this.http.get<any>(`${this.accountingApi}?action=get_fee_config`).subscribe({
      next: (res) => {
        if (res.success && res.config?.College) {
          const c = res.config.College;
          const find = (key: string) => c.find((r: any) => r.fee_key === key)?.value ?? null;
          this.feeRates.tuition    = parseFloat(find('tuition_rate_per_unit') ?? 650);
          this.feeRates.misc       = parseFloat(find('misc_fee')              ?? 6688);
          this.feeRates.reg        = parseFloat(find('reg_fee')               ?? 700);
          this.feeRates.labPerRoom = parseFloat(find('lab_fee_per_room')      ?? 1900);
          this.feeRates.energy     = parseFloat(find('energy_rate_per_unit')  ?? 63);
          const stdKeys = ['tuition_rate_per_unit','misc_fee','reg_fee','lab_fee_per_room','energy_rate_per_unit','installment_fee'];
          this.extraFeeConfig = c.filter((r: any) => !stdKeys.includes(r.fee_key));
        }
        this.http.get<any>(`${this.registrarApi}?action=get_lab_room_count`).subscribe({
          next: (r) => { if (r.success) this.feeRates.labRooms = r.count ?? 4; },
          error: () => {}
        });
        this.updateFeePreview();
        this.cdr.detectChanges();
      },
      error: () => {}
    });
  }

  private parsePhpResponse(raw: string): any {
    if (!raw || !raw.trim()) throw new Error('Empty response from server.');
    const firstBrace   = raw.indexOf('{');
    const firstBracket = raw.indexOf('[');
    let start = -1;
    if (firstBrace >= 0 && firstBracket >= 0) start = Math.min(firstBrace, firstBracket);
    else if (firstBrace >= 0) start = firstBrace;
    else if (firstBracket >= 0) start = firstBracket;
    if (start < 0) throw new Error(`No JSON in response. PHP said: ${raw.substring(0, 300)}`);
    const junk = raw.substring(0, start).trim();
    if (junk) console.warn('[TOR] PHP output before JSON:', junk);
    try { return JSON.parse(raw.substring(start)); }
    catch { throw new Error(`JSON parse failed. Raw: ${raw.substring(0, 400)}`); }
  }

  private safeGet(url: string) {
    return this.http.get(url, { responseType: 'text' }).pipe(
      map(raw => this.parsePhpResponse(raw)),
      catchError(err => {
        if (err?.status === 0 || err?.name === 'HttpErrorResponse')
          return of({ success: false, _networkError: true, message: 'Cannot reach server. Make sure XAMPP is running.' });
        return of({ success: false, _parseError: true, message: err?.message || 'Unknown error' });
      })
    );
  }

  private safePost(url: string, body: any) {
    return this.http.post(url, body, { responseType: 'text' }).pipe(
      map(raw => this.parsePhpResponse(raw)),
      catchError(err => {
        if (err?.status === 0 || err?.name === 'HttpErrorResponse')
          return of({ success: false, _networkError: true, message: 'Cannot reach server.' });
        return of({ success: false, _parseError: true, message: err?.message || 'Unknown error' });
      })
    );
  }

  loadPendingTors(): void {
    this.isLoading = true;
    this.errorMessage = '';
    this.safeGet(`${this.registrarApi}?action=get_pending_tor`).subscribe(res => {
      this.isLoading = false;
      if (res.success) {
        this.pendingTors = res.evaluations || [];
      } else {
        this.pendingTors = [];
        this.errorMessage = res._networkError ? res.message : (res.message || 'Failed to load TOR evaluations.');
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

  // ── Load student list for Student TOR tab ──────────────────────
  loadStudentTorList(): void {
    this.studentTorLoading = true;
    this.safeGet(`${this.registrarApi}?action=masterlist_students&limit=200&page=1`).subscribe(res => {
      this.studentTorLoading = false;
      if (res.success) {
        this.studentTorList = (res.students || []).map((s: any) => ({
          id:               s.id,
          studentNumber:    s.studentNumber,
          firstName:        s.firstName,
          lastName:         s.lastName,
          program:          s.program,
          yearLevel:        s.yearLevel,
          semester:         s.semester,
          studentType:      s.studentType,
          studentCategory:  s.studentCategory,
          enrollmentStatus: s.enrollmentStatus,
          torEvalStatus:    s.torEvalStatus ?? '',
          torFile:          s.torFile       ?? null,
          torFileUrl:       s.torFile
            ? `http://${window.location.hostname}/sia-api/uploads/${s.torFile}`
            : null,
        }));
      }
      this.cdr.detectChanges();
    });
  }

  // ── Open a student's TOR detail panel ─────────────────────────
  openStudentTor(student: StudentTorEntry): void {
    this.selectedStudentTor      = student;
    this.studentTorDetail        = null;
    this.studentCurriculum       = null;
    this.studentTorActiveInner   = 'tor-file';
    this.modalMode               = 'student-tor-view';
    this.showModal               = true;
    this.studentTorDetailLoading = true;
    this.cdr.detectChanges();

    // Load curriculum (has credited subjects info)
    this.safeGet(`${this.registrarApi}?action=get_student_curriculum&student_id=${student.id}`)
      .subscribe(res => {
        if (res.success) this.studentCurriculum = res;
        this.rebuildCurriculumFromStudentData();
        this.cdr.detectChanges();
      });

    // Load enrollment history
    this.safeGet(`${this.registrarApi}?action=get_enrollment_history&student_id=${student.id}`)
      .subscribe(res => {
        this.studentTorDetailLoading = false;
        if (res.success) {
          const history = (res.history || []).map((sem: any) => ({
            ...sem,
            subjects: Array.isArray(sem.subjects)
              ? sem.subjects
              : sem.subjects ? Object.values(sem.subjects) : [],
          }));
          this.studentTorDetail = { ...res, history };
        }
        this.cdr.detectChanges();
      });
  }

  private rebuildCurriculumFromStudentData(): void {
    if (!this.studentCurriculum?.courses) return;
    this.curriculumCourses = (this.studentCurriculum.courses || []).map((c: any) => ({
      ...c,
      selected:    c.status === 'Credited',
      isCredited:  c.status === 'Credited',
    }));
    this.buildYearGroups();
  }

  switchTab(tab: 'pending' | 'evaluated' | 'student-tor'): void {
    this.activeTab = tab;
    if (tab === 'evaluated' && !this.evaluatedTors.length)    this.loadEvaluatedTors();
    if (tab === 'student-tor' && !this.studentTorList.length) this.loadStudentTorList();
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
    this.manualSubjects.push({ code: this.manualNewCode.trim().toUpperCase(), name: this.manualNewName.trim(), credits: this.manualNewCredits || 3 });
    this.manualNewCode = ''; this.manualNewName = ''; this.manualNewCredits = 3;
    this.cdr.detectChanges();
  }

  removeManualSubject(i: number): void { this.manualSubjects.splice(i, 1); this.cdr.detectChanges(); }

  submitManualEvaluation(): void {
    if (!this.selectedTor) return;
    if (!this.manualSubjects.length) { this.errorMessage = 'Add at least one subject to credit.'; this.cdr.detectChanges(); return; }
    this.isSubmitting = true; this.errorMessage = '';
    const user = JSON.parse(sessionStorage.getItem('currentUser') || '{}');
    const creditedSubjects = this.manualSubjects.map(s => ({ courseId: 0, code: s.code, name: s.name, credits: s.credits, creditedFrom: this.selectedTor!.lastSchoolAttended || 'Previous School' }));
    this.safePost(`${this.registrarApi}?action=evaluate_tor&eval_id=${this.selectedTor.evalId}&student_id=${this.selectedTor.studentId}`, {
      eval_id: this.selectedTor.evalId, student_id: this.selectedTor.studentId,
      registrar_user_id: user.id || 0, credited_subjects: creditedSubjects, registrar_notes: this.registrarNotes, manual: true,
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
    this.showModal = false; this.selectedTor = null; this.selectedStudentTor = null;
    this.curriculumCourses = []; this.yearGroups = [];
    this.manualSubjects = []; this.manualNewCode = ''; this.manualNewName = '';
    this.registrarNotes = ''; this.recomputedFee = null; this.errorMessage = '';
    this.studentTorDetail = null; this.studentCurriculum = null;
    this.cdr.detectChanges();
  }

  loadCurriculum(studentId: number, program: string): void {
    this.isCoursesLoading = true;
    this.safeGet(`${this.registrarApi}?action=get_program_courses&program=${encodeURIComponent(program)}&student_id=${studentId}`)
      .subscribe(res => {
        this.isCoursesLoading = false;
        if (res.success) {
          this.curriculumCourses = (res.courses || []).map((c: any) => ({ ...c, selected: !!c.selected }));
          this.buildYearGroups(); this.updateFeePreview();
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
      if (!yearMap.has(yr))  yearMap.set(yr, new Map());
      const sm = yearMap.get(yr)!;
      if (!sm.has(sem))      sm.set(sem, []);
      sm.get(sem)!.push(c);
    }
    const years = Array.from(yearMap.keys()).sort((a, b) => (YEAR_ORDER.indexOf(a) - YEAR_ORDER.indexOf(b)) || a.localeCompare(b));
    this.yearGroups = years.map(yr => {
      const sm   = yearMap.get(yr)!;
      const sems = Array.from(sm.keys()).sort((a, b) => (SEM_ORDER.indexOf(a) - SEM_ORDER.indexOf(b)) || a.localeCompare(b));
      let yTotal = 0, yCredited = 0;
      const semesters: SemesterGroup[] = sems.map(sem => {
        const courses  = sm.get(sem)!;
        const sTotal   = courses.reduce((s, c) => s + c.credits, 0);
        const sCred    = courses.filter(c => c.selected).reduce((s, c) => s + c.credits, 0);
        yTotal += sTotal; yCredited += sCred;
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

  toggleAll(checked: boolean): void { this.curriculumCourses.forEach(c => c.selected = checked); this.buildYearGroups(); this.updateFeePreview(); this.cdr.detectChanges(); }
  onCourseToggle(): void { this.buildYearGroups(); this.updateFeePreview(); this.cdr.detectChanges(); }

  updateFeePreview(): void {
    if (!this.selectedTor) { this.recomputedFee = null; return; }
    const u = this.approvedUnits > 0 ? this.approvedUnits : (this.selectedTor.programUnits || 0);
    if (!u) { this.recomputedFee = null; return; }
    const tf     = u * this.feeRates.tuition;
    const misc   = this.feeRates.misc;
    const reg    = this.feeRates.reg;
    const lab    = this.feeRates.labRooms * this.feeRates.labPerRoom;
    const energy = u * this.feeRates.energy;
    const extraFees = this.extraFeeConfig.map(ef => ({ fee_key: ef.fee_key, fee_label: ef.fee_label, is_per_unit: ef.is_per_unit, rate: ef.value, amount: ef.value * (ef.is_per_unit ? u : 1) }));
    const extraTotal = extraFees.reduce((s, ef) => s + ef.amount, 0);
    const sub    = tf + misc + reg + lab + energy + extraTotal;
    this.recomputedFee = { units: u, tuitionFee: tf, miscellaneousFee: misc, registrationFee: reg, laboratoryFee: lab, energyFee: energy, extraFees, subtotal: sub, totalAssessment: sub };
  }

  submitEvaluation(): void {
    if (!this.selectedTor) return;
    if (!this.selectedCourses.length) { this.errorMessage = 'Select at least one subject to credit.'; this.cdr.detectChanges(); return; }
    this.isSubmitting = true; this.errorMessage = '';
    const user = JSON.parse(sessionStorage.getItem('currentUser') || '{}');
    const creditedSubjects: CreditedSubject[] = this.selectedCourses.map(c => ({ courseId: c.courseId, code: c.code, name: c.name, credits: c.credits, creditedFrom: this.selectedTor!.lastSchoolAttended || 'Previous School' }));
    this.safePost(`${this.registrarApi}?action=evaluate_tor&eval_id=${this.selectedTor.evalId}&student_id=${this.selectedTor.studentId}`, {
      eval_id: this.selectedTor.evalId, student_id: this.selectedTor.studentId,
      registrar_user_id: user.id || 0, credited_subjects: creditedSubjects, registrar_notes: this.registrarNotes,
    }).subscribe(res => {
      this.isSubmitting = false;
      if (res.success) {
        this.successMessage = `✅ ${res.creditedUnits} units credited. New total: ₱${this.fa(res.newTotal)}.`;
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

  torEvalStatusLabel(s: string): string {
    if (!s) return '—';
    const map: any = { Pending:'⏳ Pending', Evaluated:'✅ Evaluated', Rejected:'❌ Rejected' };
    return map[s] ?? s;
  }

  fd(d: string): string {
    if (!d) return '—';
    return new Date(d).toLocaleDateString('en-PH', { year:'numeric', month:'short', day:'numeric', hour:'2-digit', minute:'2-digit' });
  }
  fa(n: number): string { return (n||0).toLocaleString('en-PH',{minimumFractionDigits:2,maximumFractionDigits:2}); }

  isMinor(code: string): boolean {
    if (!code) return false;
    const upper = code.toUpperCase();
    return upper.startsWith('GE') || upper.startsWith('PE') || upper.startsWith('NSTP') || upper.startsWith('OJT');
  }

  // Normalize subjects — PHP may return array, keyed object, or null
  getYearTotalUnits(semesters: any[]): number {
    return (semesters || []).reduce((s, sem) => s + (sem.total_units || 0), 0);
  }

  getSubjects(subjects: any): any[] {
    if (!subjects) return [];
    if (Array.isArray(subjects)) return subjects;
    return Object.values(subjects);
  }

  // Group history by year level, sorted oldest → newest
  getSortedYearGroups(history: any[]): { yearLevel: string; semesters: any[] }[] {
    if (!history?.length) return [];

    const YEAR_ORDER = ['1st Year','2nd Year','3rd Year','4th Year','5th Year'];
    const SEM_ORDER  = ['1st Semester','2nd Semester','Summer','Midyear'];

    // Derive year level from the semester's subjects' course_year_level,
    // or fall back to sequential numbering based on AY order
    const yearMap = new Map<string, any[]>();

    // Sort history oldest → newest by AY then by semester
    const sorted = [...history].sort((a, b) => {
      const ayA = this._extractAY(a.semester);
      const ayB = this._extractAY(b.semester);
      if (ayA !== ayB) return ayA - ayB;
      return this._semIndex(a.semester) - this._semIndex(b.semester);
    });

    sorted.forEach(sem => {
      // Try to get year level from first subject with course_year_level
      const subjects = this.getSubjects(sem.subjects);
      const yl = subjects.find(s => s.course_year_level)?.course_year_level
        || this._guessYearLevel(sem.semester, sorted);
      if (!yearMap.has(yl)) yearMap.set(yl, []);
      yearMap.get(yl)!.push(sem);
    });

    // Sort year groups
    return Array.from(yearMap.entries())
      .sort(([a], [b]) => {
        const ia = YEAR_ORDER.indexOf(a);
        const ib = YEAR_ORDER.indexOf(b);
        if (ia !== -1 && ib !== -1) return ia - ib;
        return a.localeCompare(b);
      })
      .map(([yearLevel, semesters]) => ({
        yearLevel,
        semesters: semesters.sort((a, b) =>
          this._semIndex(a.semester) - this._semIndex(b.semester)
        ),
      }));
  }

  private _extractAY(semester: string): number {
    const m = semester?.match(/(\d{4})/);
    return m ? parseInt(m[1]) : 0;
  }

  private _semIndex(semester: string): number {
    const s = (semester || '').toLowerCase();
    if (s.includes('1st') || s.includes('first'))  return 0;
    if (s.includes('2nd') || s.includes('second')) return 1;
    if (s.includes('summer') || s.includes('mid')) return 2;
    return 3;
  }

  private _guessYearLevel(semester: string, sorted: any[]): string {
    const YEARS = ['1st Year','2nd Year','3rd Year','4th Year','5th Year'];
    // Assign year level based on position in unique AY list
    const uniqueAYs = [...new Set(sorted.map(s => this._extractAY(s.semester)))].sort();
    const ay = this._extractAY(semester);
    const idx = uniqueAYs.indexOf(ay);
    return YEARS[Math.min(idx, YEARS.length - 1)] || '1st Year';
  }

  // Download enrollment history as PDF
  downloadHistoryPdf(): void {
    if (!this.selectedStudentTor || !this.studentTorDetail) return;

    const student = this.selectedStudentTor;
    const history: any[] = this.studentTorDetail.history || [];
    const groups = this.getSortedYearGroups(history);

    const rows: string[] = [];

    groups.forEach(yg => {
      rows.push(`
        <tr class="yr-header">
          <td colspan="5">${yg.yearLevel}</td>
        </tr>`);

      yg.semesters.forEach(sem => {
        rows.push(`
          <tr class="sem-header">
            <td colspan="3">${sem.semester}</td>
            <td class="tc">${sem.total_units} units</td>
            <td class="tc">${sem.gpa ? 'GPA: ' + Number(sem.gpa).toFixed(2) : ''}</td>
          </tr>`);

        const subjects = this.getSubjects(sem.subjects);
        if (subjects.length === 0) {
          rows.push(`<tr><td colspan="5" style="text-align:center;color:#9ca3af;font-style:italic;">No records</td></tr>`);
        } else {
          subjects.forEach(subj => {
            const grade = subj.final_grade ? Number(subj.final_grade).toFixed(2) : '—';
            const status = subj.enrollment_status || '';
            rows.push(`
              <tr>
                <td class="code">${subj.course_code || ''}</td>
                <td>${subj.course_name || ''}</td>
                <td class="tc">${subj.units || ''}</td>
                <td class="tc">${grade}</td>
                <td class="tc">${status}</td>
              </tr>`);
          });
        }
      });
    });

    const html = `<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">

<style>
  body { font-family: Arial, sans-serif; font-size: 11px; margin: 24px; color: #111; }
  h2   { margin: 0 0 2px; font-size: 14px; }
  .sub { margin: 0 0 16px; font-size: 11px; color: #555; }
  table { width: 100%; border-collapse: collapse; }
  th   { background: #1e3a5f; color: #fff; padding: 6px 8px; text-align: left; font-size: 10px; text-transform: uppercase; letter-spacing: .4px; }
  th.tc, td.tc { text-align: center; }
  td   { padding: 5px 8px; border-bottom: 1px solid #e5e7eb; }
  td.code { font-family: monospace; font-weight: 700; color: #1e3a5f; white-space: nowrap; }
  tr.yr-header td  { background: #0f2a47; color: #fff; font-weight: 700; font-size: 11px; padding: 6px 8px; }
  tr.sem-header td { background: #2d5282; color: #fff; font-size: 10px; padding: 5px 8px; }
  tr:nth-child(even) td { background: #f9fafb; }
  @media print { body { margin: 10px; } }
</style>
</head>
<body>
  <h2>TRANSCRIPT OF RECORDS — ${student.lastName}, ${student.firstName}</h2>
  <p class="sub"> ${student.program} </p>
  <table>
    <thead>
      <tr>
        <th style="width:80px">Code</th>
        <th>Subject</th>
        <th class="tc" style="width:50px">Units</th>
        <th class="tc" style="width:70px">Final Grade</th>
        <th class="tc" style="width:80px">Status</th>
      </tr>
    </thead>
    <tbody>
      ${rows.join('')}
    </tbody>
  </table>
</body>
</html>`;

    const win = window.open('', '_blank');
    if (!win) return;
    win.document.write(html);
    win.document.close();
    win.focus();
    setTimeout(() => { win.print(); }, 500);
  }
}