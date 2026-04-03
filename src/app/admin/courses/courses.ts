import { Component, OnInit, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../environment';

interface Course {
  id: number;
  code: string;
  name: string;
  description: string;
  credits: number;
  lec_units: number;
  lab_units: number;
  department: string;
  program: string;
  year_level: string;
  semester: string;
  instructor?: string;
  room?: string;
  is_general?: number;
  is_lab?: number;
  enrolled_count?: number;
  created_at?: string;
  level_type?: 'College' | 'SHS' | 'TVET';
  // PREREQ-PATCH: prerequisite fields returned by get_courses
  prerequisite_id?:   number | null;
  prerequisite_code?: string;
  prerequisite_name?: string;
}

interface DeptEntry {
  id: string;
  label: string;
  dbName: string;
  type: 'College' | 'SHS' | 'TVET';
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
}

@Component({
  selector: 'app-courses',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './courses.html',
  styleUrl: './courses.css',
})
export class Courses implements OnInit {
  private api = environment.adminApi;

  courseList:   Course[] = [];
  filteredList: Course[] = [];
  isLoading   = false;
  searchQuery = '';
  filterLevel = 'All';   // 'All' | 'College' | 'SHS' | 'TVET'
  filterDept  = 'All';

  showModal       = false;
  isEditing       = false;
  isSaving        = false;
  showDeleteModal = false;
  deleteTarget: Course | null = null;
  isDeleting      = false;
  showViewModal   = false;
  viewTarget: Course | null = null;

  // PREREQ-PATCH: form now includes prerequisite_id
  form: Partial<Course> & { _levelType: 'College' | 'SHS' | 'TVET' } = this.emptyForm();

  toast: { show: boolean; type: 'success' | 'error'; message: string } =
    { show: false, type: 'success', message: '' };

  deptEntries: DeptEntry[] = [];
  allPrograms: Program[]   = [];
  sharedProgramIds: number[] = [];

  readonly YEAR_LEVELS_COLLEGE = ['1st Year', '2nd Year', '3rd Year', '4th Year'];
  readonly YEAR_LEVELS_SHS     = ['Grade 11', 'Grade 12'];
  readonly YEAR_LEVELS_TVET    = ['Year 1', 'Year 2', 'Year 3', 'Year 4'];
  readonly SEMESTERS = ['1st Semester', '2nd Semester'];

  get currentDepts(): DeptEntry[] {
    return this.deptEntries.filter(d => d.type === this.form._levelType);
  }
  get currentPrograms(): Program[] {
    return this.allPrograms.filter(p => p.level_type === this.form._levelType);
  }
  get currentYearLevels(): string[] {
    if (this.form._levelType === 'SHS')  return this.YEAR_LEVELS_SHS;
    if (this.form._levelType === 'TVET') return this.YEAR_LEVELS_TVET;
    return this.YEAR_LEVELS_COLLEGE;
  }
  deptLabel(): string {
    if (this.form._levelType === 'SHS')  return 'Strand';
    if (this.form._levelType === 'TVET') return 'Program Type';
    return 'Department';
  }

  // PREREQ-PATCH: courses eligible as prerequisites for the form's current course
  // Excludes the course being edited (self-reference) and courses from a different
  // program (optional: remove the program filter if cross-program prereqs are needed).
  get prerequisiteCandidates(): Course[] {
    const editingId = this.isEditing ? (this.form.id ?? 0) : 0;
    return this.courseList.filter(c =>
      c.id !== editingId &&
      (c.program === this.form.program || !this.form.program)
    );
  }

  constructor(private http: HttpClient, private cdr: ChangeDetectorRef) {}

  ngOnInit(): void {
    this.loadPrograms();
  }

  // ─── Sync dept entries from DB programs + localStorage new entries ────────
  syncDeptEntries(): void {
    const makeEntry = (label: string, type: 'College' | 'SHS' | 'TVET'): DeptEntry => ({
      id: `${type[0].toLowerCase()}_${label.replace(/[^a-zA-Z0-9]/g, '_')}`,
      label, dbName: label, type,
    });

    const fromDB: Record<string, string[]> = { College: [], SHS: [], TVET: [] };
    for (const p of this.allPrograms) {
      const dept = (p.department ?? '').trim();
      if (dept && fromDB[p.level_type] && !fromDB[p.level_type].includes(dept))
        fromDB[p.level_type].push(dept);
    }

    let stored: DeptEntry[] = [];
    try {
      const raw = localStorage.getItem('sia_dept_entries');
      if (raw) stored = JSON.parse(raw).map((e: any) => ({ ...e, dbName: e.dbName ?? e.label }));
    } catch {}

    const merge = (type: 'College' | 'SHS' | 'TVET'): DeptEntry[] => {
      const labels = fromDB[type];
      const result = labels.map(l => makeEntry(l, type));
      for (const s of stored.filter(e => e.type === type)) {
        const n = (s.dbName ?? s.label).trim();
        if (n && !labels.includes(n)) result.push(makeEntry(n, type));
      }
      return result;
    };

    this.deptEntries = [...merge('College'), ...merge('SHS'), ...merge('TVET')];
    localStorage.setItem('sia_dept_entries', JSON.stringify(this.deptEntries));
  }

  // ─── Load programs → sync depts → sync DB course depts → load courses ────
  loadPrograms(): void {
    this.http.get<any>(`${this.api}?action=get_programs`).subscribe({
      next: (res) => {
        if (res.success) {
          this.allPrograms = res.programs ?? [];
          this.syncDeptEntries();
          this.syncAndLoadCourses();
        } else {
          this.loadCourses();
        }
        this.cdr.detectChanges();
      },
      error: () => this.loadCourses()
    });
  }

  syncAndLoadCourses(): void {
    this.http.post<any>(`${this.api}?action=sync_course_departments`, {}).subscribe({
      next:  () => this.loadCourses(),
      error: () => this.loadCourses(),
    });
  }

  // ─── COURSES ─────────────────────────────────────────────────────────────
  // PREREQ-PATCH: emptyForm includes prerequisite_id: null
  emptyForm(): Partial<Course> & { _levelType: 'College' | 'SHS' | 'TVET' } {
    return {
      code: '', name: '', description: '', credits: 3,
      lec_units: 3, lab_units: 0,
      department: '', program: '', year_level: '1st Year',
      semester: '', is_general: 0, _levelType: 'College',
      prerequisite_id: null,   // PREREQ-PATCH
    };
  }

  loadCourses(): void {
    this.isLoading = true;
    this.http.get<any>(`${this.api}?action=get_courses`).subscribe({
      next: (res) => {
        this.isLoading = false;
        if (res.success) { this.courseList = res.courses ?? []; this.applyFilter(); }
        this.cdr.detectChanges();
      },
      error: () => {
        this.isLoading = false;
        this.showToast('error', 'Cannot load courses. Check XAMPP.');
        this.cdr.detectChanges();
      }
    });
  }

  getLevelType(c: Course): 'College' | 'SHS' | 'TVET' {
    if (c.level_type && (c.level_type === 'College' || c.level_type === 'SHS' || c.level_type === 'TVET')) {
      return c.level_type;
    }
    const matched = this.allPrograms.find(p => p.name === c.program || p.code === c.program);
    if (matched) return matched.level_type;
    const yr   = (c.year_level ?? '').toLowerCase();
    const dept = (c.department ?? '').toLowerCase();
    const prog = (c.program ?? '').toLowerCase();
    if (yr.includes('grade')) return 'SHS';
    if (dept.includes('tvet') || prog.includes('tvet')) return 'TVET';
    return 'College';
  }

  applyFilter(): void {
    let list = [...this.courseList];
    if (this.filterLevel !== 'All')
      list = list.filter(c => this.getLevelType(c) === this.filterLevel);
    if (this.filterDept !== 'All')
      list = list.filter(c => (c.department ?? '').trim() === this.filterDept);
    if (this.searchQuery.trim()) {
      const q = this.searchQuery.toLowerCase();
      list = list.filter(c =>
        c.code.toLowerCase().includes(q) ||
        c.name.toLowerCase().includes(q) ||
        (c.description ?? '').toLowerCase().includes(q) ||
        (c.department ?? '').toLowerCase().includes(q)
      );
    }
    this.filteredList = list;
  }

  onLevelFilterChange(level: string): void {
    this.filterLevel = level;
    this.filterDept  = 'All';
    this.applyFilter();
  }

  get uniqueDepts(): string[] {
    const list = this.filterLevel === 'All'
      ? this.courseList
      : this.courseList.filter(c => this.getLevelType(c) === this.filterLevel);
    return [...new Set(list.map(c => (c.department ?? '').trim()).filter(Boolean))].sort();
  }

  get countAll():     number { return this.courseList.length; }
  get countCollege(): number { return this.courseList.filter(c => this.getLevelType(c) === 'College').length; }
  get countSHS():     number { return this.courseList.filter(c => this.getLevelType(c) === 'SHS').length; }
  get countTVET():    number { return this.courseList.filter(c => this.getLevelType(c) === 'TVET').length; }
  get deptCount():    number { return this.uniqueDepts.length || this.deptEntries.filter(d => d.type === 'College').length; }
  get totalUnits():   number { return this.courseList.reduce((s, c) => s + (c.credits || 0), 0); }
  get avgUnits():     string { return this.courseList.length ? (this.totalUnits / this.courseList.length).toFixed(1) : '0'; }

  getCardLevel(c: Course): string { return this.getLevelType(c); }

  onLevelTypeChange(): void {
    this.form.department    = '';
    this.form.program       = '';
    this.form.year_level    = this.currentYearLevels[0];
    this.form.prerequisite_id = null; // PREREQ-PATCH: reset on level change
    if (this.form._levelType === 'SHS') {
      this.form.lec_units = 0;
      this.form.lab_units = 0;
      this.form.credits   = 0;
      this.form.semester  = '';   // SHS has no semester — clear it
    } else if (!this.form.lec_units && !this.form.lab_units) {
      this.form.lec_units = 3;
      this.form.lab_units = 0;
      this.form.credits   = 3;
    }
    this.cdr.detectChanges();
  }

  onProgramChange(): void {
    const prog = this.allPrograms.find(p => p.name === this.form.program);
    if (prog?.department) this.form.department = prog.department;
    this.form.prerequisite_id = null; // PREREQ-PATCH: reset when program changes
    this.cdr.detectChanges();
  }

  onLecLabChange(): void {
    const lec = Number(this.form.lec_units) || 0;
    const lab = Number(this.form.lab_units) || 0;
    this.form.credits = lec + lab;
    this.cdr.detectChanges();
  }

  openAdd(): void {
    const lt = (this.filterLevel === 'All' ? 'College' : this.filterLevel) as 'College' | 'SHS' | 'TVET';
    this.form = { ...this.emptyForm(), _levelType: lt };
    this.sharedProgramIds = [];
    this.isEditing = false;
    this.showModal = true;
    this.cdr.detectChanges();
  }

  openEdit(c: Course): void {
    const levelType = this.getLevelType(c);
    const labU = Number(c.lab_units) || 0;
    const lecU = Number(c.lec_units) || ((c.credits || 0) - labU);
    // PREREQ-PATCH: populate prerequisite_id from the course data
    this.form = {
      ...c,
      lec_units: lecU,
      lab_units: labU,
      _levelType: levelType,
      prerequisite_id: c.prerequisite_id ?? null,
    };
    this.sharedProgramIds = this.allPrograms
      .filter(p => (p.course_ids ?? []).includes(Number(c.id)))
      .map(p => Number(p.id))
      .filter(pid => {
        const primary = this.allPrograms.find(p => p.name === this.form.program);
        return !primary || pid !== Number(primary.id);
      });
    this.isEditing = true;
    this.showModal = true;
    this.cdr.detectChanges();
  }

  openView(c: Course): void {
    this.viewTarget = c;
    this.showViewModal = true;
    this.cdr.detectChanges();
  }

  closeModal():       void { this.showModal       = false; this.cdr.detectChanges(); }
  closeViewModal():   void { this.showViewModal   = false; this.cdr.detectChanges(); }
  closeDeleteModal(): void { this.showDeleteModal = false; this.deleteTarget = null; this.cdr.detectChanges(); }

  save(): void {
    if (!this.form.code || !this.form.name) {
      this.showToast('error', 'Code and name are required.'); return;
    }
    if (this.form._levelType !== 'SHS' && (!this.form.credits || this.form.credits < 1)) {
      this.showToast('error', 'Units must be at least 1.'); return;
    }

    const { _levelType, ...payload } = this.form as any;
    payload.shared_program_ids = this.sharedProgramIds;
    // PREREQ-PATCH: send prerequisite_id (null = "no prerequisite" — backend clears the row)
    payload.prerequisite_id = this.form.prerequisite_id ?? null;

    this.isSaving = true;
    const action = this.isEditing ? 'update_course' : 'create_course';
    this.http.post<any>(`${this.api}?action=${action}`, payload).subscribe({
      next: (res) => {
        this.isSaving = false;
        if (res.success) {
          this.showToast('success', this.isEditing ? 'Course updated!' : 'Course created!');
          this.closeModal(); this.loadCourses();
        } else {
          this.showToast('error', res.message || 'Save failed.');
        }
        this.cdr.detectChanges();
      },
      error: () => { this.isSaving = false; this.showToast('error', 'Server error.'); this.cdr.detectChanges(); }
    });
  }

  confirmDelete(c: Course): void {
    this.deleteTarget = c;
    this.showDeleteModal = true;
    this.cdr.detectChanges();
  }

  doDelete(): void {
    if (!this.deleteTarget) return;
    this.isDeleting = true;
    this.http.post<any>(`${this.api}?action=delete_course`, { id: this.deleteTarget.id }).subscribe({
      next: (res) => {
        this.isDeleting = false;
        if (res.success) { this.showToast('success', 'Course deleted.'); this.loadCourses(); }
        else { this.showToast('error', res.message || 'Delete failed.'); }
        this.closeDeleteModal(); this.cdr.detectChanges();
      },
      error: () => { this.isDeleting = false; this.showToast('error', 'Server error.'); this.cdr.detectChanges(); }
    });
  }

  toggleSharedProgram(pid: number, event: Event): void {
    const checked = (event.target as HTMLInputElement).checked;
    if (checked) {
      if (!this.sharedProgramIds.includes(pid)) this.sharedProgramIds.push(pid);
    } else {
      this.sharedProgramIds = this.sharedProgramIds.filter(id => id !== pid);
    }
  }

  showToast(type: 'success' | 'error', message: string): void {
    this.toast = { show: true, type, message };
    setTimeout(() => { this.toast.show = false; this.cdr.detectChanges(); }, 4000);
  }

  isMinor(code: string): boolean {
    if (!code) return false;
    const upper = code.toUpperCase();
    return upper.startsWith('GE') ||
           upper.startsWith('PE') ||
           upper.startsWith('NSTP') ||
           upper.startsWith('OJT');
  }

  // PREREQ-PATCH: helper used by the template to show the selected prerequisite's label
  getPrerequisiteLabel(): string {
    const pid = this.form.prerequisite_id;
    if (!pid) return '— None —';
    const found = this.courseList.find(c => c.id === pid);
    return found ? `${found.code} — ${found.name}` : `Course #${pid}`;
  }
}