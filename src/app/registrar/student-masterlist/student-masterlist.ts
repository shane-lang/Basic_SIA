import { Component, OnInit, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient, HttpHeaders } from '@angular/common/http';

type MainTab = 'info' | 'student-masterlist' | 'subject-masterlist';

interface Student {
  id: number;
  studentNumber: string;
  firstName: string;
  lastName: string;
  middleName: string;
  suffix: string;
  fullName: string;
  email: string;
  phone: string;
  dateOfBirth: string;
  age: string;
  sex: string;
  religion: string;
  placeOfBirth: string;
  citizenship: string;
  motherTongue: string;
  address: string;
  lrnNo: string;
  psaBirthCertNo: string;
  isIndigenous: string;
  hasSpecialNeeds: string;
  specialNeedsDetails: string;
  hasAssistiveTech: string;
  assistiveTechDetails: string;
  strand: string;
  learningDelivery: string;
  lastSchoolAttended: string;
  guardianName: string;
  guardianAddress: string;
  guardianContact: string;
  program: string;
  yearLevel: string;
  semester: string;
  studentType: string;
  studentCategory: string;
  enrollmentStatus: string;
  paymentStatus: string;
  approvalStatus: string;
  isScholar: number;
  scholarType: string;
  enrollmentDate: string;
  initials: string;
}

interface SubjectRecord {
  studentNumber: string;
  fullName: string;
  program: string;
  yearLevel: string;
  courseCode: string;
  courseName: string;
  credits: number;
  instructor: string;
  semester: string;
  prelimGrade: number | null;
  midtermGrade: number | null;
  finalGrade: number | null;
  overall: number | null;
  remarks: string;
  status: string;
}

@Component({
  selector: 'app-student-masterlist',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './student-masterlist.html',
  styleUrl: './student-masterlist.css',
})
export class StudentMasterlistComponent implements OnInit {
  private api = 'http://localhost/sia-api/registrar.php';

  private getHeaders(): { headers: HttpHeaders } {
    const token = sessionStorage.getItem('token') || localStorage.getItem('token') || '';
    return { headers: new HttpHeaders({ Authorization: `Bearer ${token}` }) };
  }

  constructor(private http: HttpClient, private cdr: ChangeDetectorRef) {}

  activeTab: MainTab = 'student-masterlist';

  // ── Shared filters ──────────────────────────────────────
  searchQuery    = '';
  filterCategory = '';
  filterProgram  = '';
  filterYearLevel = '';
  filterSemester  = '';
  filterStatus    = '';
  searchTimeout: any;

  // ── Student Masterlist ──────────────────────────────────
  students: Student[] = [];
  isLoadingStudents = false;
  currentPage   = 1;
  totalPages    = 1;
  totalStudents = 0;
  programs: string[] = [];

  // ── Student Info (detail) ───────────────────────────────
  selectedStudent: Student | null = null;
  isLoadingDetail = false;

  // ── Subject Masterlist ──────────────────────────────────
  subjectRecords: SubjectRecord[] = [];
  isLoadingSubjects = false;
  subjectSearch = '';
  subjectFilterCourse = '';
  courses: string[] = [];
  subjectSearchTimeout: any;

  ngOnInit(): void {
    this.loadStudents();
  }

  // ── Load Students ────────────────────────────────────────
  loadStudents(page = 1): void {
    this.isLoadingStudents = true;
    this.currentPage = page;
    const p = new URLSearchParams({
      action: 'masterlist_students',
      page: String(page), limit: '20',
      ...(this.searchQuery     && { q: this.searchQuery }),
      ...(this.filterCategory  && { category: this.filterCategory }),
      ...(this.filterProgram   && { program: this.filterProgram }),
      ...(this.filterYearLevel && { year_level: this.filterYearLevel }),
      ...(this.filterSemester  && { semester: this.filterSemester }),
      ...(this.filterStatus    && { status: this.filterStatus }),
    });
    this.http.get<any>(`${this.api}?${p}`, this.getHeaders()).subscribe({
      next: (res) => {
        this.isLoadingStudents = false;
        if (res.success) {
          this.students      = res.students || [];
          this.totalPages    = res.totalPages || 1;
          this.totalStudents = res.total || 0;
          if (!this.programs.length) this.programs = res.programs || [];
        }
        this.cdr.detectChanges();
      },
      error: () => { this.isLoadingStudents = false; this.cdr.detectChanges(); }
    });
  }

