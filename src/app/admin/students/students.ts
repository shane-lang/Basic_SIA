import { Component, OnInit, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient, HttpHeaders } from '@angular/common/http';
import { environment } from '../../environment';
import { MaskEmailPipe } from '../../pipes/mask-email.pipe';

interface Student {
  id: number;
  student_number: string;
  first_name: string;
  last_name: string;
  email: string;
  program: string;
  year_level: string;
  semester: string;
  student_type: string;
  enrollment_status: string;
  contact_number: string;
  address: string;
  created_at: string;
  // ── Scholar ──
  is_scholar: number;
  scholar_type: string;
  scholar_grantor: string;
  scholar_discount: number;
  payment_status: string;
}

interface StudentDetail extends Student {
  user_email: string;
  account_created: string;
  enrollments: any[];
  grades: any[];
}

interface StudentStats {
  total: number;
  enrolled: number;
  pending: number;
  inactive: number;
  byProgram: { program: string; cnt: number }[];
  byYearLevel: { year_level: string; cnt: number }[];
}

@Component({
  selector: 'app-students',
  standalone: true,
  imports: [CommonModule, FormsModule, MaskEmailPipe],
  templateUrl: './students.html',
  styleUrl: './students.css',
})
export class Students implements OnInit {
  private apiUrl       = environment.adminApi;
  private retentionUrl = environment.retentionApi; // ← NEW

  // State
  students: Student[]       = [];
  stats: StudentStats | null = null;
  selectedStudent: StudentDetail | null = null;
  isLoading         = false;
  isLoadingDetail   = false;
  showDetailModal   = false;

  // Filters & pagination
  searchQuery  = '';
  filterProgram   = '';
  filterStatus    = ''
  filterYearLevel = '';
  currentPage  = 1;
  totalPages   = 1;
  totalStudents = 0;
  pageSize     = 25;

  // Programs list for filter dropdown
  programs: string[] = [];

  toast = { show: false, type: 'success' as 'success' | 'error', message: '' };
  searchTimeout: any;

  // ── NEW: Archive modal state ───────────────────────────────────────────────
  showArchiveModal = false;
  archiveReason    = '';
  isArchiving      = false;
  archiveReasons   = [
    'Graduated',
    'Transferred to another school',
    'Dropped / Stopped attending',
    'Completed program',
    'Administrative archiving',
  ];

  constructor(private http: HttpClient, private cdr: ChangeDetectorRef) {}

  ngOnInit(): void {
    this.loadStats();
    this.loadStudents();
  }

  loadStats(): void {
    this.http.get<any>(`${this.apiUrl}?action=get_student_stats`).subscribe({
      next: (res) => {
        if (res.success) {
          this.stats = res;
          this.programs = (res.byProgram || []).map((p: any) => p.program).filter(Boolean);
          this.cdr.detectChanges();
        }
      }
    });
  }

  loadStudents(page = 1): void {
    this.isLoading   = true;
    this.currentPage = page;
    const params = new URLSearchParams({
      action: 'get_students',
      page: String(page),
      limit: String(this.pageSize),
      ...(this.searchQuery    && { q:          this.searchQuery }),
      ...(this.filterProgram  && { program:    this.filterProgram }),
      ...(this.filterStatus   && { status:     this.filterStatus }),
      ...(this.filterYearLevel && { year_level: this.filterYearLevel }),
    });

    this.http.get<any>(`${this.apiUrl}?${params}`).subscribe({
      next: (res) => {
        this.isLoading    = false;
        this.students     = res.success ? (res.students || []) : [];
        this.totalPages   = res.totalPages   || 1;
        this.totalStudents = res.total       || 0;
        this.cdr.detectChanges();
      },
      error: () => { this.isLoading = false; this.cdr.detectChanges(); }
    });
  }

  onSearchInput(): void {
    clearTimeout(this.searchTimeout);
    this.searchTimeout = setTimeout(() => this.loadStudents(1), 350);
  }

  applyFilters(): void { this.loadStudents(1); }

