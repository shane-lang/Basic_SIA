import { Component, OnInit, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';

interface PendingPayment {
  logId:          number;
  studentId:      number;
  studentNumber:  string;
  firstName:      string;
  lastName:       string;
  program:        string;
  yearLevel:      string;
  paymentMethod:  string;   // 'GCash' | 'Cash'
  gcashReference: string;
  gcashAmount:    number;
  gcashDate:      string;
  transactionId:  string;
  semester:       string;
  status:         string;
  submittedAt:    string;
}

interface PaymentHistory extends PendingPayment {
  notes:          string;
  verifiedAt:     string;
  verifiedByName: string;
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

  currentTab: 'pending' | 'history' = 'pending';

  pendingPayments: PendingPayment[] = [];
  paymentHistory:  PaymentHistory[] = [];

  isLoadingPending = true;
  isLoadingHistory = false;
  errorMessage     = '';

  showModal        = false;
  modalMode: 'approve' | 'reject' = 'approve';
  selectedPayment: PendingPayment | null = null;
  modalNotes       = '';
  isProcessing     = false;

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

  // ── Helper: check payment method ──────────────────────────
  isCash(p: PendingPayment | null): boolean {
    return p?.paymentMethod?.toLowerCase() === 'cash';
  }

  isGCash(p: PendingPayment | null): boolean {
    return !p || p?.paymentMethod?.toLowerCase() !== 'cash';
  }

  // ── Data loading ───────────────────────────────────────────
  loadPendingPayments(): void {
    this.isLoadingPending = true;
    this.cdr.detectChanges();

    this.http.get<any>(`${this.apiUrl}?action=get_pending_payments`).subscribe({
      next: (res) => {
        this.pendingPayments  = res.success ? res.payments : [];
        this.isLoadingPending = false;
        this.cdr.detectChanges();
      },
      error: () => {
        this.errorMessage    = 'Failed to load pending payments.';
        this.isLoadingPending = false;
        this.cdr.detectChanges();
      }
    });
  }

  loadPaymentHistory(): void {
    this.isLoadingHistory = true;
    this.cdr.detectChanges();

    this.http.get<any>(`${this.apiUrl}?action=get_payment_history`).subscribe({
      next: (res) => {
        this.paymentHistory  = res.success ? res.history : [];
        this.isLoadingHistory = false;
        this.cdr.detectChanges();
      },
      error: () => {
        this.isLoadingHistory = false;
        this.cdr.detectChanges();
      }
    });
  }

  switchTab(tab: 'pending' | 'history'): void {
    this.currentTab = tab;
    if (tab === 'history') this.loadPaymentHistory();
    this.cdr.detectChanges();
  }

  // ── Modal ──────────────────────────────────────────────────
  openApprove(payment: PendingPayment): void {
    this.selectedPayment = payment;
    this.modalMode       = 'approve';
    this.modalNotes      = '';
    this.showModal       = true;
    this.cdr.detectChanges();
  }

  openReject(payment: PendingPayment): void {
    this.selectedPayment = payment;
    this.modalMode       = 'reject';
    this.modalNotes      = '';
    this.showModal       = true;
    this.cdr.detectChanges();
  }

  closeModal(): void {
    this.showModal       = false;
    this.selectedPayment = null;
    this.cdr.detectChanges();
  }

  confirmAction(): void {
    if (!this.selectedPayment) return;

    this.isProcessing = true;
    this.cdr.detectChanges();

    const payload = {
      log_id:             this.selectedPayment.logId,
      student_id:         this.selectedPayment.studentId,
      accounting_user_id: this.accountingUserId,
      notes:              this.modalNotes,
      payment_method:     this.selectedPayment.paymentMethod
    };

    const action = this.modalMode === 'approve' ? 'verify_payment' : 'reject_payment';

    this.http.post<any>(`${this.apiUrl}?action=${action}`, payload).subscribe({
      next: (res) => {
        this.isProcessing = false;
        if (res.success) {
          this.pendingPayments = this.pendingPayments.filter(
            p => p.logId !== this.selectedPayment!.logId
          );
          this.closeModal();
          if (this.currentTab === 'history') this.loadPaymentHistory();
        }
        this.cdr.detectChanges();
      },
      error: () => {
        this.isProcessing = false;
        this.cdr.detectChanges();
      }
    });
  }

  // ── Formatters ─────────────────────────────────────────────
  formatAmount(amount: number): string {
    return (amount || 0).toLocaleString('en-PH', { minimumFractionDigits: 2 });
  }

  formatDate(dateStr: string): string {
    if (!dateStr) return '—';
    return new Date(dateStr).toLocaleDateString('en-PH', {
      year: 'numeric', month: 'short', day: 'numeric'
    });
  }

  formatDateTime(dateStr: string): string {
    if (!dateStr) return '—';
    return new Date(dateStr).toLocaleString('en-PH');
  }
}