import { Component, OnInit, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient, HttpHeaders } from '@angular/common/http';

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
  imports: [CommonModule, FormsModule],
  templateUrl: './students.html',
  styleUrl: './students.css',
})
export class Students implements OnInit {
  private apiUrl = 'http://localhost/sia-api/admin.php';

  private getHeaders(): { headers: HttpHeaders } {
    const token = sessionStorage.getItem('token') || localStorage.getItem('token') || '';
    return { headers: new HttpHeaders({ Authorization: `Bearer ${token}` }) };
  }

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
  filterStatus    = '';
  filterYearLevel = '';
  currentPage  = 1;
  totalPages   = 1;
  totalStudents = 0;
  pageSize     = 25;

  // Programs list for filter dropdown
  programs: string[] = [];

  toast = { show: false, type: 'success' as 'success' | 'error', message: '' };
  searchTimeout: any;

  constructor(private http: HttpClient, private cdr: ChangeDetectorRef) {}

  ngOnInit(): void {
    this.loadStats();
    this.loadStudents();
  }

  loadStats(): void {
    this.http.get<any>(`${this.apiUrl}?action=get_student_stats`, this.getHeaders()).subscribe({
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

    this.http.get<any>(`${this.apiUrl}?${params}`, this.getHeaders()).subscribe({
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
    this.http.get<any>(`${this.apiUrl}?action=get_student_detail&id=${student.id}`, this.getHeaders()).subscribe({
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

  closeDetail(): void { this.showDetailModal = false; this.selectedStudent = null; }

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
      Graduated: 'badge-graduated',
    };
    return map[status] || 'badge-default';
  }

  showToast(type: 'success' | 'error', message: string): void {
    this.toast = { show: true, type, message };
    setTimeout(() => { this.toast.show = false; this.cdr.detectChanges(); }, 3500);
  }
}