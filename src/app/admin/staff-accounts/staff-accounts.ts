import { Component, OnInit, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../environment';

type StaffRole = 'admin' | 'accounting' | 'registrar';

interface IStaff {
  user_id: number;
  profile_id: number;
  email: string;
  role: StaffRole;
  is_active: boolean | number;
  account_created: string;
  first_name: string;
  last_name: string;
  phone: string;
  position: string;
  department: string;
}

interface StaffForm {
  user_id?: number;
  first_name: string;
  last_name: string;
  email: string;
  role: StaffRole | '';
  phone: string;
  position: string;
  department: string;
}

@Component({
  selector: 'app-staff-accounts',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './staff-accounts.html',
  styleUrl: './staff-accounts.css',
})
export class StaffAccounts implements OnInit {
  private api = environment.adminApi;

  staffList: IStaff[] = [];
  isLoading = false;
  searchQuery = '';
  filterRole: StaffRole | '' = '';

  // Pagination
  page = 1;
  limit = 25;
  total = 0;
  totalPages = 0;

  // Create / Edit modal
  showModal = false;
  isEditing = false;
  isSaving = false;
  form: StaffForm = this.emptyForm();

  // Reset password modal
  showResetModal = false;
  resetTarget: IStaff | null = null;
  resetPassword = '';
  isResetting = false;
  resetResult = '';

  // Delete modal
  showDeleteModal = false;
  deleteTarget: IStaff | null = null;
  isDeleting = false;

  toast: { show: boolean; type: 'success' | 'error'; message: string } =
    { show: false, type: 'success', message: '' };

  readonly DEPARTMENTS = [
    'Administration', 'Accounting', 'Registrar', 'IT', 'Finance',
    'Human Resources', 'Student Affairs', 'Academic Affairs',
  ];

  readonly ROLES: { value: StaffRole; label: string }[] = [
    { value: 'admin',      label: '⚙️ Admin' },
    { value: 'accounting', label: '💰 Accounting' },
    { value: 'registrar',  label: '📋 Registrar' },
  ];

  constructor(private http: HttpClient, private cdr: ChangeDetectorRef) {}

  ngOnInit(): void { this.loadStaff(); }

  // ── Load ────────────────────────────────────────────────────────────────────
  loadStaff(): void {
    this.isLoading = true;
    const params = new URLSearchParams({
      action: 'get_staff_accounts',
      page: String(this.page),
      limit: String(this.limit),
    });
    if (this.searchQuery.trim()) params.set('q', this.searchQuery.trim());
    if (this.filterRole)         params.set('role', this.filterRole);

    this.http.get<any>(`${this.api}?${params}`).subscribe({
      next: (res) => {
        this.isLoading = false;
        if (res.success) {
          this.staffList  = res.staff;
          this.total      = res.total;
          this.totalPages = res.totalPages;
        }
        this.cdr.detectChanges();
      },
      error: () => {
        this.isLoading = false;
        this.showToast('error', 'Cannot load staff accounts. Check connection.');
        this.cdr.detectChanges();
      },
    });
  }

  onSearch(): void { this.page = 1; this.loadStaff(); }
  setRole(r: StaffRole | ''): void { this.filterRole = r; this.page = 1; this.loadStaff(); }
  prevPage(): void { if (this.page > 1) { this.page--; this.loadStaff(); } }
  nextPage(): void { if (this.page < this.totalPages) { this.page++; this.loadStaff(); } }

  // ── Create / Edit modal ─────────────────────────────────────────────────────
  emptyForm(): StaffForm {
    return { first_name: '', last_name: '', email: '', role: '', phone: '', position: '', department: '' };
  }

  openAdd(): void {
    this.form = this.emptyForm();
    this.isEditing = false;
    this.showModal = true;
    this.cdr.detectChanges();
  }

  openEdit(s: IStaff): void {
    this.form = {
      user_id:    s.user_id,
      first_name: s.first_name,
      last_name:  s.last_name,
      email:      s.email,
      role:       s.role,   // shown read-only in edit mode
      phone:      s.phone      ?? '',
      position:   s.position   ?? '',
      department: s.department ?? '',
    };
    this.isEditing = true;
    this.showModal = true;
    this.cdr.detectChanges();
  }

  closeModal(): void { this.showModal = false; this.cdr.detectChanges(); }

  save(): void {
    if (!this.form.first_name || !this.form.last_name || !this.form.email) {
      this.showToast('error', 'First name, last name, and email are required.'); return;
    }
    if (!this.isEditing && !this.form.role) {
      this.showToast('error', 'Please select a role.'); return;
    }

    this.isSaving = true;
    const action = this.isEditing ? 'update_staff_account' : 'create_staff_account';
    this.http.post<any>(`${this.api}?action=${action}`, this.form).subscribe({
      next: (res) => {
        this.isSaving = false;
        if (res.success) {
          const msg = this.isEditing
            ? 'Staff account updated.'
            : `Account created! Default password: ${res.temp_credential_hint?.split(' ')[0] ?? '—'}`;
          this.showToast('success', msg);
          this.closeModal();
          this.loadStaff();
        } else {
          this.showToast('error', res.message || 'Save failed.');
        }
        this.cdr.detectChanges();
      },
      error: () => { this.isSaving = false; this.showToast('error', 'Server error.'); this.cdr.detectChanges(); },
    });
  }

  // ── Toggle active ───────────────────────────────────────────────────────────
  toggleActive(s: IStaff): void {
    const newState = !s.is_active;
    this.http.post<any>(`${this.api}?action=toggle_staff_status`, {
      user_id: s.user_id,
      is_active: newState ? 1 : 0,
    }).subscribe({
      next: (res) => {
        if (res.success) {
          s.is_active = newState;
          this.showToast('success', newState ? 'Account activated.' : 'Account deactivated.');
        } else {
          this.showToast('error', res.message || 'Toggle failed.');
        }
        this.cdr.detectChanges();
      },
      error: () => { this.showToast('error', 'Server error.'); this.cdr.detectChanges(); },
    });
  }

  // ── Reset password modal ────────────────────────────────────────────────────
  openReset(s: IStaff): void {
    this.resetTarget   = s;
    this.resetPassword = '';
    this.resetResult   = '';
    this.showResetModal = true;
    this.cdr.detectChanges();
  }

  closeResetModal(): void { this.showResetModal = false; this.resetTarget = null; this.cdr.detectChanges(); }

  doReset(): void {
    if (!this.resetTarget) return;
    this.isResetting = true;
    this.http.post<any>(`${this.api}?action=reset_staff_password`, {
      user_id:      this.resetTarget.user_id,
      new_password: this.resetPassword || '',
    }).subscribe({
      next: (res) => {
        this.isResetting = false;
        if (res.success) {
          this.resetResult = res.temp_credential_hint ?? 'Password reset.';
        } else {
          this.showToast('error', res.message || 'Reset failed.');
          this.closeResetModal();
        }
        this.cdr.detectChanges();
      },
      error: () => { this.isResetting = false; this.showToast('error', 'Server error.'); this.cdr.detectChanges(); },
    });
  }

  // ── Delete modal ────────────────────────────────────────────────────────────
  confirmDelete(s: IStaff): void {
    this.deleteTarget = s;
    this.showDeleteModal = true;
    this.cdr.detectChanges();
  }

  closeDeleteModal(): void { this.showDeleteModal = false; this.deleteTarget = null; this.cdr.detectChanges(); }

  doDelete(): void {
    if (!this.deleteTarget) return;
    this.isDeleting = true;
    this.http.post<any>(`${this.api}?action=delete_staff_account`, { user_id: this.deleteTarget.user_id }).subscribe({
      next: (res) => {
        this.isDeleting = false;
        if (res.success) {
          this.showToast('success', 'Account deleted.');
          this.loadStaff();
        } else {
          this.showToast('error', res.message || 'Delete failed.');
        }
        this.closeDeleteModal();
        this.cdr.detectChanges();
      },
      error: () => { this.isDeleting = false; this.showToast('error', 'Server error.'); this.cdr.detectChanges(); },
    });
  }

  // ── Helpers ─────────────────────────────────────────────────────────────────
  showToast(type: 'success' | 'error', message: string): void {
    this.toast = { show: true, type, message };
    setTimeout(() => { this.toast.show = false; this.cdr.detectChanges(); }, 4500);
  }

  roleLabel(r: string): string {
    return this.ROLES.find(x => x.value === r)?.label ?? r;
  }

  initials(s: IStaff): string {
    return ((s.first_name?.[0] ?? '') + (s.last_name?.[0] ?? '')).toUpperCase();
  }

  get currentYear(): number { return new Date().getFullYear(); }
  get totalCount():      number { return this.total; }
  get adminCount():      number { return this.staffList.filter(s => s.role === 'admin').length; }
  get accountingCount(): number { return this.staffList.filter(s => s.role === 'accounting').length; }
  get registrarCount():  number { return this.staffList.filter(s => s.role === 'registrar').length; }
  get activeCount():     number { return this.staffList.filter(s => s.is_active).length; }
}