import { Component, OnInit, ChangeDetectorRef, Input } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../environment';

interface FeeLogEntry {
  id: number;
  student_id: number;
  course_id: number;
  course_code: string;
  course_name: string;
  action: 'Add' | 'Drop';
  subject_type: string;
  course_category: string;
  units: number;
  lec_units: number;
  lab_units: number;
  tuition_impact: number;
  lab_fee_impact: number;
  energy_impact: number;
  total_impact: number;
  semester: string;
  reason: string;
  added_by_role: string;
  added_by_email: string;
  created_at: string;
  first_name: string;
  last_name: string;
  student_number: string;
  program: string;
  year_level: string;
}

interface AddDropRequest {
  id: number;
  student_id: number;
  request_type: 'Add' | 'Drop';
  course_id: number;
  reason: string;
  status: string;
  accounting_status: string;
  accounting_notes: string;
  fee_impact: number;
  new_total_assessment: number;
  created_at: string;
  // course info
  code: string;
  course_name: string;
  credits: number;
  // student info
  first_name: string;
  last_name: string;
  student_number: string;
  program: string;
  year_level: string;
  semester: string;
  // fee preview
  fee_preview?: {
    currentTotal: number;
    currentUnits: number;
    courseUnits: number;
    tuitionImpact: number;
    labImpact: number;
    energyImpact: number;
    totalImpact: number;
    newTotal: number;
    isFullScholar?: boolean;
    scholarDiscount?: number;
  };
}

@Component({
  selector: 'app-subject-fee-log',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './subject-fee-log.html',
  styleUrl: './subject-fee-log.css',
})
export class SubjectFeeLogComponent implements OnInit {
  @Input() studentId = 0;

  private accountingApi = environment.accountingApi;
  private enrollApi     = environment.enrollApi;

  // ── Tab ──────────────────────────────────────────────────────
  activeTab: 'pending' | 'log' = 'pending';

  // ── Pending Add/Drop Requests (Accounting review) ─────────────
  pendingRequests: AddDropRequest[] = [];
  isLoadingPending  = false;
  pendingStatusFilter: 'Pending' | 'Approved' | 'Rejected' | 'All' = 'Pending';
  pendingSearchQuery = '';

  // Review modal
  showReviewModal  = false;
  reviewingReq: AddDropRequest | null = null;
  reviewAction: 'Approved' | 'Rejected' = 'Approved';
  reviewNotes  = '';
  isReviewing  = false;
  reviewError  = '';
  reviewSuccess = '';

  // ── Fee Log (completed) ───────────────────────────────────────
  isLoading = false;
  errorMsg  = '';
  logs: FeeLogEntry[] = [];

  totalTuitionImpact = 0;
  totalLabImpact     = 0;
  totalNetImpact     = 0;

  searchQuery  = '';
  filterAction: 'all' | 'Add' | 'Drop' = 'all';
  filterType:   'all' | 'Laboratory' | 'Lecture' = 'all';

  successMsg = '';

  constructor(private http: HttpClient, private cdr: ChangeDetectorRef) {}

  ngOnInit(): void {
    this.loadPendingRequests();
    this.loadLogs();
  }

  // ── Tab switching ─────────────────────────────────────────────
  setTab(tab: 'pending' | 'log'): void {
    this.activeTab = tab;
    this.cdr.detectChanges();
  }

  // ── Pending Add/Drop Requests ─────────────────────────────────
  loadPendingRequests(): void {
    this.isLoadingPending = true;
    const status = this.pendingStatusFilter || 'Pending';
    // BUG-ACCTG-02 FIX: use enrollApi (enrollment.php) which has the correct
    // getPendingAddDropForAccounting() with _calcAddDropFeeImpact and scholar logic.
    // The accountingApi (Accounting.php) version had $storedImpact undefined (now fixed
    // too), but enrollment.php is the canonical source for this action.
    this.http.get<any>(
      `${this.enrollApi}?action=get_pending_add_drop_for_accounting&status=${encodeURIComponent(status)}`
    ).subscribe({
      next: (res) => {
        this.isLoadingPending = false;
        if (res.success) {
          this.pendingRequests = res.requests ?? [];
        } else {
          // Surface backend error message in the UI
          this.errorMsg = res.message || 'Failed to load add/drop requests.';
          this.pendingRequests = [];
        }
        this.cdr.detectChanges();
      },
      error: (err) => {
        this.isLoadingPending = false;
        this.errorMsg = 'Network error loading add/drop requests.';
        this.pendingRequests = [];
        this.cdr.detectChanges();
      }
    });
  }

