import { Component, OnInit, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { MatCardModule } from '@angular/material/card';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatSnackBar, MatSnackBarModule } from '@angular/material/snack-bar';
import { environment } from '../../environment';

@Component({
  selector: 'app-gcash',
  standalone: true,
  imports: [
    CommonModule, FormsModule, MatCardModule, MatFormFieldModule,
    MatInputModule, MatButtonModule, MatIconModule, MatSnackBarModule
  ],
  templateUrl: './gcash.html',
  styleUrl: './gcash.css',
})
export class Gcash implements OnInit {
  private apiUrl         = environment.accountingApi;
  private enrollmentApi  = environment.enrollApi;

  // Form fields (plain properties — NOT signals, for ngModel compatibility)
  referenceNumber  = '';
  amount           = '';
  paymentDate      = '';

  // Auto-filled from logged-in user
  studentId        = '';  // display only (student_number)
  studentDbId      = 0;   // actual DB id for API calls
  studentName      = '';
  program          = '';
  semester         = '';

  encodedData: any = null;
  processing       = false;
  submitted        = false; // true after successful submission to accounting
  constructor(
    private http: HttpClient,
    private snackBar: MatSnackBar,
    private cdr: ChangeDetectorRef
  ) {}

  ngOnInit(): void {
    this.loadStudentInfo();
  }

  loadStudentInfo(): void {
    const stored = sessionStorage.getItem('currentUser');
    if (!stored) return;
    const user = JSON.parse(stored);

    this.http.get<any>(`${this.enrollmentApi}?action=get_profile&user_id=${user.id}`).subscribe({
      next: (res) => {
        if (res.success) {
          const s = res.student;
          this.studentId    = s.id;          // student_number e.g. STU-2024-0001
          this.studentDbId  = s.dbId;
          this.studentName  = `${s.firstName} ${s.lastName}`;
          this.program      = s.program;
          this.semester     = s.semester || '';
          // Pre-fill amount from enrollment fee
          if (!this.amount) this.amount = '25000';
          // Pre-fill today's date
          if (!this.paymentDate) this.paymentDate = new Date().toISOString().split('T')[0];

          // ── Restore submitted state so reload doesn't allow re-submission ──
          const savedKey = `gcashSubmitted_${this.studentDbId}`;
          const savedRaw = sessionStorage.getItem(savedKey);
          if (savedRaw) {
            try {
              this.encodedData = JSON.parse(savedRaw);
              this.submitted   = true;
            } catch { sessionStorage.removeItem(savedKey); }
          }

          this.cdr.detectChanges();
        }
      },
      error: () => {
        this.snackBar.open('Failed to load student profile. Please refresh.', 'Close', { duration: 3000 });
        this.cdr.detectChanges();
      }
    });
  }

  isProcessing(): boolean { return this.processing; }

  encodeReference(): void {
    if (!this.referenceNumber || !this.amount) {
      this.snackBar.open('Please fill in all required fields', 'Close', { duration: 3000 });
      return;
    }
    if (!this.studentDbId) {
      this.snackBar.open('Student profile not loaded. Please login again.', 'Close', { duration: 3000 });
      return;
    }

    this.processing = true;
    this.cdr.detectChanges();

    const txnId = this.generateTransactionId();
    const payload = {
      student_id:       this.studentDbId,
      gcash_reference:  this.referenceNumber,
      gcash_amount:     parseFloat(this.amount),
      gcash_date:       this.paymentDate || new Date().toISOString().split('T')[0],
      transaction_id:   txnId,
      semester:         this.semester
    };

    // Submit to accounting API
    this.http.post<any>(`${this.apiUrl}?action=submit_gcash`, payload).subscribe({
      next: (res) => {
        this.processing = false;
        if (res.success) {
          this.encodedData = {
            referenceNumber: this.referenceNumber,
            studentId:       this.studentId,
            studentName:     this.studentName,
            program:         this.program,
            semester:        this.semester,
            amount:          parseFloat(this.amount).toLocaleString('en-PH', { minimumFractionDigits: 2 }),
            paymentDate:     this.paymentDate || new Date().toISOString().split('T')[0],
            status:          'Pending Verification',
            encodedAt:       new Date().toLocaleString('en-PH'),
            transactionId:   txnId,
            logId:           res.log_id
          };
          this.submitted = true;
          // Persist so reload shows "already submitted" instead of blank form
          sessionStorage.setItem(`gcashSubmitted_${this.studentDbId}`, JSON.stringify(this.encodedData));
          this.snackBar.open('Payment submitted! Waiting for accounting verification.', 'Close', { duration: 4000 });
        } else {
          this.snackBar.open(res.message || 'Submission failed.', 'Close', { duration: 3000 });
        }
        this.cdr.detectChanges();
      },
      error: () => {
        this.processing = false;
        this.snackBar.open('Cannot connect to server. Check XAMPP is running.', 'Close', { duration: 3000 });
        this.cdr.detectChanges();
      }
    });
  }

  copyToClipboard(text: string): void {
    navigator.clipboard.writeText(text).then(() => {
      this.snackBar.open('Copied to clipboard!', 'Close', { duration: 2000 });
    });
  }

  clearForm(): void {
    this.referenceNumber = '';
    this.amount          = '25000';
    this.paymentDate     = new Date().toISOString().split('T')[0];
    this.encodedData     = null;
    this.submitted       = false;
    // Clear persisted state so form is truly reset
    if (this.studentDbId) sessionStorage.removeItem(`gcashSubmitted_${this.studentDbId}`);
    this.cdr.detectChanges();
  }

  private generateTransactionId(): string {
    return 'TXN-' + Date.now() + '-' + Math.random().toString(36).substring(2, 7).toUpperCase();
  }
}