  clearFilters(): void {
    this.searchQuery = ''; this.filterProgram = '';
    this.filterStatus = ''; this.filterYearLevel = '';
    this.loadStudents(1);
  }

  openDetail(student: Student): void {
    this.showDetailModal  = true;
    this.isLoadingDetail  = true;
    this.selectedStudent  = null;
    this.http.get<any>(`${this.apiUrl}?action=get_student_detail&id=${student.id}`).subscribe({
      next: (res) => {
        this.isLoadingDetail = false;
        if (res.success) {
          this.selectedStudent = { ...res.student, enrollments: res.enrollments, grades: res.grades };
        }
        this.cdr.detectChanges();
      },
      error: () => { this.isLoadingDetail = false; this.cdr.detectChanges(); }
    });
  }

  closeDetail(): void {
    this.showDetailModal = false;
    this.selectedStudent = null;
    this.closeArchiveModal();
  }

  prevPage(): void { if (this.currentPage > 1) this.loadStudents(this.currentPage - 1); }
  nextPage(): void { if (this.currentPage < this.totalPages) this.loadStudents(this.currentPage + 1); }

  getPages(): number[] {
    const pages: number[] = [];
    const start = Math.max(1, this.currentPage - 2);
    const end   = Math.min(this.totalPages, start + 4);
    for (let i = start; i <= end; i++) pages.push(i);
    return pages;
  }

  getInitials(s: Student): string {
    return ((s.first_name?.[0] || '') + (s.last_name?.[0] || '')).toUpperCase();
  }

  statusClass(status: string): string {
    const map: Record<string, string> = {
      Enrolled: 'badge-enrolled', Pending: 'badge-pending',
      Inactive: 'badge-inactive', Dropped: 'badge-dropped',
      Graduated: 'badge-graduated', Completed: 'badge-completed',
      Failed: 'badge-dropped',
    };
    return map[status] || 'badge-default';
  }

  scholarLabel(s: Student): string {
    if (!s.is_scholar) return '';
    return s.scholar_type || 'Scholar';
  }

  showToast(type: 'success' | 'error', message: string): void {
    this.toast = { show: true, type, message };
    setTimeout(() => { this.toast.show = false; this.cdr.detectChanges(); }, 3500);
  }

  isMinor(code: string): boolean {
    if (!code) return false;
    const upper = code.toUpperCase();
    return upper.startsWith('GE') ||
           upper.startsWith('PE') ||
           upper.startsWith('NSTP') ||
           upper.startsWith('OJT');
  }

  // ── NEW: Archive methods ───────────────────────────────────────────────────

  openArchiveModal(): void {
    this.archiveReason    = '';
    this.showArchiveModal = true;
    this.cdr.detectChanges();
  }

  closeArchiveModal(): void {
    this.showArchiveModal = false;
    this.archiveReason    = '';
    this.isArchiving      = false;
    this.cdr.detectChanges();
  }

  confirmArchive(): void {
    if (!this.selectedStudent) return;
    if (!this.archiveReason.trim()) {
      this.showToast('error', 'Please select or enter a reason for archiving.');
      return;
    }

    this.isArchiving = true;
    this.http.post<any>(`${this.retentionUrl}?action=archive_student`, {
      student_id: this.selectedStudent.id,
      reason:     this.archiveReason.trim(),
    }).subscribe({
      next: (res) => {
        this.isArchiving = false;
        if (res.success) {
          this.showToast('success',
            `✅ ${res.full_name} (${res.student_number}) archived. ` +
            `PII scheduled for anonymization on ${res.scheduled_anonymize}.`
          );
          // Update the student's status in the list without reloading
          const idx = this.students.findIndex(s => s.id === this.selectedStudent!.id);
          if (idx !== -1) this.students[idx].enrollment_status = 'Dropped';
          this.closeDetail();
          this.loadStats();
        } else {
          this.showToast('error', res.message || 'Archive failed. Please try again.');
        }
        this.cdr.detectChanges();
      },
      error: () => {
        this.isArchiving = false;
        this.showToast('error', 'Network error. Could not archive student.');
        this.cdr.detectChanges();
      }
    });
  }
}