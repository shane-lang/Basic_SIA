import { Component, OnInit, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';

interface Course {
  id: number;
  code: string;
  name: string;
  credits: number;
  lec_units?: number;
  lab_units?: number;
  department?: string;
  year_level?: string;
  semester?: string;
  is_lab?: number;
  program?: string;
}

interface CurrCell {
  code: string;
  name: string;
  lec: number;
  lab: number;
  total: number;
  id: number;
  is_lab?: number;
}

interface Program {
  id: number;
  name: string;
  code: string;
  level_type: 'College' | 'SHS' | 'TVET';
  duration: number;
  description: string;
  department: string;
  course_ids: number[];
  created_at?: string;
}

/**
 * DeptEntry — single source of truth for dept/strand/program-type display.
 * `dbName` = the EXACT string stored in programs.department in MySQL.
 * `label`  = display name shown in the UI (always kept in sync with dbName).
 * After every successful rename/delete API call, dbName is also updated.
 */
interface DeptEntry {
  id: string;
  label: string;
  dbName: string;
  type: 'College' | 'SHS' | 'TVET';
}

@Component({
  selector: 'app-levels',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './levels.html',
  styleUrl: './levels.css',
})
export class Levels implements OnInit {
  private api = 'http://localhost/sia-api/admin.php';

  programs:   Program[] = [];
  allCourses: Course[]  = [];
  isLoading   = false;
  filterLevel = 'All';

  showModal  = false;
  isEditing  = false;
  isSaving   = false;
  courseSearch = '';

  // ── Course Picker Modal ───────────────────────────────────
  showPickerModal   = false;
  pickerSearch      = '';
  pickerFilterYear  = '';
  pickerFilterSem   = '';
  pickerFilterDept  = '';

  showDeleteModal = false;
  deleteTarget: Program | null = null;
  isDeleting = false;

  form: Partial<Program> & { course_ids: number[]; department: string } = this.emptyForm();

  toast: { show: boolean; type: 'success' | 'error'; message: string } =
    { show: false, type: 'success', message: '' };

  // ── Department/Strand/ProgramType manager ──────────────────────────────
  deptEntries: DeptEntry[] = [];

  showDeptModal = false;
  deptModalTab: 'College' | 'SHS' | 'TVET' = 'College';
  newDeptLabel  = '';
  isDeptSaving  = false;

  // Delete dept
  showDeleteDeptModal = false;
  deleteDeptTarget: DeptEntry | null = null;
  isDeletingDept = false;

  // Edit dept
  editingDeptId: string | null = null;
  editingDeptLabel = '';

  // ── Curriculum view modal ───────────────────────────────────
  showCurrModal    = false;
  currProgram: Program | null = null;

  // ── Getters for grouped dept entries ──────────────────────────────────
  get collegeDepts():  DeptEntry[] { return this.deptEntries.filter(d => d.type === 'College'); }
  get shsStrands():    DeptEntry[] { return this.deptEntries.filter(d => d.type === 'SHS'); }
  get tvetProgTypes(): DeptEntry[] { return this.deptEntries.filter(d => d.type === 'TVET'); }

  get currentDeptOptions(): DeptEntry[] {
    return this.deptEntries.filter(d => d.type === this.form.level_type);
  }

  private getHeaders() {
    const token = sessionStorage.getItem('token') ?? localStorage.getItem('token') ?? '';
    return { headers: { Authorization: `Bearer ${token}` } };
  }

  constructor(private http: HttpClient, private cdr: ChangeDetectorRef) {}

  ngOnInit(): void {
    this.loadAll(); // loadAll will call syncDeptEntriesFromDB after programs load
  }

  // ════════════════════════════════════════════════════════════════════════
  // DEPT ENTRIES — always synced FROM DB on load, localStorage is just cache
  // ════════════════════════════════════════════════════════════════════════

  saveDeptEntries(): void {
    localStorage.setItem('sia_dept_entries', JSON.stringify(this.deptEntries));
  }

  /**
   * Build deptEntries from DB programs + any in-memory/localStorage entries
   * that haven't been assigned to a program yet (newly added).
   * DB is authoritative; we just preserve newly added entries that have no programs.
   */
  syncDeptEntriesFromDB(): void {
    const makeEntry = (label: string, type: 'College' | 'SHS' | 'TVET'): DeptEntry => ({
      id:     `${type[0].toLowerCase()}_${label.replace(/[^a-zA-Z0-9]/g, '_')}`,
      label, dbName: label, type,
    });

    // Step 1: unique dept labels per type from DB programs (deduplicated)
    const fromDB: Record<string, string[]> = { College: [], SHS: [], TVET: [] };
    for (const p of this.programs) {
      const dept = (p.department ?? '').trim();
      if (dept && fromDB[p.level_type] && !fromDB[p.level_type].includes(dept))
        fromDB[p.level_type].push(dept);
    }

    // Step 2: also read localStorage to preserve admin entries not yet tied to a program
    let storedEntries: DeptEntry[] = [];
    try {
      const raw = localStorage.getItem('sia_dept_entries');
      if (raw) storedEntries = JSON.parse(raw).map((e: any) => ({ ...e, dbName: e.dbName ?? e.label }));
    } catch {}

    const merge = (type: 'College' | 'SHS' | 'TVET'): DeptEntry[] => {
      const dbLabels = fromDB[type];
      const result   = dbLabels.map(l => makeEntry(l, type));
      // Add localStorage entries not yet in DB (no programs assigned yet)
      for (const s of storedEntries.filter(e => e.type === type)) {
        const n = (s.dbName ?? s.label).trim();
        if (n && !dbLabels.includes(n)) result.push(makeEntry(n, type));
      }
      return result;
    };

    this.deptEntries = [...merge('College'), ...merge('SHS'), ...merge('TVET')];
    this.saveDeptEntries();
    this.cdr.detectChanges();
  }

  openDeptManager(): void {
    this.newDeptLabel    = '';
    this.editingDeptId   = null;
    this.editingDeptLabel = '';
    this.showDeptModal   = true;
    this.cdr.detectChanges();
  }

  closeDeptModal(): void {
    this.showDeptModal    = false;
    this.newDeptLabel     = '';
    this.editingDeptId    = null;
    this.editingDeptLabel = '';
    this.cdr.detectChanges();
  }

  // ── ADD ────────────────────────────────────────────────────────────────
  addDeptEntry(): void {
    const label = this.newDeptLabel.trim();
    if (!label) { this.showToast('error', 'Please enter a name.'); return; }

    const duplicate = this.deptEntries.some(
      d => d.type === this.deptModalTab && d.dbName.toLowerCase() === label.toLowerCase()
    );
    if (duplicate) { this.showToast('error', 'Already exists.'); return; }

    this.isDeptSaving = true;
    const id = `${this.deptModalTab[0].toLowerCase()}${Date.now()}`;
    this.deptEntries.push({ id, label, dbName: label, type: this.deptModalTab });
    this.saveDeptEntries();
    this.newDeptLabel = '';
    this.isDeptSaving = false;
    this.showToast('success', `"${label}" added!`);
    this.cdr.detectChanges();
  }

  // ── EDIT ───────────────────────────────────────────────────────────────
  startEditDept(entry: DeptEntry): void {
    this.editingDeptId    = entry.id;
    this.editingDeptLabel = entry.label;
    this.cdr.detectChanges();
  }

  cancelEditDept(): void {
    this.editingDeptId    = null;
    this.editingDeptLabel = '';
    this.cdr.detectChanges();
  }

  saveEditDept(): void {
    const newLabel = this.editingDeptLabel.trim();
    if (!newLabel) { this.showToast('error', 'Name cannot be empty.'); return; }

    const idx = this.deptEntries.findIndex(d => d.id === this.editingDeptId);
    if (idx === -1) { this.showToast('error', 'Entry not found.'); return; }

    const duplicate = this.deptEntries.some(
      d => d.id !== this.editingDeptId &&
           d.type === this.deptModalTab &&
           d.dbName.toLowerCase() === newLabel.toLowerCase()
    );
    if (duplicate) { this.showToast('error', 'Name already exists.'); return; }

    // CRITICAL: use dbName (the real DB value) — NOT label — as old_name
    const oldDbName = this.deptEntries[idx].dbName;

    if (oldDbName === newLabel) {
      // No actual change
      this.editingDeptId    = null;
      this.editingDeptLabel = '';
      this.cdr.detectChanges();
      return;
    }

    // Optimistic update in memory
    this.deptEntries[idx] = { ...this.deptEntries[idx], label: newLabel, dbName: newLabel };
    this.saveDeptEntries();
    this.programs = this.programs.map(p =>
      p.department === oldDbName ? { ...p, department: newLabel } : p
    );
    this.editingDeptId    = null;
    this.editingDeptLabel = '';
    this.cdr.detectChanges();

    // Persist to DB
    this.http.post<any>(
      `${this.api}?action=rename_department`,
      { old_name: oldDbName, new_name: newLabel },
      this.getHeaders()
    ).subscribe({
      next: (res) => {
        if (res.success) {
          this.showToast('success', `Renamed to "${newLabel}" (${res.programs_updated} program(s) updated).`);
          // Reload programs so all cards reflect the new dept name
          this.loadAll();
        } else {
          // Rollback optimistic update
          this.showToast('error', `DB error: ${res.message || 'rename failed'}. Reverting…`);
          const rollbackIdx = this.deptEntries.findIndex(d => d.dbName === newLabel && d.type === this.deptModalTab);
          if (rollbackIdx !== -1) {
            this.deptEntries[rollbackIdx] = { ...this.deptEntries[rollbackIdx], label: oldDbName, dbName: oldDbName };
          }
          this.saveDeptEntries();
          this.programs = this.programs.map(p =>
            p.department === newLabel ? { ...p, department: oldDbName } : p
          );
          this.cdr.detectChanges();
        }
      },
      error: () => {
        this.showToast('error', 'Server error — reverting rename.');
        const rollbackIdx = this.deptEntries.findIndex(d => d.dbName === newLabel && d.type === this.deptModalTab);
        if (rollbackIdx !== -1) {
          this.deptEntries[rollbackIdx] = { ...this.deptEntries[rollbackIdx], label: oldDbName, dbName: oldDbName };
        }
        this.saveDeptEntries();
        this.programs = this.programs.map(p =>
          p.department === newLabel ? { ...p, department: oldDbName } : p
        );
        this.cdr.detectChanges();
      }
    });
  }

  // ── DELETE ─────────────────────────────────────────────────────────────
  confirmDeleteDept(entry: DeptEntry): void {
    this.deleteDeptTarget = entry;
    this.showDeleteDeptModal = true;
    this.cdr.detectChanges();
  }

  cancelDeleteDept(): void {
    this.showDeleteDeptModal = false;
    this.deleteDeptTarget = null;
    this.cdr.detectChanges();
  }

  doDeleteDept(): void {
    if (!this.deleteDeptTarget) return;

    const target  = this.deleteDeptTarget;
    const dbName  = target.dbName; // real DB value
    const entryId = target.id;

    this.isDeletingDept = true;
    this.cdr.detectChanges();

    // Call backend to NULL-out department on all programs using this value
    this.http.post<any>(
      `${this.api}?action=delete_department`,
      { dept_name: dbName },
      this.getHeaders()
    ).subscribe({
      next: (res) => {
        this.isDeletingDept = false;
        if (res.success) {
          // Remove from local list immediately
          this.deptEntries = this.deptEntries.filter(d => d.id !== entryId);
          this.saveDeptEntries();
          this.showToast('success', `"${target.label}" deleted (${res.programs_updated} program(s) cleared).`);
          // Reload programs so all cards clear the old dept badge
          this.loadAll();
        } else {
          this.showToast('error', `DB error: ${res.message || 'delete failed'}.`);
        }
        this.showDeleteDeptModal = false;
        this.deleteDeptTarget    = null;
        this.cdr.detectChanges();
      },
      error: () => {
        this.isDeletingDept = false;
        this.showToast('error', 'Server error — department not deleted.');
        this.showDeleteDeptModal = false;
        this.deleteDeptTarget    = null;
        this.cdr.detectChanges();
      }
    });
  }

  // ════════════════════════════════════════════════════════════════════════
  // PROGRAMS
  // ════════════════════════════════════════════════════════════════════════
  emptyForm(): Partial<Program> & { course_ids: number[]; department: string } {
    return { name: '', code: '', level_type: 'College', duration: 4, description: '', department: '', course_ids: [] };
  }

  loadAll(): void {
    this.isLoading = true;
    this.http.get<any>(`${this.api}?action=get_courses`, this.getHeaders()).subscribe({
      next: (res) => { if (res.success) this.allCourses = res.courses; this.cdr.detectChanges(); }
    });
    this.http.get<any>(`${this.api}?action=get_programs`, this.getHeaders()).subscribe({
      next: (res) => {
        this.isLoading = false;
        if (res.success) {
          this.programs = res.programs;
          this.syncDeptEntriesFromDB(); // Always rebuild from DB truth
        }
        this.cdr.detectChanges();
      },
      error: () => { this.isLoading = false; this.showToast('error', 'Cannot load programs.'); this.cdr.detectChanges(); }
    });
  }

  get filteredPrograms(): Program[] {
    if (this.filterLevel === 'All') return this.programs;
    return this.programs.filter(p => p.level_type === this.filterLevel);
  }

  get collegePrograms(): Program[] { return this.programs.filter(p => p.level_type === 'College'); }
  get shsPrograms():     Program[] { return this.programs.filter(p => p.level_type === 'SHS'); }
  get tvetPrograms():    Program[] { return this.programs.filter(p => p.level_type === 'TVET'); }

  get filteredCourseSearch(): Course[] {
    if (!this.courseSearch.trim()) return this.allCourses;
    const q = this.courseSearch.toLowerCase();
    return this.allCourses.filter(c => c.code.toLowerCase().includes(q) || c.name.toLowerCase().includes(q));
  }

  // ── Course Picker Modal helpers ──────────────────────────
  openCoursePicker(): void {
    this.pickerSearch     = '';
    this.pickerFilterYear = '';
    this.pickerFilterSem  = '';
    this.pickerFilterDept = '';
    this.showPickerModal  = true;
    this.cdr.detectChanges();
  }

  closeCoursePicker(): void {
    this.showPickerModal = false;
    this.cdr.detectChanges();
  }

  get pickerFilteredCourses(): Course[] {
    let list = [...this.allCourses];

    if (this.form.level_type === 'College') {
      list = list.filter(c => {
        const yl = (c.year_level ?? '').toLowerCase();
        return !yl.includes('grade') && !yl.includes('tvet') && yl !== '';
      });
    } else if (this.form.level_type === 'SHS') {
      list = list.filter(c => (c.year_level ?? '').toLowerCase().includes('grade'));
    }

    if (this.pickerFilterYear)
      list = list.filter(c => c.year_level === this.pickerFilterYear);
    if (this.pickerFilterSem)
      list = list.filter(c => (c.semester ?? '').toLowerCase().startsWith(this.pickerFilterSem.toLowerCase().split(' ')[0]));
    if (this.pickerFilterDept)
      list = list.filter(c => c.department === this.pickerFilterDept);
    if (this.pickerSearch.trim()) {
      const q = this.pickerSearch.toLowerCase();
      list = list.filter(c =>
        c.code.toLowerCase().includes(q) ||
        c.name.toLowerCase().includes(q) ||
        (c.program ?? '').toLowerCase().includes(q)
      );
    }
    return list;
  }

  get pickerYearOptions(): string[] {
    const set = new Set(this.allCourses.map(c => c.year_level).filter(Boolean)) as Set<string>;
    return [...set].sort();
  }

  get pickerSemOptions(): string[] {
    const raw = new Set(this.allCourses.map(c => {
      const s = (c.semester ?? '').toLowerCase();
      if (s.startsWith('1st')) return '1st Semester';
      if (s.startsWith('2nd')) return '2nd Semester';
      return null;
    }).filter(Boolean)) as Set<string>;
    return [...raw].sort();
  }

  get pickerDeptOptions(): string[] {
    const set = new Set(this.allCourses.map(c => c.department).filter(Boolean)) as Set<string>;
    return [...set].sort();
  }

  selectAllFiltered(): void {
    const toAdd = this.pickerFilteredCourses
      .map(c => Number(c.id))
      .filter(id => !this.form.course_ids.includes(id));
    this.form.course_ids = [...this.form.course_ids, ...toAdd];
    this.cdr.detectChanges();
  }

  deselectAllFiltered(): void {
    const toRemove = new Set(this.pickerFilteredCourses.map(c => Number(c.id)));
    this.form.course_ids = this.form.course_ids.filter(id => !toRemove.has(id));
    this.cdr.detectChanges();
  }

  get selectedCourses(): Course[] {
    return this.form.course_ids
      .map(id => this.allCourses.find(c => Number(c.id) === Number(id)))
      .filter(Boolean) as Course[];
  }

  isSelected(courseId: number): boolean {
    return this.form.course_ids.some(id => Number(id) === Number(courseId));
  }

  toggleCourse(courseId: number): void {
    const n = Number(courseId);
    const idx = this.form.course_ids.findIndex(id => Number(id) === n);
    if (idx === -1) this.form.course_ids.push(n);
    else this.form.course_ids.splice(idx, 1);
    this.cdr.detectChanges();
  }

  getCourseName(id: number): string {
    const c = this.allCourses.find(x => x.id === id);
    return c ? `${c.code} — ${c.name}` : `Course #${id}`;
  }

  getCoursesByProgram(p: Program): Course[] {
    return this.allCourses.filter(c => p.course_ids.includes(c.id));
  }

  onLevelChange(): void {
    this.form.duration = this.form.level_type === 'SHS' ? 2 : (this.form.level_type === 'TVET' ? 1 : 4);
    this.form.department = '';
    this.cdr.detectChanges();
  }

  openAdd(): void {
    this.form = this.emptyForm();
    this.isEditing = false;
    this.courseSearch = '';
    this.showModal = true;
    this.cdr.detectChanges();
  }

  openEdit(p: Program): void {
    this.form = { ...p, course_ids: [...(p.course_ids ?? [])], department: p.department ?? '' };
    this.isEditing = true;
    this.courseSearch = '';
    this.showModal = true;
    this.cdr.detectChanges();
  }

  closeModal(): void { this.showModal = false; this.cdr.detectChanges(); }

  save(): void {
    if (!this.form.name || !this.form.code) {
      this.showToast('error', 'Program name and code are required.'); return;
    }
    this.isSaving = true;
    const action = this.isEditing ? 'update_program' : 'create_program';
    this.http.post<any>(`${this.api}?action=${action}`, this.form, this.getHeaders()).subscribe({
      next: (res) => {
        this.isSaving = false;
        if (res.success) {
          this.showToast('success', this.isEditing ? 'Program updated!' : 'Program created!');
          this.closeModal(); this.loadAll();
        } else {
          this.showToast('error', res.message || 'Save failed.');
        }
        this.cdr.detectChanges();
      },
      error: () => { this.isSaving = false; this.showToast('error', 'Server error.'); this.cdr.detectChanges(); }
    });
  }

  confirmDelete(p: Program): void {
    this.deleteTarget = p;
    this.showDeleteModal = true;
    this.cdr.detectChanges();
  }

  closeDeleteModal(): void { this.showDeleteModal = false; this.deleteTarget = null; this.cdr.detectChanges(); }

  doDelete(): void {
    if (!this.deleteTarget) return;
    this.isDeleting = true;
    this.http.post<any>(`${this.api}?action=delete_program`, { id: this.deleteTarget.id }, this.getHeaders()).subscribe({
      next: (res) => {
        this.isDeleting = false;
        if (res.success) { this.showToast('success', 'Program deleted.'); this.loadAll(); }
        else { this.showToast('error', res.message || 'Delete failed.'); }
        this.closeDeleteModal(); this.cdr.detectChanges();
      },
      error: () => { this.isDeleting = false; this.showToast('error', 'Server error.'); this.cdr.detectChanges(); }
    });
  }

  // ════════════════════════════════════════════════════════
  // CURRICULUM VIEW
  // ════════════════════════════════════════════════════════
  openCurriculum(p: Program): void {
    this.currProgram = p;
    this.showCurrModal = true;
    this.cdr.detectChanges();
  }

  closeCurrModal(): void {
    this.showCurrModal = false;
    this.currProgram   = null;
    this.cdr.detectChanges();
  }

  get currYears(): string[] {
    const dur = this.currProgram?.duration ?? 4;
    if (this.currProgram?.level_type === 'SHS') return ['Grade 11', 'Grade 12'];
    if (this.currProgram?.level_type === 'TVET') return ['Year 1'];
    return Array.from({ length: dur }, (_, i) =>
      ['1st Year', '2nd Year', '3rd Year', '4th Year', '5th Year'][i] ?? ('Year ' + (i + 1))
    );
  }

  private getProgramCourses(): Course[] {
    if (!this.currProgram) return [];
    const ids = (this.currProgram.course_ids ?? []).map(Number);
    if (ids.length > 0) {
      return this.allCourses.filter(c => ids.includes(Number(c.id)));
    }
    const progName = (this.currProgram.name ?? '').toLowerCase().trim();
    const progCode = (this.currProgram.code ?? '').toLowerCase().trim();
    return this.allCourses.filter(c => {
      const cp = (c.program ?? '').toLowerCase().trim();
      return cp === progName || cp === progCode;
    });
  }

  getCurrCourses(year: string, sem: string): Course[] {
    const progCourses = this.getProgramCourses();
    const yearKey = year.toLowerCase().trim();
    const semKey  = sem.toLowerCase().split(' ')[0];
    return progCourses.filter(c => {
      const yl = (c.year_level ?? '').toLowerCase().trim();
      const sv = (c.semester   ?? '').toLowerCase().trim();
      return yl === yearKey && sv.startsWith(semKey);
    });
  }

  get currUnassignedCourses(): Course[] {
    return this.getProgramCourses().filter(c => !c.year_level || !c.semester);
  }

  semUnits(year: string, sem: string): number {
    return this.getCurrCourses(year, sem).reduce((s, c) => s + (c.credits ?? 0), 0);
  }

  semLabCount(year: string, sem: string): number {
    return this.getCurrCourses(year, sem).reduce((s, c) => s + this.labUnits(c), 0);
  }

  semLecUnits(year: string, sem: string): number {
    return this.semUnits(year, sem) - this.semLabCount(year, sem);
  }

  get currTotalUnits(): number {
    return this.getProgramCourses().reduce((s, c) => s + (c.credits ?? 0), 0);
  }

  get currAssignedCount(): number {
    return this.getProgramCourses().filter(c => !!c.year_level && !!c.semester).length;
  }

  sumUnits(courses: Course[]): number {
    return courses.reduce((s, c) => s + (c.credits ?? 0), 0);
  }

  labUnits(c: Course): number {
    if (c.lab_units !== undefined && c.lab_units !== null) return Number(c.lab_units);
    return c.is_lab ? 1 : 0;
  }

  lecUnits(c: Course): number {
    if (c.lec_units !== undefined && c.lec_units !== null) return Number(c.lec_units);
    return (c.credits ?? 0) - this.labUnits(c);
  }

  showToast(type: 'success' | 'error', message: string): void {
    this.toast = { show: true, type, message };
    setTimeout(() => { this.toast.show = false; this.cdr.detectChanges(); }, 4000);
  }

  deptLabel(type: 'College' | 'SHS' | 'TVET'): string {
    if (type === 'College') return 'Department';
    if (type === 'SHS')     return 'Strand';
    return 'Program Type';
  }

  deptPlaceholder(type: 'College' | 'SHS' | 'TVET'): string {
    if (type === 'College') return 'e.g. Business, ICTD, Engineering';
    if (type === 'SHS')     return 'e.g. Academic Track, TVL, Arts and Design';
    return 'e.g. TVET, Cookery, Welding Technology';
  }
}