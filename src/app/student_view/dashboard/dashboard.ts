import { Component, OnInit, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { RouterLink } from '@angular/router';
import { environment } from '../../environment';

interface Course {
  id?: number;
  code: string;
  name: string;
  instructor: string;
  credits: number;
  schedule: string;
  time: string;
  day?: string;
  room: string;
  progress: number;
  semester?: string;
}

interface Announcement {
  id?: number;
  title: string;
  message: string;
  date: string;
  icon: string;
  type?: string;
  priority?: string;
}

interface SchoolEvent {
  id: number;
  title: string;
  event_date: string;
  type: string;
  description: string;
}

interface PaymentRecord {
  id: number;
  method: string;
  reference: string;
  amount: number;
  date: string;
  semester: string;
  status: string;
}

interface ClassBlock {
  id: number;
  blockCode: string;
  program: string;
  yearLevel: string;
  semester: string;
  schoolYear: string;
  maxCapacity: number;
  enrolledCount: number;
}

interface CalendarCell {
  date: Date;
  isCurrentMonth: boolean;
  isToday: boolean;
  events: SchoolEvent[];
}

@Component({
  selector: 'app-dashboard',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterLink],
  templateUrl: './dashboard.html',
  styleUrls: ['./dashboard.css']
})
export class StudentDashboard implements OnInit {
  private apiUrl = environment.dashboardApi;
  constructor(private http: HttpClient, private cdr: ChangeDetectorRef) {}

  // ── spec.ts requires dashboardCards.length === 4 ────────────
  dashboardCards = [
    { title: 'Courses',  value: '0',  icon: '📖', color: '#667eea' },
    { title: 'Credits',  value: '0',  icon: '⏰', color: '#48bb78' },
  ];

  // ── Original props (keep for spec.ts / template compat) ─────
  studentName   = 'Loading...';
  studentId     = '—';
  gpa           = '—';
  totalCredits  = 0;
  nextClassTime = '—';
  weekDays      = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
  enrolledCourses: Course[]       = [];
  announcements:   Announcement[] = [];

  // ── New state ────────────────────────────────────────────────
  isLoading         = true;
  error             = '';
  program           = '';
  studentCategory   = '';   // 'SHS' | 'TVET' | '' (College)
  isIrregular       = false;
  studentType       = '';   // 'New' | 'Old' | 'Transferee'
  yearLevel         = '';
  academicYear      = '2024–2025';
  semester          = '';
  academicStatus    = '';
  enrollmentStatus  = '';
  enrollmentDate    = '';
  paymentStatus     = '';
  isScholar         = false;
  scholarType       = '';
  scholarGrantor    = '';
  scholarshipAmount = 0;
  isFullScholar     = false;
  scholarPending    = false;
  nextClassObj:     Course | null = null;
  nextClassLabel    = '';
  block:            ClassBlock | null = null;

  fees: any = {
    tuitionBase: 25000, miscFee: 1500, totalFees: 26500,
    amountPaid: 0, scholarship: 0, remainingBal: 26500,
    dueDate: '', paymentStatus: 'Pending'
  };
  paymentHistory: PaymentRecord[] = [];
  activeFeesTab: 'breakdown' | 'history' = 'breakdown';

  annFilter = 'all';
  annTypes  = [
    { key: 'all',        label: 'All',        icon: '📢' },
    { key: 'school',     label: 'School',     icon: '🏫' },
    { key: 'department', label: 'Department', icon: '📚' },
    { key: 'payment',    label: 'Payment',    icon: '💳' },
    { key: 'enrollment', label: 'Enrollment', icon: '📋' },
    { key: 'system',     label: 'System',     icon: '⚙️' },
  ];