  onSearch(): void {
    clearTimeout(this.searchTimeout);
    this.searchTimeout = setTimeout(() => this.loadStudents(1), 350);
  }

  clearFilters(): void {
    this.searchQuery = ''; this.filterCategory = ''; this.filterProgram = '';
    this.filterYearLevel = ''; this.filterSemester = ''; this.filterStatus = '';
    this.loadStudents(1);
  }

  prevPage(): void { if (this.currentPage > 1) this.loadStudents(this.currentPage - 1); }
  nextPage(): void { if (this.currentPage < this.totalPages) this.loadStudents(this.currentPage + 1); }

  // ── Student Info View ────────────────────────────────────
  viewStudentInfo(s: Student): void {
    this.selectedStudent = s;
    this.activeTab = 'info';
    this.cdr.detectChanges();
  }

  backToMasterlist(): void {
    this.selectedStudent = null;
    this.activeTab = 'student-masterlist';
    this.cdr.detectChanges();
  }

  get previousSchools(): { level: string; school: string; year: string }[] {
    if (!this.selectedStudent?.lastSchoolAttended) return [];
    return this.selectedStudent.lastSchoolAttended.split(';').map(entry => {
      const clean = entry.trim();
      // Format: "Level - School Name (Year)"
      const match = clean.match(/^(.*?)\s*-\s*(.*?)\s*\(([^)]*)\)\s*$/);
      if (match) return { level: match[1].trim(), school: match[2].trim(), year: match[3].trim() };
      return { level: '—', school: clean, year: '—' };
    }).filter(s => s.school);
  }

  // ── Subject Masterlist ────────────────────────────────────
  switchToSubjects(): void {
    this.activeTab = 'subject-masterlist';
    if (!this.subjectRecords.length) this.loadSubjectMasterlist();
    this.cdr.detectChanges();
  }

  loadSubjectMasterlist(): void {
    this.isLoadingSubjects = true;
    const p = new URLSearchParams({
      action: 'masterlist_subjects',
      ...(this.subjectSearch       && { q: this.subjectSearch }),
      ...(this.filterCategory      && { category: this.filterCategory }),
      ...(this.filterSemester      && { semester: this.filterSemester }),
      ...(this.subjectFilterCourse && { course: this.subjectFilterCourse }),
    });
    this.http.get<any>(`${this.api}?${p}`, this.getHeaders()).subscribe({
      next: (res) => {
        this.isLoadingSubjects = false;
        if (res.success) {
          this.subjectRecords = res.records || [];
          if (!this.courses.length) this.courses = res.courses || [];
        }
        this.cdr.detectChanges();
      },
      error: () => { this.isLoadingSubjects = false; this.cdr.detectChanges(); }
    });
  }

  onSubjectSearch(): void {
    clearTimeout(this.subjectSearchTimeout);
    this.subjectSearchTimeout = setTimeout(() => this.loadSubjectMasterlist(), 350);
  }

  // ── Helpers ──────────────────────────────────────────────
  fmtGrade(g: number | null): string { return g !== null ? g.toFixed(2) : '—'; }

  gradeClass(g: number | null): string {
    if (g === null) return '';
    if (g <= 1.5) return 'g-excel';
    if (g <= 2.0) return 'g-good';
    if (g <= 3.0) return 'g-pass';
    return 'g-fail';
  }

  statusClass(s: string): string {
    const m: Record<string, string> = {
      Enrolled: 'st-enrolled', Pending: 'st-pending',
      Approved: 'st-approved', Rejected: 'st-rejected', Completed: 'st-completed'
    };
    return m[s] || '';
  }

  categoryClass(c: string): string {
    return c === 'College' ? 'cat-college' : c === 'SHS' ? 'cat-shs' : c === 'TVET' ? 'cat-tvet' : '';
  }
}