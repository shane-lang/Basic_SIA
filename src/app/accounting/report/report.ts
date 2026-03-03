import { Component, OnInit, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient, HttpHeaders } from '@angular/common/http';
import { MethodFilterPipe } from './method-filter.pipe';

type Period = 'weekly' | 'monthly' | 'yearly';

interface IncomeSummary {
  today: number;
  thisWeek: number;
  thisMonth: number;
  thisYear: number;
  prevYear: number;
  yoyChange: number | null;
  outstanding: number;
  enrolledCount: number;
  monthlyTrend: { month: number; label: string; total: number }[];
}

interface IncomeBreakdown {
  periodKey: string | number;
  periodLabel: string;
  transactionCount: number;
  studentCount: number;
  totalAmount: number;
  cashAmount: number;
  gcashAmount: number;
  checkAmount: number;
}

interface IncomeReport {
  grandTotal: number;
  grandCount: number;
  dateFrom: string;
  dateTo: string;
  breakdown: IncomeBreakdown[];
  byMethod: { method: string; count: number; total: number }[];
  byExamPeriod: { period: string; count: number; total: number }[];
  topStudents: { studentNumber: string; name: string; program: string; totalPaid: number; txnCount: number }[];
}

interface ProgramIncome {
  program: string;
  studentCount: number;
  txnCount: number;
  totalAmount: number;
  percentage: number;
}

@Component({
  selector: 'app-report',
  standalone: true,
  imports: [CommonModule, FormsModule, MethodFilterPipe],
  templateUrl: './report.html',
  styleUrl: './report.css',
})
export class Report implements OnInit {
  private apiUrl = 'http://localhost/sia-api/Accounting.php';

  private getHeaders(): { headers: HttpHeaders } {
    const token = sessionStorage.getItem('token') || localStorage.getItem('token') || '';
    return { headers: new HttpHeaders({ Authorization: `Bearer ${token}` }) };
  }

  constructor(private http: HttpClient, private cdr: ChangeDetectorRef) {}

  // UI State
  activePeriod: Period = 'monthly';
  activeTab = 'overview';

  // Date selectors
  selectedYear  = new Date().getFullYear();
  selectedMonth = new Date().getMonth() + 1;
  selectedWeekStart = this.getMonday();

  // Data
  summary: IncomeSummary | null = null;
  report:  IncomeReport  | null = null;
  programData: ProgramIncome[]  = [];
  isLoadingSummary = false;
  isLoadingReport  = false;
  isLoadingProgram = false;

  years  = Array.from({ length: 5 }, (_, i) => new Date().getFullYear() - i);
  months = [
    { value: 1, label: 'January' },   { value: 2,  label: 'February' },
    { value: 3, label: 'March' },      { value: 4,  label: 'April' },
    { value: 5, label: 'May' },        { value: 6,  label: 'June' },
    { value: 7, label: 'July' },       { value: 8,  label: 'August' },
    { value: 9, label: 'September' },  { value: 10, label: 'October' },
    { value: 11, label: 'November' },  { value: 12, label: 'December' },
  ];

  ngOnInit(): void {
    this.loadSummary();
    this.loadReport();
    this.loadProgramData();
  }

  loadSummary(): void {
    this.isLoadingSummary = true;
    this.http.get<any>(`${this.apiUrl}?action=get_income_summary`, this.getHeaders()).subscribe({
      next: (res) => {
        this.isLoadingSummary = false;
        if (res.success) { this.summary = res; }
        this.cdr.detectChanges();
      },
      error: () => { this.isLoadingSummary = false; this.cdr.detectChanges(); }
    });
  }

