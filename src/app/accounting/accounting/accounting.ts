import { Component, OnInit, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';

interface PendingPayment {
  logId:           number;
  studentId:       number;
  studentNumber:   string;
  firstName:       string;
  lastName:        string;
  program:         string;
  yearLevel:       string;
  paymentMethod:   string;
  gcashReference:  string;
  gcashAmount:     number;
  gcashDate:       string;
  transactionId:   string;
  semester:        string;
  status:          string;
  submittedAt:     string;
  isScholar?:      boolean;
  scholarType?:    string;
  scholarGrantor?: string;
  scholarshipAmount?: number;
  // Fee fields
  totalAssessment: number;
  totalPaid:       number;
  balance:         number;
}

interface PaymentHistory extends PendingPayment {
  notes:          string;
  verifiedAt:     string;
  verifiedByName: string;
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

@Component({
  selector: 'app-accounting',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './accounting.html',
  styleUrl: './accounting.css',
})
export class Accounting implements OnInit {
  private apiUrl = 'http://localhost/sia-api/accounting.php';

  currentTab: 'pending' | 'history' | 'installment' | 'liquidation' = 'pending';

  pendingPayments: PendingPayment[] = [];
  paymentHistory:  PaymentHistory[] = [];

  isLoadingPending = true;
  isLoadingHistory = false;
  errorMessage     = '';

  // ── View mode toggle ──────────────────────────────────────
  viewMode: 'thumbnail' | 'list' = 'thumbnail';

  // ── History view mode ─────────────────────────────────────
  historyViewMode: 'thumbnail' | 'list' = 'list';

  // ── Search/Filter ─────────────────────────────────────────
  searchQuery      = '';
  filterMethod: 'all' | 'cash' | 'gcash' = 'all';

  showModal        = false;
  modalMode: 'approve' | 'reject' | 'edit' = 'approve';
  selectedPayment: PendingPayment | null = null;
  modalNotes       = '';
  isProcessing     = false;

  // Cash-specific fields for accounting to fill in
  cashAmount: number = 0;
  cashDate:   string = new Date().toISOString().split('T')[0];

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
  selectedInstallmentStudent: PendingPayment | null = null;

  // ── Liquidation ───────────────────────────────────────────
  liquidationReport: LiquidationReport | null = null;
  liquidationDateFrom = new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0];
  liquidationDateTo   = new Date().toISOString().split('T')[0];
  isLoadingLiquidation = false;

  accountingUserId = 0;

  constructor(private http: HttpClient, private cdr: ChangeDetectorRef) {}

  ngOnInit(): void {
    const stored = localStorage.getItem('currentUser');
    if (stored) {
      const u = JSON.parse(stored);
      this.accountingUserId = u.id;
    }
    this.loadPendingPayments();
  }

  isCash(p: PendingPayment | null): boolean  { return p?.paymentMethod?.toLowerCase() === 'cash'; }
  isGCash(p: PendingPayment | null): boolean { return !p || p?.paymentMethod?.toLowerCase() !== 'cash'; }

  get filteredPending(): PendingPayment[] {
    return this.pendingPayments.filter(p => {
      const q = this.searchQuery.toLowerCase();
      const matchSearch = !q || (p.firstName + ' ' + p.lastName).toLowerCase().includes(q) || p.studentNumber.toLowerCase().includes(q) || p.program.toLowerCase().includes(q);
      const matchMethod = this.filterMethod === 'all' || (this.filterMethod === 'cash' && this.isCash(p)) || (this.filterMethod === 'gcash' && this.isGCash(p));
      return matchSearch && matchMethod;
    });
  }

  get filteredHistory(): PaymentHistory[] {
    return this.paymentHistory.filter(p => {
      const q = this.searchQuery.toLowerCase();
      return !q || (p.firstName + ' ' + p.lastName).toLowerCase().includes(q) || p.studentNumber.toLowerCase().includes(q);
    });
  }

  // ── Data loading ──────────────────────────────────────────
  loadPendingPayments(): void {
    this.isLoadingPending = true;
    this.cdr.detectChanges();
    this.http.get<any>(`${this.apiUrl}?action=get_pending_payments`).subscribe({
      next: (res) => { this.pendingPayments = res.success ? res.payments : []; this.isLoadingPending = false; this.cdr.detectChanges(); },
      error: () => { this.errorMessage = 'Failed to load pending payments.'; this.isLoadingPending = false; this.cdr.detectChanges(); }
    });
  }

  loadPaymentHistory(): void {
    this.isLoadingHistory = true;
    this.cdr.detectChanges();
    this.http.get<any>(`${this.apiUrl}?action=get_payment_history`).subscribe({
      next: (res) => { this.paymentHistory = res.success ? res.history : []; this.isLoadingHistory = false; this.cdr.detectChanges(); },
      error: () => { this.isLoadingHistory = false; this.cdr.detectChanges(); }
    });
  }

  switchTab(tab: 'pending' | 'history' | 'installment' | 'liquidation'): void {
    this.currentTab = tab;
    this.searchQuery = '';
    if (tab === 'history') this.loadPaymentHistory();
    if (tab === 'liquidation') this.loadLiquidation();
    this.cdr.detectChanges();
  }

  // ── Modal ─────────────────────────────────────────────────
  openApprove(payment: PendingPayment): void {
    this.selectedPayment = payment;
    this.modalMode  = 'approve';
    this.modalNotes = '';
    this.cashAmount = 0;
    this.cashDate   = new Date().toISOString().split('T')[0];
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

  confirmAction(): void {
    if (!this.selectedPayment) return;
    if (this.modalMode === 'edit') { this.saveEdit(); return; }

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
      log_id:         this.selectedPayment.logId,
      student_id:     this.selectedPayment.studentId,
      gcash_reference: this.editForm.gcashReference,
      gcash_amount:   this.editForm.gcashAmount,
      gcash_date:     this.editForm.gcashDate,
      semester:       this.editForm.semester,
    }).subscribe({
      next: (res) => {
        this.isProcessing = false;
        if (res.success) {
          // Update locally
          const idx = this.pendingPayments.findIndex(p => p.logId === this.selectedPayment!.logId);
          if (idx !== -1) {
            this.pendingPayments[idx] = { ...this.pendingPayments[idx], ...this.editForm };
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
          // Update balance in list
          const p = this.pendingPayments.find(x => x.studentId === this.installmentForm.student_id);
          if (p) {
            (p as any).totalPaid = res.totalPaid;
            (p as any).balance   = res.balance;
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
}