import { Component, OnInit, OnDestroy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { Subject, Subscription } from 'rxjs';
import { debounceTime, distinctUntilChanged } from 'rxjs/operators';
import { environment } from '../../environment';
import { MaskRefPipe } from '../../pipes/mask-ref.pipe';

interface PendingPayment {
  logId:           number;
  studentId:       number;
  studentNumber:   string;
  firstName:       string;
  lastName:        string;
  program:         string;
  yearLevel:       string;
  department:      string;
  studentCategory: string;
  paymentMethod:   string;
  gcashReference:  string;
  gcashAmount:     number;
  gcashDate:       string;
  transactionId:   string;
  semester:        string;
  examPeriod:      string;
  enrollmentStatus?: string;
  notes?:          string;
  status:          string;
  submittedAt:     string;
  isScholar?:      boolean;
  scholarType?:    string;
  scholarGrantor?: string;
  scholarshipAmount?: number;
  hasPendingScholarship?: boolean;  // FIX SCHOLAR-VERIFY-01
  pendingScholarType?:    string;
  // Fee fields
  totalAssessment: number;
  totalPaid:       number;
  balance:         number;
  // Payment plan
  paymentPlan?:      string;  // 'full' | 'installment'
  scheduleAmounts?:  { downpayment: number; prelim: number; midterm: number; finals: number; total: number };
  // Actual amount paid per term (from installment_payments) — for showing balance in modal
  termPaidAmounts?:  { downpayment: number; prelim: number; midterm: number; finals: number };
}

interface PaymentHistory extends PendingPayment {
  notes:          string;
  verifiedAt:     string;
  verifiedByName: string;
  orArNumber:     string;
  orArType:       string;
  examPeriod:     string;
}

interface InstallmentRecord {
  id:             number;
  orArNumber:     string;
  orArType:       string;
  studentName:    string;
  studentNumber:  string;
  program:        string;
  yearLevel:      string;
  paymentMethod:  string;
  gcashReference: string;
  examPeriod:     string;
  amount:         number;
  totalAssessment: number;
  paymentDate:    string;
  notes:          string;
  recordedBy:     string;
}

interface LiquidationReport {
  dateFrom:     string;
  dateTo:       string;
  entries:      InstallmentRecord[];
  totalEntries: number;
  totalCash:    number;
  totalGCash:   number;
  grandTotal:   number;
}

// ── UI-02: Per-student payment record ────────────────────────────────────────
interface StudentPaymentRecord {
  id:            number;
  orArNumber:    string;
  orArType:      string;
  examPeriod:    string;
  paymentDate:   string;
  amount:        number;
  paymentMethod: string;
  verifiedBy:    string;
  semester:      string;
}

interface StudentPaymentModalData {
  studentId:       number;
  studentNumber:   string;
  firstName:       string;
  lastName:        string;
  history:         StudentPaymentRecord[];
  totalPaid:       number;
  totalAssessment: number;
  balance:         number;
  guardianEmail:   string;
  semesters:       string[];
  selectedSemester: string;
}

@Component({
  selector: 'app-accounting',
  standalone: true,
  imports: [CommonModule, FormsModule, MaskRefPipe],
  templateUrl: './accounting.html',
  styleUrl: './accounting.css',
})
export class Accounting implements OnInit, OnDestroy {
  private apiUrl   = environment.accountingApi;
  private enrollApi = environment.enrollApi;  // used for get_enrollment_period (public endpoint)

  currentTab: 'pending' | 'history' | 'installment' | 'liquidation' | 'duedates' = 'pending';

  pendingPayments: PendingPayment[] = [];
  paymentHistory:  PaymentHistory[] = [];

  isLoadingPending = true;
  isLoadingHistory = false;
  errorMessage     = '';

  // ── View mode toggle ──────────────────────────────────────
  viewMode: 'thumbnail' | 'list' = 'thumbnail';

  // ── History view mode ─────────────────────────────────────
  historyViewMode: 'thumbnail' | 'list' = 'list';

  // ── Search/Filter (pending tab) ──────────────────────────
  searchQuery        = '';

  // ── Pending course-group cards ────────────────────────────
  activePendingCardKey = '';

  /** Build pending-tab course cards directly from the loaded pendingPayments list.
   *  Only groups that actually have a pending transaction will appear — avoids
   *  showing ALL enrolled students like the shared courseGroups query does. */
  get groupedPendingCards(): { label: string; groups: any[] }[] {
    // Deduplicate by category|program|year_level|semester key
    const map = new Map<string, any>();
    for (const p of this.pendingPayments) {
      const key = `${p.studentCategory}|${p.program}|${p.yearLevel}|${p.semester}`;
      if (!map.has(key)) {
        map.set(key, {
          category:      p.studentCategory || 'College',
          program:       p.program,
          year_level:    p.yearLevel,
          strand:        '',
          semester:      p.semester,
          student_count: 0,
          pending_count: 0,
        });
      }
      const g = map.get(key)!;
      // Count distinct students
      g.student_count++;
      g.pending_count++;
    }
    // Deduplicate student counts (one student may have multiple pending rows)
    const studentMap = new Map<string, Set<number>>();
    for (const p of this.pendingPayments) {
      const key = `${p.studentCategory}|${p.program}|${p.yearLevel}|${p.semester}`;
      if (!studentMap.has(key)) studentMap.set(key, new Set());
      studentMap.get(key)!.add(p.studentId);
    }
    for (const [key, g] of map.entries()) {
      g.student_count = studentMap.get(key)?.size ?? 0;
      g.pending_count = g.student_count;
    }
    // Group by category
    const catMap = new Map<string, any[]>();
    for (const g of map.values()) {
      const cat = g.category || 'College';
      if (!catMap.has(cat)) catMap.set(cat, []);
      catMap.get(cat)!.push(g);
    }
    return Array.from(catMap.entries()).map(([label, groups]) => ({ label, groups }));
  }

  pendingCardKey(g: any): string {
    return `${g.program}|${g.year_level}|${g.semester}|${g.category}`;
  }

  isPendingCardActive(g: any): boolean {
    return this.activePendingCardKey === this.pendingCardKey(g);
  }

  onPendingCardClick(g: any): void {
    const key = this.pendingCardKey(g);
    if (this.activePendingCardKey === key) {
      this.activePendingCardKey = '';
      this.pendingCardFilter    = null;
    } else {
      this.activePendingCardKey = key;
      this.pendingCardFilter    = g;
    }
    this.pendingPage = 1;
    this.cdr.detectChanges();
  }

  // Holds the currently active course-group card filter for pending tab
  pendingCardFilter: any = null;

  // ── Pending semester filter ───────────────────────────────
  pendingFilterSemester = '';

  get pendingSemesterOptions(): string[] {
    const seen = new Set<string>();
    for (const p of this.pendingPayments) {
      if (p.semester) seen.add(p.semester.trim());
    }
    return Array.from(seen).sort();
  }

  onPendingSemesterChange(): void {
    this.pendingPage = 1;
    this.cdr.detectChanges();
  }

  // ── Pending tab pagination ────────────────────────────────
  pendingPage      = 1;
  readonly PENDING_PAGE_SIZE = 10;

  get pendingTotalPages(): number {
    return Math.max(1, Math.ceil(this.groupedPending.length / this.PENDING_PAGE_SIZE));
  }

  get pagedGroupedPending() {
    const start = (this.pendingPage - 1) * this.PENDING_PAGE_SIZE;
    return this.groupedPending.slice(start, start + this.PENDING_PAGE_SIZE);
  }

  pendingPrevPage(): void {
    if (this.pendingPage > 1) { this.pendingPage--; this.cdr.detectChanges(); }
  }

  pendingNextPage(): void {
    if (this.pendingPage < this.pendingTotalPages) { this.pendingPage++; this.cdr.detectChanges(); }
  }

  clearPendingFilters(): void {
    this.searchQuery          = '';
    this.activePendingCardKey = '';
    this.pendingCardFilter    = null;
    this.pendingFilterSemester = '';
    this.pendingPage          = 1;
  }

  get activeFilterCount(): number {
    return [
      this.searchQuery,
      this.activePendingCardKey,
      this.pendingFilterSemester,
    ].filter(v => !!v).length;
  }

  // ── Grouped pending: track which student cards are expanded ──
  expandedPendingStudents = new Set<number>();
  expandedHistoryStudents = new Set<number>();

  togglePendingStudent(studentId: number): void {
    if (this.expandedPendingStudents.has(studentId)) {
      this.expandedPendingStudents.delete(studentId);
    } else {
      this.expandedPendingStudents.add(studentId);
    }
    this.cdr.detectChanges();
  }

  toggleHistoryStudent(studentId: number): void {
    if (this.expandedHistoryStudents.has(studentId)) {
      this.expandedHistoryStudents.delete(studentId);
    } else {
      this.expandedHistoryStudents.add(studentId);
    }
    this.cdr.detectChanges();
  }

  // ── Group helper methods (replaces pipes — avoids NG8113 warnings) ─────────
  groupHasMethod(rows: any[], method: string): boolean {
    return rows.some(r => (r.paymentMethod || '').toLowerCase() === method.toLowerCase());
  }
  groupSumAmount(rows: any[]): number {
    return rows.reduce((sum, r) => sum + (r.gcashAmount || 0), 0);
  }
  groupAllVerified(rows: any[]): boolean {
    return rows.length > 0 && rows.every(r => r.status === 'Verified');
  }
  groupCountVerified(rows: any[]): number {
    return rows.filter(r => r.status === 'Verified').length;
  }
  groupCountRejected(rows: any[]): number {
    return rows.filter(r => r.status === 'Rejected').length;
  }

  /** Flat filtered list — used only for summary counts */
  get filteredPendingFlat(): PendingPayment[] {
    const q   = this.searchQuery.toLowerCase();
    const g   = this.pendingCardFilter;
    const sem = this.pendingFilterSemester;
    return this.pendingPayments.filter(p => {
      const matchSearch = !q
        || (p.firstName + ' ' + p.lastName).toLowerCase().includes(q)
        || p.studentNumber.toLowerCase().includes(q);
      const matchCard = !g
        || (p.program      === g.program
         && p.yearLevel    === g.year_level
         && p.semester     === g.semester
         && (p.studentCategory || 'College') === (g.category || 'College'));
      const matchSem = !sem || p.semester === sem;
      return matchSearch && matchCard && matchSem;
    });
  }

  /** Grouped pending: Map<studentId, PendingPayment[]> — for the accordion view */
  get groupedPending(): { studentId: number; firstName: string; lastName: string; studentNumber: string; program: string; yearLevel: string; rows: PendingPayment[] }[] {
    const map = new Map<number, PendingPayment[]>();
    for (const p of this.filteredPendingFlat) {
      const existing = map.get(p.studentId);
      if (existing) existing.push(p);
      else map.set(p.studentId, [p]);
    }
    return Array.from(map.entries()).map(([studentId, rows]) => ({
      studentId,
      firstName:     rows[0].firstName,
      lastName:      rows[0].lastName,
      studentNumber: rows[0].studentNumber,
      program:       rows[0].program,
      yearLevel:     rows[0].yearLevel,
      rows,
    }));
  }

  /** Total pending amount across all filtered rows */
  get pendingTotalAmount(): number {
    return this.filteredPendingFlat.reduce((s, p) => s + (p.gcashAmount || 0), 0);
  }

  /** Grouped history: Map<studentId, PaymentHistory[]> — for the accordion view */
  get groupedHistory(): { studentId: number; firstName: string; lastName: string; studentNumber: string; program: string; rows: PaymentHistory[] }[] {
    const map = new Map<number, PaymentHistory[]>();
    for (const h of this.paymentHistory) {
      const existing = map.get(h.studentId);
      if (existing) existing.push(h);
      else map.set(h.studentId, [h]);
    }
    return Array.from(map.entries()).map(([studentId, rows]) => ({
      studentId,
      firstName:     rows[0].firstName,
      lastName:      rows[0].lastName,
      studentNumber: rows[0].studentNumber,
      program:       rows[0].program,
      // Sort by semester desc (most recent first), then by date desc within each semester
      rows: [...rows].sort((a, b) => {
        const semCmp = (b.semester ?? '').localeCompare(a.semester ?? '');
        if (semCmp !== 0) return semCmp;
        return (b.gcashDate || b.verifiedAt || '').localeCompare(a.gcashDate || a.verifiedAt || '');
      }),
    }));
  }

  // ── History: server-side pagination & filter state ────────
  historySearchQuery      = '';
  historyFilterMethod     = '';          // '' | 'Cash' | 'GCash'
  historyFilterPeriod     = '';          // '' | 'Prelim' | 'Midterm' | 'Finals' | 'Full' | 'Downpayment'
  historyFilterSemester   = '';          // free text partial match
  historyFilterCategory   = '';          // '' | 'College' | 'SHS' | 'TVET'
  historyFilterDepartment = '';          // free text partial match
  historyFilterYearLevel  = '';          // '' | '1st Year' | '2nd Year' | etc.
  historyFilterStatus     = '';          // '' | 'Verified' | 'Rejected'
  historyPage             = 1;
  historyLimit            = 25;
  historyTotal            = 0;
  historyTotalPages       = 1;

  get historyActiveFilterCount(): number {
    return [
      this.historySearchQuery,
      this.historyFilterMethod,
      this.historyFilterPeriod,
      this.historyFilterSemester,
      this.historyFilterCategory,
      this.historyFilterDepartment,
      this.historyFilterYearLevel,
      this.historyFilterStatus,
    ].filter(v => !!v).length;
  }

  // Dynamic program options — scoped by selected category, derived from loaded courseGroups
  get historyProgramOptions(): string[] {
    const cat = this.historyFilterCategory;
    const groups = cat
      ? this.courseGroups.filter(g => (g.category || '').toUpperCase() === cat.toUpperCase())
      : this.courseGroups;
    const seen = new Set<string>();
    for (const g of groups) {
      if (g.program) seen.add(g.program.trim());
    }
    return Array.from(seen).sort();
  }

  // Dynamic year level options — scoped by category
  get historyYearLevelOptions(): { value: string; label: string }[] {
    const cat = this.historyFilterCategory;
    if (cat === 'SHS') return [
      { value: 'Grade 11', label: 'Grade 11' },
      { value: 'Grade 12', label: 'Grade 12' },
    ];
    if (cat === 'TVET') return [
      { value: '1st Year', label: '1st Year' },
      { value: '2nd Year', label: '2nd Year' },
      { value: '3rd Year', label: '3rd Year' },
    ];
    if (cat === 'College') return [
      { value: '1st Year', label: '1st Year' },
      { value: '2nd Year', label: '2nd Year' },
      { value: '3rd Year', label: '3rd Year' },
      { value: '4th Year', label: '4th Year' },
    ];
    // All categories
    return [
      { value: '1st Year',  label: '1st Year'  },
      { value: '2nd Year',  label: '2nd Year'  },
      { value: '3rd Year',  label: '3rd Year'  },
      { value: '4th Year',  label: '4th Year'  },
      { value: 'Grade 11',  label: 'Grade 11'  },
      { value: 'Grade 12',  label: 'Grade 12'  },
    ];
  }

  get historyYearLevelLabel(): string {
    return this.historyFilterCategory === 'SHS' ? 'All Grade Levels' : 'All Year Levels';
  }

  // Dynamic semester options — scoped by category, deduped
  get historySemesterOptions(): string[] {
    const cat = this.historyFilterCategory;
    const groups = cat
      ? this.courseGroups.filter(g => (g.category || '').toUpperCase() === cat.toUpperCase())
      : this.courseGroups;
    const seen = new Set<string>();
    for (const g of groups) {
      if (g.semester) seen.add(g.semester.trim());
    }
    return Array.from(seen).sort();
  }

  // Reset dependent filters when category changes
  onHistoryCategoryChange(): void {
    this.historyFilterDepartment = '';
    this.historyFilterYearLevel  = '';
    this.historyFilterSemester   = '';
    this.applyHistoryFilters();
  }

  // ── History course group cards ─────────────────────────────────────────────
  activeHistoryCardKey = '';

  historyCardKey(g: any): string {
    return `${g.program}|${g.year_level}|${g.semester}|${g.category}`;
  }

  get groupedHistoryCards(): { label: string; groups: any[] }[] {
    const catMap = new Map<string, any[]>();
    for (const g of this.courseGroups) {
      const cat = g.category || 'College';
      if (!catMap.has(cat)) catMap.set(cat, []);
      catMap.get(cat)!.push(g);
    }
    return Array.from(catMap.entries()).map(([label, groups]) => ({ label, groups }));
  }

  onHistoryCardClick(g: any): void {
    const key = this.historyCardKey(g);
    if (this.activeHistoryCardKey === key) {
      this.activeHistoryCardKey    = '';
      this.historyFilterDepartment = '';
      this.historyFilterYearLevel  = '';
      this.historyFilterSemester   = '';
      this.historyFilterCategory   = '';
    } else {
      this.activeHistoryCardKey    = key;
      this.historyFilterDepartment = g.program  || '';
      this.historyFilterYearLevel  = g.year_level || '';
      this.historyFilterSemester   = g.semester  || '';
      this.historyFilterCategory   = g.category  || '';
    }
    this.applyHistoryFilters();
  }

  isHistoryCardActive(g: any): boolean {
    return this.activeHistoryCardKey === this.historyCardKey(g);
  }

  clearHistoryFilters(): void {
    this.historySearchQuery      = '';
    this.historyFilterMethod     = '';
    this.historyFilterPeriod     = '';
    this.historyFilterSemester   = '';
    this.historyFilterCategory   = '';
    this.historyFilterDepartment = '';
    this.historyFilterYearLevel  = '';
    this.historyFilterStatus     = '';
    this.activeHistoryCardKey    = '';
    this.historyPage             = 1;
    this.loadPaymentHistory();
  }

  onHistorySearchInput(value: string): void {
    this.historySearch$.next(value);
  }

  private historySearch$ = new Subject<string>();
  private historySubs    = new Subscription();

  showModal        = false;
  modalMode: 'approve' | 'reject' | 'edit' | 'editHistory' = 'approve';
  selectedPayment: PendingPayment | null = null;
  modalNotes       = ''  ;
  isProcessing     = false;

  // Cash-specific fields for accounting to fill in
  cashAmount: number = 0;
  cashDate:   string = new Date().toISOString().split('T')[0];
  cashAmountError: string = '';

  // ── Edit form ─────────────────────────────────────────────
  editForm: any = {};

  // ── Installment recording ─────────────────────────────────
  showInstallmentModal = false;
  installmentForm: any = {
    student_id:      0,
    amount:          0,
    payment_date:    new Date().toISOString().split('T')[0],
    payment_method:  'Cash',
    gcash_reference: '',
    exam_period:     'Downpayment',
    or_ar_type:      'AR',
    notes:           '',
  };
  installmentResult: { orArNumber: string; orArType: string; totalPaid: number; balance: number; isFullyPaid: boolean } | null = null;
  installmentStudents: PendingPayment[] = [];  // enrolled installment students with remaining balance
  isLoadingInstallmentStudents = false;
  selectedInstallmentStudent: PendingPayment | null = null;

  // ── Liquidation ───────────────────────────────────────────
  liquidationReport: LiquidationReport | null = null;
  liquidationDateFrom = new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0];
  liquidationDateTo   = new Date().toISOString().split('T')[0];
  isLoadingLiquidation = false;

  accountingUserId = 0;

  // ── Due Dates ─────────────────────────────────────────────
  dueDatesLoading   = false;
  dueDatesSaving    = false;
  dueDatesSaved     = false;
  // Always driven from admin enrollment period — never manually set by accounting.
  dueDatesSemester   = '';   // e.g. "1st Semester"  (from admin enrollment period)
  dueDatesSchoolYear = '';   // e.g. "2025-2026"     (from admin enrollment period)
  dueDates: Record<string, { label: string; date_range: string }> = {
    downpayment: { label: 'Downpayment', date_range: '' },
    prelim:      { label: 'Prelim',      date_range: '' },
    midterm:     { label: 'Midterm',     date_range: '' },
    finals:      { label: 'Finals',      date_range: '' },
  };

  // ── UI-02: Per-Student Payment Modal ──────────────────────
  showStudentPaymentModal  = false;
  studentPaymentModalTab: 'history' | 'soa' = 'history';
  studentPaymentModalData: StudentPaymentModalData | null = null;
  isLoadingStudentPayments = false;
  isSendingSoaModal        = false;
  soaModalResult           = '';
  // ─────────────────────────────────────────────────────────

  constructor(private http: HttpClient, private cdr: ChangeDetectorRef) {}

  printSoa(): void {
    const snap    = this.soaViewerSnapshot;
    const student = this.soaViewerStudent;
    if (!snap || !student) return;

    const fmt = (n: number) => (+n || 0).toLocaleString('en-PH', { minimumFractionDigits: 2 });
    const isInstallment = snap.payment_plan === 'installment';
    const rows = this.soaViewerInstallmentRows();

    const fmtDate = (d: string) => d
      ? new Date(d).toLocaleDateString('en-PH', { month: '2-digit', day: '2-digit', year: '2-digit' })
      : '';

    // ── Due dates from snap (stored by accounting in sys_config) ─────────────
    // snap.due_dates shape: { downpayment: { date_range }, prelim: {...}, ... }
    // Falls back to empty string so cells are just blank if not configured.
    const dueDates: Record<string, { label?: string; date_range?: string } | string> = snap.due_dates ?? {};
    const getDueRange = (key: string): string => {
      const entry = dueDates[key.toLowerCase()] ?? dueDates[key];
      if (!entry) return '';
      if (typeof entry === 'string') return entry;
      return entry.date_range ?? '';
    };

    // ── Schedule row builder — matches student enrollment viewSOA exactly ─────
    // Shows due date range; replaces it with "Paid: DD/MM/YY" once payment recorded.
    const scheduleRow = (label: string, term: string, amount: number, row: typeof rows[0] | null) => {
      const dueRange   = getDueRange(term);
      const paidDateStr = row?.paid && row.paymentDate ? fmtDate(row.paymentDate) : '';
      const dateCell = dueRange
        ? `${dueRange}${paidDateStr ? `<br><span style="color:#166534;font-size:9px;">Paid: ${paidDateStr}</span>` : ''}`
        : (paidDateStr || '');
      const highlight = !row?.paid && term === 'Prelim' ? 'style="color:#c00;font-weight:700;"' : '';
      return `<tr>
        <td style="padding:3px 6px;">${label}</td>
        <td style="padding:3px 6px;" ${highlight}>${dateCell}</td>
        <td style="padding:3px 6px;text-align:right;">${row?.paid ? fmt(row.amountPaid) : ''}</td>
        <td style="padding:3px 6px;text-align:center;font-size:9px;color:#1d4ed8;font-weight:700;">${row?.paid ? (row.orNo || '') : ''}</td>
      </tr>`;
    };

    // ── Schedule of payment rows ─────────────────────────────────────────────
    let schedRows = '';
    if (isInstallment) {
      const getRow = (term: string) => rows.find(r => r.term === term) ?? null;
      schedRows = [
        scheduleRow('Downpayment', 'Downpayment', getRow('Downpayment')?.amount ?? 0, getRow('Downpayment')),
        '<tr><td colspan="4" style="padding:1px;"></td></tr>',
        scheduleRow('PRELIM',      'Prelim',      getRow('Prelim')?.amount      ?? 0, getRow('Prelim')),
        '<tr><td colspan="4" style="padding:1px;"></td></tr>',
        scheduleRow('MIDTERM',     'Midterm',     getRow('Midterm')?.amount     ?? 0, getRow('Midterm')),
        '<tr><td colspan="4" style="padding:1px;"></td></tr>',
        scheduleRow('FINAL',       'Finals',      getRow('Finals')?.amount      ?? 0, getRow('Finals')),
      ].join('');
    } else {
      const fp = snap.payments?.[0];
      const paidDateStr = fp?.payment_date ? fmtDate(fp.payment_date) : '';
      schedRows = `<tr>
        <td style="padding:3px 6px;">Full Payment</td>
        <td style="padding:3px 6px;">${paidDateStr ? `<span style="color:#166534;font-weight:600;">${paidDateStr}</span>` : ''}</td>
        <td style="padding:3px 6px;text-align:right;">${snap.total_paid > 0 ? fmt(snap.total_paid) : ''}</td>
        <td style="padding:3px 6px;text-align:center;">${fp?.or_ar_number || ''}</td>
      </tr>`;
    }

    // ── Extra fees rows (from subjects_json / stored fee breakdown) ───────────
    const extraFees: any[] = snap.extra_fees ?? [];
    const extraFeeRows = extraFees.map((ef: any) =>
      `<tr><td>${ef.fee_label}${ef.is_per_unit ? ` (${snap.units} units &times; ${fmt(ef.rate)})` : ''}</td><td>${fmt(ef.amount)}</td></tr>`
    ).join('');

    // ── Exam covered — derive from payment status (mirrors student view logic) ─
    const examCovered = (() => {
      if (!isInstallment) return snap.total_paid > 0 ? 'FINAL' : '—';
      const getRow = (t: string) => rows.find(r => r.term === t);
      if (getRow('Finals')?.paid)      return 'FINAL';
      if (getRow('Midterm')?.paid)     return 'MIDTERM';
      if (getRow('Prelim')?.paid)      return 'PRELIM';
      if (getRow('Downpayment')?.paid) return 'DOWNPAYMENT';
      return '—';
    })();

    const html = `<!DOCTYPE html><html><head><meta charset="utf-8">
<title>Statement of Account — ${student.name}</title>
<style>
  *{box-sizing:border-box;margin:0;padding:0;}
  body{font-family:Arial,sans-serif;font-size:10px;padding:18px 22px;color:#000;width:780px;}
  /* Header */
  .top-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;}
  .logo-area{display:flex;align-items:center;gap:10px;}
  .logo-circle{width:70px;height:70px;border:2px solid #000;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:9px;text-align:center;font-weight:900;}
  .school-info-center{text-align:center;flex:1;}
  .school-name-big{font-size:18px;font-weight:900;text-transform:uppercase;letter-spacing:1px;color:#1a1a6e;}
  .school-name-sub{font-size:9px;text-transform:uppercase;letter-spacing:0.5px;}
  .school-addr{font-size:8.5px;color:#444;margin-top:1px;}
  .badges-right{display:flex;gap:8px;align-items:center;}
  .badge-img{width:45px;height:45px;border:1px solid #ccc;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:7px;text-align:center;}
  /* SOA Title */
  .soa-title{text-align:center;font-size:11px;font-weight:900;text-transform:uppercase;border-top:2px solid #000;border-bottom:1px solid #000;padding:3px;margin:6px 0;}
  /* Student Info Bar */
  .info-bar{display:grid;grid-template-columns:1fr auto auto;gap:0;border:1px solid #000;margin-bottom:4px;}
  .info-cell{padding:3px 8px;border-right:1px solid #000;font-size:10px;}
  .info-cell:last-child{border-right:none;}
  .info-label{font-size:8.5px;color:#555;}
  .info-val{font-weight:700;font-size:11px;}
  .info-bar2{display:grid;grid-template-columns:1fr 1fr 1fr;border:1px solid #000;border-top:none;margin-bottom:8px;}
  /* Two column layout */
  .main-grid{display:grid;grid-template-columns:300px 1fr;gap:16px;}
  /* Assessment table */
  .assess-table{width:100%;border-collapse:collapse;font-size:10px;}
  .assess-table td{padding:2px 4px;border-bottom:1px dotted #ddd;}
  .assess-table td:last-child{text-align:right;min-width:70px;}
  .assess-section-title{text-align:center;font-size:10px;font-weight:700;background:#f0f0f0;border:1px solid #ccc;padding:3px;margin-bottom:2px;}
  .subtotal-row td{font-weight:700;border-top:1px solid #000;padding-top:3px;}
  .total-row td{font-weight:900;font-size:11px;background:#d0d0d0;padding:3px 4px;}
  .final-row td{font-weight:900;font-size:12px;background:#4040a0;color:#fff;padding:4px;}
  /* Schedule table */
  .sched-table{width:100%;border-collapse:collapse;font-size:10px;}
  .sched-table th{background:#4040a0;color:#fff;padding:3px 6px;text-align:left;font-size:9.5px;}
  .sched-table td{border:1px solid #ccc;padding:2px 6px;}
  .total-balance-box{border:2px solid #000;padding:5px 10px;text-align:center;margin:10px 0;}
  .total-balance-label{font-size:11px;font-weight:700;}
  .total-balance-amt{font-size:16px;font-weight:900;color:#c00;}
  /* Installment schedule */
  .install-table{width:100%;border-collapse:collapse;font-size:10px;margin-top:8px;}
  .install-table th{background:#f0c040;color:#000;font-weight:700;padding:3px 6px;border:1px solid #999;}
  .install-table td{border:1px solid #ccc;padding:3px 6px;}
  /* Withdrawal policies */
  .policies{font-size:8px;margin-top:10px;color:#333;}
  .policies p{margin-bottom:2px;}
  /* Signature */
  .sig-area{display:grid;grid-template-columns:1fr 1fr;gap:30px;margin-top:16px;}
  .sig-block{text-align:center;}
  .sig-name{font-size:11px;font-weight:900;border-bottom:1.5px solid #000;padding-bottom:2px;margin-bottom:2px;}
  .sig-title{font-size:9px;}
  .sig-date{font-size:9px;margin-top:8px;}
  @media print{body{padding:8px 12px;}@page{margin:8mm;size:A4;} .no-print{display:none!important;}}
</style></head><body>

<!-- HEADER -->
<div class="top-header">
  <div class="logo-area">
    <div class="logo-circle">ST.<br>BENILDE</div>
  </div>
  <div class="school-info-center">
    <div class="school-name-big">ST. BENILDE</div>
    <div class="school-name-sub">Center for Global Competence, Inc.</div>
    <div class="school-addr">2647 RIZAL AVENUE, WEST BAJAC-BAJAC, OLONGAPO CITY &nbsp;|&nbsp; TELEFAX: (047) 223 - 9031</div>
  </div>
  <div class="badges-right">
    <div class="badge-img">CHED<br>Reg.</div>
    <div class="badge-img">DepEd<br>Reg.</div>
    <div class="badge-img">TESDA<br>Reg.</div>
  </div>
</div>

<div class="soa-title">STATEMENT OF ACCOUNT &nbsp; ${snap.semester}</div>

<!-- Student Info -->
<div class="info-bar">
  <div class="info-cell">
    <div class="info-label">Name:</div>
    <div class="info-val">${student.name.toUpperCase()}</div>
  </div>
  <div class="info-cell">
    <div class="info-label">Course:</div>
    <div class="info-val">${student.program || ''}</div>
  </div>
  <div class="info-cell">
    <div class="info-label">Department:</div>
    <div class="info-val">ICTD</div>
  </div>
</div>
<div class="info-bar2">
  <div class="info-cell">
    <div class="info-label">EXAMINATION COVERED</div>
    <div class="info-val">${examCovered}</div>
  </div>
  <div class="info-cell">
    <div class="info-label">Semester:</div>
    <div class="info-val">${snap.semester}</div>
  </div>
  <div class="info-cell">
    <div class="info-label">Student No.:</div>
    <div class="info-val">${student.number}</div>
  </div>
</div>

<!-- Main 2-col layout -->
<div class="main-grid">

  <!-- LEFT: Assessment -->
  <div>
    <div class="assess-section-title">ASSESSMENT FOR THE CURRENT SEMESTER</div>
    <table class="assess-table">
      <tr><td>No. of Units</td><td>${snap.units || ''}</td></tr>
      <tr><td>Tuition Fee</td><td>${fmt(snap.tuition_fee)}</td></tr>
      <tr><td>Miscellaneous Fee</td><td>${fmt(snap.miscellaneous_fee)}</td></tr>
      <tr><td>Registration Fee</td><td>${fmt(snap.registration_fee)}</td></tr>
      <tr><td>NSTP Fee</td><td></td></tr>
      <tr><td>ENERGY FEE</td><td>${snap.energy_fee ? fmt(snap.energy_fee) : ''}</td></tr>
      ${extraFeeRows}
      <tr><td>Supervision Fee</td><td></td></tr>
      <tr><td style="padding-top:4px;"># of laboratory</td><td></td></tr>
      <tr><td>Laboratory Fees:</td><td>${snap.laboratory_fee ? fmt(snap.laboratory_fee) : ''}</td></tr>
      <tr><td style="padding-left:12px;">Computer Lab.</td><td></td></tr>
      <tr><td style="padding-left:12px;">Kitchen Lab.</td><td></td></tr>
      <tr><td style="padding-left:12px;">Bartending Lab.</td><td></td></tr>
      <tr><td style="padding-left:12px;">F&amp;B Lab.</td><td></td></tr>
      <tr><td style="padding-left:12px;">Housekeeping Lab.</td><td></td></tr>
      <tr><td>Penalty Late Payment</td><td></td></tr>
      <tr><td>&nbsp;</td><td></td></tr>
      <tr class="subtotal-row"><td>Subtotal</td><td>${fmt(snap.subtotal)}</td></tr>
      <tr><td>Discount of TF</td><td>${snap.discount > 0 ? fmt(snap.discount) : '- -'}</td></tr>
      <tr><td>Installment Fee</td><td>${isInstallment ? fmt(snap.installment_fee || 0) : ''}</td></tr>
      <tr class="total-row"><td>TOTAL ASSESSMENT</td><td>${fmt(snap.total_assessment)}</td></tr>
    </table>
    <table class="assess-table" style="margin-top:4px;">
      <tr class="final-row"><td>FINAL ASSESSMENT</td><td>${fmt(snap.total_assessment)}</td></tr>
    </table>
  </div>

  <!-- RIGHT: Schedule of Payment -->
  <div>
    <table class="sched-table">
      <thead>
        <tr>
          <th>SCHEDULE OF PAYMENT</th>
          <th>DATE OF PAYMENTS</th>
          <th style="text-align:right;">PAYMENTS</th>
          <th style="text-align:center;">O.R. NUMBER</th>
        </tr>
      </thead>
      <tbody>${schedRows}</tbody>
    </table>

    <div class="total-balance-box">
      <span class="total-balance-label">Total Balance &nbsp;&nbsp;</span>
      <span class="total-balance-amt">${fmt(snap.balance)}</span>
    </div>

    <!-- Installment breakdown — only for installment plan with remaining balance -->
    ${isInstallment && snap.balance > 0 ? `
    <table class="install-table">
      <thead>
        <tr>
          <th>INSTALLMENT PAYMENT</th>
          <th>DUE DATES</th>
          <th style="text-align:right;">AMOUNT</th>
        </tr>
      </thead>
      <tbody>
        <tr><td>Downpayment :</td><td>${getDueRange('downpayment') || ''}</td><td style="text-align:right;">${fmt(rows.find(r => r.term === 'Downpayment')?.amount ?? 0)}</td></tr>
        <tr><td style="color:#c00;font-weight:700;">Prelim :</td><td style="color:#c00;font-weight:700;">${getDueRange('prelim')}</td><td style="text-align:right;">${fmt(rows.find(r => r.term === 'Prelim')?.amount ?? 0)}</td></tr>
        <tr><td>Midterm:</td><td>${getDueRange('midterm')}</td><td style="text-align:right;">${fmt(rows.find(r => r.term === 'Midterm')?.amount ?? 0)}</td></tr>
        <tr><td>Final:</td><td>${getDueRange('finals')}</td><td style="text-align:right;">${fmt(rows.find(r => r.term === 'Finals')?.amount ?? 0)}</td></tr>
      </tbody>
    </table>
    <div style="text-align:right;font-size:11px;font-weight:700;margin-top:6px;padding-right:4px;">
      Total amount to be paid: &nbsp; <span style="background:#add8e6;padding:2px 8px;">${fmt(snap.total_assessment)}</span>
    </div>` : ''}
  </div>
</div>

<!-- Withdrawal Policies -->
<div class="policies">
  <strong>Withdrawal Policies</strong>
  <p>1. In case of withdrawal of enrollment, the amount of Php7,388.00 (Registration and Miscellaneous fees) shall be retained at all times.</p>
  <p>2. In case withdrawal is filed during the first week of classes, 50% of other fees shall be paid in addition to Php7,388.00.</p>
  <p>3. In case withdrawal is filed during the second week of classes, 50% of other fees shall be paid in addition to Php7,388.00.</p>
  <p>4. In case withdrawal is filed during the third week of classes, 100% of the total assessment shall be paid.</p>
  <p>5. No document shall be released to any withdrawing student without complete payment of financial obligation.</p>
</div>

<!-- Signatures -->
<div class="sig-area">
  <div class="sig-block">
    <div class="sig-name">Jhomer M. Onoya</div>
    <div class="sig-title">Account Management Officer</div>
    <div class="sig-date">DATE &nbsp;&nbsp;&nbsp;&nbsp; ${new Date().toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}</div>
  </div>
  <div class="sig-block">
    <div class="sig-name">${student.name.toUpperCase()}</div>
    <div class="sig-title">Signature Over Printed Name</div>
    <div style="margin-top:8px;">
      <span style="font-size:9px;">Acknowledged by:</span>
    </div>
  </div>
</div>

<div class="no-print" style="text-align:center;margin-top:16px;">
  <button onclick="window.print()" style="background:#1a1a6e;color:white;border:none;padding:10px 32px;font-size:14px;font-weight:700;border-radius:7px;cursor:pointer;margin-right:10px;">🖨️ Print SOA</button>
  <button onclick="window.close()" style="background:#64748b;color:white;border:none;padding:10px 24px;font-size:14px;font-weight:700;border-radius:7px;cursor:pointer;">✕ Close</button>
</div>

</body></html>`;

    const win = window.open('', '_blank', 'width=860,height=900');
    if (!win) return;
    win.document.write(html);
    win.document.close();
  }

  // Course groups for dynamic filter dropdowns
  courseGroups: any[] = [];

  ngOnInit(): void {
    const stored = sessionStorage.getItem('currentUser');
    if (stored) {
      const u = JSON.parse(stored);
      this.accountingUserId = u.id;
    }
    this.loadPendingPayments();
    this.loadCourseGroups();
    // Pre-fill semester + school year from admin enrollment period on startup
    // so the Payment Due Dates tab already shows the correct scope when opened.
    this.loadDueDatesWithPeriod();

    // Wire 350ms debounce on the history search input
    this.historySubs.add(
      this.historySearch$.pipe(
        debounceTime(350),
        distinctUntilChanged()
      ).subscribe(q => {
        this.historySearchQuery = q;
        this.historyPage = 1;
        this.loadPaymentHistory();
      })
    );
  }

  ngOnDestroy(): void {
    this.historySubs.unsubscribe();
  }

  isCash(p: PendingPayment | null): boolean  { return p?.paymentMethod?.toLowerCase() === 'cash'; }
  isGCash(p: PendingPayment | null): boolean { return p?.paymentMethod?.toLowerCase() === 'gcash'; }

  get filteredPending(): PendingPayment[] { return this.filteredPendingFlat; }

  // filteredHistory removed — history is now filtered server-side via loadPaymentHistory()

  // ── Data loading ──────────────────────────────────────────
  loadPendingPayments(): void {
    this.isLoadingPending = true;
    this.cdr.detectChanges();
    this.http.get<any>(`${this.apiUrl}?action=get_pending_payments`).subscribe({
      next: (res) => { this.pendingPayments = res.success ? res.payments : []; this.isLoadingPending = false; this.cdr.detectChanges(); },
      error: () => { this.errorMessage = 'Failed to load pending payments.'; this.isLoadingPending = false; this.cdr.detectChanges(); }
    });
  }

  loadCourseGroups(): void {
    this.http.get<any>(`${this.apiUrl}?action=get_course_groups`).subscribe({
      next: (res) => { this.courseGroups = res.success ? res.groups : []; this.cdr.detectChanges(); },
      error: () => {}
    });
  }

  loadPaymentHistory(): void {
    this.isLoadingHistory = true;
    this.cdr.detectChanges();

    const params: Record<string, string> = {
      action: 'get_payment_history',
      page:   String(this.historyPage),
      limit:  String(this.historyLimit),
    };
    if (this.historySearchQuery.trim())      params['q']            = this.historySearchQuery.trim();
    if (this.historyFilterMethod)            params['method']       = this.historyFilterMethod;
    if (this.historyFilterPeriod)            params['exam_period']  = this.historyFilterPeriod;
    if (this.historyFilterSemester.trim())   params['semester']     = this.historyFilterSemester.trim();
    if (this.historyFilterCategory)          params['category']     = this.historyFilterCategory;
    if (this.historyFilterDepartment.trim()) params['department']   = this.historyFilterDepartment.trim();
    if (this.historyFilterYearLevel)         params['year_level']   = this.historyFilterYearLevel;
    if (this.historyFilterStatus)            params['status']       = this.historyFilterStatus;

    const qs = Object.entries(params).map(([k, v]) => `${k}=${encodeURIComponent(v)}`).join('&');

    this.http.get<any>(`${this.apiUrl}?${qs}`).subscribe({
      next: (res) => {
        this.paymentHistory    = res.success ? res.history : [];
        this.historyTotal      = res.total      ?? 0;
        this.historyTotalPages = res.totalPages ?? 1;
        this.isLoadingHistory  = false;
        this.cdr.detectChanges();
      },
      error: () => { this.isLoadingHistory = false; this.cdr.detectChanges(); }
    });
  }

  onHistorySearchChange(value: string): void {
    this.historySearch$.next(value);
  }

  historyPrevPage(): void {
    if (this.historyPage > 1) { this.historyPage--; this.loadPaymentHistory(); }
  }

  historyNextPage(): void {
    if (this.historyPage < this.historyTotalPages) { this.historyPage++; this.loadPaymentHistory(); }
  }

  applyHistoryFilters(): void {
    this.historyPage = 1;
    this.loadPaymentHistory();
  }

  loadInstallmentStudents(): void {
    this.isLoadingInstallmentStudents = true;
    this.cdr.detectChanges();
    this.http.get<any>(`${this.apiUrl}?action=get_installment_students`).subscribe({
      next: (res) => {
        this.isLoadingInstallmentStudents = false;
        this.installmentStudents = res.success ? res.students : [];
        this.cdr.detectChanges();
      },
      error: () => { this.isLoadingInstallmentStudents = false; this.cdr.detectChanges(); }
    });
  }

  switchTab(tab: 'pending' | 'history' | 'installment' | 'liquidation' | 'duedates'): void {
    this.currentTab = tab;
    this.searchQuery = '';
    if (tab === 'history') {
      // Reset history pagination/filters when switching to this tab
      this.historyPage             = 1;
      this.historySearchQuery      = '';
      this.historyFilterMethod     = '';
      this.historyFilterPeriod     = '';
      this.historyFilterSemester   = '';
      this.historyFilterCategory   = '';
      this.historyFilterDepartment = '';
      this.historyFilterYearLevel  = '';
      this.historyFilterStatus     = '';
      this.loadPaymentHistory();
    }
    if (tab === 'installment')  this.loadInstallmentStudents();
    if (tab === 'liquidation')  this.loadLiquidation();
    if (tab === 'duedates')     this.loadDueDatesWithPeriod();
    this.cdr.detectChanges();
  }

  // ── Modal ─────────────────────────────────────────────────
  openApprove(payment: PendingPayment): void {
    this.selectedPayment = payment;
    this.modalMode  = 'approve';
    this.modalNotes = '';
    this.cashDate   = new Date().toISOString().split('T')[0];
    this.cashAmountError = '';
    // FIX CASH-PREFILL-01: Pre-fill cash amount in priority order:
    //   1. Student-submitted amount (gcashAmount from payment_logs) — most accurate;
    //      submit_installment_payment saves the amount the student typed in the modal.
    //   2. Scheduled balance for the term (installment due - already paid).
    //   3. Total assessment (full-payment fallback).
    // Previously always used totalAssessment for Cash, ignoring the student's input.
    if (payment.gcashAmount && payment.gcashAmount > 0) {
      // Student submitted a specific amount — pre-fill with that value.
      // Accounting can still change it if the actual received amount differs.
      this.cashAmount = payment.gcashAmount;
    } else if (payment.paymentPlan === 'installment' && payment.scheduleAmounts) {
      const ep = payment.examPeriod || 'Downpayment';
      const sa = payment.scheduleAmounts;
      const tp = payment.termPaidAmounts;
      const due = ep === 'Prelim'  ? sa.prelim  :
                  ep === 'Midterm' ? sa.midterm :
                  ep === 'Finals'  ? sa.finals  : sa.downpayment;
      const alreadyPaid = tp
        ? (ep === 'Prelim'  ? tp.prelim  :
           ep === 'Midterm' ? tp.midterm :
           ep === 'Finals'  ? tp.finals  : tp.downpayment)
        : 0;
      this.cashAmount = Math.max(0, due - alreadyPaid);
    } else {
      this.cashAmount = payment.totalAssessment || 0;
    }
    this.showModal  = true;
    this.cdr.detectChanges();
  }

  openReject(payment: PendingPayment): void {
    this.selectedPayment = payment;
    this.modalMode  = 'reject';
    this.modalNotes = '';
    this.showModal  = true;
    this.cdr.detectChanges();
  }

  openEdit(payment: PendingPayment): void {
    this.selectedPayment = payment;
    this.modalMode  = 'edit';
    this.cashAmountError = '';
    this.editForm   = {
      firstName:        payment.firstName,
      lastName:         payment.lastName,
      program:          payment.program,
      yearLevel:        payment.yearLevel,
      gcashReference:   payment.gcashReference,
      gcashAmount:      payment.gcashAmount,
      gcashDate:        payment.gcashDate,
      semester:         payment.semester,
    };
    this.showModal  = true;
    this.cdr.detectChanges();
  }

  closeModal(): void { this.showModal = false; this.selectedPayment = null; this.cdr.detectChanges(); }

  openEditHistory(h: any): void {
    this.selectedPayment = h;
    this.modalMode = 'editHistory';
    this.cashAmountError = '';
    this.editForm = {
      cashAmount: h.gcashAmount || 0,
      cashDate:   h.gcashDate || (h.verifiedAt ? h.verifiedAt.split('T')[0] : new Date().toISOString().split('T')[0]),
      gcashAmount:    h.gcashAmount || 0,
      gcashDate:      h.gcashDate || '',
      gcashReference: h.gcashReference || '',
      notes: h.notes || '',
    };
    this.showModal = true;
    this.cdr.detectChanges();
  }

  saveEditHistory(): void {
    if (!this.selectedPayment) return;
    this.isProcessing = true;
    const payload: any = {
      log_id:     this.selectedPayment.logId,
      student_id: this.selectedPayment.studentId,
      notes:      this.editForm.notes,
    };
    if (this.isCash(this.selectedPayment)) {
      payload.cash_amount = this.editForm.cashAmount;
      payload.cash_date   = this.editForm.cashDate;
    } else {
      payload.gcash_amount    = this.editForm.gcashAmount;
      payload.gcash_date      = this.editForm.gcashDate;
      payload.gcash_reference = this.editForm.gcashReference;
    }
    this.http.post<any>(`${this.apiUrl}?action=correct_verified_payment`, payload).subscribe({
      next: (res) => {
        this.isProcessing = false;
        if (res.success) {
          const idx = this.paymentHistory.findIndex((x: any) => x.logId === this.selectedPayment!.logId);
          if (idx !== -1) {
            this.paymentHistory[idx] = {
              ...this.paymentHistory[idx],
              gcashAmount: this.isCash(this.selectedPayment!) ? this.editForm.cashAmount : this.editForm.gcashAmount,
              gcashDate:   this.isCash(this.selectedPayment!) ? this.editForm.cashDate   : this.editForm.gcashDate,
              gcashReference: this.editForm.gcashReference,
              notes: this.editForm.notes,
            };
          }
          this.closeModal();
          this.loadPaymentHistory();
        } else { alert(res.message || 'Failed to update.'); }
        this.cdr.detectChanges();
      },
      error: () => { this.isProcessing = false; this.cdr.detectChanges(); }
    });
  }

  confirmAction(): void {
    if (!this.selectedPayment) return;
    if (this.modalMode === 'edit') { this.saveEdit(); return; }
    if (this.modalMode === 'editHistory') { this.saveEditHistory(); return; }

    // Validate cash amount -- overpayment allowed (excess carries over to next term)
    if (this.isCash(this.selectedPayment) && this.modalMode === 'approve') {
      if (!this.cashAmount || this.cashAmount <= 0) {
        this.cashAmountError = 'Please enter the amount received.';
        return;
      }
      const total = this.selectedPayment.totalAssessment || 0;
      if (total > 0 && this.cashAmount > total) {
        // Warning only -- backend will carry over excess to next term automatically
        this.cashAmountError = 'Warning: Overpayment of P' + this.formatAmount(this.cashAmount - total) + ' -- excess will carry over to the next term.';
      } else {
        this.cashAmountError = '';
      }
    }

    this.isProcessing = true;
    this.cdr.detectChanges();

    const payload: any = {
      log_id:             this.selectedPayment.logId,
      student_id:         this.selectedPayment.studentId,
      accounting_user_id: this.accountingUserId,
      notes:              this.modalNotes,
      payment_method:     this.selectedPayment.paymentMethod
    };

    // For cash payments, include the amount and date entered by accounting
    if (this.isCash(this.selectedPayment) && this.modalMode === 'approve') {
      payload.cash_amount = this.cashAmount;
      payload.cash_date   = this.cashDate;
    }

    const action = this.modalMode === 'approve' ? 'verify_payment' : 'reject_payment';

    this.http.post<any>(`${this.apiUrl}?action=${action}`, payload).subscribe({
      next: (res) => {
        this.isProcessing = false;
        if (res.success) {
          this.pendingPayments = this.pendingPayments.filter(p => p.logId !== this.selectedPayment!.logId);
          this.closeModal();
          if (this.currentTab === 'history') this.loadPaymentHistory();
        } else if (res.pending_scholarship) {
          // FIX SCHOLAR-VERIFY-01: scholarship still pending — block verify and prompt accounting
          this.errorMessage = '⚠️ ' + (res.message || 'Approve or reject the scholarship first.');
          this.closeModal();
        } else if (res.locked) {
          // Period still locked — remind accounting to send notice first
          this.errorMessage = '🔒 ' + (res.message || 'Send a payment notice to unlock this period first.');
        }
        this.cdr.detectChanges();
      },
      error: () => { this.isProcessing = false; this.cdr.detectChanges(); }
    });
  }

  saveEdit(): void {
    if (!this.selectedPayment) return;
    this.isProcessing = true;
    this.http.post<any>(`${this.apiUrl}?action=edit_payment`, {
      payment_log_id:  this.selectedPayment.logId,   // FIX: was 'log_id', backend expects 'payment_log_id'
      student_id:      this.selectedPayment.studentId,
      gcash_reference: this.editForm.gcashReference,
      amount:          this.editForm.gcashAmount,     // FIX: was 'gcash_amount', backend expects 'amount'
      gcash_date:      this.editForm.gcashDate,
      semester:        this.editForm.semester,
    }).subscribe({
      next: (res) => {
        this.isProcessing = false;
        if (res.success) {
          // Update locally — patch the specific field names the list uses
          const idx = this.pendingPayments.findIndex(p => p.logId === this.selectedPayment!.logId);
          if (idx !== -1) {
            this.pendingPayments[idx] = {
              ...this.pendingPayments[idx],
              gcashReference: this.editForm.gcashReference,
              gcashAmount:    this.editForm.gcashAmount,
              gcashDate:      this.editForm.gcashDate,
              semester:       this.editForm.semester,
            };
          }
          this.closeModal();
        }
        this.cdr.detectChanges();
      },
      error: () => { this.isProcessing = false; this.cdr.detectChanges(); }
    });
  }

  // ── Installment ───────────────────────────────────────────
  openInstallmentModal(payment: PendingPayment): void {
    this.selectedInstallmentStudent = payment;
    this.installmentResult = null;
    this.installmentForm = {
      student_id:      payment.studentId,
      amount:          payment.balance > 0 ? payment.balance : payment.totalAssessment,
      payment_date:    new Date().toISOString().split('T')[0],
      payment_method:  payment.paymentMethod,
      gcash_reference: '',
      exam_period:     'Downpayment',
      or_ar_type:      'AR',
      notes:           '',
    };
    this.showInstallmentModal = true;
    this.cdr.detectChanges();
  }

  closeInstallmentModal(): void {
    this.showInstallmentModal = false;
    this.selectedInstallmentStudent = null;
    this.installmentResult = null;
    this.cdr.detectChanges();
  }

  submitInstallment(): void {
    if (!this.installmentForm.amount || this.installmentForm.amount <= 0) return;
    this.isProcessing = true;
    this.cdr.detectChanges();
    this.http.post<any>(`${this.apiUrl}?action=record_installment`, {
      ...this.installmentForm,
      accounting_user_id: this.accountingUserId,
    }).subscribe({
      next: (res) => {
        this.isProcessing = false;
        if (res.success) {
          this.installmentResult = {
            orArNumber:  res.orArNumber,
            orArType:    res.orArType,
            totalPaid:   res.totalPaid,
            balance:     res.balance,
            isFullyPaid: res.isFullyPaid,
          };
          // Update balance in both lists
          const p1 = this.pendingPayments.find(x => x.studentId === this.installmentForm.student_id);
          if (p1) { (p1 as any).totalPaid = res.totalPaid; (p1 as any).balance = res.balance; }
          const p2 = this.installmentStudents.find(x => x.studentId === this.installmentForm.student_id);
          if (p2) {
            (p2 as any).totalPaid = res.totalPaid;
            (p2 as any).balance   = res.balance;
            // Remove from list if fully paid
            if (res.isFullyPaid) this.installmentStudents = this.installmentStudents.filter(x => x.studentId !== this.installmentForm.student_id);
          }
        }
        this.cdr.detectChanges();
      },
      error: () => { this.isProcessing = false; this.cdr.detectChanges(); }
    });
  }

  // ── Liquidation ───────────────────────────────────────────
  loadLiquidation(): void {
    this.isLoadingLiquidation = true;
    this.cdr.detectChanges();
    this.http.get<any>(`${this.apiUrl}?action=get_liquidation&date_from=${this.liquidationDateFrom}&date_to=${this.liquidationDateTo}`).subscribe({
      next: (res) => {
        this.isLoadingLiquidation = false;
        if (res.success) this.liquidationReport = res;
        this.cdr.detectChanges();
      },
      error: () => { this.isLoadingLiquidation = false; this.cdr.detectChanges(); }
    });
  }

  // ── Due Dates ─────────────────────────────────────────────

  /**
   * Fetch the active enrollment period from admin, then load the due dates
   * scoped to that semester + school year.
   * The semester scope is ALWAYS driven by the admin enrollment period —
   * accounting cannot override it manually.  Called every time the tab opens.
   */
  loadDueDatesWithPeriod(): void {
    this.dueDatesLoading = true;
    this.http.get<any>(`${this.enrollApi}?action=get_enrollment_period`).subscribe({
      next: res => {
        // Prefer the pre-parsed fields (semester / school_year) added by FIX EP-01.
        // Fall back to regex-parsing the label string for legacy rows.
        const period = res.period ?? {};
        if (period.semester && period.school_year) {
          this.dueDatesSemester   = period.semester.trim();
          this.dueDatesSchoolYear = period.school_year.trim().replace('\u2013', '-');
        } else {
          const label    = (period.label ?? res.label ?? '').trim();
          const semMatch = label.match(/\b(1st\s+Semester|2nd\s+Semester|Summer|Midyear)\b/i);
          const ayMatch  = label.match(/\b(\d{4}[-\u2013]\d{4})\b/);
          this.dueDatesSemester   = semMatch ? semMatch[1].replace(/\s+/g, ' ') : '';
          this.dueDatesSchoolYear = ayMatch  ? ayMatch[1].replace('\u2013', '-')  : '';
        }
        this.cdr.detectChanges();
        this.loadDueDates();
      },
      // On error still attempt to load whatever is in the global key
      error: () => { this.dueDatesLoading = false; this.cdr.detectChanges(); }
    });
  }

  /** Load due dates for the current enrollment-period scope (set by loadDueDatesWithPeriod). */
  loadDueDates(): void {
    this.dueDatesLoading = true;
    let url = `${this.apiUrl}?action=get_due_dates`;
    if (this.dueDatesSemester && this.dueDatesSchoolYear) {
      url += `&semester=${encodeURIComponent(this.dueDatesSemester)}`
           + `&school_year=${encodeURIComponent(this.dueDatesSchoolYear)}`;
    }
    this.http.get<any>(url).subscribe({
      next: res => {
        if (res.success && res.dueDates) {
          // Merge so that any period not returned by the API keeps its default label
          Object.keys(res.dueDates).forEach(k => {
            if (this.dueDates[k] !== undefined) {
              this.dueDates[k] = res.dueDates[k];
            }
          });
        }
        this.dueDatesLoading = false;
        this.cdr.detectChanges();
      },
      error: () => { this.dueDatesLoading = false; this.cdr.detectChanges(); }
    });
  }

  /** Save due dates scoped to the active enrollment period. */
  saveDueDates(): void {
    if (!this.dueDatesSemester || !this.dueDatesSchoolYear) return;
    this.dueDatesSaving = true;
    this.dueDatesSaved  = false;
    const payload = {
      ...this.dueDates,
      semester:    this.dueDatesSemester,
      school_year: this.dueDatesSchoolYear,
    };
    this.http.post<any>(`${this.apiUrl}?action=save_due_dates`, payload).subscribe({
      next: res => {
        this.dueDatesSaving = false;
        if (res.success) {
          this.dueDatesSaved = true;
          setTimeout(() => { this.dueDatesSaved = false; this.cdr.detectChanges(); }, 3500);
        }
        this.cdr.detectChanges();
      },
      error: () => { this.dueDatesSaving = false; this.cdr.detectChanges(); }
    });
  }

  getInitials(p: PendingPayment): string {
    return ((p.firstName?.[0] || '') + (p.lastName?.[0] || '')).toUpperCase();
  }

  formatAmount(amount: number): string {
    return (amount || 0).toLocaleString('en-PH', { minimumFractionDigits: 2 });
  }

  formatDate(dateStr: string): string {
    if (!dateStr) return '—';
    return new Date(dateStr).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' });
  }

  formatDateTime(dateStr: string): string {
    if (!dateStr) return '—';
    return new Date(dateStr).toLocaleString('en-PH');
  }

  // ── UI-02: Per-Student Payment Modal ─────────────────────────────────────
  /**
   * Opens the per-student payment modal.
   * Called when the user clicks a student name in either the card view or list view.
   */
  openStudentPaymentModal(studentId: number, firstName: string, lastName: string, studentNumber: string): void {
    this.showStudentPaymentModal  = true;
    this.studentPaymentModalTab   = 'history';
    this.soaModalResult           = '';
    this.isSendingSoaModal        = false;
    this.isLoadingStudentPayments = true;
    this.studentPaymentModalData  = null;
    this.cdr.detectChanges();

    // Load ALL history first (no semester filter) to build the semester selector list,
    // then default to showing the most recent semester.
    this.http.get<any>(`${this.apiUrl}?action=get_student_payment_history&student_id=${studentId}&all_semesters=1`)
      .subscribe({
        next: (res) => {
          this.isLoadingStudentPayments = false;
          if (res.success) {
            const allHistory: StudentPaymentRecord[] = (res.history ?? []).map((r: any) => ({
              ...r,
              semester: r.semester ?? '',
            }));

            // Collect unique semesters in reverse-chronological order
            const semSet = new Set<string>();
            for (const r of allHistory) {
              if (r.semester) semSet.add(r.semester);
            }
            const semesters = Array.from(semSet);

            // Default to the student's current semester (returned by the API) or the first semester in the list
            const defaultSem = res.student?.semester ?? semesters[0] ?? '';

            // Filter history to the default semester
            const filteredHistory = defaultSem
              ? allHistory.filter(r => r.semester === defaultSem)
              : allHistory;

            // Compute totals for the default semester
            const semTotalPaid = filteredHistory.reduce((s, r) => s + r.amount, 0);

            this.studentPaymentModalData = {
              studentId,
              studentNumber,
              firstName,
              lastName,
              history:         filteredHistory,
              totalPaid:       semTotalPaid,
              totalAssessment: res.totalAssessment  ?? 0,
              balance:         Math.max(0, (res.totalAssessment ?? 0) - semTotalPaid),
              guardianEmail:   res.student?.guardian_email ?? res.guardianEmail ?? '',
              semesters,
              selectedSemester: defaultSem,
            };

            // Store the full unfiltered history for switching semesters without re-fetching
            (this.studentPaymentModalData as any).__allHistory = allHistory;
            (this.studentPaymentModalData as any).__allTotalAssessments = res.semesterAssessments ?? {};
          }
          this.cdr.detectChanges();
        },
        error: () => {
          this.isLoadingStudentPayments = false;
          this.cdr.detectChanges();
        }
      });
  }

  /** Called when the user picks a different semester in the per-student payment modal. */
  onStudentPaymentSemesterChange(sem: string): void {
    if (!this.studentPaymentModalData) return;
    const allHistory: StudentPaymentRecord[] = (this.studentPaymentModalData as any).__allHistory ?? [];
    const assessments: Record<string, number> = (this.studentPaymentModalData as any).__allTotalAssessments ?? {};

    const filtered = sem ? allHistory.filter(r => r.semester === sem) : allHistory;
    const semTotalPaid = filtered.reduce((s, r) => s + r.amount, 0);
    const totalAssessment = assessments[sem] ?? this.studentPaymentModalData.totalAssessment;

    this.studentPaymentModalData = {
      ...this.studentPaymentModalData,
      selectedSemester: sem,
      history:          filtered,
      totalPaid:        semTotalPaid,
      totalAssessment,
      balance:          Math.max(0, totalAssessment - semTotalPaid),
    };
    // Re-attach the hidden full-history reference
    (this.studentPaymentModalData as any).__allHistory = allHistory;
    (this.studentPaymentModalData as any).__allTotalAssessments = assessments;
    this.cdr.detectChanges();
  }

  closeStudentPaymentModal(): void {
    this.showStudentPaymentModal = false;
    this.studentPaymentModalData = null;
    this.soaModalResult          = '';
    this.cdr.detectChanges();
  }

  /**
   * Sends SOA from inside the per-student modal (SOA tab).
   */
  /**
   * Build installment schedule rows from the SOA snapshot payments_json.
   * Returns one row per term (Downpayment, Prelim, Midterm, Finals) with
   * paid/unpaid status — matching the student-view enrollment SOA layout.
   */
  /**
   * Build installment schedule rows from the SOA snapshot.
   * Matches the student-view enrollment SOA exactly:
   *   DP = 25% of total_assessment
   *   Prelim = Midterm = Finals = 25% each (remaining 75% split equally)
   * For each term, checks payments_json for a matching exam_period entry.
   * Also handles 'Full' period entries for students who paid everything at once.
   */
  soaViewerInstallmentRows(): Array<{term: string; label: string; paid: boolean; amount: number; amountPaid: number; paymentDate: string; orNo: string}> {
    const snap = this.soaViewerSnapshot;
    if (!snap) return [];

    const payments: any[] = snap.payments || [];
    const total  = +(snap.total_assessment || 0);

    // FIX SOA-STALE-01: Use the ACTUAL downpayment paid (from payments_json) as the
    // DP amount instead of always computing total/4. When the cashier records an
    // amount that differs from total/4 (e.g. ₱8,034.25 vs ₱8,034.25 exactly),
    // the old total/4 split could round differently and give wrong Prelim/Midterm/Finals
    // due amounts, causing them to never match and always show as unpaid.
    // Sum payments per period first so we have the actual DP paid.
    const paidByTerm: Record<string, { total: number; orNo: string; date: string }> = {};
    for (const p of payments) {
      const period = (p.exam_period || '').trim();
      if (!period || period === 'Full') continue;
      if (!paidByTerm[period]) paidByTerm[period] = { total: 0, orNo: '', date: '' };
      paidByTerm[period].total += +(p.amount || 0);
      if (!paidByTerm[period].orNo) paidByTerm[period].orNo = p.or_ar_number || '';
      if (!paidByTerm[period].date) paidByTerm[period].date = p.payment_date || '';
    }

    // Use actual DP paid if available; otherwise fall back to total/4 for the schedule display
    const dpActual = paidByTerm['Downpayment']?.total ?? 0;
    const dp       = dpActual > 0 ? dpActual : Math.round(total / 4 * 100) / 100;
    const remain   = Math.max(0, total - dp);
    const third    = remain > 0 ? Math.round(remain / 3 * 100) / 100 : 0;
    const finals   = Math.max(0, remain - third - third);

    const termDefs = [
      { term: 'Downpayment', label: '1st — Downpayment (DP)', due: dp },
      { term: 'Prelim',      label: '2nd — Prelim',           due: third },
      { term: 'Midterm',     label: '3rd — Midterm',          due: third },
      { term: 'Finals',      label: '4th — Finals',           due: finals },
    ];

    return termDefs.map(t => {
      const rec = paidByTerm[t.term];
      return {
        term:        t.term,
        label:       t.label,
        paid:        !!rec && rec.total > 0,
        amount:      t.due,
        amountPaid:  rec ? rec.total : 0,
        paymentDate: rec ? rec.date  : '',
        orNo:        rec ? (rec.orNo ? `${rec.orNo}` : '') : '',
      };
    });
  }

  sendSoaFromModal(): void {
    if (!this.studentPaymentModalData || this.isSendingSoaModal) return;
    this.isSendingSoaModal = true;
    this.soaModalResult    = '';
    this.cdr.detectChanges();

    this.http.post<any>(`${environment.notifyApi}?action=send_soa`, {
      student_id: this.studentPaymentModalData.studentId,
    }).subscribe({
      next: (res) => {
        this.isSendingSoaModal = false;
        if (res.success) {
          const emails = (res.recipients || []).map((r: any) => r.email).join(', ');
          this.soaModalResult = `✅ SOA sent to: ${emails}`;
        } else {
          this.soaModalResult = `❌ ${res.message || 'Failed to send SOA. Make sure a guardian email is saved.'}`;
        }
        this.cdr.detectChanges();
      },
      error: () => {
        this.isSendingSoaModal = false;
        this.soaModalResult    = '❌ Network error. Could not send SOA.';
        this.cdr.detectChanges();
      }
    });
  }
  // ─────────────────────────────────────────────────────────────────────────

  // ── Amount to words ──────────────────────────────────────────────────────
  private amountToWords(amount: number): string {
    const ones = ['','One','Two','Three','Four','Five','Six','Seven','Eight','Nine',
      'Ten','Eleven','Twelve','Thirteen','Fourteen','Fifteen','Sixteen','Seventeen','Eighteen','Nineteen'];
    const tens = ['','','Twenty','Thirty','Forty','Fifty','Sixty','Seventy','Eighty','Ninety'];
    const toWords = (n: number): string => {
      if (n === 0) return '';
      if (n < 20) return ones[n] + ' ';
      if (n < 100) return tens[Math.floor(n/10)] + (n%10 ? ' ' + ones[n%10] : '') + ' ';
      return ones[Math.floor(n/100)] + ' Hundred ' + toWords(n%100);
    };
    const pesos = Math.floor(amount); const centavos = Math.round((amount - pesos) * 100);
    let result = toWords(pesos).trim() + ' Pesos';
    if (centavos > 0) result += ' and ' + toWords(centavos).trim() + '/100 Centavos';
    return result;
  }

  // ── Print OR (Official Receipt) ──────────────────────────────────────────
  viewOR(h: PaymentHistory): void {
    this.openServiceInvoice(h);
  }

  printOR(h: PaymentHistory): void {
    const name = `${h.lastName || ''}, ${h.firstName || ''}`;
    const amount = h.gcashAmount || 0;
    const amtWords = this.amountToWords(amount);
    const fmt = (n: number) => n.toLocaleString('en-PH', { minimumFractionDigits: 2 });
    const period = h.examPeriod || 'Full';
    const isCashPay = h.paymentMethod === 'Cash';
    const payDate = h.gcashDate || '';

    const html = `<!DOCTYPE html><html><head><meta charset="utf-8">
<title>Official Receipt ${h.orArNumber || ''}</title>
<style>
  *{box-sizing:border-box;margin:0;padding:0;}
  body{font-family:Arial,sans-serif;font-size:10.5px;padding:14px 18px;color:#000;background:#fff;width:720px;}
  .outer{display:grid;grid-template-columns:220px 1fr;gap:0;border:1.5px solid #000;}
  .left-panel{border-right:1.5px solid #000;display:flex;flex-direction:column;}
  .part-header{background:#000;color:#fff;text-align:center;font-size:9px;font-weight:700;padding:3px;}
  .part-table{width:100%;border-collapse:collapse;font-size:9.5px;}
  .part-table td{border:1px solid #000;padding:2px 4px;height:18px;}
  .part-table td:last-child{width:70px;text-align:right;}
  .part-table .total-row td{font-weight:700;background:#f5f5f5;}
  .subj-area{border-top:1.5px solid #000;padding:4px;font-size:9px;}
  .subj-row{display:grid;grid-template-columns:55px 1fr 25px;border-bottom:1px solid #ddd;padding:1px 0;}
  .right-panel{display:flex;flex-direction:column;}
  .school-header{display:flex;align-items:flex-start;gap:8px;padding:6px 8px;border-bottom:1.5px solid #000;}
  .logo-circle{width:52px;height:52px;border:2px solid #000;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:7.5px;text-align:center;flex-shrink:0;font-weight:700;}
  .school-info{flex:1;}
  .school-name{font-size:13px;font-weight:900;text-transform:uppercase;letter-spacing:0.5px;}
  .school-sub{font-size:8px;color:#333;margin-top:1px;}
  .deped-badge{font-size:8px;border:1px solid #999;padding:2px 5px;border-radius:3px;margin-left:auto;white-space:nowrap;align-self:flex-start;}
  .receipt-bar{text-align:center;background:#fff;border-bottom:1.5px solid #000;padding:4px;}
  .receipt-bar-title{font-size:13px;font-weight:900;letter-spacing:1.5px;}
  .body-pad{padding:6px 10px;}
  .field-row{display:flex;align-items:flex-end;gap:5px;margin-bottom:4px;}
  .flabel{font-size:9px;white-space:nowrap;}
  .fval{border-bottom:1px solid #000;flex:1;font-weight:700;font-size:10px;padding-bottom:1px;min-width:40px;}
  .sum-words{font-style:italic;}
  .pay-method{display:flex;align-items:center;gap:6px;margin-top:5px;flex-wrap:wrap;}
  .cb{display:inline-flex;align-items:center;gap:3px;font-size:9px;}
  .cb-sq{width:11px;height:11px;border:1px solid #000;display:inline-flex;align-items:center;justify-content:center;font-size:9px;}
  .receipt-no-bar{display:flex;justify-content:flex-end;padding:4px 10px;border-top:1px solid #ccc;}
  .receipt-no{font-size:16px;font-weight:900;color:#c00;}
  .sig-row{display:flex;justify-content:space-between;align-items:flex-end;padding:6px 10px 4px;border-top:1px solid #ccc;}
  .sig-block{text-align:center;}
  .sig-line{border-top:1px solid #000;padding-top:3px;font-size:8.5px;margin-top:20px;}
  .footer{font-size:7.5px;color:#444;padding:4px 8px;border-top:1px dashed #aaa;}
  @media print{body{padding:6px 10px;}@page{margin:6mm;size:A4 landscape;}.no-print{display:none!important;}}
</style></head><body>
<div class="outer">
  <div class="left-panel">
    <div class="part-header">In settlement of the following:</div>
    <table class="part-table">
      <tr><td>Particulars</td><td><strong>Amount</strong></td></tr>
      <tr><td>Downpayment</td><td>${period==='Downpayment' ? fmt(amount) : ''}</td></tr>
      <tr><td>1st Payment</td><td>${period==='Prelim' ? fmt(amount) : ''}</td></tr>
      <tr><td>2nd Payment</td><td>${period==='Midterm' ? fmt(amount) : ''}</td></tr>
      <tr><td>3rd Payment</td><td>${period==='Finals' ? fmt(amount) : ''}</td></tr>
      <tr><td>4th Payment</td><td></td></tr>
      <tr><td>5th Payment</td><td></td></tr>
      <tr><td>6th Payment</td><td></td></tr>
      <tr><td>Others</td><td></td></tr>
      <tr class="total-row"><td>Total Due</td><td>${fmt(amount)}</td></tr>
      <tr><td>Less: Withholding Tax</td><td></td></tr>
      <tr class="total-row"><td>Payment Due</td><td>${fmt(amount)}</td></tr>
    </table>
    <div class="subj-area">
      <div class="subj-row" style="font-weight:700;font-size:9px;border-bottom:1px solid #000;"><span>Code</span><span>Subject</span><span>Units</span></div>
      <div class="subj-row"><span>${h.program || ''}</span><span>${h.semester || ''}</span><span></span></div>
      <div class="subj-row"><span></span><span></span><span></span></div>
      <div class="subj-row"><span></span><span></span><span></span></div>
    </div>
  </div>
  <div class="right-panel">
    <div class="school-header">
      <div class="logo-circle">ST.<br>BENILDE</div>
      <div class="school-info">
        <div class="school-name">ST. BENILDE</div>
        <div class="school-sub">CENTER FOR GLOBAL COMPETENCE, INC.</div>
        <div class="school-sub">Email: stbenilde_olongapo@yahoo.com &nbsp;|&nbsp; Telefax: (047) 223-3031</div>
        <div class="school-sub">Olongapo City, Zambales, Philippines</div>
        <div class="school-sub">NON-VAT Reg. TIN: 006-722-355-00000</div>
      </div>
      <div class="deped-badge">Registered with:<br>CHED · TESDA · DepEd</div>
    </div>
    <div class="receipt-bar"><div class="receipt-bar-title">OFFICIAL RECEIPT (EXEMPT)</div></div>
    <div class="body-pad">
      <div class="field-row"><span class="flabel">RECEIVED from</span><span class="fval">${name}</span><span class="flabel">with TIN</span><span class="fval" style="max-width:90px;"></span></div>
      <div class="field-row"><span class="flabel">business style of</span><span class="fval"></span><span class="flabel">and address at</span><span class="fval">Olongapo City</span></div>
      <div class="field-row"><span class="flabel">in partial/full payment for</span><span class="fval">${h.program || ''} — ${h.semester || ''}</span></div>
      <div class="field-row"><span class="flabel">the sum of</span><span class="fval sum-words">( P ${fmt(amount)} ) &nbsp; ${amtWords}</span><span class="flabel">pesos</span></div>
      <div class="pay-method">
        <span class="flabel">Form of Payment:</span>
        <span class="cb"><span class="cb-sq">${isCashPay ? '✓' : ''}</span> Cash</span>
        <span class="cb"><span class="cb-sq">${!isCashPay ? '✓' : ''}</span> Check</span>
        <span class="flabel" style="margin-left:8px;">Bank</span>
        <span class="fval" style="max-width:90px;">${!isCashPay ? (h.gcashReference || '') : ''}</span>
        <span class="flabel">Check No.</span><span class="fval" style="max-width:70px;"></span>
        <span class="flabel">Date</span><span class="fval" style="max-width:80px;">${payDate}</span>
      </div>
    </div>
    <div class="receipt-no-bar"><span style="font-size:9px;margin-right:8px;">No.</span><span class="receipt-no">${h.orArNumber || '—'}</span></div>
    <div class="sig-row">
      <div><div style="font-size:8px;color:#555;"><em>THIS DOCUMENT IS NOT VALID FOR CLAIM OF INPUT TAXES</em></div></div>
      <div class="sig-block">
        <div style="height:24px;font-size:10px;">${h.verifiedByName || ''}</div>
        <div class="sig-line">Cashier/Authorized Representative</div>
      </div>
    </div>
    <div class="footer">
      200-Elitre 1503 42501-52500 &nbsp;|&nbsp; BIR Authority to Print No.: OCN: 018AU20220000004994<br>
      Printer's Accreditation No.: 018MP2019000000000001 &nbsp;|&nbsp; Date Issued: 01-09-2019<br>
      Dinamika Printing Intl. Corp. &nbsp;|&nbsp; 75m St. Tapinac, Olongapo City &nbsp;|&nbsp; TIN: 215-213-220-00000 - VAT
    </div>
  </div>
</div>
<div class="no-print" style="text-align:center;margin-top:14px;">
  <button onclick="window.print()" style="background:#1d4ed8;color:white;border:none;padding:10px 32px;font-size:14px;font-weight:700;border-radius:7px;cursor:pointer;margin-right:10px;">🖨️ Print</button>
  <button onclick="window.close()" style="background:#64748b;color:white;border:none;padding:10px 24px;font-size:14px;font-weight:700;border-radius:7px;cursor:pointer;">✕ Close</button>
</div>
</body></html>`;
    const win = window.open('', '_blank', 'width=800,height=700');
    if (!win) return;
    win.document.write(html); win.document.close();
  }

  viewAR(h: PaymentHistory): void {
    this.openServiceInvoice(h);
  }

  printAR(h: PaymentHistory): void {
    const name = `${h.lastName || ''}, ${h.firstName || ''}`;
    const amount = h.gcashAmount || 0;
    const fmt = (n: number) => n.toLocaleString('en-PH', { minimumFractionDigits: 2 });
    const period = h.examPeriod || 'Downpayment';
    const periodLabel: any = { 'Downpayment':'Downpayment / Enrollment','Prelim':'1st Term (Prelim) Installment','Midterm':'2nd Term (Midterm) Installment','Finals':'3rd Term (Finals) Installment' };

    const html = `<!DOCTYPE html><html><head><meta charset="utf-8">
<title>Acknowledgement Receipt ${h.orArNumber || ''}</title>
<style>
  *{box-sizing:border-box;margin:0;padding:0;}
  body{font-family:Arial,sans-serif;font-size:11px;padding:20px 24px;color:#000;}
  .header{text-align:center;border-bottom:2px solid #000;padding-bottom:8px;margin-bottom:8px;}
  .school-name{font-size:16px;font-weight:900;text-transform:uppercase;letter-spacing:1px;}
  .school-addr{font-size:9px;margin-top:3px;}
  .form-label{font-size:9px;color:#555;margin-top:2px;}
  .receipt-title{text-align:center;font-size:14px;font-weight:900;letter-spacing:1px;border:2px solid #000;padding:5px;margin:6px 0;}
  .receipt-no-row{display:flex;justify-content:space-between;margin-bottom:6px;}
  .receipt-no{font-size:12px;font-weight:900;color:#c00;}
  .body-section{border:1px solid #000;padding:8px 10px;margin:6px 0;}
  .field-line{display:flex;align-items:flex-end;gap:6px;margin-bottom:5px;}
  .field-label{white-space:nowrap;font-size:10px;}
  .field-val{border-bottom:1px solid #000;flex:1;font-weight:700;padding-bottom:1px;}
  .checkbox-grid{display:grid;grid-template-columns:1fr 1fr;gap:4px 16px;margin:8px 0 4px 0;font-size:10px;}
  .cb-item{display:flex;align-items:center;gap:5px;}
  .cb-box{width:13px;height:13px;border:1px solid #000;display:inline-block;text-align:center;font-size:10px;line-height:13px;}
  .info-row{display:flex;gap:12px;margin-top:8px;font-size:10px;}
  .info-field{display:flex;align-items:flex-end;gap:4px;}
  .info-val{border-bottom:1px solid #000;min-width:80px;font-weight:700;padding-bottom:1px;}
  .sig-area{display:flex;justify-content:flex-end;margin-top:24px;}
  .sig-block{text-align:center;min-width:140px;}
  .sig-line{border-top:1.5px solid #000;padding-top:3px;font-size:9px;font-weight:700;}
  @media print{body{padding:10px 14px;}@page{margin:8mm;}.no-print{display:none!important;}}
</style></head><body>
<div class="header">
  <div class="school-name">St. Benilde Center for Global Competence, Inc.</div>
  <div class="school-addr">2647 Rizal Avenue, West Bajac-Bajac, Olongapo City</div>
  <div class="form-label">ADMIN FORM 09</div>
</div>
<div class="receipt-title">ACKNOWLEDGEMENT RECEIPT</div>
<div class="receipt-no-row">
  <span class="receipt-no">No. &nbsp; ${h.orArNumber || '—'}</span>
  <span style="font-size:11px;">DATE: &nbsp; ${h.gcashDate || ''}</span>
</div>
<div class="body-section">
  <div class="field-line"><span class="field-label">This is to acknowledge the receipt of payment from</span><span class="field-val">${name}</span></div>
  <div class="field-line"><span class="field-label">amounting to,</span><span class="field-val">( P &nbsp;${fmt(amount)} )</span></div>
  <div style="margin-top:8px;font-size:10px;font-weight:700;">As partial/Full payment for:</div>
  <div class="checkbox-grid">
    <div class="cb-item"><span class="cb-box">&nbsp;</span> School Uniform</div>
    <div class="cb-item"><span class="cb-box">&nbsp;</span> Graduation</div>
    <div class="cb-item"><span class="cb-box">&nbsp;</span> ID Lace</div>
    <div class="cb-item"><span class="cb-box">&nbsp;</span> Sports Fest</div>
    <div class="cb-item"><span class="cb-box">&nbsp;</span> P.E. Uniform</div>
    <div class="cb-item"><span class="cb-box">☑</span> <strong>Tuition / ${periodLabel[period] || period}</strong></div>
    <div class="cb-item"><span class="cb-box">&nbsp;</span> Books/Workbooks</div>
  </div>
  <div class="info-row">
    <div class="info-field"><span>Course:</span><span class="info-val">${h.program || ''}</span></div>
    <div class="info-field"><span>Semester:</span><span class="info-val">${h.semester || ''}</span></div>
    <div class="info-field"><span>Academic Year:</span><span class="info-val">${(()=>{ const m=(h.semester||'').match(/(\d{4})-(\d{4})/); if(m) return m[0]; const y=new Date(h.gcashDate||h.verifiedAt||Date.now()).getFullYear(); return y+'-'+(y+1); })()}</span></div>
  </div>
</div>
<div class="sig-area">
  <div class="sig-block">
    <div style="height:32px;font-size:12px;font-weight:700;">${h.verifiedByName || ''}</div>
    <div class="sig-line">CASHIER</div>
  </div>
</div>
<div class="no-print" style="text-align:center;margin-top:16px;padding:12px;background:#f8fafc;border-top:1px solid #e2e8f0;">
  <button onclick="window.print()" style="background:#7c3aed;color:white;border:none;padding:10px 32px;font-size:14px;font-weight:700;border-radius:7px;cursor:pointer;margin-right:10px;">🖨️ Print</button>
  <button onclick="window.close()" style="background:#64748b;color:white;border:none;padding:10px 24px;font-size:14px;font-weight:700;border-radius:7px;cursor:pointer;">✕ Close</button>
</div>
</body></html>`;
    const win = window.open('', '_blank', 'width=760,height=860');
    if (!win) return;
    win.document.write(html); win.document.close();
  }

  // ── Service Invoice (online/browser view — NOT an official receipt) ─────────
  // UPGRADED: Now matches the student enrollment view — includes full payment
  // history table with per-receipt cumulative totals, highlighted current row,
  // and a separate payment summary section below the history.
  openServiceInvoice(h: PaymentHistory): void {
    const name      = `${h.lastName || ''}, ${h.firstName || ''}`;
    const stNum     = h.studentNumber || '';
    const amount    = h.gcashAmount || 0;
    const fmt       = (n: number) => (+n || 0).toLocaleString('en-PH', { minimumFractionDigits: 2 });
    const period    = h.examPeriod || '';
    const method    = h.paymentMethod || '';
    const gcashRef  = h.gcashReference || '';
    const orNo      = h.orArNumber || '';
    // Use gcashDate (actual payment date) first; fall back to verifiedAt
    const payDate   = h.gcashDate || h.verifiedAt || '';
    const totalAssess = h.totalAssessment || 0;

    // ── Build the per-student receipt history from this.paymentHistory ────────
    // Filter all payment history rows that belong to the same student AND the
    // same semester as this invoice, then sort by payment date ascending so the
    // cumulative running total is computed in chronological order.
    const studentHistory = this.paymentHistory
      .filter((r: PaymentHistory) =>
        r.studentId === h.studentId &&
        (r.semester || '') === (h.semester || '')
      )
      .sort((a: PaymentHistory, b: PaymentHistory) => {
        const da = new Date(a.gcashDate || a.verifiedAt || 0).getTime();
        const db = new Date(b.gcashDate || b.verifiedAt || 0).getTime();
        return da - db;
      });

    // Find the index of THIS invoice in the sorted list
    const receiptIndex = studentHistory.findIndex(
      (r: PaymentHistory) => r.orArNumber === orNo
    );

    // Cumulative paid UP TO AND INCLUDING this invoice
    let cumulativePaid = 0;
    if (receiptIndex >= 0) {
      for (let i = 0; i <= receiptIndex; i++) {
        cumulativePaid += (studentHistory[i]?.gcashAmount || 0);
      }
    } else {
      cumulativePaid = h.totalPaid || amount;
    }
    const totalPaid = cumulativePaid;
    const balance   = Math.max(0, totalAssess - totalPaid);

    let statusClass = 'status-unpaid'; let statusLabel = 'UNPAID';
    if (balance <= 0 && totalPaid > 0) { statusClass = 'status-paid';    statusLabel = 'FULLY PAID'; }
    else if (totalPaid > 0)            { statusClass = 'status-partial'; statusLabel = 'PARTIALLY PAID'; }

    // ── Build payment history rows HTML ──────────────────────────────────────
    const historyRowsHtml = (() => {
      const slice = receiptIndex >= 0
        ? studentHistory.slice(0, receiptIndex + 1)
        : (studentHistory.length > 0 ? studentHistory : [h]);

      if (slice.length === 0) {
        return '<tr><td colspan="6" style="padding:6px 8px;color:#888;">No history available.</td></tr>';
      }

      return slice.map((r: PaymentHistory) => {
        const isCurrent  = r.orArNumber === orNo;
        const rowBg      = isCurrent ? 'background:#eff6ff;font-weight:700;' : '';
        const marker     = isCurrent ? '&#9664; This Invoice' : '&#10003; Paid';
        const markerColor = isCurrent ? '#1a3c6e' : '#166534';
        const rDate      = r.gcashDate || r.verifiedAt || '';
        const rDateFmt   = rDate
          ? new Date(rDate).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' })
          : '—';
        return `<tr style="${rowBg}">
          <td style="padding:4px 8px;font-size:10px;">${r.examPeriod || ''}</td>
          <td style="padding:4px 8px;font-size:10px;">${r.orArNumber || ''}</td>
          <td style="padding:4px 8px;font-size:10px;">${r.paymentMethod || ''}</td>
          <td style="padding:4px 8px;font-size:10px;">${rDateFmt}</td>
          <td style="padding:4px 8px;text-align:right;font-size:10px;">&#8369;${fmt(r.gcashAmount || 0)}</td>
          <td style="padding:4px 8px;text-align:center;font-size:9px;color:${markerColor};font-weight:700;">${marker}</td>
        </tr>`;
      }).join('');
    })();

    const html = `<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Service Invoice &#8212; ${orNo}</title>
<style>
  @import url('https://fonts.googleapis.com/css2?family=Source+Sans+3:wght@400;600;700&display=swap');
  *,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
  body{font-family:'Source Sans 3',Arial,sans-serif;background:#eee;padding:20px;font-size:12px;color:#111;}
  .page{background:white;width:148mm;margin:0 auto;padding:10mm 12mm 8mm;box-shadow:0 2px 12px rgba(0,0,0,.15);}
  .school-header{display:flex;align-items:center;gap:10px;border-bottom:2px solid #1a3c6e;padding-bottom:8px;margin-bottom:8px;}
  .logo-circle{width:52px;height:52px;background:#1a3c6e;border-radius:50%;flex-shrink:0;display:flex;align-items:center;justify-content:center;color:white;font-weight:700;font-size:16px;}
  .school-name{font-size:15px;font-weight:700;color:#1a3c6e;line-height:1.2;}
  .school-addr{font-size:10px;color:#555;}
  .doc-type{margin-top:12px;text-align:center;}
  .doc-type h2{font-size:14px;letter-spacing:2px;color:#1a3c6e;text-transform:uppercase;font-weight:700;}
  .doc-number{font-size:18px;font-weight:700;color:#c8352a;letter-spacing:1px;margin-top:2px;}
  .meta-row{display:flex;justify-content:space-between;font-size:10px;color:#666;margin-top:4px;}
  .badge-row{text-align:center;margin-top:6px;}
  .status-badge{display:inline-block;border-radius:4px;font-size:9px;font-weight:700;letter-spacing:1px;padding:2px 8px;text-transform:uppercase;}
  .status-paid{background:#d1e7dd;color:#0a6640;border:1px solid #a3cfbb;}
  .status-partial{background:#fff3cd;color:#856404;border:1px solid #ffc107;}
  .status-unpaid{background:#f8d7da;color:#842029;border:1px solid #f5c2c7;}
  .divider{border:none;border-top:1px dashed #ccc;margin:8px 0;}
  .section-title{font-size:9px;text-transform:uppercase;letter-spacing:1px;color:#888;font-weight:600;margin-bottom:4px;}
  .info-grid{display:grid;grid-template-columns:1fr 1fr;gap:2px 16px;margin-bottom:8px;}
  .field label{font-size:9px;color:#888;text-transform:uppercase;display:block;}
  .field span{font-size:11px;font-weight:600;}
  .box{background:#f8f9fb;border:1px solid #dde;border-radius:4px;padding:8px 10px;margin-bottom:8px;}
  .amt-center{text-align:center;margin:4px 0 8px;}
  .amt-label{font-size:9px;color:#888;text-transform:uppercase;letter-spacing:1px;}
  .amt-value{font-size:26px;font-weight:700;color:#1a3c6e;}
  table.bk{width:100%;font-size:10px;border-collapse:collapse;}
  table.bk td{padding:2px 4px;}
  table.bk td:last-child{text-align:right;font-weight:600;}
  .total-row td{border-top:1px solid #ccc;padding-top:4px;font-weight:700;font-size:11px;}
  .bal-row td{color:#c8352a;}
  .bal-row.paid td{color:#1a7a3c;}
  .footer-note{text-align:center;font-size:9px;color:#bbb;margin-top:8px;letter-spacing:1px;border-top:1px solid #eee;padding-top:6px;}
  .sig-row{display:flex;justify-content:space-between;margin-top:10px;}
  .sig{text-align:center;}
  .sig .line{border-top:1px solid #333;width:100px;margin:22px auto 2px;}
  .sig .sig-name{font-size:10px;font-weight:600;}
  .sig .sig-role{font-size:9px;color:#888;}
  @media print{body{background:white;padding:0;}.page{box-shadow:none;}.no-print{display:none;}}
</style></head><body>
<div class="no-print" style="text-align:center;margin-bottom:12px;">
  <button onclick="window.print()" style="background:#1a3c6e;color:white;border:none;padding:8px 20px;border-radius:4px;cursor:pointer;font-size:13px;">&#128424; Print Invoice</button>
  <button onclick="window.close()" style="margin-left:8px;background:#eee;border:none;padding:8px 16px;border-radius:4px;cursor:pointer;font-size:13px;">Close</button>
</div>
<div class="page">
  <div class="school-header">
    <div class="logo-circle">S</div>
    <div>
      <div class="school-name">St. Benilde Center for Global Competence, Inc.</div>
      <div class="school-addr">#2647 Rizal Avenue, West Bajac-Bajac, Olongapo City | Tel/Fax: (047) 223-9031</div>
    </div>
  </div>
  <div class="doc-type">
    <h2>Service Invoice</h2>
    <div class="doc-number">Ref. No. ${orNo}</div>
    <div class="meta-row"><span>Date: ${payDate}</span></div>
    <div class="badge-row">
      <span class="status-badge ${statusClass}">${statusLabel}</span>
    </div>
  </div>
  <hr class="divider">
  <div class="section-title">Student Information</div>
  <div class="info-grid">
    <div class="field"><label>Name</label><span>${name}</span></div>
    <div class="field"><label>Student No.</label><span>${stNum}</span></div>
    <div class="field"><label>Program</label><span>${h.program || ''}</span></div>
    <div class="field"><label>Year Level</label><span>${h.yearLevel || ''}</span></div>
    <div class="field"><label>Semester</label><span>${h.semester || ''}</span></div>
    <div class="field"><label>Payment Plan</label><span>${h.paymentPlan || ''}</span></div>
  </div>
  <hr class="divider">
  <div class="section-title">This Invoice</div>
  <div class="box" style="margin-bottom:10px;">
    <div class="amt-center">
      <div class="amt-label">Amount &#8212; ${period}</div>
      <div class="amt-value">&#8369;${fmt(amount)}</div>
    </div>
    <table class="bk">
      <tr><td>Payment Method</td><td>${method}</td></tr>
      ${gcashRef ? `<tr><td>GCash Ref No.</td><td>${gcashRef}</td></tr>` : ''}
    </table>
  </div>
  <hr class="divider">
  <div class="section-title">Payment History</div>
  <table class="bk" style="margin-bottom:10px;border:1px solid #e2e8f0;border-radius:4px;overflow:hidden;">
    <thead>
      <tr style="background:#1a3c6e;color:white;">
        <th style="padding:5px 8px;text-align:left;font-size:9px;font-weight:700;">Period</th>
        <th style="padding:5px 8px;text-align:left;font-size:9px;font-weight:700;">Ref No.</th>
        <th style="padding:5px 8px;text-align:left;font-size:9px;font-weight:700;">Method</th>
        <th style="padding:5px 8px;text-align:left;font-size:9px;font-weight:700;">Date</th>
        <th style="padding:5px 8px;text-align:right;font-size:9px;font-weight:700;">Amount</th>
        <th style="padding:5px 8px;text-align:center;font-size:9px;font-weight:700;">Status</th>
      </tr>
    </thead>
    <tbody>
      ${historyRowsHtml}
    </tbody>
  </table>
  <table class="bk" style="margin-bottom:10px;">
    <tr class="total-row"><td>Total Assessment</td><td>&#8369;${fmt(totalAssess)}</td></tr>
    <tr><td>Total Paid to Date</td><td>&#8369;${fmt(totalPaid)}</td></tr>
    <tr class="bal-row ${balance <= 0 ? 'paid' : ''}"><td>Remaining Balance</td><td>&#8369;${fmt(balance)}</td></tr>
  </table>
  <div class="sig-row">
    <div class="sig"><div class="line"></div><div class="sig-name">${h.verifiedByName || 'Accounting Office'}</div><div class="sig-role">Accounting Staff</div></div>
    <div class="sig"><div class="line"></div><div class="sig-name">${name}</div><div class="sig-role">Student / Representative</div></div>
  </div>
  <div class="footer-note">SERVICE INVOICE</div>
</div>
</body></html>`;
    const win = window.open('', '_blank', 'width=760,height=860');
    if (!win) return;
    win.document.write(html);
    win.document.close();
  }



  // ── Existing: Send SOA to parent/guardian (card/list button) ─────────────
  isSendingSoa: { [studentId: number]: boolean } = {};

  // ── SOA Viewer Modal ──────────────────────────────────────────────────────
  showSoaViewer       = false;
  soaViewerLoading    = false;
  soaViewerStudent: { id: number; name: string; number: string; program: string } | null = null;
  soaViewerSnapshot: any = null;
  soaViewerSemesters: string[] = [];
  soaViewerSemester   = '';

  openSoaViewer(studentId: number, firstName: string, lastName: string, studentNumber: string, program: string): void {
    this.soaViewerStudent  = { id: studentId, name: `${lastName}, ${firstName}`, number: studentNumber, program };
    this.soaViewerSnapshot = null;
    this.soaViewerSemesters = [];
    this.soaViewerSemester  = '';
    this.showSoaViewer     = true;
    document.body.classList.add('soa-print-mode');
    this.loadSoaSnapshot(studentId);
    this.cdr.detectChanges();
  }

  closeSoaViewer(): void {
    this.showSoaViewer = false;
    this.soaViewerStudent = null;
    document.body.classList.remove('soa-print-mode');
    this.cdr.detectChanges();
  }

  loadSoaSnapshot(studentId: number, semester = ''): void {
    this.soaViewerLoading = true;
    this.cdr.detectChanges();
    const qs = semester
      ? `action=get_soa_snapshot&student_id=${studentId}&semester=${encodeURIComponent(semester)}`
      : `action=get_soa_snapshot&student_id=${studentId}`;
    this.http.get<any>(`${this.enrollApi}?${qs}`).subscribe({
      next: (res) => {
        this.soaViewerLoading   = false;
        this.soaViewerSnapshot  = res.snapshot || null;
        this.soaViewerSemesters = res.availableSemesters || [];
        if (this.soaViewerSnapshot) this.soaViewerSemester = this.soaViewerSnapshot.semester;
        this.cdr.detectChanges();
      },
      error: () => { this.soaViewerLoading = false; this.cdr.detectChanges(); }
    });
  }

  onSoaSemesterChange(sem: string): void {
    if (!this.soaViewerStudent) return;
    this.soaViewerSemester = sem;
    this.loadSoaSnapshot(this.soaViewerStudent.id, sem);
  }

  fmtPeso(n: number): string {
    return (n || 0).toLocaleString('en-PH', { minimumFractionDigits: 2 });
  }

  sendSoa(studentId: number, studentName: string): void {
    if (this.isSendingSoa[studentId]) return;
    if (!confirm(`Send Statement of Account to the parent/guardian of ${studentName}?\n\nMake sure a guardian email is saved in their record.`)) return;

    this.isSendingSoa[studentId] = true;
    this.cdr.detectChanges();

    this.http.post<any>(`${environment.notifyApi}?action=send_soa`, {
      student_id: studentId,
    }).subscribe({
      next: (res) => {
        this.isSendingSoa[studentId] = false;
        if (res.success) {
          alert(`✅ SOA sent successfully to:\n${(res.recipients || []).map((r: any) => r.email).join('\n')}\n\nBalance: ₱${(res.balance || 0).toLocaleString('en-PH', { minimumFractionDigits: 2 })}`);
        } else {
          alert(`❌ Failed to send SOA:\n${res.message || 'Unknown error.'}\n\nMake sure a guardian email is saved in the student record.`);
        }
        this.cdr.detectChanges();
      },
      error: () => {
        this.isSendingSoa[studentId] = false;
        alert('❌ Network error. Could not send SOA. Please check your connection and try again.');
        this.cdr.detectChanges();
      }
    });
  }

}