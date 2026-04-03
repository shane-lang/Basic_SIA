import { Component, OnInit, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient, HttpHeaders } from '@angular/common/http';
import { MethodFilterPipe } from './method-filter.pipe';
import { environment } from '../../environment';

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
  private apiUrl = environment.accountingApi;

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
  isGenerating     = false;

  // Generate Report Modal
  showReportModal  = false;
  modalPeriod: Period = 'monthly';
  modalYear   = new Date().getFullYear();
  modalMonth  = new Date().getMonth() + 1;
  modalWeekStart = this.getMonday();

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
    this.http.get<any>(`${this.apiUrl}?action=get_income_summary`).subscribe({
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

    this.http.get<any>(`${this.apiUrl}?${params}`).subscribe({
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

    this.http.get<any>(`${this.apiUrl}?${params}`).subscribe({
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

  openReportModal(): void {
    this.modalPeriod = this.activePeriod;
    this.modalYear   = this.selectedYear;
    this.modalMonth  = this.selectedMonth;
    this.modalWeekStart = this.selectedWeekStart;
    this.showReportModal = true;
  }

  closeReportModal(): void {
    this.showReportModal = false;
  }

  generateReport(): void {
    if (!this.summary) return;
    this.isGenerating = true;

    const params = new URLSearchParams({
      action: 'get_income_report',
      period: this.modalPeriod,
      year:   String(this.modalYear),
      month:  String(this.modalMonth),
      week_start: this.modalWeekStart,
    });

    const progParams = new URLSearchParams({
      action: 'get_income_by_program',
      year:  String(this.modalYear),
      month: this.modalPeriod === 'monthly' ? String(this.modalMonth) : '0',
    });

    // Fetch report data for the selected modal period
    this.http.get<any>(`${this.apiUrl}?${params}`).subscribe({
      next: (reportRes) => {
        this.http.get<any>(`${this.apiUrl}?${progParams}`).subscribe({
          next: (progRes) => {
            const rpt = reportRes.success ? reportRes as IncomeReport : null;
            const prg: ProgramIncome[] = progRes.success ? (progRes.programs || []) : [];
            this.isGenerating = false;
            this.showReportModal = false;
            if (rpt) this.buildAndPrintReport(rpt, prg);
            this.cdr.detectChanges();
          },
          error: () => { this.isGenerating = false; this.cdr.detectChanges(); }
        });
      },
      error: () => { this.isGenerating = false; this.cdr.detectChanges(); }
    });
  }

  private getModalWeekEnd(): string {
    const d = new Date(this.modalWeekStart);
    d.setDate(d.getDate() + 6);
    return d.toISOString().split('T')[0];
  }

  private getModalMonthLabel(): string {
    return this.months.find(m => m.value === this.modalMonth)?.label || '';
  }

  private buildAndPrintReport(rpt: IncomeReport, prg: ProgramIncome[]): void {
    const periodLabel = this.modalPeriod === 'weekly'
      ? `Week of ${this.modalWeekStart} – ${this.getModalWeekEnd()}`
      : this.modalPeriod === 'monthly'
        ? `${this.getModalMonthLabel()} ${this.modalYear}`
        : `Year ${this.modalYear}`;

    const isWeekly  = this.modalPeriod === 'weekly';
    const isMonthly = this.modalPeriod === 'monthly';
    const isYearly  = this.modalPeriod === 'yearly';

    // ── Shared sections ──────────────────────────────────────
    const summaryTable = `
      <h2>Summary</h2>
      <table>
        <tr><th>Total Transactions</th><th>Total Income</th><th>Date From</th><th>Date To</th></tr>
        <tr>
          <td style="text-align:center">${rpt.grandCount}</td>
          <td><strong>${this.fmt(rpt.grandTotal)}</strong></td>
          <td>${rpt.dateFrom}</td><td>${rpt.dateTo}</td>
        </tr>
      </table>`;

    const byMethodRows = rpt.byMethod.map(m =>
      `<tr><td>${m.method}</td><td style="text-align:center">${m.count}</td><td style="text-align:right">${this.fmt(m.total)}</td></tr>`
    ).join('');
    const paymentMethodTable = `
      <h2>Payment Methods</h2>
      <table><tr><th>Method</th><th>Transactions</th><th>Total</th></tr>${byMethodRows}</table>`;

    const breakdownRows = rpt.breakdown.map(b =>
      `<tr>
        <td>${b.periodLabel}</td>
        <td style="text-align:center">${b.transactionCount}</td>
        <td style="text-align:center">${b.studentCount}</td>
        <td style="text-align:right">${this.fmt(b.cashAmount)}</td>
        <td style="text-align:right">${this.fmt(b.gcashAmount)}</td>
        <td style="text-align:right"><strong>${this.fmt(b.totalAmount)}</strong></td>
      </tr>`
    ).join('');
    const breakdownLabel = isWeekly ? 'Daily Breakdown' : isMonthly ? 'Weekly Breakdown' : 'Monthly Breakdown';
    const breakdownTable = `
      <h2>${breakdownLabel}</h2>
      <table>
        <tr><th>Period</th><th style="text-align:center">Txns</th><th style="text-align:center">Students</th><th style="text-align:right">Cash</th><th style="text-align:right">GCash</th><th style="text-align:right">Total</th></tr>
        ${breakdownRows}
      </table>`;

    const byExamRows = rpt.byExamPeriod.map(e =>
      `<tr><td>${e.period}</td><td style="text-align:center">${e.count}</td><td style="text-align:right">${this.fmt(e.total)}</td></tr>`
    ).join('');
    const examPeriodTable = `
      <h2>By Exam Period</h2>
      <table><tr><th>Exam Period</th><th>Transactions</th><th>Total</th></tr>${byExamRows}</table>`;

    const programRows = prg.map(p =>
      `<tr>
        <td>${p.program}</td>
        <td style="text-align:center">${p.studentCount}</td>
        <td style="text-align:center">${p.txnCount}</td>
        <td style="text-align:right">${this.fmt(p.totalAmount)}</td>
        <td style="text-align:right">${p.percentage.toFixed(1)}%</td>
      </tr>`
    ).join('');
    const byProgramTable = `
      <h2>By Program</h2>
      <table>
        <tr><th>Program</th><th style="text-align:center">Students</th><th style="text-align:center">Txns</th><th style="text-align:right">Total</th><th style="text-align:right">Share</th></tr>
        ${programRows}
      </table>`;

    // ── Assemble sections per period ─────────────────────────
    // Weekly:  Summary | Payment Methods | Daily Breakdown
    // Monthly: Summary | Payment Methods | By Exam Period | Weekly Breakdown | By Program
    // Yearly:  Summary | Payment Methods | By Program | Monthly Breakdown
    const sections = isWeekly
      ? [summaryTable, paymentMethodTable, breakdownTable].join('\n')
      : isMonthly
        ? [summaryTable, paymentMethodTable, examPeriodTable, breakdownTable, byProgramTable].join('\n')
        : [summaryTable, paymentMethodTable, byProgramTable, breakdownTable].join('\n');

    const html = `
      <!DOCTYPE html><html>
      <head>
        <meta charset="utf-8"/>
        <title>Income Report — ${periodLabel}</title>
        <style>
          * { box-sizing:border-box; margin:0; padding:0; }
          body { font-family:Arial,sans-serif; font-size:13px; color:#1e293b; padding:32px; }
          h1 { font-size:22px; color:#1e40af; }
          h2 { font-size:15px; color:#1e293b; margin:24px 0 8px; border-bottom:2px solid #e2e8f0; padding-bottom:4px; }
          .meta { color:#64748b; font-size:12px; margin-top:4px; margin-bottom:24px; }
          table { width:100%; border-collapse:collapse; margin-bottom:24px; }
          th { background:#1e40af; color:white; padding:8px 12px; text-align:left; font-size:12px; }
          td { padding:7px 12px; border-bottom:1px solid #e2e8f0; }
          tr:nth-child(even) td { background:#f8fafc; }
          .footer { margin-top:40px; font-size:11px; color:#94a3b8; text-align:right; }
          @media print { body { padding:16px; } }
        </style>
      </head>
      <body>
        <h1>💰 Income Report</h1>
        <p class="meta">
          Period: <strong>${periodLabel}</strong> &nbsp;|&nbsp;
          Generated: <strong>${new Date().toLocaleString('en-PH')}</strong> &nbsp;|&nbsp;
          Date Range: <strong>${rpt.dateFrom} – ${rpt.dateTo}</strong>
        </p>
        ${sections}
        <div class="footer">BASIC Accounting System — Income Report — ${periodLabel}</div>
      </body></html>
    `;

    const win = window.open('', '_blank');
    if (win) {
      win.document.write(html);
      win.document.close();
      win.focus();
      setTimeout(() => { win.print(); }, 500);
    }
  }
}