  events:       SchoolEvent[]  = [];
  calendarMonth = new Date();
  calendarDays: CalendarCell[] = [];
  readonly MONTHS = ['January','February','March','April','May','June',
                     'July','August','September','October','November','December'];
  readonly DOW    = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];

  // ── Category helpers (used in template to hide college-only sections) ─
  get isSHS():         boolean { return this.studentCategory === 'SHS'; }
  get isTVET():        boolean { return this.studentCategory === 'TVET'; }
  get isCollege():     boolean { return !this.isSHS && !this.isTVET; }
  get isTransferee():  boolean { return this.studentType === 'Transferee'; }
  get isFreeStudent(): boolean { return (this.isSHS || this.isTVET) && !this.isTransferee; }
  // For SHS: show 'Grade 11' / 'Grade 12' instead of '1st Year' / '2nd Year'
  get displayYearLevel(): string {
    if (this.isSHS) {
      if (this.yearLevel === '1st Year' || this.yearLevel === 'Grade 11') return 'Grade 11';
      if (this.yearLevel === '2nd Year' || this.yearLevel === 'Grade 12') return 'Grade 12';
      return this.yearLevel || '—';
    }
    return this.yearLevel || '—';
  }

  // ── Lifecycle ────────────────────────────────────────────────
  ngOnInit(): void {
    const stored = sessionStorage.getItem('currentUser');
    if (!stored) {
      this.isLoading = false;
      this.useFallback();
      return;
    }
    const user  = JSON.parse(stored);
    const dbId  = sessionStorage.getItem('studentDbId');
    const param = dbId ? `student_id=${dbId}` : `user_id=${user.id}`;

    this.loadDashboard(param);
    this.loadAnnouncements();
    this.loadEvents();
  }

  loadDashboard(param: string): void {
    this.http.get<any>(`${this.apiUrl}?action=get_dashboard&${param}`).subscribe({
      next: (res) => {
        this.isLoading = false;
        if (res.success) {
          const s = res.student;
          const a = res.academic;

          this.studentName     = `${s.firstName} ${s.lastName}`;
          this.studentId       = s.id;
          this.program         = s.program;
          this.studentCategory = (s.studentCategory ?? '').toUpperCase();
          this.studentType     = s.studentType ?? '';
          this.isIrregular     = s.isIrregular ?? (this.academicStatus === 'Irregular');
          this.yearLevel       = a.yearLevel;
          this.gpa             = a.gpa > 0 ? Number(a.gpa).toFixed(2) : '—';
          this.totalCredits    = a.totalCredits;
          this.academicStatus  = a.status;
          this.semester        = a.semester ?? this.semester;
          this.academicYear    = a.academicYear ?? this.academicYear;
          this.enrollmentStatus= s.enrollmentStatus;
          this.enrollmentDate  = s.enrollmentDate;
          this.paymentStatus   = s.paymentStatus;
          // ── Scholarship ────────────────────────────────────────────────
          this.isScholar        = s.isScholar       ?? false;
          this.scholarType      = s.scholarType     ?? '';
          this.scholarGrantor   = s.scholarGrantor  ?? '';
          this.scholarshipAmount= s.scholarshipAmount ?? 0;
          this.scholarPending   = s.scholarPending  ?? false;
          this.isFullScholar    = s.isFullScholar   ?? false;
          // Fallback: if scholarship amount covers full fees, treat as full scholar
          if (!this.isFullScholar && this.isScholar && this.scholarshipAmount > 0
              && this.paymentStatus === 'Paid') {
            this.isFullScholar = true;
          }
          this.fees            = res.fees ?? this.fees;
          this.paymentHistory  = res.paymentHistory ?? [];

          this.enrolledCourses = (res.courses ?? []).map((c: any) => ({
            ...c, schedule: c.day ?? c.schedule ?? '', progress: 0
          }));

          if (res.nextClass) {
            const nc          = res.nextClass;
            this.nextClassObj = { ...nc, schedule: nc.day ?? '', progress: 0 };
            this.nextClassTime= nc.time ?? '—';
            this.nextClassLabel = this.getNextLabel(nc.day ?? '');
          }

          this.dashboardCards = [
            { title: 'Courses', value: String(this.enrolledCourses.length), icon: '📖', color: '#667eea' },
            // Credits card removed
          ];

          sessionStorage.setItem('studentDbId', String(s.dbId));
          this.block = res.block ?? null;
        } else {
          this.useFallback();
        }
        this.cdr.detectChanges();
      },
      error: () => {
        this.isLoading = false;
        this.useFallback();
        this.cdr.detectChanges();
      }
    });
  }

  loadAnnouncements(): void {
    this.http.get<any>(`${this.apiUrl}?action=get_announcements`).subscribe({
      next: (res) => {
        if (res.success) {
          this.announcements = (res.announcements ?? []).map((a: any) => ({
            ...a, icon: this.annIcon(a.type)
          }));
          this.cdr.detectChanges();
        }
      }
    });
  }

  loadEvents(): void {
    this.http.get<any>(`${this.apiUrl}?action=get_events`).subscribe({
      next: (res) => {
        if (res.success) {
          this.events = res.events ?? [];
          this.buildCalendar();
          this.cdr.detectChanges();
        }
      }
    });
  }

  useFallback(): void {
    this.announcements = [
      { title: 'Enrollment Open',    message: 'Enrollment for this semester is now open.',        date: 'Today', icon: '📋', type: 'enrollment', priority: 'high' },
      { title: 'Payment Reminder',   message: 'Tuition fee due soon. Please settle your balance.', date: 'Today', icon: '💳', type: 'payment',    priority: 'high' },
      { title: 'Library Extended',   message: 'Library is now open 24/7 for the exam period.',    date: 'Jan 28, 2025', icon: '📖', type: 'school', priority: 'normal' },
    ];
    this.events = this.sampleEvents();
    this.buildCalendar();
  }

  // ── Calendar ─────────────────────────────────────────────────
  buildCalendar(): void {
    const year  = this.calendarMonth.getFullYear();
    const month = this.calendarMonth.getMonth();
    const first = new Date(year, month, 1);
    const last  = new Date(year, month + 1, 0);
    const today = new Date();
    const days: CalendarCell[] = [];

    // Leading days from previous month (fix: use day-offset from 1, not negative dates)
    const startDow = first.getDay(); // 0=Sun … 6=Sat
    for (let i = startDow - 1; i >= 0; i--) {
      const date = new Date(year, month, 1 - (i + 1));
      days.push({ date, isCurrentMonth: false, isToday: false, events: [] });
    }

    for (let d = 1; d <= last.getDate(); d++) {
      const date = new Date(year, month, d);
      // FIX: compare using local YYYY-MM-DD string so timezone doesn't shift the date
      const ds = this.toDateStr(date);
      days.push({
        date, isCurrentMonth: true,
        isToday: date.toDateString() === today.toDateString(),
        events: this.events.filter(e => (e.event_date ?? '').slice(0, 10) === ds)
      });
    }
    // Trailing days from next month
    let nd = 1;
    while (days.length < 42)
      days.push({ date: new Date(year, month + 1, nd++), isCurrentMonth: false, isToday: false, events: [] });

    this.calendarDays = days;
  }

  prevMonth(): void {
    this.calendarMonth = new Date(this.calendarMonth.getFullYear(), this.calendarMonth.getMonth() - 1, 1);
    this.buildCalendar(); this.cdr.detectChanges();
  }
  nextMonth(): void {
    this.calendarMonth = new Date(this.calendarMonth.getFullYear(), this.calendarMonth.getMonth() + 1, 1);
    this.buildCalendar(); this.cdr.detectChanges();
  }

  get calendarTitle(): string {
    return `${this.MONTHS[this.calendarMonth.getMonth()]} ${this.calendarMonth.getFullYear()}`;
  }
  get upcomingEvents(): SchoolEvent[] {
    // Show events for the currently viewed calendar month (not always from today)
    const year  = this.calendarMonth.getFullYear();
    const month = this.calendarMonth.getMonth();
    const monthStr = `${year}-${String(month + 1).padStart(2, '0')}`;
    const todayStr = this.toDateStr(new Date());
    const isCurrentMonth = monthStr === todayStr.slice(0, 7);

    if (isCurrentMonth) {
      // For current month: show from today onwards
      return this.events
        .filter(e => (e.event_date ?? '').slice(0, 10) >= todayStr)
        .slice(0, 6);
    } else {
      // For past/future months: show all events in that month
      return this.events
        .filter(e => (e.event_date ?? '').slice(0, 7) === monthStr)
        .slice(0, 6);
    }
  }

  // ── Helpers ───────────────────────────────────────────────────
  get nextClass(): Course | null {
    return this.nextClassObj ?? (this.enrolledCourses[0] ?? null);
  }

  getClassesByDay(day: string): Course[] {
    return this.enrolledCourses.filter(c =>
      (c.day || c.schedule || '').split(',').map(d => d.trim())
        .some(d => d.toLowerCase().startsWith(day.substring(0, 3).toLowerCase()))
    );
  }

  get filteredAnn(): Announcement[] {
    return this.annFilter === 'all'
      ? this.announcements
      : this.announcements.filter(a => a.type === this.annFilter);
  }

  getNextLabel(day: string): string {
    const map: Record<string,number> = { Monday:1, Tuesday:2, Wednesday:3, Thursday:4, Friday:5, Saturday:6, Sunday:0 };
    const diff = ((map[day] ?? 0) - new Date().getDay() + 7) % 7;
    if (diff === 0) return 'Today';
    if (diff === 1) return 'Tomorrow';
    return `In ${diff} days`;
  }

  annIcon(type: string): string {
    const m: Record<string,string> = { enrollment:'📋', payment:'💳', school:'🏫', department:'📚', system:'⚙️' };
    return m[type] ?? '📢';
  }

  eventColor(type: string): string {
    const m: Record<string,string> = { holiday:'#e53e3e', exam:'#ed8936', enrollment:'#667eea', activity:'#48bb78', payment:'#9f7aea' };
    return m[type] ?? '#718096';
  }

  fmt(n: number | null | undefined): string {
    if (n == null) return '₱0.00';
    return '₱' + n.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
  }

  // Balance-specific formatter: shows first 2 digits, masks the rest
  // e.g. 12500.00 → ₱12,***.**
  fmtBalance(n: number | null | undefined): string {
    if (n == null) return '₱0.00';
    if (n <= 0) return '₱0.00';
    const formatted = '₱' + n.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    let digitCount = 0, maskFrom = -1;
    for (let i = 1; i < formatted.length; i++) {
      if (formatted[i] >= '0' && formatted[i] <= '9') {
        digitCount++;
        if (digitCount === 2) { maskFrom = i + 1; break; }
      }
    }
    if (maskFrom === -1) return formatted;
    return formatted.slice(0, maskFrom) + formatted.slice(maskFrom).replace(/\d/g, '*');
  }
  fmtAmountPaid(n: number | null | undefined): string {
    if (n == null) return '₱0.00';
    if (n <= 0) return '₱0.00';
    const formatted = '₱' + n.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    let digitCount = 0, maskFrom = -1;
    for (let i = 1; i < formatted.length; i++) {
      if (formatted[i] >= '0' && formatted[i] <= '9') {
        digitCount++;
        if (digitCount === 2) { maskFrom = i + 1; break; }
      }
    }
    if (maskFrom === -1) return formatted;
    return formatted.slice(0, maskFrom) + formatted.slice(maskFrom).replace(/\d/g, '*');
  }

  fmtDate(d: string): string {
    if (!d) return '—';
    try { return new Date(d).toLocaleDateString('en-PH', { year:'numeric', month:'long', day:'numeric' }); }
    catch { return d; }
  }

  toDateStr(d: Date): string {
    return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
  }

  getProgramFull(code: string): string {
    const m: Record<string,string> = {
      'IT':'BS Information Technology', 'CS':'BS Computer Science',
      'BIS':'BS Information Systems',   'CE':'BS Civil Engineering',
    };
    return m[code] ?? code;
  }

  sampleEvents(): SchoolEvent[] {
    const y = new Date().getFullYear();
    const m = String(new Date().getMonth() + 1).padStart(2, '0');
    return [
      { id:1, title:'Foundation Day',      event_date:`${y}-${m}-15`, type:'holiday',    description:'University Foundation Day' },
      { id:2, title:'Midterm Exams',       event_date:`${y}-${m}-20`, type:'exam',       description:'Midterm examinations week' },
      { id:3, title:'Tuition Due',         event_date:`${y}-${m}-28`, type:'payment',    description:'Tuition fee deadline' },
      { id:4, title:'Enrollment Deadline', event_date:`${y}-${m}-31`, type:'enrollment', description:'Last day to enroll' },
    ];
  }
}