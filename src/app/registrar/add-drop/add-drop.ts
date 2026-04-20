import { Component, OnInit, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../environment';

interface Student {
  id: number; studentNumber: string; firstName: string; lastName: string;
  fullName: string; program: string; yearLevel: string; semester: string;
  studentCategory: string; enrollmentStatus: string; approvalStatus: string;
}
interface EnrolledSubject {
  enrollment_id: number; course_id: number; code: string; name: string;
  credits: number; instructor: string; day: string; time: string;
  room: string; semester: string; status: string;
}
interface AvailableSubject {
  id: number; code: string; name: string; credits: number;
  instructor: string; day: string; time: string; room: string;
  capacity: number; enrolled_count: number; available_seats: number;
  year_level?: string; semester?: string;
  isPastSubject?: boolean; isRetake?: boolean; isFutureSemester?: boolean;
  prereqMet?: boolean; prereqList?: string;
}
interface ADRequest {
  id: number; request_type: string; course_id: number; enrollment_id: number;
  code: string; course_name: string; credits: number; instructor: string;
  day: string; time: string; reason: string; status: string; remarks: string;
  created_at: string; first_name: string; last_name: string;
  student_number: string; program: string; year_level: string; student_category: string;
}
interface AddDropWindow { id?: number; start_date: string; end_date: string; label: string; is_active: number; }

@Component({
  selector: 'app-add-drop',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './add-drop.html',
  styleUrl: './add-drop.css'
})
export class AddDropComponent implements OnInit {
  private api = environment.enrollApi;

  mainTab: 'requests' | 'manual' | 'window' = 'requests';

  // ── Requests tab ──────────────────────────────────────────────
  allRequests: ADRequest[] = [];
  statusFilter = 'Pending';
  isLoadingReqs = false;
  showProcessModal = false;
  processReq: ADRequest | null = null;
  processAction: 'Approved' | 'Rejected' = 'Approved';
  processRemarks = '';
  isProcessing = false;

  // ── Manual tab ────────────────────────────────────────────────
  searchQuery = ''; searchResults: Student[] = []; isSearching = false; searchDebounce: any;
  selectedStudent: Student | null = null;
  enrolled: EnrolledSubject[] = [];
  available: AvailableSubject[] = [];
  isLoadingSubjects = false;
  manualTab: 'drop' | 'add' = 'drop';
  avFilter = ''; enFilter = '';
  // New: semester + year-level filters for the manual add panel
  avSemFilter  = '';
  avYearFilter = '';
  showManualModal = false; manualAction: 'add' | 'drop' = 'add';
  manualSubject: any = null; manualReason = '';
  isManualProcessing = false;

  // ── Window tab ────────────────────────────────────────────────
  currentWindow: AddDropWindow | null = null;
  windowOpen = false;
  isLoadingWindow = false;
  windowForm = { start_date: '', end_date: '', label: '' };
  isSavingWindow = false;
  windowSuccess = '';
  windowError = '';

  successMsg = ''; errorMsg = '';

  constructor(private http: HttpClient, private cdr: ChangeDetectorRef) {}

  ngOnInit() {
    this.loadRequests();
    this.loadWindow();
  }

  // ── Requests ──────────────────────────────────────────────────
  loadRequests() {
    this.isLoadingReqs = true;
    this.http.get<any>(`${this.api}?action=get_add_drop_requests&status=${encodeURIComponent(this.statusFilter)}`).subscribe({
      next: r => { this.allRequests = r.requests || []; this.isLoadingReqs = false; this.cdr.detectChanges(); },
      error: () => { this.isLoadingReqs = false; this.cdr.detectChanges(); }
    });
  }

  openProcess(req: ADRequest, action: 'Approved' | 'Rejected') {
    this.processReq = req; this.processAction = action;
    this.processRemarks = ''; this.errorMsg = '';
    this.showProcessModal = true; this.cdr.detectChanges();
  }
  closeProcess() { this.showProcessModal = false; this.cdr.detectChanges(); }

  confirmProcess() {
    if (!this.processReq) return;
    this.isProcessing = true; this.errorMsg = '';
    const u = JSON.parse(sessionStorage.getItem('currentUser') || '{}');
    const body = {
      request_id: this.processReq.id, action: this.processAction,
      remarks: this.processRemarks, processed_by: u.id || 0
    };
    this.http.post<any>(`${this.api}?action=process_add_drop`, body).subscribe({
      next: r => {
        this.isProcessing = false;
        if (r.success) {
          this.showProcessModal = false;
          this.successMsg = r.message;
          this.loadRequests();
          setTimeout(() => { this.successMsg = ''; this.cdr.detectChanges(); }, 4000);
        } else { this.errorMsg = r.message || 'Failed.'; }
        this.cdr.detectChanges();
      },
      error: () => { this.isProcessing = false; this.errorMsg = 'Server error.'; this.cdr.detectChanges(); }
    });
  }

  // ── Manual ────────────────────────────────────────────────────
  onSearch() {
    clearTimeout(this.searchDebounce);
    if (this.searchQuery.trim().length < 2) { this.searchResults = []; return; }
    this.searchDebounce = setTimeout(() => {
      this.isSearching = true;
      this.http.get<any>(`${this.api}?action=search_students&q=${encodeURIComponent(this.searchQuery)}`).subscribe({
        next: r => { this.searchResults = r.students || []; this.isSearching = false; this.cdr.detectChanges(); },
        error: () => { this.isSearching = false; this.cdr.detectChanges(); }
      });
    }, 350);
  }

  selectStudent(s: Student) {
    this.selectedStudent = s;
    this.searchQuery = ''; this.searchResults = [];
    this.avFilter = ''; this.enFilter = '';
    this.avSemFilter = ''; this.avYearFilter = '';
    this.successMsg = ''; this.errorMsg = '';
    this.isLoadingSubjects = true;
    this.http.get<any>(`${this.api}?action=get_student_enrollments&student_id=${s.id}`).subscribe({
      next: r => {
        this.enrolled  = r.enrolled  || [];
        this.available = (r.available || []).map((av: any) => ({
          ...av,
          isPastSubject:    av.isPastSubject    || false,
          isRetake:         av.isRetake         || false,
          isFutureSemester: av.isFutureSemester || false,
          prereqMet:        av.prereqMet        !== undefined ? av.prereqMet : true,
          prereqList:       av.prereqList       || null,
        }));

        // Default semester filter to student's current semester from backend
        const backendSem: string = r.student_semester || '';
        if (backendSem && !this.avSemFilter) {
          const matched = this.available.find(av =>
            av.semester && av.semester.toLowerCase().startsWith(backendSem.toLowerCase())
          )?.semester || '';
          this.avSemFilter = matched || backendSem;
        }

        this.isLoadingSubjects = false;
        this.cdr.detectChanges();
      },
      error: () => { this.isLoadingSubjects = false; this.cdr.detectChanges(); }
    });
    this.cdr.detectChanges();
  }

  get filteredEnrolled() {
    const q = this.enFilter.toLowerCase();
    return q
      ? this.enrolled.filter(s => s.code.toLowerCase().includes(q) || s.name.toLowerCase().includes(q))
      : this.enrolled;
  }

  get filteredAvailable() {
    let list = [...this.available];
    // Registrar sees ALL subjects (no future-sem block) — they can manually add anything.
    // But we do filter by the dropdowns if set, so they can quickly find the right subject.
    if (this.avSemFilter)  list = list.filter(s => s.semester === this.avSemFilter);
    if (this.avYearFilter) list = list.filter(s => s.year_level === this.avYearFilter);
    const q = this.avFilter.toLowerCase();
    if (q) list = list.filter(s => s.code.toLowerCase().includes(q) || s.name.toLowerCase().includes(q));
    return list;
  }

  get uniqueAvSemesters(): string[] {
    return [...new Set(this.available.map(s => s.semester).filter(Boolean) as string[])].sort();
  }
  get uniqueAvYearLevels(): string[] {
    return [...new Set(this.available.map(s => s.year_level).filter(Boolean) as string[])].sort();
  }

  openManual(subject: any, action: 'add' | 'drop') {
    this.manualAction = action; this.manualSubject = subject;
    this.manualReason = ''; this.errorMsg = '';
    this.showManualModal = true; this.cdr.detectChanges();
  }
  closeManual() { this.showManualModal = false; this.cdr.detectChanges(); }

  confirmManual() {
    if (!this.selectedStudent) return;
    this.isManualProcessing = true; this.errorMsg = '';
    const obs = this.manualAction === 'add'
      ? this.http.post<any>(`${this.api}?action=registrar_add_subject`, {
          student_id: this.selectedStudent.id,
          course_id:  this.manualSubject.id,
          reason:     this.manualReason || 'Manual add by Registrar'
        })
      : this.http.post<any>(`${this.api}?action=registrar_drop_subject`, {
          student_id:    this.selectedStudent.id,
          enrollment_id: this.manualSubject.enrollment_id,
          reason:        this.manualReason || 'Manual drop by Registrar'
        });
    obs.subscribe({
      next: r => {
        this.isManualProcessing = false;
        if (r.success) {
          this.showManualModal = false;
          this.successMsg = r.message;
          this.selectStudent(this.selectedStudent!);
        } else { this.errorMsg = r.message || 'Failed.'; }
        this.cdr.detectChanges();
      },
      error: () => { this.isManualProcessing = false; this.errorMsg = 'Server error.'; this.cdr.detectChanges(); }
    });
  }

  // ── Window Management ─────────────────────────────────────────
  loadWindow() {
    this.isLoadingWindow = true;
    this.http.get<any>(`${this.api}?action=get_add_drop_window`).subscribe({
      next: r => {
        this.currentWindow = r.window || null;
        this.windowOpen    = r.is_open || false;
        this.isLoadingWindow = false;
        if (this.currentWindow) {
          this.windowForm.start_date = this.toDatetimeLocal(this.currentWindow.start_date);
          this.windowForm.end_date   = this.toDatetimeLocal(this.currentWindow.end_date);
          this.windowForm.label      = this.currentWindow.label || '';
        }
        this.cdr.detectChanges();
      },
      error: () => { this.isLoadingWindow = false; this.cdr.detectChanges(); }
    });
  }

  saveWindow() {
    if (!this.windowForm.start_date || !this.windowForm.end_date) {
      this.windowError = 'Please set both start and end date/time.'; return;
    }
    this.isSavingWindow = true; this.windowError = ''; this.windowSuccess = '';
    const body = {
      start_date: this.windowForm.start_date.replace('T', ' ') + ':00',
      end_date:   this.windowForm.end_date.replace('T', ' ')   + ':00',
      label:      this.windowForm.label
    };
    this.http.post<any>(`${this.api}?action=set_add_drop_window`, body).subscribe({
      next: r => {
        this.isSavingWindow = false;
        if (r.success) { this.windowSuccess = r.message; this.loadWindow(); }
        else { this.windowError = r.message || 'Failed to save.'; }
        this.cdr.detectChanges();
      },
      error: () => { this.isSavingWindow = false; this.windowError = 'Server error.'; this.cdr.detectChanges(); }
    });
  }

  // ── Helpers ───────────────────────────────────────────────────
  toDatetimeLocal(s: string) {
    if (!s) return '';
    return s.replace(' ', 'T').substring(0, 16);
  }
  fmtDate(s: string) {
    if (!s) return '';
    return new Date(s).toLocaleString('en-PH', {
      month: 'short', day: 'numeric', year: 'numeric',
      hour: 'numeric', minute: '2-digit'
    });
  }
  clearStudent() {
    this.selectedStudent = null; this.enrolled = []; this.available = [];
    this.avSemFilter = ''; this.avYearFilter = '';
    this.cdr.detectChanges();
  }
  getInitials(s: any) {
    return ((s.firstName || s.first_name || '')[0] || '') +
           ((s.lastName  || s.last_name  || '')[0] || '');
  }
  getCatColor(cat: string) {
    return cat === 'SHS' ? '#7c3aed' : cat === 'TVET' ? '#0891b2' : '#1d4ed8';
  }
  pendingCount() { return this.allRequests.filter(r => r.status === 'Pending').length; }
  statusCls(s: string) { return s === 'Approved' ? 'adr-approved' : s === 'Rejected' ? 'adr-rejected' : 'adr-pending'; }
  seatPct(s: AvailableSubject) { return s.capacity > 0 ? Math.round((s.enrolled_count / s.capacity) * 100) : 0; }
  isMinor(code: string) { return /^(GE|PE|NSTP|OJT)/i.test(code || ''); }
}