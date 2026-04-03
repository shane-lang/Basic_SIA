import { Component, OnInit, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../environment';
import { AuthService } from '../../services/auth';
import { ScholarshipComponent } from '../scholarship/scholarship';

interface StudentResult {
  id: number;
  studentNumber: string;
  firstName: string;
  lastName: string;
  program: string;
  yearLevel: string;
  paymentPlan: string;
  paymentStatus: string;
  enrollmentStatus: string;
  studentCategory: string;
  totalAssessment: number;
  totalPaid: number;
  balance: number;
}

interface QuickPayResult {
  orArNumber: string;
  orArType: string;
  paymentId: number;
  examPeriod: string;
  amountPaid: number;
  changeDue: number;
  totalPaid: number;
  balance: number;
  isFullyPaid: boolean;
  student: { name: string; program: string };
}

interface TallyEntry {
  id: number;
  orArNumber: string;
  orArType: string;
  studentNumber: string;
  studentName: string;
  program: string;
  examPeriod: string;
  amount: number;
  paymentMethod: string;
  gcashReference: string;
  cashierName: string;
}

interface DailyTally {
  date: string;
  transactionCount: number;
  totalCash: number;
  totalGCash: number;
  totalCheck: number;
  grandTotal: number;
  transactions: TallyEntry[];
}

@Component({
  selector: 'app-cashier',
  standalone: true,
  imports: [CommonModule, FormsModule, ScholarshipComponent],
  templateUrl: './cashier.html',
  styleUrl: './cashier.css',
})
export class CashierComponent implements OnInit {
  private apiUrl    = environment.api;
  private receiptUrl = environment.receiptApi;

  // ── Tab ───────────────────────────────────────────────
  currentTab: 'quickpay' | 'tally' = 'quickpay';

  // ── Search ────────────────────────────────────────────
  searchQuery   = '';
  searchResults: StudentResult[] = [];
  isSearching   = false;
  searchTimeout: any;

  // ── Selected student ──────────────────────────────────
  selectedStudent: StudentResult | null = null;
  showScholarshipModal = false;

  // ── Quick pay form ────────────────────────────────────
  payForm = {
    amount:          0,
    payment_method:  'Cash',
    gcash_reference: '',
    payment_date:    new Date().toISOString().split('T')[0],
    notes:           '',
    or_ar_type:      'AR',
  };
  tendered        = 0;   // amount given by student
  changeDue       = 0;   // change to return
  isSubmitting    = false;
  payResult: QuickPayResult | null = null;
  payError = '';

  // ── Daily tally ───────────────────────────────────────
  tallyDate     = new Date().toISOString().split('T')[0];
  tally: DailyTally | null = null;
  isLoadingTally = false;

  accountingUserId = 0;

  constructor(private http: HttpClient, private cdr: ChangeDetectorRef, private auth: AuthService) {}

  ngOnInit(): void {
    const stored = sessionStorage.getItem('currentUser');
    if (stored) this.accountingUserId = JSON.parse(stored).id;
  }
  // ── Search ────────────────────────────────────────────
  onSearchInput(): void {
    clearTimeout(this.searchTimeout);
    if (this.searchQuery.length < 2) { this.searchResults = []; return; }
    this.searchTimeout = setTimeout(() => this.doSearch(), 300);
  }

  doSearch(): void {
    this.isSearching = true;
    this.http.get<any>(`${this.apiUrl}?action=search_student&q=${encodeURIComponent(this.searchQuery)}`)
      .subscribe({
        next: res => {
          this.searchResults = res?.success ? (res.students ?? []) : [];
          this.isSearching = false;
          this.cdr.detectChanges();
        },
        error: () => { this.searchResults = []; this.isSearching = false; this.cdr.detectChanges(); }
      });
  }

  selectStudent(s: StudentResult): void {
    this.selectedStudent = s;
    this.searchResults   = [];
    this.searchQuery     = `${s.studentNumber} — ${s.firstName} ${s.lastName}`;
    this.payResult       = null;
    this.payError        = '';
    // Pre-fill amount with balance
    this.payForm.amount = s.balance > 0 ? s.balance : s.totalAssessment;
    this.payForm.or_ar_type = s.paymentPlan === 'installment' ? 'AR' : 'OR';
    this.computeChange();
    this.cdr.detectChanges();
  }

  clearStudent(): void {
    this.selectedStudent = null;
    this.searchQuery     = '';
    this.searchResults   = [];
    this.payResult       = null;
    this.payError        = '';
    this.tendered        = 0;
    this.changeDue       = 0;
    this.cdr.detectChanges();
  }

  openScholarship(): void {
    this.showScholarshipModal = true;
    this.cdr.detectChanges();
  }

  closeScholarship(): void {
    this.showScholarshipModal = false;
    // Reload student balance in case scholarship changed the total assessment
    if (this.selectedStudent) {
      this.http.get<any>(
        `${this.apiUrl}?action=search_student&q=${encodeURIComponent(this.selectedStudent.studentNumber)}`
      ).subscribe({
        next: (res) => {
          const updated = (res?.students ?? []).find((s: any) => s.id === this.selectedStudent!.id);
          if (updated) {
            this.selectedStudent!.totalAssessment = updated.totalAssessment ?? this.selectedStudent!.totalAssessment;
            this.selectedStudent!.balance         = updated.balance         ?? this.selectedStudent!.balance;
          }
          this.cdr.detectChanges();
        },
        error: () => { this.cdr.detectChanges(); }
      });
    }
  }

  computeChange(): void {
    this.changeDue = Math.max(0, this.tendered - this.payForm.amount);
  }

  // ── Quick pay ─────────────────────────────────────────
  submitPayment(): void {
    if (!this.selectedStudent || !this.payForm.amount || this.payForm.amount <= 0) return;
    this.isSubmitting = true;
    this.payError     = '';
    this.cdr.detectChanges();

    const payload = {
      student_number:  this.selectedStudent.studentNumber,
      amount:          this.payForm.amount,
      payment_method:  this.payForm.payment_method,
      gcash_reference: this.payForm.gcash_reference,
      payment_date:    this.payForm.payment_date,
      notes:           this.payForm.notes,
      or_ar_type:      this.payForm.or_ar_type,
    };

    this.http.post<any>(`${this.apiUrl}?action=quick_record_cash`, payload)
      .subscribe({
        next: res => {
          this.isSubmitting = false;
          if (res?.success) {
            this.payResult = res as QuickPayResult;
            this.changeDue = res.changeDue ?? 0;
            // Update student balance in UI
            if (this.selectedStudent) {
              this.selectedStudent.totalPaid = res.totalPaid ?? this.selectedStudent.totalPaid;
              this.selectedStudent.balance   = res.balance   ?? this.selectedStudent.balance;
            }
          } else {
            this.payError = res?.message || 'Payment failed.';
          }
          this.cdr.detectChanges();
        },
        error: () => {
          this.isSubmitting = false;
          this.payError = 'Network error. Please try again.';
          this.cdr.detectChanges();
        }
      });
  }

  newTransaction(): void {
    this.payResult      = null;
    this.payError       = '';
    this.selectedStudent = null;
    this.searchQuery    = '';
    this.tendered       = 0;
    this.changeDue      = 0;
    this.payForm = {
      amount: 0, payment_method: 'Cash', gcash_reference: '',
      payment_date: new Date().toISOString().split('T')[0],
      notes: '', or_ar_type: 'AR',
    };
    this.cdr.detectChanges();
  }

  // ── Print receipt via receipt.php ─────────────────────
  printReceipt(paymentId: number): void {
    const url = `${this.receiptUrl}?action=get_printable&payment_id=${paymentId}`;
    // Open in new window with token in header — use fetch workaround
    fetch(url, { headers: { Authorization: `Bearer ${this.auth.getToken()}` } })
      .then(r => r.text())
      .then(html => {
        const win = window.open('', '_blank', 'width=620,height=900');
        if (!win) return;
        win.document.write(html);
        win.document.close();
      });
  }

  // ── Daily tally ───────────────────────────────────────
  loadTally(): void {
    this.isLoadingTally = true;
    this.cdr.detectChanges();
    this.http.get<any>(`${this.apiUrl}?action=get_daily_tally&date=${this.tallyDate}`)
      .subscribe({
        next: res => {
          this.isLoadingTally = false;
          if (res?.success) this.tally = res as DailyTally;
          this.cdr.detectChanges();
        },
        error: () => { this.isLoadingTally = false; this.cdr.detectChanges(); }
      });
  }

  switchTab(tab: 'quickpay' | 'tally'): void {
    this.currentTab = tab;
    if (tab === 'tally') this.loadTally();
    this.cdr.detectChanges();
  }

  // ── Helpers ───────────────────────────────────────────
  fmt(n: number): string {
    return (n ?? 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  categoryClass(cat: string): string {
    return cat === 'College' ? 'badge-college' : cat === 'SHS' ? 'badge-shs' : 'badge-tvet';
  }

  printTally(): void {
    if (!this.tally) return;
    const rows = this.tally.transactions.map(t =>
      `<tr>
        <td>${t.orArNumber}</td>
        <td>${t.orArType}</td>
        <td>${t.studentNumber}</td>
        <td>${t.studentName}</td>
        <td>${t.examPeriod}</td>
        <td>${t.paymentMethod}</td>
        <td style="text-align:right">₱${this.fmt(t.amount)}</td>
      </tr>`
    ).join('');

    const html = `<!DOCTYPE html><html><head><meta charset="utf-8">
<title>Daily Cashier Tally — ${this.tally.date}</title>
<style>
body{font-family:Arial,sans-serif;font-size:11px;padding:20px;color:#000;}
h2{font-size:15px;margin:0 0 4px;}
.sub{color:#555;font-size:10px;margin-bottom:14px;}
.totals{display:flex;gap:20px;margin-bottom:14px;}
.tot{background:#f5f5f5;border:1px solid #ddd;padding:8px 14px;border-radius:4px;}
.tot-label{font-size:9px;text-transform:uppercase;color:#888;}
.tot-val{font-size:14px;font-weight:700;color:#111;}
table{width:100%;border-collapse:collapse;font-size:10px;}
th{background:#1a3c6e;color:white;padding:5px 8px;text-align:left;}
td{padding:4px 8px;border-bottom:1px solid #eee;}
tfoot td{font-weight:700;border-top:2px solid #333;background:#f9f9f9;}
@media print{@page{margin:8mm;}body{padding:8px;}}
</style></head><body>
<h2>Daily Cashier Tally</h2>
<div class="sub">Date: ${this.tally.date} · Transactions: ${this.tally.transactionCount}</div>
<div class="totals">
  <div class="tot"><div class="tot-label">💵 Cash</div><div class="tot-val">₱${this.fmt(this.tally.totalCash)}</div></div>
  <div class="tot"><div class="tot-label">📱 GCash</div><div class="tot-val">₱${this.fmt(this.tally.totalGCash)}</div></div>
  <div class="tot"><div class="tot-label">🏦 Grand Total</div><div class="tot-val">₱${this.fmt(this.tally.grandTotal)}</div></div>
</div>
<table>
<thead><tr><th>OR/AR No.</th><th>Type</th><th>Student No.</th><th>Name</th><th>Period</th><th>Method</th><th>Amount</th></tr></thead>
<tbody>${rows}</tbody>
<tfoot><tr><td colspan="6" style="text-align:right;">GRAND TOTAL</td><td style="text-align:right;">₱${this.fmt(this.tally.grandTotal)}</td></tr></tfoot>
</table>
<div style="margin-top:40px;display:flex;justify-content:flex-end;">
<div style="text-align:center;min-width:180px;">
<div style="border-top:1px solid #000;padding-top:4px;font-size:10px;font-weight:700;">Cashier Signature</div>
</div></div>
<script>window.print();<\/script>
</body></html>`;
    const win = window.open('', '_blank');
    if (!win) return;
    win.document.write(html);
    win.document.close();
  }
}