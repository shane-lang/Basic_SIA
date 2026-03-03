import { Component, OnInit, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';

interface Course {
  id: number;
  code: string;
  name: string;
  credits: number;
  department?: string;
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

@Component({
  selector: 'app-levels',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './levels.html',
  styleUrl: './levels.css',
})
export class Levels implements OnInit {
  private api = 'http://localhost/sia-api/admin.php';

  programs:     Program[] = [];
  allCourses:   Course[]  = [];
  isLoading   = false;
  filterLevel = 'All';  // All | College | SHS | TVET

  showModal  = false;
  isEditing  = false;
  isSaving   = false;
  courseSearch = '';

  showDeleteModal = false;
  deleteTarget: Program | null = null;
  isDeleting = false;

  form: Partial<Program> & { course_ids: number[]; department: string } = this.emptyForm();

  toast: { show: boolean; type: 'success' | 'error'; message: string } = { show: false, type: 'success', message: '' };

  
  /** Returns HTTP headers with the auth token. Call this in every API request. */
  private getHeaders() {
    const token = sessionStorage.getItem('token') ?? '';
    return { headers: { Authorization: `Bearer ${token}` } };
  }

  constructor(private http: HttpClient, private cdr: ChangeDetectorRef) {}

  ngOnInit(): void { this.loadAll(); }

  emptyForm(): Partial<Program> & { course_ids: number[]; department: string } {
    return { name: '', code: '', level_type: 'College', duration: 4, description: '', department: '', course_ids: [] };
  }

  loadAll(): void {
    this.isLoading = true;
    // Load programs and courses in parallel
    this.http.get<any>(`${this.api}?action=get_courses`, this.getHeaders()).subscribe({
      next: (res) => { if (res.success) this.allCourses = res.courses; this.cdr.detectChanges(); }
    });
    this.http.get<any>(`${this.api}?action=get_programs`, this.getHeaders()).subscribe({
      next: (res) => {
        this.isLoading = false;
        if (res.success) this.programs = res.programs;
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

  isSelected(courseId: number): boolean { return this.form.course_ids.includes(courseId); }

  toggleCourse(courseId: number): void {
    const idx = this.form.course_ids.indexOf(courseId);
    if (idx === -1) this.form.course_ids.push(courseId);
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
    this.form.duration = this.form.level_type === 'SHS' ? 2 : 4;
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

  showToast(type: 'success' | 'error', message: string): void {
    this.toast = { show: true, type, message };
    setTimeout(() => { this.toast.show = false; this.cdr.detectChanges(); }, 4000);
  }
}