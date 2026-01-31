import { Injectable } from '@angular/core';
import { BehaviorSubject, Observable } from 'rxjs';

export interface PaymentRecord {
  id: string;
  studentId: string;
  studentName: string;
  amount: number;
  status: 'Pending' | 'Paid' | 'Overdue';
  dueDate: string;
  paidDate?: string;
  paymentDate?: string;
  paymentReference?: string;
  paymentMethod?: 'Online' | 'Bank Transfer' | 'Cash' | 'GCash';
  enrollmentId?: string;
}

@Injectable({
  providedIn: 'root'
})
export class PaymentService {
  private paymentRecords: Map<string, PaymentRecord> = new Map();
  private paymentRecords$ = new BehaviorSubject<PaymentRecord[]>([]);

  constructor() {
    this.initializeMockData();
  }

  // Initialize mock payment data
  private initializeMockData(): void {
    const mockPayments: PaymentRecord[] = [
      {
        id: 'PAY-001',
        studentId: 'STU-001234',
        studentName: 'Maria Santos',
        amount: 25000,
        status: 'Paid',
        dueDate: '2025-02-28',
        paidDate: '2025-01-10',
        paymentReference: 'GCH-20250110-001',
        paymentMethod: 'GCash'
      },
      {
        id: 'PAY-002',
        studentId: 'STU-001235',
        studentName: 'Juan Dela Cruz',
        amount: 30000,
        status: 'Pending',
        dueDate: '2025-02-28',
        paymentMethod: 'Online'
      },
      {
        id: 'PAY-003',
        studentId: 'STU-001236',
        studentName: 'Anna Garcia',
        amount: 25000,
        status: 'Paid',
        dueDate: '2025-02-28',
        paidDate: '2025-01-08',
        paymentReference: 'GCH-20250108-002',
        paymentMethod: 'GCash'
      },
      {
        id: 'PAY-004',
        studentId: 'STU-001237',
        studentName: 'Luis Rodriguez',
        amount: 25000,
        status: 'Overdue',
        dueDate: '2025-01-15',
        paymentMethod: 'Online'
      },
      {
        id: 'PAY-005',
        studentId: 'STU-001238',
        studentName: 'Maria Lopez',
        amount: 25000,
        status: 'Paid',
        dueDate: '2025-02-28',
        paidDate: '2025-01-14',
        paymentReference: 'GCH-20250114-003',
        paymentMethod: 'GCash'
      },
      {
        id: 'PAY-006',
        studentId: 'STU-001239',
        studentName: 'Pedro Reyes',
        amount: 30000,
        status: 'Paid',
        dueDate: '2025-02-28',
        paidDate: '2025-01-17',
        paymentReference: 'GCH-20250117-004',
        paymentMethod: 'GCash'
      }
    ];

    mockPayments.forEach(payment => {
      this.paymentRecords.set(payment.studentId, payment);
    });

    this.paymentRecords$.next(Array.from(this.paymentRecords.values()));
  }

  /**
   * Get payment status for a specific student
   */
  getPaymentStatus(studentId: string): Observable<PaymentRecord | undefined> {
    return new Observable(observer => {
      const payment = this.paymentRecords.get(studentId);
      observer.next(payment);
      observer.complete();
    });
  }

  /**
   * Get all payment records
   */
  getAllPayments(): Observable<PaymentRecord[]> {
    return new Observable(observer => {
      observer.next(Array.from(this.paymentRecords.values()));
      observer.complete();
    });
  }

  /**
   * Check if student has paid
   */
  isPaid(studentId: string): boolean {
    const payment = this.paymentRecords.get(studentId);
    return payment ? payment.status === 'Paid' : false;
  }

  /**
   * Get payment status string
   */
  getPaymentStatusString(studentId: string): 'Pending' | 'Paid' | 'Overdue' | 'Not Found' {
    const payment = this.paymentRecords.get(studentId);
    return payment ? payment.status : 'Not Found';
  }

  /**
   * Record a payment
   */
  recordPayment(studentId: string, paymentMethod: string, reference: string): boolean {
    const payment = this.paymentRecords.get(studentId);
    if (payment) {
      payment.status = 'Paid';
      payment.paidDate = new Date().toISOString().split('T')[0];
      payment.paymentDate = payment.paidDate;
      payment.paymentReference = reference;
      payment.paymentMethod = paymentMethod as any;
      this.paymentRecords$.next(Array.from(this.paymentRecords.values()));
      return true;
    }
    return false;
  }

  /**
   * Get all unpaid students
   */
  getUnpaidStudents(): PaymentRecord[] {
    return Array.from(this.paymentRecords.values()).filter(
      p => p.status !== 'Paid'
    );
  }

  /**
   * Get overdue payments
   */
  getOverduePayments(): PaymentRecord[] {
    const today = new Date();
    return Array.from(this.paymentRecords.values()).filter(
      p => p.status !== 'Paid' && new Date(p.dueDate) < today
    );
  }
}
