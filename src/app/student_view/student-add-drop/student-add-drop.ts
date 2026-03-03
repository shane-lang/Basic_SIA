import { Component, OnInit, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';

interface EnrolledSubject {
  enrollment_id: number; course_id: number;
  code: string; name: string; credits: number;
  instructor: string; day: string; time: string; room: string;
  semester: string; status: string;
}
interface AvailableSubject {
  id: number; code: string; name: string; credits: number;
  instructor: string; day: string; time: string; room: string;
  capacity: number; enrolled_count: number; available_seats: number;
}
interface ADRequest {
  id: number; request_type: string; course_id: number;
  code: string; course_name: string; credits: number;
  instructor: string; day: string; time: string;
  reason: string; status: string; remarks: string;
  created_at: string; processed_at: string;
}
interface AddDropWindow {
  start_date: string; end_date: string; label: string;
}

@Component({
  selector: 'app-student-add-drop',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './student-add-drop.html',
  styleUrl: './student-add-drop.css'
})
export class StudentAddDropComponent implements OnInit {
  private api = 'http://localhost/sia-api/enrollment.php';

  // FIX: store both user_id (from login) and student_id (from students table)
  userId    = 0;
  studentId = 0;

  activeTab: 'request' | 'history' = 'request';
  requestTab: 'drop' | 'add' = 'drop';
  enrolled: EnrolledSubject[]   = [];
  available: AvailableSubject[] = [];
  requests: ADRequest[]         = [];
  isLoading  = false;
  avFilter   = ''; enFilter = '';
  showModal  = false; modalType: 'add'|'drop' = 'drop';
  modalSubject: any = null; modalReason = '';
  isSubmitting = false; successMsg = ''; errorMsg = '';

  // Add/Drop window
  window: AddDropWindow | null = null;
  windowOpen = false;
  windowLoading = true;

  constructor(private http: HttpClient, private cdr: ChangeDetectorRef) {}

  private hdrs() {
    return { headers: { Authorization: `Bearer ${sessionStorage.getItem('token') ?? ''}` } };
  }

  ngOnInit() {
    const u = JSON.parse(sessionStorage.getItem('currentUser') || '{}');
    // FIX: Use user_id (the id from the users table stored at login)
    // The API will resolve this to the correct students.id
    this.userId    = u.id || 0;
    this.studentId = u.student_id || 0; // might be set if backend returned it
    this.loadWindow();
    this.loadAll();
  }

  loadWindow() {
    this.windowLoading = true;
    this.http.get<any>(`${this.api}?action=get_add_drop_window`, this.hdrs()).subscribe({
      next: r => {
        this.window     = r.window  || null;
        this.windowOpen = r.is_open || false;
        this.windowLoading = false;
        this.cdr.detectChanges();
      },
      error: () => { this.windowLoading = false; this.cdr.detectChanges(); }
    });
  }

  loadAll() {
    if (!this.userId && !this.studentId) return;
    this.isLoading = true;
    // Always pass user_id — server resolves to correct students.id
    const idParam = `user_id=${this.userId}`;

    this.http.get<any>(`${this.api}?action=get_student_enrollments&${idParam}`, this.hdrs()).subscribe({
      next: r => {
        this.enrolled  = r.enrolled  || [];
        this.available = r.available || [];
        // Cache the resolved student_id for display purposes
        if (r.student_id) this.studentId = r.student_id;
        this.isLoading = false;
        this.cdr.detectChanges();
      },
      error: () => { this.isLoading = false; this.cdr.detectChanges(); }
    });

    // Always pass user_id for requests too — server resolves correctly
    this.http.get<any>(`${this.api}?action=get_my_add_drop&user_id=${this.userId}`, this.hdrs()).subscribe({
      next: r => { this.requests = r.requests || []; this.cdr.detectChanges(); }
    });
  }

  get filteredEnrolled() {
    const q = this.enFilter.toLowerCase();
    return q ? this.enrolled.filter(s => s.code.toLowerCase().includes(q) || s.name.toLowerCase().includes(q)) : this.enrolled;
  }
  get filteredAvailable() {
    const q = this.avFilter.toLowerCase();
    return q ? this.available.filter(s => s.code.toLowerCase().includes(q) || s.name.toLowerCase().includes(q)) : this.available;
  }
  get pendingCount() { return this.requests.filter(r => r.status === 'Pending').length; }

  hasPending(courseId: number, type: string) {
    return this.requests.some(r => r.course_id === courseId && r.request_type === type && r.status === 'Pending');
  }

  open(subject: any, type: 'add'|'drop') {
    if (!this.windowOpen) {
      this.errorMsg = 'Add/Drop is currently closed. Please check back during the open period.';
      return;
    }
    this.modalType = type; this.modalSubject = subject;
    this.modalReason = ''; this.errorMsg = ''; this.showModal = true;
    this.cdr.detectChanges();
  }
  close() { this.showModal = false; this.cdr.detectChanges(); }

  submit() {
    if (!this.modalReason.trim()) { this.errorMsg = 'Please state your reason.'; return; }
    this.isSubmitting = true; this.errorMsg = '';
    // Always send user_id — server resolves to correct students.id
    const body: any = {
      user_id:      this.userId,
      student_id:   this.studentId || 0, // send both; server prefers student_id if > 0
      request_type: this.modalType === 'add' ? 'Add' : 'Drop',
      course_id:    this.modalType === 'add' ? this.modalSubject.id : this.modalSubject.course_id,
      reason:       this.modalReason
    };
    if (this.modalType === 'drop') body.enrollment_id = this.modalSubject.enrollment_id;
    this.http.post<any>(`${this.api}?action=submit_add_drop`, body, this.hdrs()).subscribe({
      next: r => {
        this.isSubmitting = false;
        if (r.success) {
          this.showModal  = false;
          this.successMsg = r.message;
          this.loadAll();
          setTimeout(() => { this.successMsg = ''; this.cdr.detectChanges(); }, 5000);
        } else {
          this.errorMsg = r.message || 'Failed.';
          if (r.window_closed) this.loadWindow(); // refresh window status
        }
        this.cdr.detectChanges();
      },
      error: () => { this.isSubmitting = false; this.errorMsg = 'Server error.'; this.cdr.detectChanges(); }
    });
  }

  windowDates() {
    if (!this.window) return '';
    const fmt = (d: string) => new Date(d).toLocaleString('en-PH', { month:'short', day:'numeric', year:'numeric', hour:'numeric', minute:'2-digit' });
    return `${fmt(this.window.start_date)} – ${fmt(this.window.end_date)}`;
  }

  statusCls(s: string)  { return s==='Approved'?'sad-approved':s==='Rejected'?'sad-rejected':'sad-pending'; }
  statusIcon(s: string) { return s==='Approved'?'✅':s==='Rejected'?'❌':'⏳'; }
  seatPct(s: AvailableSubject) { return s.capacity>0 ? Math.round((s.enrolled_count/s.capacity)*100) : 0; }
}