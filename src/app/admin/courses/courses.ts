import { Component, OnInit, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';

interface Course {
  id: number;
  code: string;
  name: string;
  description: string;
  credits: number;
  department: string;
  program: string;
  year_level: string;
  semester: string;
  instructor?: string;
  room?: string;
  is_lab?: number;
  enrolled_count?: number;
  created_at?: string;
}

@Component({
  selector: 'app-courses',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './courses.html',
  styleUrl: './courses.css',
})
export class Courses implements OnInit {
  private api = 'http://localhost/sia-api/admin.php';

  courseList:    Course[] = [];
  filteredList:  Course[] = [];
  isLoading   = false;
  searchQuery = '';
  filterDept  = 'All';

  showModal  = false;
  isEditing  = false;
  isSaving   = false;

  showDeleteModal = false;
  deleteTarget: Course | null = null;
  isDeleting = false;

  showViewModal = false;
  viewTarget:   Course | null = null;

  form: Partial<Course> = this.emptyForm();

  toast: { show: boolean; type: 'success' | 'error'; message: string } = { show: false, type: 'success', message: '' };

  readonly DEPARTMENTS = ['Information Technology', 'Computer Science', 'Mathematics', 'English', 'Natural Sciences', 'Social Sciences', 'Engineering', 'Business', 'General Education'];
  readonly YEAR_LEVELS = ['1st Year', '2nd Year', '3rd Year', '4th Year', 'Grade 11', 'Grade 12'];
  readonly SEMESTERS   = ['1st Semester, AY 2024-2025', '2nd Semester, AY 2024-2025', '1st Semester, AY 2025-2026', '2nd Semester, AY 2025-2026'];

  
  /** Returns HTTP headers with the auth token. Call this in every API request. */
  private getHeaders() {
    const token = sessionStorage.getItem('token') ?? '';
    return { headers: { Authorization: `Bearer ${token}` } };
  }

  constructor(private http: HttpClient, private cdr: ChangeDetectorRef) {}

  ngOnInit(): void { this.loadCourses(); }

  emptyForm(): Partial<Course> {
    return { code: '', name: '', description: '', credits: 3, department: '', program: '', year_level: '1st Year', semester: '', is_lab: 0 };
  }

  loadCourses(): void {
    this.isLoading = true;
    this.http.get<any>(`${this.api}?action=get_courses`, this.getHeaders()).subscribe({
      next: (res) => {
        this.isLoading = false;
        if (res.success) { this.courseList = res.courses; this.applyFilter(); }
        this.cdr.detectChanges();
      },
      error: () => { this.isLoading = false; this.showToast('error', 'Cannot load courses. Check XAMPP.'); this.cdr.detectChanges(); }
    });
  }

  applyFilter(): void {
    let list = [...this.courseList];
    if (this.searchQuery.trim()) {
      const q = this.searchQuery.toLowerCase();
      list = list.filter(c =>
        c.code.toLowerCase().includes(q) ||
        c.name.toLowerCase().includes(q) ||
        c.description?.toLowerCase().includes(q) ||
        c.department?.toLowerCase().includes(q)
      );
    }
    if (this.filterDept !== 'All') list = list.filter(c => c.department === this.filterDept);
    this.filteredList = list;
  }

  get uniqueDepts(): string[] {
    return [...new Set(this.courseList.map(c => c.department).filter(Boolean))].sort() as string[];
  }

  openAdd(): void {
    this.form = this.emptyForm();
    this.isEditing = false;
    this.showModal = true;
    this.cdr.detectChanges();
  }

  openEdit(c: Course): void {
    this.form = { ...c };
    this.isEditing = true;
    this.showModal = true;
    this.cdr.detectChanges();
  }

  openView(c: Course): void {
    this.viewTarget = c;
    this.showViewModal = true;
    this.cdr.detectChanges();
  }

  closeModal():     void { this.showModal     = false; this.cdr.detectChanges(); }
  closeViewModal(): void { this.showViewModal = false; this.cdr.detectChanges(); }

  save(): void {
    if (!this.form.code || !this.form.name) {
      this.showToast('error', 'Course code and name are required.'); return;
    }
    if (!this.form.credits || this.form.credits < 1) {
      this.showToast('error', 'Units must be at least 1.'); return;
    }
    this.isSaving = true;
    const action = this.isEditing ? 'update_course' : 'create_course';
    this.http.post<any>(`${this.api}?action=${action}`, this.form, this.getHeaders()).subscribe({
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

  closeDeleteModal(): void { this.showDeleteModal = false; this.deleteTarget = null; this.cdr.detectChanges(); }

  doDelete(): void {
    if (!this.deleteTarget) return;
    this.isDeleting = true;
    this.http.post<any>(`${this.api}?action=delete_course`, { id: this.deleteTarget.id }, this.getHeaders()).subscribe({
      next: (res) => {
        this.isDeleting = false;
        if (res.success) { this.showToast('success', 'Course deleted.'); this.loadCourses(); }
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

  get totalUnits():   number { return this.courseList.reduce((s, c) => s + (c.credits || 0), 0); }
  get avgUnits():     string { return this.courseList.length ? (this.totalUnits / this.courseList.length).toFixed(1) : '0'; }
}