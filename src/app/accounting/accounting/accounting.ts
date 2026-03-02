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
  installmentStudents: PendingPayment[] = [];  // enrolled installment students with remaining balance
  isLoadingInstallmentStudents = false;
  selectedInstallmentStudent: PendingPayment | null = null;

  // ── Liquidation ───────────────────────────────────────────
  liquidationReport: LiquidationReport | null = null;
  liquidationDateFrom = new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0];
  liquidationDateTo   = new Date().toISOString().split('T')[0];
  isLoadingLiquidation = false;

  accountingUserId = 0;

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
  printOR(h: PaymentHistory): void {
    const name = `${h.lastName || ''}, ${h.firstName || ''}`;
    const amount = h.gcashAmount || 0;
    const amtWords = this.amountToWords(amount);
    const fmt = (n: number) => n.toLocaleString('en-PH', { minimumFractionDigits: 2 });
    const period = h.examPeriod || 'Full';
    const periodLabel: any = { 'Downpayment':'Downpayment','Prelim':'1st Payment','Midterm':'2nd Payment','Finals':'3rd Payment','Full':'Full Payment' };

    const html = `<!DOCTYPE html><html><head><meta charset="utf-8">
<title>Official Receipt ${h.orArNumber || ''}</title>
<style>
  *{box-sizing:border-box;margin:0;padding:0;}
  body{font-family:Arial,sans-serif;font-size:11px;padding:20px 24px;color:#000;}
  .header{display:flex;align-items:flex-start;gap:12px;border-bottom:2px solid #000;padding-bottom:8px;margin-bottom:6px;}
  .logo{width:60px;height:60px;border:2px solid #000;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:8px;text-align:center;flex-shrink:0;}
  .school-name{font-size:15px;font-weight:900;text-transform:uppercase;}
  .school-sub,.school-addr{font-size:9px;margin-top:2px;}
  .badges{font-size:8px;text-align:right;margin-left:auto;}
  .receipt-title{text-align:center;font-size:14px;font-weight:900;letter-spacing:1px;border:2px solid #000;padding:4px;margin:6px 0;}
  .receipt-no{text-align:right;font-size:13px;font-weight:900;color:#c00;margin-bottom:4px;}
  .body-section{border:1px solid #000;padding:8px 10px;margin:6px 0;}
  .field-line{display:flex;align-items:flex-end;gap:6px;margin-bottom:5px;}
  .field-label{white-space:nowrap;font-size:10px;}
  .field-val{border-bottom:1px solid #000;flex:1;font-weight:700;padding-bottom:1px;}
  .pay-box{border:1px solid #000;padding:2px 8px;font-size:10px;margin-right:4px;display:inline-block;}
  .two-col{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin:6px 0;}
  .ptable{width:100%;border-collapse:collapse;font-size:10px;}
  .ptable th,.ptable td{border:1px solid #000;padding:3px 5px;}
  .ptable th{background:#eee;text-align:left;}
  .sig-area{display:flex;justify-content:space-between;margin-top:20px;}
  .sig-block{text-align:center;min-width:160px;}
  .sig-line{border-top:1.5px solid #000;padding-top:3px;font-size:9px;}
  .footer-text{font-size:8px;color:#555;margin-top:8px;border-top:1px solid #ccc;padding-top:4px;}
  @media print{body{padding:10px 14px;}@page{margin:8mm;}}
</style></head><body>
<div class="header">
  <div class="logo">ST.<br>BENILDE</div>
  <div>
    <div class="school-name">St. Benilde Center for Global Competence, Inc.</div>
    <div class="school-sub">NON-VAT Reg. TIN: 006-722-355-00000</div>
    <div class="school-addr">2647 Rizal Avenue, West Bajac-Bajac, Olongapo City &nbsp;|&nbsp; Tel/Fax: (047) 223-3031</div>
  </div>
  <div class="badges">Registered with:<br>CHED · TESDA<br>DepEd</div>
</div>
<div class="receipt-title">OFFICIAL RECEIPT (EXEMPT)</div>
<div class="receipt-no">No. &nbsp; ${h.orArNumber || '—'}</div>
<div class="body-section">
  <div class="field-line"><span class="field-label">RECEIVED from</span><span class="field-val">${name}</span><span class="field-label">with TIN</span><span class="field-val" style="max-width:120px;"></span></div>
  <div class="field-line"><span class="field-label">business style of</span><span class="field-val"></span><span class="field-label">and address at</span><span class="field-val">Olongapo City</span></div>
  <div class="field-line"><span class="field-label">in partial/full payment for</span><span class="field-val">${period} — ${h.program || ''} ${h.semester || ''}</span></div>
  <div class="field-line"><span class="field-label">the sum of</span><span class="field-val" style="font-style:italic;">( P &nbsp;${fmt(amount)} ) &nbsp;${amtWords}</span><span class="field-label">pesos</span></div>
  <div style="margin-top:8px;">
    <span class="field-label">Form of Payment:</span>
    <span class="pay-box">${h.paymentMethod === 'Cash' ? '☑' : '☐'} Cash</span>
    <span class="pay-box">${h.paymentMethod !== 'Cash' ? '☑' : '☐'} Check/GCash</span>
    <span class="field-label" style="margin-left:12px;">Date</span>
    <span class="field-val" style="max-width:120px;">${h.gcashDate || ''}</span>
  </div>
</div>
<div class="two-col">
  <table class="ptable">
    <thead><tr><th colspan="2">In settlement of the following:</th></tr><tr><th>Particulars</th><th>Amount</th></tr></thead>
    <tbody>
      <tr><td>Downpayment</td><td style="text-align:right;">${period==='Downpayment'?'P '+fmt(amount):''}</td></tr>
      <tr><td>1st Payment</td><td style="text-align:right;">${period==='Prelim'?'P '+fmt(amount):''}</td></tr>
      <tr><td>2nd Payment</td><td style="text-align:right;">${period==='Midterm'?'P '+fmt(amount):''}</td></tr>
      <tr><td>3rd Payment</td><td style="text-align:right;">${period==='Finals'?'P '+fmt(amount):''}</td></tr>
      <tr><td>Full Payment</td><td style="text-align:right;">${period==='Full'?'P '+fmt(amount):''}</td></tr>
      <tr><td><strong>Payment Due</strong></td><td style="text-align:right;"><strong>P ${fmt(amount)}</strong></td></tr>
    </tbody>
  </table>
  <table class="ptable"><thead><tr><th>Subject / Course</th></tr></thead><tbody><tr><td>${h.program || ''}</td></tr><tr><td>${h.semester || ''}</td></tr><tr><td>&nbsp;</td></tr><tr><td>&nbsp;</td></tr></tbody></table>
</div>
<div class="sig-area">
  <div class="sig-block"><div style="height:30px;"></div><div class="sig-line">${h.verifiedByName || 'Cashier'}<br>Cashier / Authorized Representative</div></div>
  <div style="font-size:9px;text-align:right;color:#555;">Date: ${h.gcashDate || ''}<br><em>THIS DOCUMENT IS NOT VALID FOR CLAIM OF INPUT TAXES</em></div>
</div>
<div class="footer-text">Printer's Accreditation No.: 018MP2019000000000001 &nbsp;|&nbsp; Date Issued: 01-09-2019<br>BIR Authority to Print No.: OCN: 018AU20220000004994</div>
<script>window.onload=()=>{window.print();}<\/script>
</body></html>`;
    const win = window.open('', '_blank', 'width=820,height=900');
    if (!win) return;
    win.document.write(html); win.document.close();
  }

  // ── Print AR (Acknowledgement Receipt) ───────────────────────────────────
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
  @media print{body{padding:10px 14px;}@page{margin:8mm;}}
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
<script>window.onload=()=>{window.print();}<\/script>
</body></html>`;
    const win = window.open('', '_blank', 'width=760,height=860');
    if (!win) return;
    win.document.write(html); win.document.close();
  }

}