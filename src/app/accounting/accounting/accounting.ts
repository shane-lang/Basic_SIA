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
  // Fee fields
  totalAssessment: number;
  totalPaid:       number;
  balance:         number;
  // Payment plan
  paymentPlan?:      string;  // 'full' | 'installment'
  scheduleAmounts?:  { downpayment: number; prelim: number; midterm: number; finals: number; total: number };
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
  modalMode: 'approve' | 'reject' | 'edit' | 'editHistory' = 'approve';
  selectedPayment: PendingPayment | null = null;
  modalNotes       = '';
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

  
  /** Returns HTTP headers with the auth token. Call this in every API request. */
  private getHeaders() {
    const token = sessionStorage.getItem('token') ?? '';
    return { headers: { Authorization: `Bearer ${token}` } };
  }

  constructor(private http: HttpClient, private cdr: ChangeDetectorRef) {}

  ngOnInit(): void {
    const stored = sessionStorage.getItem('currentUser');
    if (stored) {
      const u = JSON.parse(stored);
      this.accountingUserId = u.id;
    }
    this.loadPendingPayments();
  }

  isCash(p: PendingPayment | null): boolean  { return p?.paymentMethod?.toLowerCase() === 'cash'; }
  isGCash(p: PendingPayment | null): boolean { return p?.paymentMethod?.toLowerCase() === 'gcash'; }

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
    this.http.get<any>(`${this.apiUrl}?action=get_pending_payments`, this.getHeaders()).subscribe({
      next: (res) => { this.pendingPayments = res.success ? res.payments : []; this.isLoadingPending = false; this.cdr.detectChanges(); },
      error: () => { this.errorMessage = 'Failed to load pending payments.'; this.isLoadingPending = false; this.cdr.detectChanges(); }
    });
  }

  loadPaymentHistory(): void {
    this.isLoadingHistory = true;
    this.cdr.detectChanges();
    this.http.get<any>(`${this.apiUrl}?action=get_payment_history`, this.getHeaders()).subscribe({
      next: (res) => { this.paymentHistory = res.success ? res.history : []; this.isLoadingHistory = false; this.cdr.detectChanges(); },
      error: () => { this.isLoadingHistory = false; this.cdr.detectChanges(); }
    });
  }

  loadInstallmentStudents(): void {
    this.isLoadingInstallmentStudents = true;
    this.cdr.detectChanges();
    this.http.get<any>(`${this.apiUrl}?action=get_installment_students`, this.getHeaders()).subscribe({
      next: (res) => {
        this.isLoadingInstallmentStudents = false;
        this.installmentStudents = res.success ? res.students : [];
        this.cdr.detectChanges();
      },
      error: () => { this.isLoadingInstallmentStudents = false; this.cdr.detectChanges(); }
    });
  }

  switchTab(tab: 'pending' | 'history' | 'installment' | 'liquidation'): void {
    this.currentTab = tab;
    this.searchQuery = '';
    if (tab === 'history')      this.loadPaymentHistory();
    if (tab === 'installment')  this.loadInstallmentStudents();
    if (tab === 'liquidation')  this.loadLiquidation();
    this.cdr.detectChanges();
  }

  // ── Modal ─────────────────────────────────────────────────
  openApprove(payment: PendingPayment): void {
    this.selectedPayment = payment;
    this.modalMode  = 'approve';
    this.modalNotes = '';
    this.cashDate   = new Date().toISOString().split('T')[0];
    this.cashAmountError = '';
    // Pre-fill cash amount: installment = term due; full = total assessment
    if (payment.paymentPlan === 'installment' && payment.scheduleAmounts) {
      const ep = payment.examPeriod || 'Downpayment';
      const sa = payment.scheduleAmounts;
      this.cashAmount = ep === 'Prelim'  ? sa.prelim  :
                        ep === 'Midterm' ? sa.midterm :
                        ep === 'Finals'  ? sa.finals  : sa.downpayment;
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
    this.http.post<any>(`${this.apiUrl}?action=correct_verified_payment`, payload, this.getHeaders()).subscribe({
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

    // Validate cash amount: must be positive and not exceed total assessment (typo guard)
    if (this.isCash(this.selectedPayment) && this.modalMode === 'approve') {
      if (!this.cashAmount || this.cashAmount <= 0) {
        this.cashAmountError = 'Please enter the amount received.';
        return;
      }
      const total = this.selectedPayment.totalAssessment || 0;
      if (total > 0 && this.cashAmount > total) {
        this.cashAmountError = `Amount ₱${this.formatAmount(this.cashAmount)} exceeds total assessment ₱${this.formatAmount(total)}. Check for typos.`;
        return;
      }
      this.cashAmountError = '';
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

    this.http.post<any>(`${this.apiUrl}?action=${action}`, payload, this.getHeaders()).subscribe({
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
    }, this.getHeaders()).subscribe({
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
    }, this.getHeaders()).subscribe({
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
    this.http.get<any>(`${this.apiUrl}?action=get_liquidation&date_from=${this.liquidationDateFrom}&date_to=${this.liquidationDateTo}`, this.getHeaders()).subscribe({
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
  /* LEFT PANEL */
  .left-panel{border-right:1.5px solid #000;display:flex;flex-direction:column;}
  .part-header{background:#000;color:#fff;text-align:center;font-size:9px;font-weight:700;padding:3px;}
  .part-table{width:100%;border-collapse:collapse;font-size:9.5px;}
  .part-table td{border:1px solid #000;padding:2px 4px;height:18px;}
  .part-table td:last-child{width:70px;text-align:right;}
  .part-table .total-row td{font-weight:700;background:#f5f5f5;}
  .subj-area{border-top:1.5px solid #000;padding:4px;font-size:9px;}
  .subj-row{display:grid;grid-template-columns:55px 1fr 25px;border-bottom:1px solid #ddd;padding:1px 0;}
  /* RIGHT PANEL */
  .right-panel{display:flex;flex-direction:column;}
  .school-header{display:flex;align-items:flex-start;gap:8px;padding:6px 8px;border-bottom:1.5px solid #000;}
  .logo-circle{width:52px;height:52px;border:2px solid #000;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:7.5px;text-align:center;flex-shrink:0;font-weight:700;}
  .school-info{flex:1;}
  .school-name{font-size:13px;font-weight:900;text-transform:uppercase;letter-spacing:0.5px;}
  .school-sub{font-size:8px;color:#333;margin-top:1px;}
  .deped-badge{font-size:8px;border:1px solid #999;padding:2px 5px;border-radius:3px;margin-left:auto;white-space:nowrap;align-self:flex-start;}
  .receipt-bar{text-align:center;background:#fff;border-bottom:1.5px solid #000;padding:4px;}
  .receipt-bar-title{font-size:13px;font-weight:900;letter-spacing:1.5px;}
  .receipt-bar-sub{font-size:8.5px;color:#444;}
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
  @media print{
    body{padding:6px 10px;}
    @page{margin:6mm;size:A4 landscape;}
    .no-print{display:none!important;}
  }
</style></head><body>

<div class="outer">
  <!-- LEFT: Particulars + Subjects -->
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
      <tr class="total-row"><td>Total Due</td><td>${period==='Full' ? fmt(amount) : fmt(amount)}</td></tr>
      <tr><td>Less: Withholding Tax</td><td></td></tr>
      <tr class="total-row"><td>Payment Due</td><td>${fmt(amount)}</td></tr>
    </table>
    <div class="subj-area">
      <div class="subj-row" style="font-weight:700;font-size:9px;border-bottom:1px solid #000;">
        <span>Code</span><span>Subject</span><span>Units</span>
      </div>
      <div class="subj-row"><span>${h.program || ''}</span><span>${h.semester || ''}</span><span></span></div>
      <div class="subj-row"><span></span><span></span><span></span></div>
      <div class="subj-row"><span></span><span></span><span></span></div>
      <div class="subj-row"><span></span><span></span><span></span></div>
      <div class="subj-row"><span></span><span></span><span></span></div>
    </div>
  </div>

  <!-- RIGHT: Header + Receipt Body -->
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

    <div class="receipt-bar">
      <div class="receipt-bar-title">OFFICIAL RECEIPT (EXEMPT)</div>
    </div>

    <div class="body-pad">
      <div class="field-row">
        <span class="flabel">RECEIVED from</span>
        <span class="fval">${name}</span>
        <span class="flabel">with TIN</span>
        <span class="fval" style="max-width:90px;"></span>
      </div>
      <div class="field-row">
        <span class="flabel">business style of</span>
        <span class="fval"></span>
        <span class="flabel">and address at</span>
        <span class="fval">Olongapo City</span>
      </div>
      <div class="field-row">
        <span class="flabel">in partial/full payment for</span>
        <span class="fval">${h.program || ''} — ${h.semester || ''}</span>
      </div>
      <div class="field-row">
        <span class="flabel">the sum of</span>
        <span class="fval sum-words">( P ${fmt(amount)} ) &nbsp; ${amtWords}</span>
        <span class="flabel">pesos</span>
      </div>

      <div class="pay-method">
        <span class="flabel">Form of Payment:</span>
        <span class="cb"><span class="cb-sq">${isCashPay ? '✓' : ''}</span> Cash</span>
        <span class="cb"><span class="cb-sq">${!isCashPay ? '✓' : ''}</span> Check</span>
        <span class="flabel" style="margin-left:8px;">Bank</span>
        <span class="fval" style="max-width:90px;">${!isCashPay ? (h.gcashReference || '') : ''}</span>
        <span class="flabel">Check No.</span>
        <span class="fval" style="max-width:70px;"></span>
        <span class="flabel">Date</span>
        <span class="fval" style="max-width:80px;">${payDate}</span>
      </div>
    </div>

    <div class="receipt-no-bar">
      <span style="font-size:9px;margin-right:8px;">No.</span>
      <span class="receipt-no">${h.orArNumber || '—'}</span>
    </div>

    <div class="sig-row">
      <div>
        <div style="font-size:8px;color:#555;"><em>THIS DOCUMENT IS NOT VALID FOR CLAIM OF INPUT TAXES</em></div>
      </div>
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
  @media print{body{padding:10px 14px;}@page{margin:8mm;} .no-print{display:none!important;}}
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
    <div class="info-field"><span>Academic Year:</span><span class="info-val">${new Date().getFullYear()}-${new Date().getFullYear()+1}</span></div>
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

}