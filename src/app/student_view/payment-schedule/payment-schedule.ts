import { Component, OnInit, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { HttpClient } from '@angular/common/http';

interface Schedule {
  payment_type: string;
  total_assessment: number;
  prelim_due: number;  midterm_due: number;  finals_due: number;
  prelim_paid: number; midterm_paid: number; finals_paid: number;
  prelim_status: string; midterm_status: string; finals_status: string;
}

interface Notice {
  exam_period: string; amount_due: number; due_date: string;
  message: string; sent_at: string; is_read: number;
}

interface Permit {
  id: number; exam_period: string; school_year: string;
  semester: string; status: string; requested_at: string;
  approved_at: string; remarks: string;
}

@Component({
  selector: 'app-payment-schedule',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './payment-schedule.html',
  styleUrl: './payment-schedule.css'
})
export class PaymentSchedule implements OnInit {
  private apiUrl = 'http://localhost/sia-api/accounting.php';
  studentId = 0; studentInfo: any = {};

  schedule: Schedule | null = null;
  notices: Record<string, Notice> = {};
  permits: Permit[] = [];
  isLoading = true;
  isRequesting = false;
  msg = ''; msgType: 'ok'|'err' = 'ok';

  periods = ['Prelim','Midterm','Finals'] as const;

  constructor(private http: HttpClient, private cdr: ChangeDetectorRef) {}

  ngOnInit(): void {
    const s = sessionStorage.getItem('currentUser');
    if (s) { const u = JSON.parse(s); this.studentId = u.id; this.studentInfo = u; }
    this.load();
  }

  load(): void {
    this.isLoading = true;
    this.http.get<any>(`${this.apiUrl}?action=get_payment_schedule&student_id=${this.studentId}`).subscribe({
      next: (res) => {
        this.schedule = res.success ? res.schedule : null;
        this.notices  = res.notices  || {};
        this.isLoading = false;
        this.loadPermits();
        this.cdr.detectChanges();
      },
      error: () => { this.isLoading = false; this.cdr.detectChanges(); }
    });
  }

  loadPermits(): void {
    this.http.get<any>(`${this.apiUrl}?action=get_student_permit_status&student_id=${this.studentId}`).subscribe({
      next: (res) => { this.permits = res.success ? res.permits : []; this.cdr.detectChanges(); }
    });
  }

  getStatus(period: string): string {
    const p = period.toLowerCase() as any;
    return this.schedule ? (this.schedule as any)[p+'_status'] : 'locked';
  }

  getDue(period: string):  number { const p=period.toLowerCase() as any; return this.schedule?(this.schedule as any)[p+'_due']:0; }
  getPaid(period: string): number { const p=period.toLowerCase() as any; return this.schedule?(this.schedule as any)[p+'_paid']:0; }

  getNotice(period: string): Notice | null { return this.notices[period] || null; }

  getPermit(period: string): Permit | undefined { return this.permits.find(p => p.exam_period === period); }

  canRequest(period: string): boolean {
    const status = this.getStatus(period);
    if (status !== 'paid') return false;
    const permit = this.getPermit(period);
    if (permit && (permit.status === 'pending' || permit.status === 'approved')) return false;
    return true;
  }

  requestPermit(period: string): void {
    this.isRequesting = true;
    this.http.post<any>(`${this.apiUrl}?action=request_exam_permit`, {
      student_id:  this.studentId,
      exam_period: period,
      school_year: this.studentInfo.school_year || '2025-2026',
      semester:    this.studentInfo.semester    || '2nd Semester'
    }).subscribe({
      next: (res) => {
        this.isRequesting = false;
        this.msg     = res.message;
        this.msgType = res.success ? 'ok' : 'err';
        if (res.success) this.loadPermits();
        this.cdr.detectChanges();
        setTimeout(() => { this.msg = ''; this.cdr.detectChanges(); }, 5000);
      },
      error: () => { this.isRequesting = false; this.cdr.detectChanges(); }
    });
  }

  statusLabel(s: string): string {
    return { locked:'🔒 Locked', unpaid:'Unpaid', partial:'Partial', paid:'Paid' }[s] || s;
  }

  fmt(n: number): string { return (n||0).toLocaleString('en-PH',{minimumFractionDigits:2}); }
  fmtDate(d: string): string { return d ? new Date(d).toLocaleDateString('en-PH',{year:'numeric',month:'short',day:'numeric'}) : '—'; }
}