  loadReport(): void {
    this.isLoadingReport = true;
    const params = new URLSearchParams({
      action: 'get_income_report',
      period: this.activePeriod,
      year:   String(this.selectedYear),
      month:  String(this.selectedMonth),
      week_start: this.selectedWeekStart,
    });

    this.http.get<any>(`${this.apiUrl}?${params}`, this.getHeaders()).subscribe({
      next: (res) => {
        this.isLoadingReport = false;
        if (res.success) { this.report = res; }
        this.cdr.detectChanges();
      },
      error: () => { this.isLoadingReport = false; this.cdr.detectChanges(); }
    });
  }

  loadProgramData(): void {
    this.isLoadingProgram = true;
    const params = new URLSearchParams({
      action: 'get_income_by_program',
      year:   String(this.selectedYear),
      month:  this.activePeriod === 'monthly' ? String(this.selectedMonth) : '0',
    });

    this.http.get<any>(`${this.apiUrl}?${params}`, this.getHeaders()).subscribe({
      next: (res) => {
        this.isLoadingProgram = false;
        if (res.success) { this.programData = res.programs || []; }
        this.cdr.detectChanges();
      },
      error: () => { this.isLoadingProgram = false; this.cdr.detectChanges(); }
    });
  }

  onPeriodChange(p: Period): void {
    this.activePeriod = p;
    this.loadReport();
    this.loadProgramData();
  }

  onDateChange(): void {
    this.loadReport();
    this.loadProgramData();
  }

  // Helpers
  getMonday(): string {
    const d = new Date();
    const day = d.getDay();
    const diff = d.getDate() - day + (day === 0 ? -6 : 1);
    const mon = new Date(d.setDate(diff));
    return mon.toISOString().split('T')[0];
  }

  prevWeek(): void {
    const d = new Date(this.selectedWeekStart);
    d.setDate(d.getDate() - 7);
    this.selectedWeekStart = d.toISOString().split('T')[0];
    this.loadReport();
  }

  nextWeek(): void {
    const d = new Date(this.selectedWeekStart);
    d.setDate(d.getDate() + 7);
    this.selectedWeekStart = d.toISOString().split('T')[0];
    this.loadReport();
  }

  prevMonth(): void {
    if (this.selectedMonth === 1) { this.selectedMonth = 12; this.selectedYear--; }
    else this.selectedMonth--;
    this.onDateChange();
  }

  nextMonth(): void {
    if (this.selectedMonth === 12) { this.selectedMonth = 1; this.selectedYear++; }
    else this.selectedMonth++;
    this.onDateChange();
  }

  getMonthLabel(): string {
    return this.months.find(m => m.value === this.selectedMonth)?.label || '';
  }

  getWeekEnd(): string {
    const d = new Date(this.selectedWeekStart);
    d.setDate(d.getDate() + 6);
    return d.toISOString().split('T')[0];
  }

  maxBreakdown(): number {
    if (!this.report?.breakdown?.length) return 1;
    return Math.max(...this.report.breakdown.map(b => b.totalAmount), 1);
  }

  maxTrend(): number {
    if (!this.summary?.monthlyTrend?.length) return 1;
    return Math.max(...this.summary.monthlyTrend.map(t => t.total), 1);
  }

  methodColor(method: string): string {
    const map: Record<string, string> = { Cash: '#10b981', GCash: '#3b82f6', Check: '#f59e0b' };
    return map[method] || '#94a3b8';
  }

  programColorByIndex(i: number): string {
    const colors = ['#3b82f6','#10b981','#f59e0b','#ef4444','#8b5cf6','#06b6d4','#ec4899','#14b8a6'];
    return colors[i % colors.length];
  }

  fmt(n: number): string {
    return '₱' + n.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  fmtShort(n: number): string {
    if (n >= 1_000_000) return '₱' + (n / 1_000_000).toFixed(1) + 'M';
    if (n >= 1_000)     return '₱' + (n / 1_000).toFixed(1) + 'K';
    return '₱' + n.toFixed(0);
  }

  getNameInitials(name: string): string {
    return name.split(' ').map(w => w[0] || '').slice(0, 2).join('').toUpperCase();
  }
}