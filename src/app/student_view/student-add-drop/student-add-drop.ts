import { Component, OnInit, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../environment';

interface EnrolledSubject {
  enrollment_id: number; course_id: number;
  code: string; name: string; credits: number;
  instructor: string; day: string; time: string; room: string;
  semester: string; status: string;
  lecUnits?: number; labUnits?: number; isGeneral?: boolean; isLab?: boolean;
  lec_units?: number; lab_units?: number; is_general?: number; is_lab?: number;
  // FIX ADD-DROP-CREDITED-03: backend tags credited subjects so the UI can
  // hide the Drop button and show a Credited badge instead of letting the
  // student submit a request that will be immediately rejected.
  is_credited?: boolean;
  // FIX ADD-DROP-CREDITED-05: school/source the credit came from (TOR evaluation)
  credited_from?: string;
}

interface AvailableSubject {
  id: number; code: string; name: string; credits: number;
  instructor: string; day: string; time: string; room: string;
  capacity: number; enrolled_count: number; available_seats: number;
  year_level?: string; yearLevel?: string;
  semester?: string;
  prereqMet?: boolean;
  prereqList?: string;
  // Backend flags added by FIX ADD-DROP-YL-02 / ADD-DROP-SEM-01
  isPastSubject?: boolean;    // Failed / Dropped / Completed-with-fail in a past sem
  isRetake?: boolean;         // Same as isPastSubject — explicit retake candidate
  isFutureSemester?: boolean; // Subject belongs to a semester the student hasn't reached yet
  lecUnits?: number; labUnits?: number; isGeneral?: boolean; isLab?: boolean;
  lec_units?: number; lab_units?: number; is_general?: number; is_lab?: number;
  // FIX ADD-DROP-CREDITED-04: Backend excludes credited subjects from available list via SQL,
  // but this flag is a defense-in-depth guard for any slip-through. UI hides Add button.
  is_credited?: boolean;
}

interface ADRequest {
  id: number; request_type: string; course_id: number;
  code: string; course_name: string; credits: number;
  instructor: string; day: string; time: string;
  reason: string; status: string; remarks: string;
  created_at: string; processed_at: string;
  lecUnits?: number; labUnits?: number;
  lec_units?: number; lab_units?: number;
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
  private api = environment.enrollApi;

  userId    = 0;
  studentId = 0;

  activeTab: 'request' | 'history' = 'request';
  requestTab: 'drop' | 'add' = 'drop';

  enrolled: EnrolledSubject[]   = [];
  available: AvailableSubject[] = [];
  requests: ADRequest[]         = [];

  isLoading  = false;
  avFilter   = '';
  enFilter   = '';
  avSemFilter  = '';  // filter by semester
  avYearFilter = '';  // filter by year level — for transferees viewing lower year subjects
  studentYearLevel = '';  // FIX YEAR-GUARD-01: student's own year level, used to hide higher-year subjects

  showModal  = false;
  modalType: 'add' | 'drop' = 'drop';
  modalSubject: any = null;
  modalReason  = '';
  isSubmitting = false;
  successMsg   = '';
  errorMsg     = '';

  window: AddDropWindow | null = null;
  windowOpen    = false;
  windowLoading = true;

  constructor(private http: HttpClient, private cdr: ChangeDetectorRef) {}

  ngOnInit() {
    const u = JSON.parse(sessionStorage.getItem('currentUser') || '{}');
    this.userId    = u.id || 0;
    this.studentId = u.student_id || 0;
    this.loadWindow();
    this.loadAll();
  }

  loadWindow() {
    this.windowLoading = true;
    this.http.get<any>(`${this.api}?action=get_add_drop_window`).subscribe({
      next: r => {
        this.window       = r.window  || null;
        this.windowOpen   = r.is_open || false;
        this.windowLoading = false;
        this.cdr.detectChanges();
      },
      error: () => { this.windowLoading = false; this.cdr.detectChanges(); }
    });
  }

  loadAll() {
    this.isLoading = true;
    const idParam = this.studentId > 0
      ? `student_id=${this.studentId}`
      : `user_id=${this.userId}`;

    this.http.get<any>(`${this.api}?action=get_student_enrollments&${idParam}`).subscribe({
      next: r => {
        if (r.success) {
          this.enrolled  = r.enrolled  || [];
          this.available = (r.available || []).map((s: any) => ({
            ...s,
            prereqMet:       s.prereqMet       !== undefined ? s.prereqMet       : true,
            prereqList:      s.prereqList       || null,
            isPastSubject:   s.isPastSubject    || false,
            isRetake:        s.isRetake         || false,
            isFutureSemester: s.isFutureSemester || false,
            is_credited:     s.is_credited      || false,
          }));

          // FIX YEAR-GUARD-01: Store student's own year level to filter out higher-year subjects
          this.studentYearLevel = r.student_year_level || '';

          // Default semester filter: use the value returned by backend (student's current sem).
          // Backend sets student_semester on the response — use it directly so we don't
          // accidentally pick a future-sem subject count as "dominant".
          if (!this.avSemFilter) {
            const backendSem: string = r.student_semester || '';
            if (backendSem) {
              // Find exact match in available list (backend sem may have AY suffix stripped)
              const matchSem = this.available.find(s =>
                s.semester && s.semester.toLowerCase().startsWith(backendSem.toLowerCase())
              )?.semester || '';
              this.avSemFilter = matchSem || backendSem;
            } else if (this.available.length > 0) {
              // Fallback: pick most common non-future semester
              const semCounts: Record<string, number> = {};
              for (const s of this.available) {
                if (s.semester && !s.isFutureSemester)
                  semCounts[s.semester] = (semCounts[s.semester] || 0) + 1;
              }
              const dominant = Object.entries(semCounts).sort((a, b) => b[1] - a[1])[0];
              if (dominant) this.avSemFilter = dominant[0];
            }
          }
        }
        this.isLoading = false;
        this.cdr.detectChanges();
      },
      error: () => { this.isLoading = false; this.cdr.detectChanges(); }
    });

    const histParam = this.studentId > 0
      ? `student_id=${this.studentId}`
      : `user_id=${this.userId}`;
    this.http.get<any>(`${this.api}?action=get_my_add_drop&${histParam}`).subscribe({
      next: r => {
        this.requests = r.requests || [];
        this.cdr.detectChanges();
      },
      error: () => {}
    });
  }

  // ── Computed ──────────────────────────────────────────────────────────────
  get filteredEnrolled() {
    const q = this.enFilter.toLowerCase();
    return q
      ? this.enrolled.filter(s =>
          s.code.toLowerCase().includes(q) || s.name.toLowerCase().includes(q))
      : this.enrolled;
  }

  get filteredAvailable() {
    let list = [...this.available];

    // FIX YEAR-GUARD-01: Always strip higher-year subjects first, regardless of other filters.
    // Retakes bypass this (student already reached that year before).
    if (this.studentYearLevel) {
      const ylOrder: Record<string, number> = {
        '1st Year': 1, '2nd Year': 2, '3rd Year': 3,
        '4th Year': 4, '5th Year': 5,
        'Grade 11': 11, 'Grade 12': 12,
      };
      const stuYLNum = ylOrder[this.studentYearLevel] ?? 0;
      if (stuYLNum > 0) {
        list = list.filter(s => {
          if (s.isRetake) return true; // retakes always allowed
          const cYLNum = ylOrder[s.year_level ?? s.yearLevel ?? ''] ?? 0;
          return cYLNum === 0 || cYLNum <= stuYLNum;
        });
      }
    }

    // Hide future-semester subjects by default — unless the student explicitly clears
    // all filters (avSemFilter = '') and avYearFilter is also empty.
    // If the student clears semester filter, show everything (let them browse).
    // If a semester filter IS active, only show that sem (which naturally excludes future).
    if (this.avSemFilter) {
      list = list.filter(s => s.semester === this.avSemFilter);
    } else if (!this.avYearFilter) {
      // No filters at all: suppress future-semester subjects so they don't confuse students.
      // Retake subjects are always shown even if isFutureSemester was wrongly set.
      list = list.filter(s => !s.isFutureSemester || s.isRetake);
    }

    if (this.avYearFilter)
      list = list.filter(s => (s.year_level || s.yearLevel) === this.avYearFilter);

    const q = this.avFilter.toLowerCase();
    if (q) list = list.filter(s =>
      s.code.toLowerCase().includes(q) || s.name.toLowerCase().includes(q));
    return list;
  }

  /** Count of subjects addable right now (current sem + past retakes/back subjects, excl. future/higher-year) */
  get addableCount(): number {
    const ylOrder: Record<string, number> = {
      '1st Year': 1, '2nd Year': 2, '3rd Year': 3,
      '4th Year': 4, '5th Year': 5,
      'Grade 11': 11, 'Grade 12': 12,
    };
    const stuYLNum = ylOrder[this.studentYearLevel] ?? 0;
    return this.available.filter(s => {
      if (s.is_credited) return false; // FIX ADD-DROP-CREDITED-04: never count credited as addable
      if (s.isRetake) return true;
      if (s.isFutureSemester) return false;
      if (stuYLNum > 0) {
        const cYLNum = ylOrder[s.year_level ?? s.yearLevel ?? ''] ?? 0;
        if (cYLNum > 0 && cYLNum > stuYLNum) return false;
      }
      return true;
    }).length;
  }

  get uniqueAvSemesters(): string[] {
    return [...new Set(this.available.map(s => s.semester).filter((v): v is string => !!v))].sort();
  }

  get uniqueAvYearLevels(): string[] {
    return [...new Set(this.available.map(s => s.year_level || s.yearLevel).filter((v): v is string => !!v))].sort();
  }

  get pendingCount() {
    return this.requests.filter(r => r.status === 'Pending').length;
  }

  hasPending(courseId: number, type: string): boolean {
    return this.requests.some(
      r => r.course_id === courseId && r.request_type === type && r.status === 'Pending'
    );
  }

  seatPct(s: AvailableSubject): number {
    return s.capacity > 0 ? Math.min(Math.round((s.enrolled_count / s.capacity) * 100), 100) : 0;
  }

  isMinor(code: string): boolean {
    if (!code) return false;
    return /^(GE|PE|NSTP|OJT)/i.test(code);
  }

  statusCls(s: string): string {
    return s === 'Approved' ? 'sad-status-approved'
         : s === 'Rejected' ? 'sad-status-rejected'
         : 'sad-status-pending';
  }

  windowDates(): string {
    if (!this.window) return '';
    const fmt = (d: string) => new Date(d).toLocaleString('en-PH', {
      month: 'short', day: 'numeric', year: 'numeric',
      hour: 'numeric', minute: '2-digit'
    });
    return `${fmt(this.window.start_date)} – ${fmt(this.window.end_date)}`;
  }

  // ── Modal ─────────────────────────────────────────────────────────────────
  open(subject: any, type: 'add' | 'drop') {
    if (!this.windowOpen) {
      this.errorMsg = 'Add/Drop is currently closed. Please check back during the open window.';
      return;
    }
    this.modalType    = type;
    this.modalSubject = subject;
    this.modalReason  = '';
    this.errorMsg     = '';
    this.showModal    = true;
    this.cdr.detectChanges();
  }

  close() {
    this.showModal = false;
    this.cdr.detectChanges();
  }

  submit() {
    if (!this.modalReason.trim()) {
      this.errorMsg = 'Please state your reason.';
      return;
    }

    this.isSubmitting = true;
    this.errorMsg     = '';

    const body: any = {
      user_id:      this.userId,
      student_id:   this.studentId || 0,
      request_type: this.modalType === 'add' ? 'Add' : 'Drop',
      course_id:    this.modalType === 'add'
                      ? this.modalSubject.id
                      : this.modalSubject.course_id,
      reason: this.modalReason
    };
    if (this.modalType === 'drop') {
      body.enrollment_id = this.modalSubject.enrollment_id;
    }

    this.http.post<any>(`${this.api}?action=submit_add_drop`, body).subscribe({
      next: r => {
        this.isSubmitting = false;
        if (r.success) {
          this.showModal  = false;
          this.successMsg = r.message;
          this.loadAll();
          setTimeout(() => { this.successMsg = ''; this.cdr.detectChanges(); }, 5000);
        } else {
          // FIX ADD-DROP-PREREQ-01 / ADD-DROP-YL-01:
          // Surface specific error codes with contextual messages.
          const code = r.code || '';
          if (code === 'PREREQ_NOT_MET') {
            this.errorMsg = r.message || 'You have not completed the prerequisites for this subject.';
          } else if (code === 'YEAR_LEVEL_MISMATCH') {
            this.errorMsg = r.message || 'This subject is not available for your current year level.';
          } else if (code === 'FUTURE_SEMESTER') {
            this.errorMsg = r.message || 'You cannot add a subject from a future semester.';
          } else if (code === 'PROGRAM_MISMATCH') {
            this.errorMsg = r.message || 'This subject belongs to a different program.';
          } else if (code === 'SUBJECT_CREDITED') {
            // FIX ADD-DROP-CREDITED-03: Reload so the credited badge appears
            // (backend already tagged it; UI just needs a refresh to reflect it).
            this.errorMsg = r.message || 'This subject is credited and cannot be dropped.';
            this.loadAll();
          } else {
            this.errorMsg = r.message || 'Failed to submit request.';
          }
          // Refresh the window status in case it closed between page load and submit
          if (r.window_closed) this.loadWindow();
          // Refresh available list to reflect updated prereqMet status if it changed
          if (code === 'PREREQ_NOT_MET') this.loadAll();
        }
        this.cdr.detectChanges();
      },
      error: () => {
        this.isSubmitting = false;
        this.errorMsg = 'Server error. Please try again.';
        this.cdr.detectChanges();
      }
    });
  }
}