  get filteredPending(): AddDropRequest[] {
    const q = this.pendingSearchQuery.toLowerCase();
    return this.pendingRequests.filter(r =>
      !q
      || (r.first_name + ' ' + r.last_name).toLowerCase().includes(q)
      || r.student_number.toLowerCase().includes(q)
      || (r.code ?? '').toLowerCase().includes(q)
      || (r.course_name ?? '').toLowerCase().includes(q)
    );
  }

  get pendingCount(): number {
    return this.pendingRequests.filter(r => r.accounting_status === 'Pending').length;
  }

  openReview(req: AddDropRequest, action: 'Approved' | 'Rejected'): void {
    this.reviewingReq  = req;
    this.reviewAction  = action;
    this.reviewNotes   = '';
    this.reviewError   = '';
    this.showReviewModal = true;
    this.cdr.detectChanges();
  }

  closeReview(): void {
    this.showReviewModal = false;
    this.reviewingReq   = null;
    this.cdr.detectChanges();
  }

  confirmReview(): void {
    if (!this.reviewingReq) return;
    this.isReviewing = true;
    this.reviewError = '';

    const body = {
      request_id: this.reviewingReq.id,
      action:     this.reviewAction,
      notes:      this.reviewNotes,
    };

    this.http.post<any>(`${this.enrollApi}?action=accounting_approve_add_drop`, body).subscribe({
      next: (res) => {
        this.isReviewing = false;
        if (res.success) {
          this.showReviewModal = false;
          this.successMsg = res.message;
          this.loadPendingRequests();
          this.loadLogs();
          setTimeout(() => { this.successMsg = ''; this.cdr.detectChanges(); }, 5000);
        } else {
          this.reviewError = res.message || 'Failed.';
        }
        this.cdr.detectChanges();
      },
      error: () => {
        this.isReviewing = false;
        this.reviewError = 'Server error. Please try again.';
        this.cdr.detectChanges();
      }
    });
  }

  // ── Subject Fee Log (approved entries) ───────────────────────
  loadLogs(): void {
    this.isLoading = true;
    const sid = this.studentId ? `&student_id=${this.studentId}` : '';
    this.http.get<any>(`${this.accountingApi}?action=get_subject_fee_log${sid}&limit=200`).subscribe({
      next: (res) => {
        this.isLoading          = false;
        this.logs               = res.logs ?? [];
        this.totalTuitionImpact = res.total_tuition_impact ?? 0;
        this.totalLabImpact     = res.total_lab_impact      ?? 0;
        this.totalNetImpact     = res.total_net_impact      ?? 0;
        this.cdr.detectChanges();
      },
      error: () => { this.isLoading = false; this.errorMsg = 'Failed to load fee log.'; this.cdr.detectChanges(); }
    });
  }

  get filtered(): FeeLogEntry[] {
    const q = this.searchQuery.toLowerCase();
    return this.logs.filter(l => {
      const matchQ = !q
        || (l.first_name + ' ' + l.last_name).toLowerCase().includes(q)
        || l.student_number.toLowerCase().includes(q)
        || l.course_code.toLowerCase().includes(q)
        || l.course_name.toLowerCase().includes(q);
      const matchA = this.filterAction === 'all' || l.action === this.filterAction;
      const matchT = this.filterType   === 'all' || l.subject_type === this.filterType;
      return matchQ && matchA && matchT;
    });
  }

  // ── Helpers ───────────────────────────────────────────────────
  fmt(n: number): string {
    const abs = Math.abs(n ?? 0);
    const str = abs.toLocaleString('en-PH', { minimumFractionDigits: 2 });
    return (n < 0 ? '-₱' : '₱') + str;
  }

  fmtDate(d: string): string {
    if (!d) return '—';
    return new Date(d).toLocaleDateString('en-PH', {
      year: 'numeric', month: 'short', day: 'numeric',
      hour: '2-digit', minute: '2-digit'
    });
  }

  impactColor(n: number): string {
    if (n > 0) return '#dc2626';
    if (n < 0) return '#16a34a';
    return '#6b7280';
  }

  accStatusClass(s: string): string {
    if (s === 'Approved') return 'acc-approved';
    if (s === 'Rejected') return 'acc-rejected';
    return 'acc-pending';
  }

  accStatusLabel(s: string): string {
    if (s === 'Approved') return '✅ Fee Approved';
    if (s === 'Rejected') return '❌ Fee Rejected';
    return '⏳ Awaiting Accounting';
  }
}