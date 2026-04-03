import { Component, OnInit, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../environment';

interface EnrolledStudent {
  studentId: number;
  studentNumber: string;
  firstName: string;
  lastName: string;
  program: string;
  yearLevel: string;
  semester: string;
  studentCategory: string;
  paymentPlan: string;
  paymentStatus: string;
  totalAssessment: number;
  prelimDue: number; midtermDue: number; finalsDue: number;
  prelimPaid: number; midtermPaid: number; finalsPaid: number;
  prelimStatus: string; midtermStatus: string; finalsStatus: string;
}

interface ExamPermit {
  id: number;
  student_id: number;
  student_number: string;
  first_name: string; last_name: string;
  program: string; year_level: string; semester: string;
  exam_period: string; school_year: string;
  status: string;
  requested_at: string; approved_at: string;
  remarks: string;
  approved_by_first: string; approved_by_last: string;
  student_category?: string;
}

// Course group returned by GET ?action=get_course_groups
interface CourseGroup {
  category: string;
  program: string;
  year_level: string;
  strand: string;
  semester: string;
  student_count: number;
  pending_count: number;
  paid_count: number;
  installment_count: number;
  full_count: number;
}

@Component({
  selector: 'app-permit-generation',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './permit-generation.html',
  styleUrl: './permit-generation.css'
})
export class PermitGeneration implements OnInit {
  private apiUrl = environment.accountingApi;
  accountingUserId = 0;

  mainTab: 'notices' | 'permits' = 'notices';
  permitTab: 'pending' | 'approved' | 'rejected' = 'pending';

  // ── Course group cards ──────────────────────────────────────────────────────
  courseGroups: CourseGroup[] = [];
  activeCardKey = '';           // "<program>|<year_level>|<semester>|<category>"
  isLoadingGroups = false;

  // Notice sending
  students: EnrolledStudent[] = [];
  isLoadingStudents = false;
  searchStudents   = '';
  filterCategory: 'ALL'|'COLLEGE'|'SHS'|'TVET' = 'ALL';
  filterProgram  = 'ALL';
  filterYear     = 'ALL';

  // ── Notices pagination ────────────────────────────────────
  noticesPage = 1;
  readonly NOTICES_PAGE_SIZE = 15;

  get noticesTotalPages(): number {
    return Math.max(1, Math.ceil(this.filteredStudents.length / this.NOTICES_PAGE_SIZE));
  }

  get pagedStudents(): EnrolledStudent[] {
    const start = (this.noticesPage - 1) * this.NOTICES_PAGE_SIZE;
    return this.filteredStudents.slice(start, start + this.NOTICES_PAGE_SIZE);
  }

  noticesPrevPage(): void {
    if (this.noticesPage > 1) { this.noticesPage--; this.cdr.detectChanges(); }
  }

  noticesNextPage(): void {
    if (this.noticesPage < this.noticesTotalPages) { this.noticesPage++; this.cdr.detectChanges(); }
  }

  // Bulk send notice
  showBulkModal    = false;
  bulkPeriod: 'Prelim'|'Midterm'|'Finals' = 'Prelim';
  bulkCategory: 'ALL'|'COLLEGE'|'SHS'|'TVET' = 'ALL';
  bulkDueDate      = '';
  bulkMsgTemplate  = '';
  isSendingBulk    = false;
  bulkResult       = '';
  bulkResultType: 'success'|'error' = 'success';

  showNoticeModal  = false;
  noticeStudent: EnrolledStudent | null = null;
  noticeForm = { exam_period: 'Prelim', amount_due: 0, due_date: '', message: '' };
  isSendingNotice = false;
  noticeResult = '';
  noticeResultType: 'success'|'error' = 'success';

  // Permits
  permits: ExamPermit[] = [];
  isLoadingPermits = false;
  searchPermits = '';
  filterPeriod: 'all'|'Prelim'|'Midterm'|'Finals' = 'all';
  filterPermitProgram  = 'ALL';
  filterPermitYear     = 'ALL';
  filterPermitCategory: 'ALL'|'COLLEGE'|'SHS'|'TVET' = 'ALL';
  activePermitCardKey  = '';

  // ── Permits pagination ────────────────────────────────────
  permitsPage = 1;
  readonly PERMITS_PAGE_SIZE = 15;

  get permitsTotalPages(): number {
    return Math.max(1, Math.ceil(this.filteredPermits.length / this.PERMITS_PAGE_SIZE));
  }

  get pagedPermits(): ExamPermit[] {
    const start = (this.permitsPage - 1) * this.PERMITS_PAGE_SIZE;
    return this.filteredPermits.slice(start, start + this.PERMITS_PAGE_SIZE);
  }

  permitsPrevPage(): void {
    if (this.permitsPage > 1) { this.permitsPage--; this.cdr.detectChanges(); }
  }

  permitsNextPage(): void {
    if (this.permitsPage < this.permitsTotalPages) { this.permitsPage++; this.cdr.detectChanges(); }
  }
  showPermitModal = false;
  modalAction: 'approve'|'reject' = 'approve';
  selectedPermit: ExamPermit | null = null;
  remarks = '';
  isProcessing = false;

  constructor(private http: HttpClient, private cdr: ChangeDetectorRef) {}

  ngOnInit(): void {
    const s = sessionStorage.getItem('currentUser');
    if (s) this.accountingUserId = JSON.parse(s).id;
    this.loadCourseGroups();
    this.loadStudents();
  }

  // ── Course group cards ──────────────────────────────────────────────────────

  loadCourseGroups(): void {
    this.isLoadingGroups = true;
    this.http.get<any>(`${this.apiUrl}?action=get_course_groups`).subscribe({
      next: (res) => {
        this.courseGroups = res.success ? res.groups : [];
        this.isLoadingGroups = false;
        this.cdr.detectChanges();
      },
      error: () => { this.isLoadingGroups = false; this.cdr.detectChanges(); }
    });
  }

  cardKey(g: CourseGroup): string {
    return `${g.program}|${g.year_level}|${g.semester}|${g.category}`;
  }

  onCardClick(g: CourseGroup): void {
    const key = this.cardKey(g);
    if (this.activeCardKey === key) {
      this.activeCardKey = '';
      this.filterCategory = 'ALL';
      this.filterProgram  = 'ALL';
      this.filterYear     = 'ALL';
      this.noticesPage    = 1;
      this.cdr.detectChanges();
      return;
    }
    this.activeCardKey  = key;
    const cat = (g.category || 'COLLEGE').toUpperCase() as 'ALL'|'COLLEGE'|'SHS'|'TVET';
    this.filterCategory = ['COLLEGE','SHS','TVET'].includes(cat) ? cat : 'ALL';
    this.filterProgram  = g.program || 'ALL';
    this.filterYear     = g.year_level || 'ALL';
    this.noticesPage    = 1;
    this.cdr.detectChanges();
  }

  isCardActive(g: CourseGroup): boolean {
    return this.activeCardKey === this.cardKey(g);
  }

  get groupedCards(): { label: string; groups: CourseGroup[] }[] {
    const catMap = new Map<string, CourseGroup[]>();
    for (const g of this.courseGroups) {
      const cat = g.category || 'College';
      if (!catMap.has(cat)) catMap.set(cat, []);
      catMap.get(cat)!.push(g);
    }
    return Array.from(catMap.entries()).map(([label, groups]) => ({ label, groups }));
  }

  // ── Students with payment schedules ────────────────────────────────────────
  loadStudents(): void {
    this.isLoadingStudents = true;
    this.http.get<any>(`${this.apiUrl}?action=get_all_enrolled_students`).subscribe({
      next: (res) => {
        this.isLoadingStudents = false;
        this.students = res.success ? res.students : [];
        this.cdr.detectChanges();
      },
      error: () => {
        this.isLoadingStudents = false;
        this.cdr.detectChanges();
      }
    });
  }

  loadStudentSchedule(student: EnrolledStudent): void {
    this.http.get<any>(`${this.apiUrl}?action=get_payment_schedule&student_id=${student.studentId}`).subscribe({
      next: (res) => {
        if (res.success && res.schedule) {
          const s = res.schedule;
          student.totalAssessment = s.total_assessment;
          student.prelimDue   = s.prelim_due;   student.midtermDue  = s.midterm_due;  student.finalsDue  = s.finals_due;
          student.prelimPaid  = s.prelim_paid;  student.midtermPaid = s.midterm_paid; student.finalsPaid = s.finals_paid;
          student.prelimStatus  = s.prelim_status;
          student.midtermStatus = s.midterm_status;
          student.finalsStatus  = s.finals_status;
        }
        this.cdr.detectChanges();
      }
    });
  }

  get filteredStudents(): EnrolledStudent[] {
    const q    = this.searchStudents.toLowerCase();
    const cat  = this.filterCategory;
    const prog = this.filterProgram;
    const yr   = this.filterYear;
    return this.students.filter(s => {
      const matchQ    = !q    || (s.firstName+' '+s.lastName).toLowerCase().includes(q) || s.studentNumber.toLowerCase().includes(q);
      const matchCat  = cat  === 'ALL' || (s.studentCategory || 'COLLEGE').toUpperCase() === cat;
      const matchProg = prog === 'ALL' || s.program === prog;
      const matchYr   = yr   === 'ALL' || s.yearLevel === yr;
      return matchQ && matchCat && matchProg && matchYr;
    });
  }

  get uniquePrograms(): string[] {
    const s = new Set(this.students.map(s => s.program).filter(Boolean));
    return ['ALL', ...Array.from(s).sort()];
  }

  get uniqueYears(): string[] {
    const s = new Set(this.students.map(s => s.yearLevel).filter(Boolean));
    return ['ALL', ...Array.from(s).sort()];
  }

  get categoryCounts(): Record<string, number> {
    const counts: Record<string, number> = { ALL: this.students.length, COLLEGE: 0, SHS: 0, TVET: 0 };
    for (const s of this.students) {
      const c = (s.studentCategory || 'COLLEGE').toUpperCase();
      if (c in counts) counts[c]++;
      else { counts['COLLEGE']++; }
    }
    return counts;
  }

  openBulkModal(period: 'Prelim'|'Midterm'|'Finals'): void {
    this.bulkPeriod       = period;
    this.bulkCategory     = this.filterCategory === 'ALL' ? 'ALL' : this.filterCategory;
    this.bulkDueDate      = '';
    this.bulkMsgTemplate  = `Dear {name}, your {period} payment of {amount} is now due. Please settle at the Accounting office.`;
    this.bulkResult       = '';
    this.showBulkModal    = true;
    this.cdr.detectChanges();
  }

  closeBulkModal(): void { this.showBulkModal = false; this.cdr.detectChanges(); }

  sendBulkNotice(): void {
    this.isSendingBulk = true;
    this.http.post<any>(`${this.apiUrl}?action=send_bulk_notice`, {
      exam_period:        this.bulkPeriod,
      category:           this.bulkCategory,
      due_date:           this.bulkDueDate,
      message_template:   this.bulkMsgTemplate,
      accounting_user_id: this.accountingUserId
    }).subscribe({
      next: (res) => {
        this.isSendingBulk    = false;
        this.bulkResult       = res.message;
        this.bulkResultType   = res.success ? 'success' : 'error';
        if (res.success) {
          this.loadStudents();
          setTimeout(() => { this.closeBulkModal(); }, 2000);
        }
        this.cdr.detectChanges();
      },
      error: () => { this.isSendingBulk = false; this.cdr.detectChanges(); }
    });
  }

  // ── Send Notice Modal ───────────────────────────────────────────────────────
  openNoticeModal(student: EnrolledStudent, period: string): void {
    this.noticeStudent = student;
    const due = period === 'Prelim' ? student.prelimDue : period === 'Midterm' ? student.midtermDue : student.finalsDue;
    const paid = period === 'Prelim' ? student.prelimPaid : period === 'Midterm' ? student.midtermPaid : student.finalsPaid;
    this.noticeForm = {
      exam_period: period,
      amount_due:  due - paid,
      due_date:    '',
      message:     `Dear ${student.firstName}, your ${period} payment of ₱${(due-paid).toLocaleString('en-PH',{minimumFractionDigits:2})} is now due. Please settle at the Accounting office.`
    };
    this.noticeResult = '';
    this.showNoticeModal = true;
    this.cdr.detectChanges();
  }

  closeNoticeModal(): void { this.showNoticeModal = false; this.noticeStudent = null; this.cdr.detectChanges(); }

  sendNotice(): void {
    if (!this.noticeStudent) return;
    this.isSendingNotice = true;
    this.http.post<any>(`${this.apiUrl}?action=send_payment_notice`, {
      student_id:          this.noticeStudent.studentId,
      exam_period:         this.noticeForm.exam_period,
      amount_due:          this.noticeForm.amount_due,
      due_date:            this.noticeForm.due_date,
      message:             this.noticeForm.message,
      accounting_user_id:  this.accountingUserId
    }).subscribe({
      next: (res) => {
        this.isSendingNotice = false;
        this.noticeResult     = res.message;
        this.noticeResultType = res.success ? 'success' : 'error';
        if (res.success && this.noticeStudent) {
          const period = this.noticeForm.exam_period.toLowerCase() as 'prelim'|'midterm'|'finals';
          const newStatus = this.noticeStudent.paymentPlan === 'full' ? 'paid' : 'unpaid';
          (this.noticeStudent as any)[period+'Status'] = newStatus;
          this.loadStudentSchedule(this.noticeStudent);
          setTimeout(() => { this.closeNoticeModal(); }, 1800);
        }
        this.cdr.detectChanges();
      },
      error: () => { this.isSendingNotice = false; this.cdr.detectChanges(); }
    });
  }

  // ── Permits ─────────────────────────────────────────────────────────────────
  loadPermits(): void {
    this.isLoadingPermits = true;
    this.http.get<any>(`${this.apiUrl}?action=get_exam_permits&status=${this.permitTab}`).subscribe({
      next: (res) => { this.permits = res.success ? res.permits : []; this.isLoadingPermits = false; this.cdr.detectChanges(); },
      error: () => { this.isLoadingPermits = false; this.cdr.detectChanges(); }
    });
  }

  unlockPeriod(student: EnrolledStudent, period: string): void {
    if (!confirm(`Unlock ${period} payment period for ${student.firstName} ${student.lastName}?`)) return;
    this.http.post<any>(`${this.apiUrl}?action=unlock_payment_period`, {
      student_id: student.studentId,
      exam_period: period,
      accounting_user_id: this.accountingUserId
    }).subscribe({
      next: (res) => {
        if (res.success) {
          const p = period.toLowerCase() as 'prelim'|'midterm'|'finals';
          (student as any)[p + 'Status'] = 'unpaid';
          this.loadStudentSchedule(student);
          alert(res.message);
        } else {
          alert(res.message || 'Failed to unlock period');
        }
        this.cdr.detectChanges();
      },
      error: () => { alert('Error connecting to server'); this.cdr.detectChanges(); }
    });
  }

  switchMainTab(tab: 'notices'|'permits'): void {
    this.mainTab = tab;
    if (tab === 'permits') this.loadPermits();
    this.cdr.detectChanges();
  }

  switchPermitTab(tab: 'pending'|'approved'|'rejected'): void {
    this.permitTab = tab; this.loadPermits();
  }

  onPermitCardClick(g: CourseGroup): void {
    const key = this.cardKey(g);
    if (this.activePermitCardKey === key) {
      this.activePermitCardKey = '';
      this.filterPermitCategory = 'ALL';
      this.filterPermitProgram  = 'ALL';
      this.filterPermitYear     = 'ALL';
      this.cdr.detectChanges();
      return;
    }
    this.activePermitCardKey  = key;
    // Do NOT set category filter — get_exam_permits doesn't return student_category,
    // so filtering by it would hide all results. Filter by program + year only.
    this.filterPermitCategory = 'ALL';
    this.filterPermitProgram  = g.program || 'ALL';
    this.filterPermitYear     = g.year_level || 'ALL';
    this.permitsPage = 1;
    this.cdr.detectChanges();
  }

  isPermitCardActive(g: CourseGroup): boolean {
    return this.activePermitGroupCardActive(g);
  }

  /**
   * Build course group cards DIRECTLY from the loaded permits (not from courseGroups).
   * Cards only appear when there are actual permits in the current tab,
   * and the filter key uses student_category returned by the API.
   */
  get groupedPermitCards(): { label: string; groups: { program: string; year_level: string; strand: string; semester: string; category: string; count: number }[] }[] {
    type PGroup = { program: string; year_level: string; strand: string; semester: string; category: string; count: number; studentIds: Set<number> };
    const map = new Map<string, PGroup>();
    for (const p of this.permits) {
      const cat = (p.student_category || 'College');
      const key = `${cat}|${p.program}|${p.year_level}|${p.semester}`;
      if (map.has(key)) {
        map.get(key)!.studentIds.add(p.student_id);
      } else {
        map.set(key, { program: p.program, year_level: p.year_level, strand: '', semester: p.semester, category: cat, count: 0, studentIds: new Set([p.student_id]) });
      }
    }
    // Resolve count = number of distinct students in each group
    for (const g of map.values()) {
      g.count = g.studentIds.size;
    }
    const catMap = new Map<string, PGroup[]>();
    for (const g of map.values()) {
      if (!catMap.has(g.category)) catMap.set(g.category, []);
      const { studentIds: _removed, ...clean } = g as any;
      catMap.get(g.category)!.push(clean);
    }
    return Array.from(catMap.entries()).map(([label, groups]) => ({ label, groups }));
  }

  permitCardKey(g: { program: string; year_level: string; semester: string; category: string }): string {
    return `${g.category}|${g.program}|${g.year_level}|${g.semester}`;
  }

  activePermitGroupCardActive(g: { program: string; year_level: string; semester: string; category: string }): boolean {
    return this.activePermitCardKey === this.permitCardKey(g);
  }

  onPermitGroupCardClick(g: { program: string; year_level: string; semester: string; category: string }): void {
    const key = this.permitCardKey(g);
    if (this.activePermitCardKey === key) {
      this.activePermitCardKey = '';
      this.permitsPage = 1;
      this.cdr.detectChanges();
      return;
    }
    this.activePermitCardKey = key;
    this.permitsPage = 1;
    this.cdr.detectChanges();
  }

  get filteredPermits(): ExamPermit[] {
    const q = this.searchPermits.toLowerCase();
    const activeKey = this.activePermitCardKey;
    return this.permits.filter(p => {
      const matchQ = !q
        || (p.first_name + ' ' + p.last_name).toLowerCase().includes(q)
        || p.student_number.toLowerCase().includes(q);
      const matchCard = !activeKey
        || this.permitCardKey({
             program:    p.program,
             year_level: p.year_level,
             semester:   p.semester,
             category:   p.student_category || 'College',
           }) === activeKey;
      return matchQ && matchCard;
    });
  }

  // ── Accordion: expand/collapse per student in Approved/Rejected tab ─────
  expandedStudentKey = '';   // "<student_id>|<student_number>"

  toggleStudentRow(key: string): void {
    this.expandedStudentKey = this.expandedStudentKey === key ? '' : key;
    this.cdr.detectChanges();
  }

  /** Group filteredPermits by student for the accordion view. */
  get groupedApprovedPermits(): Array<{
    key: string;
    studentId: number;
    studentNumber: string;
    firstName: string;
    lastName: string;
    program: string;
    yearLevel: string;
    permits: ExamPermit[];
  }> {
    const map = new Map<string, {
      key: string; studentId: number; studentNumber: string;
      firstName: string; lastName: string; program: string; yearLevel: string;
      permits: ExamPermit[];
    }>();
    for (const p of this.filteredPermits) {
      const key = `${p.student_id}|${p.student_number}`;
      if (!map.has(key)) {
        map.set(key, {
          key,
          studentId:     p.student_id,
          studentNumber: p.student_number,
          firstName:     p.first_name,
          lastName:      p.last_name,
          program:       p.program,
          yearLevel:     p.year_level,
          permits:       [],
        });
      }
      map.get(key)!.permits.push(p);
    }
    return Array.from(map.values());
  }

  openPermitModal(permit: ExamPermit, action: 'approve'|'reject'): void {
    this.selectedPermit = permit; this.modalAction = action; this.remarks = '';
    this.showPermitModal = true; this.cdr.detectChanges();
  }

  closePermitModal(): void { this.showPermitModal = false; this.selectedPermit = null; this.cdr.detectChanges(); }

  confirmPermit(): void {
    if (!this.selectedPermit) return;
    this.isProcessing = true;
    this.http.post<any>(`${this.apiUrl}?action=process_exam_permit`, {
      permit_id:           this.selectedPermit.id,
      action:              this.modalAction,
      remarks:             this.remarks,
      accounting_user_id:  this.accountingUserId
    }).subscribe({
      next: (res) => {
        this.isProcessing = false;
        if (res.success) { this.permits = this.permits.filter(p => p.id !== this.selectedPermit!.id); this.closePermitModal(); }
        this.cdr.detectChanges();
      },
      error: () => { this.isProcessing = false; this.cdr.detectChanges(); }
    });
  }

  printPermit(p: ExamPermit): void {
    this.http.get<any>(`${this.apiUrl}?action=get_permit_details&permit_id=${p.id}&student_id=${p.student_id}`).subscribe({
      next: (res) => {
        const permit  = res.success ? res.permit : p;
        const courses: any[] = permit.courses ?? [];
        const balance         = parseFloat(permit.balance         ?? 0);
        const totalPaid       = parseFloat(permit.total_paid      ?? 0);
        const totalAssessment = parseFloat(permit.total_assessment ?? 0);
        const approvedBy      = `${p.approved_by_first || ''} ${p.approved_by_last || ''}`.trim() || 'Accounting Officer';
        const approvedDate    = p.approved_at
          ? new Date(p.approved_at).toLocaleDateString('en-PH', {year:'numeric',month:'long',day:'numeric'})
          : new Date().toLocaleDateString('en-PH', {year:'numeric',month:'long',day:'numeric'});

        const subjectRows = courses.length > 0
          ? courses.map((s: any) =>
              `<tr><td>${s.code||''}</td><td>${s.name||''}</td><td>${s.instructor||''}</td><td></td><td></td></tr>`
            ).join('')
          : Array(6).fill('<tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>').join('');

        const balanceColor = balance <= 0 ? '#1a7a3c' : '#c8352a';
        const balanceLabel = balance <= 0
          ? '✓ FULLY PAID'
          : `Balance: ₱${balance.toLocaleString('en-PH',{minimumFractionDigits:2})}`;

        const win = window.open('', '_blank', 'width=900,height=750');
        if (!win) return;
        win.document.write(`<!DOCTYPE html><html><head><title>Exam Permit</title>
        <style>
          *{box-sizing:border-box;margin:0;padding:0;}
          body{font-family:Arial,sans-serif;padding:30px;font-size:13px;}
          .header{text-align:center;border-bottom:3px solid #1a3c6e;padding-bottom:12px;margin-bottom:16px;}
          .school{font-size:18px;font-weight:bold;text-transform:uppercase;color:#1a3c6e;}
          .address{font-size:11px;margin-top:4px;color:#555;}
          .permit-title{background:#1a3c6e;color:#fff;font-weight:bold;font-size:15px;padding:8px;margin-top:12px;text-align:center;letter-spacing:2px;}
          .sem-ay{font-size:13px;margin-top:8px;font-weight:bold;}
          .info-grid{display:grid;grid-template-columns:1fr 1fr;gap:6px 20px;margin:14px 0;padding:10px;background:#f5f7fa;border:1px solid #dde;border-radius:4px;}
          .field{padding:3px 0;}
          .field-label{font-size:10px;color:#888;text-transform:uppercase;}
          .field-value{font-weight:bold;font-size:13px;}
          .payment-status{text-align:center;padding:8px;margin:10px 0;border-radius:4px;font-weight:bold;font-size:12px;}
          .note{color:#c00;font-style:italic;font-size:11px;margin:8px 0;text-align:center;}
          .permit-no{font-size:11px;color:#666;text-align:right;margin-bottom:6px;}
          table{width:100%;border-collapse:collapse;margin-top:10px;}
          th,td{border:1px solid #333;padding:7px 8px;font-size:12px;}
          th{background:#1a3c6e;color:#fff;font-weight:bold;text-align:center;}
          .sig-area{display:grid;grid-template-columns:1fr 1fr;gap:40px;margin-top:36px;}
          .sig-line{border-top:2px solid #333;margin-top:44px;text-align:center;font-size:11px;padding-top:5px;}
          .sig-name{font-weight:bold;font-size:12px;}
          .sig-role{font-size:10px;color:#666;margin-top:2px;}
          .issued{font-size:10px;color:#888;text-align:center;margin-top:16px;}
          .no-print{text-align:center;margin-bottom:14px;}
          @media print{.no-print{display:none;}body{padding:15px;}}
        </style></head><body>
        <div class="no-print">
          <button onclick="window.print()" style="background:#1a3c6e;color:white;border:none;padding:8px 20px;border-radius:4px;cursor:pointer;font-size:13px;">🖨️ Print Permit</button>
          <button onclick="window.close()" style="margin-left:8px;background:#eee;border:none;padding:8px 16px;border-radius:4px;cursor:pointer;">Close</button>
        </div>
        <div class="header">
          <div class="school">St. Benilde Center for Global Competence, Inc.</div>
          <div class="address">#2647 Rizal Avenue, West Bajac-Bajac, Olongapo City | Tel/Fax: (047) 223-9031</div>
          <div class="permit-title">${p.exam_period.toUpperCase()} EXAMINATION PERMIT</div>
          <div class="sem-ay">${p.semester} &nbsp;|&nbsp; A.Y. ${p.school_year}</div>
        </div>
        <div class="permit-no">Permit No.: ${permit.permit_identifier || p.id}</div>
        <div class="info-grid">
          <div class="field"><div class="field-label">Student No.</div><div class="field-value">${p.student_number}</div></div>
          <div class="field"><div class="field-label">Name</div><div class="field-value">${p.last_name}, ${p.first_name}</div></div>
          <div class="field"><div class="field-label">Course / Program</div><div class="field-value">${p.program}</div></div>
          <div class="field"><div class="field-label">Year Level</div><div class="field-value">${p.year_level}</div></div>
        </div>
        <div class="payment-status" style="background:${balanceColor}18;border:1px solid ${balanceColor};color:${balanceColor};">
          ${balanceLabel} &nbsp;|&nbsp; Total Assessment: ₱${totalAssessment.toLocaleString('en-PH',{minimumFractionDigits:2})} &nbsp;|&nbsp; Total Paid: ₱${totalPaid.toLocaleString('en-PH',{minimumFractionDigits:2})}
        </div>
        <div class="note">⚠ ANY ERASURE WILL INVALIDATE THIS ${p.exam_period.toUpperCase()} PERMIT</div>
        <table>
          <tr><th>Subject Code</th><th>Subject Description</th><th>Instructor</th><th>Date of Exam</th><th>Instructor's Signature</th></tr>
          ${subjectRows}
        </table>
        <div class="sig-area">
          <div>
            <div class="sig-line">
              <div class="sig-name">${approvedBy}</div>
              <div class="sig-role">Account Management Officer / Cashier</div>
              <div class="sig-role">Date Approved: ${approvedDate}</div>
            </div>
          </div>
          <div>
            <div class="sig-line">
              <div class="sig-name">${p.last_name}, ${p.first_name}</div>
              <div class="sig-role">Student's Signature over Printed Name</div>
            </div>
          </div>
        </div>
        <div class="issued">Issued: ${new Date().toLocaleDateString('en-PH',{year:'numeric',month:'long',day:'numeric'})}</div>
        <script>window.onload=()=>{window.print();}<\/script>
        </body></html>`);
        win.document.close();
      },
      error: () => {
        alert('Could not load permit details. Please try again.');
      }
    });
  }

  periodClass(period: string) { return {Prelim:'badge-prelim',Midterm:'badge-midterm',Finals:'badge-finals'}[period]||''; }
  statusClass(s: string)     { return {unpaid:'st-unpaid',partial:'st-partial',paid:'st-paid',locked:'st-locked'}[s]||''; }
  fmt(n: number)             { return (n||0).toLocaleString('en-PH',{minimumFractionDigits:2}); }
  fmtDate(d: string)         { return d ? new Date(d).toLocaleDateString('en-PH',{year:'numeric',month:'short',day:'numeric'}) : '—'; }
}