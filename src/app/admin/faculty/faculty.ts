import { Component, OnInit, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';

interface IFaculty {
  id: number;
  faculty_id: string;
  first_name: string;
  last_name: string;
  email: string;
  department: string;
  specialty: string;
  subjects: string[];
  status: 'Active' | 'Inactive' | 'On Leave';
  created_at?: string;
}

@Component({
  selector: 'app-faculty',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './faculty.html',
  styleUrl: './faculty.css',
})
export class Faculty implements OnInit {
  private api = 'http://localhost/sia-api/admin.php';

  facultyList: IFaculty[] = [];
  filteredList: IFaculty[] = [];
  isLoading = false;
  searchQuery = '';
  filterStatus = 'All';

  showModal = false;
  isEditing = false;
  isSaving  = false;

  showDeleteModal = false;
  deleteTarget: IFaculty | null = null;
  isDeleting = false;

  newSubjectInput = '';

  form: Partial<IFaculty> & { subjects: string[] } = this.emptyForm();

  toast: { show: boolean; type: 'success' | 'error'; message: string } = { show: false, type: 'success', message: '' };

  readonly DEPARTMENTS = [
    'Information Technology', 'Computer Science', 'Mathematics',
    'English', 'Natural Sciences', 'Social Sciences', 'Engineering', 'Business'
  ];

  coursesFromDb: { code: string; name: string }[] = [];

  constructor(private http: HttpClient, private cdr: ChangeDetectorRef) {}

  ngOnInit(): void { this.loadFaculty(); this.loadCoursesFromDb(); }

  loadCoursesFromDb(): void {
    this.http.get<any>('http://localhost/sia-api/admin.php?action=get_courses').subscribe({
      next: (res) => {
        if (res.success) {
          this.coursesFromDb = res.courses.map((c: any) => ({ code: c.code, name: c.name }));
          this.cdr.detectChanges();
        }
      }
    });
  }

  emptyForm(): Partial<IFaculty> & { subjects: string[] } {
    return { first_name: '', last_name: '', email: '', department: '', specialty: '', subjects: [], status: 'Active' };
  }

  loadFaculty(): void {
    this.isLoading = true;
    this.http.get<any>(`${this.api}?action=get_faculty`).subscribe({
      next: (res) => {
        this.isLoading = false;
        if (res.success) { this.facultyList = res.faculty; this.applyFilter(); }
        this.cdr.detectChanges();
      },
      error: () => { this.isLoading = false; this.showToast('error', 'Cannot load faculty. Check XAMPP.'); this.cdr.detectChanges(); }
    });
  }

  applyFilter(): void {
    let list = [...this.facultyList];
    if (this.searchQuery.trim()) {
      const q = this.searchQuery.toLowerCase();
      list = list.filter(f =>
        f.first_name.toLowerCase().includes(q) ||
        f.last_name.toLowerCase().includes(q) ||
        f.email.toLowerCase().includes(q) ||
        f.department?.toLowerCase().includes(q) ||
        f.specialty?.toLowerCase().includes(q)
      );
    }
    if (this.filterStatus !== 'All') {
      list = list.filter(f => f.status === this.filterStatus);
    }
    this.filteredList = list;
  }

  openAdd(): void {
    this.form = this.emptyForm();
    this.isEditing = false;
    this.newSubjectInput = '';
    this.showModal = true;
    this.cdr.detectChanges();
  }

  openEdit(f: IFaculty): void {
    this.form = { ...f, subjects: [...(f.subjects ?? [])] };
    this.isEditing = true;
    this.newSubjectInput = '';
    this.showModal = true;
    this.cdr.detectChanges();
  }

  closeModal(): void { this.showModal = false; this.cdr.detectChanges(); }

  addSubject(): void {
    const s = this.newSubjectInput.trim().toUpperCase();
    if (s && !this.form.subjects!.includes(s)) {
      this.form.subjects!.push(s);
      this.newSubjectInput = '';
      this.cdr.detectChanges();
    }
  }

  removeSubject(i: number): void {
    this.form.subjects!.splice(i, 1);
    this.cdr.detectChanges();
  }

  onSubjectKeydown(e: KeyboardEvent): void {
    if (e.key === 'Enter') { e.preventDefault(); this.addSubject(); }
  }

  save(): void {
    if (!this.form.first_name || !this.form.last_name || !this.form.email) {
      this.showToast('error', 'First name, last name, and email are required.'); return;
    }
    this.isSaving = true;
    const action = this.isEditing ? 'update_faculty' : 'create_faculty';
    this.http.post<any>(`${this.api}?action=${action}`, this.form).subscribe({
      next: (res) => {
        this.isSaving = false;
        if (res.success) {
          this.showToast('success', this.isEditing ? 'Faculty updated!' : `Faculty created! ID: ${res.generated_id}`);
          this.closeModal();
          this.loadFaculty();
        } else {
          this.showToast('error', res.message || 'Save failed.');
        }
        this.cdr.detectChanges();
      },
      error: () => { this.isSaving = false; this.showToast('error', 'Server error.'); this.cdr.detectChanges(); }
    });
  }

  confirmDelete(f: IFaculty): void {
    this.deleteTarget = f;
    this.showDeleteModal = true;
    this.cdr.detectChanges();
  }

  closeDeleteModal(): void { this.showDeleteModal = false; this.deleteTarget = null; this.cdr.detectChanges(); }

  doDelete(): void {
    if (!this.deleteTarget) return;
    this.isDeleting = true;
    this.http.post<any>(`${this.api}?action=delete_faculty`, { id: this.deleteTarget.id }).subscribe({
      next: (res) => {
        this.isDeleting = false;
        if (res.success) { this.showToast('success', 'Faculty deleted.'); this.loadFaculty(); }
        else { this.showToast('error', res.message || 'Delete failed.'); }
        this.closeDeleteModal();
        this.cdr.detectChanges();
      },
      error: () => { this.isDeleting = false; this.showToast('error', 'Server error.'); this.cdr.detectChanges(); }
    });
  }

  showToast(type: 'success' | 'error', message: string): void {
    this.toast = { show: true, type, message };
    setTimeout(() => { this.toast.show = false; this.cdr.detectChanges(); }, 4000);
  }

  get activeCount()   { return this.facultyList.filter(f => f.status === 'Active').length; }
  get inactiveCount() { return this.facultyList.filter(f => f.status === 'Inactive').length; }
  get onLeaveCount()  { return this.facultyList.filter(f => f.status === 'On Leave').length; }
}