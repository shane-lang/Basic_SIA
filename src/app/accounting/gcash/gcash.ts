import { Component, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { MatCardModule } from '@angular/material/card';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatSnackBar, MatSnackBarModule } from '@angular/material/snack-bar';

@Component({
  selector: 'app-gcash',
  standalone: true,
  imports: [
    CommonModule,
    FormsModule,
    MatCardModule,
    MatFormFieldModule,
    MatInputModule,
    MatButtonModule,
    MatIconModule,
    MatSnackBarModule
  ],
  templateUrl: './gcash.html',
  styleUrl: './gcash.css',
})
export class Gcash {
  referenceNumber = signal('');
  studentId = signal('');
  amount = signal('');
  paymentDate = signal('');
  encodedData = signal<any>(null);
  isProcessing = signal(false);

  constructor(private snackBar: MatSnackBar) {}

  encodeReference() {
    if (!this.referenceNumber() || !this.studentId() || !this.amount()) {
      this.snackBar.open('Please fill in all required fields', 'Close', { duration: 3000 });
      return;
    }

    this.isProcessing.set(true);

    // Simulate encoding process
    setTimeout(() => {
      const encoded = {
        referenceNumber: this.referenceNumber(),
        studentId: this.studentId(),
        amount: this.amount(),
        paymentDate: this.paymentDate() || new Date().toISOString().split('T')[0],
        status: 'Pending',
        encodedAt: new Date().toLocaleString(),
        transactionId: this.generateTransactionId()
      };

      this.encodedData.set(encoded);
      this.isProcessing.set(false);
      this.snackBar.open('Reference number encoded successfully!', 'Close', { duration: 3000 });
    }, 1000);
  }

  copyToClipboard(text: string) {
    navigator.clipboard.writeText(text).then(() => {
      this.snackBar.open('Copied to clipboard!', 'Close', { duration: 2000 });
    });
  }

  clearForm() {
    this.referenceNumber.set('');
    this.studentId.set('');
    this.amount.set('');
    this.paymentDate.set('');
    this.encodedData.set(null);
  }

  private generateTransactionId(): string {
    return 'TXN-' + Date.now() + '-' + Math.random().toString(36).substring(7).toUpperCase();
  }
}