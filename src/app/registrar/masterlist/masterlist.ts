import { Component, OnInit, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient, HttpHeaders } from '@angular/common/http';

export interface Student {
  id: number; studentNumber: string;
  firstName: string; lastName: string; middleName: string; suffix: string;
  fullName: string; email: string; phone: string; dateOfBirth: string; age: string;
  sex: string; religion: string; placeOfBirth: string; citizenship: string;
  motherTongue: string; address: string; lrnNo: string; psaBirthCertNo: string;
  isIndigenous: string; hasSpecialNeeds: string; specialNeedsDetails: string;
  hasAssistiveTech: string; assistiveTechDetails: string; strand: string;
  learningDelivery: string; lastSchoolAttended: string;
  guardianName: string; guardianAddress: string; guardianContact: string;
  program: string; yearLevel: string; semester: string; studentType: string;
  studentCategory: string; enrollmentStatus: string; paymentStatus: string;
  approvalStatus: string; isScholar: number; scholarType: string;
  enrollmentDate: string; initials: string; department: string;
}

export interface ProgramRecord {
  id: number; name: string; code: string; levelType: string;
  department: string; description: string; duration: number;
  totalEnrolled: number;
  subjects: SubjectRecord[];
  subjectsLoaded: boolean;
  expanded: boolean;
}

export interface SubjectRecord {
  id: number; code: string; name: string; credits: number;
  lecUnits?: number; labUnits?: number;
  instructor: string; semester: string; day: string; time: string; room: string;
  capacity: number; enrolledCount: number;
  yearLevel: string; isGeneral: boolean;
  schoolYear?: string;
}

@Component({
  selector: 'app-masterlist',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './masterlist.html',
  styleUrl: './masterlist.css',
})
export class MasterlistComponent implements OnInit {
  private api = 'http://localhost/sia-api/registrar.php';
  private getHeaders() {
    const t = sessionStorage.getItem('token') || localStorage.getItem('token') || '';
    return { headers: new HttpHeaders({ Authorization: `Bearer ${t}` }) };
  }
  constructor(private http: HttpClient, private cdr: ChangeDetectorRef) {}

  // ═══════════════════════════════════════════════════════
  // PROGRAMS (needed for report filters)
  // ═══════════════════════════════════════════════════════
  courseTab: 'College' | 'SHS' | 'TVET' = 'College';
  collegePrograms: ProgramRecord[] = [];
  shsPrograms: ProgramRecord[]     = [];
  tvetPrograms: ProgramRecord[]    = [];
  programsLoaded: Set<string>      = new Set();

  get activePrograms(): ProgramRecord[] {
    return this.courseTab === 'College' ? this.collegePrograms
         : this.courseTab === 'SHS'     ? this.shsPrograms
         : this.tvetPrograms;
  }

  switchCourseTab(tab: 'College' | 'SHS' | 'TVET'): void {
    this.courseTab = tab;
    this.rProgId   = 'all';
    this.rYearLevel = 'all';
    if (!this.programsLoaded.has(tab)) this.loadPrograms(tab);
    this.cdr.detectChanges();
  }

  loadPrograms(level: string): void {
    const p = new URLSearchParams({ action: 'masterlist_programs', level });
    this.http.get<any>(`${this.api}?${p}`, this.getHeaders()).subscribe({
      next: res => {
        if (res.success) {
          const list = (res.programs || []).map((p: any) => ({
            ...p, expanded: false, subjects: [], subjectsLoaded: false
          }));
          if (level === 'College') this.collegePrograms = list;
          else if (level === 'SHS') this.shsPrograms = list;
          else this.tvetPrograms = list;
          this.programsLoaded.add(level);
        }
        this.cdr.detectChanges();
      }
    });
  }

  // ═══════════════════════════════════════════════════════
  // REPORT MODAL
  // ═══════════════════════════════════════════════════════
  showReportModal = false;
  rProgId         = 'all';
  rYearLevel      = 'all';

  readonly rYearOptions = ['1st Year','2nd Year','3rd Year','4th Year','5th Year','Grade 11','Grade 12'];

  private parseAY(sem: string): string {
    const m = sem?.match(/AY\s*(\d{4}-\d{4})/i);
    return m ? `AY ${m[1]}` : '';
  }

  get rAvailableYears(): string[] {
    const prog = this.rProgId && this.rProgId !== 'all'
      ? this.activePrograms.find(p => String(p.id) === this.rProgId)
      : null;
    const subjects = prog ? prog.subjects : this.activePrograms.flatMap(p => p.subjects);
    const found = new Set(subjects.map(s => s.yearLevel || '1st Year'));
    return this.rYearOptions.filter(y => found.has(y));
  }

  getProgNameById(id: string): string {
    return this.activePrograms.find(p => String(p.id) === id)?.name || 'Selected Program';
  }

  openReportModal(): void {
    this.rProgId    = 'all';
    this.rYearLevel = 'all';
    if (!this.programsLoaded.has(this.courseTab)) this.loadPrograms(this.courseTab);
    this.showReportModal = true;
    this.cdr.detectChanges();
  }
  closeReportModal(): void { this.showReportModal = false; this.cdr.detectChanges(); }

  runReport(type: string): void {
    this.closeReportModal();
    if (type === 'students-per-year') this.reportStudentsPerYear();
    if (type === 'subjects-per-year') this.reportSubjectsPerYear();
  }

  // ── Report: Students enrolled per program per school year ─
  private reportStudentsPerYear(): void {
    const win = window.open('', '_blank', 'width=1050,height=700');
    if (!win) return;

    const p = new URLSearchParams({
      action: 'report_students_per_year',
      level:  this.courseTab,
      ...(this.rProgId !== 'all' && { program_id: this.rProgId }),
      ...(this.rYearLevel !== 'all' && { year_level: this.rYearLevel }),
    });

    win.document.write(this.reportHtml(
      `${this.courseTab} — Students per Program per Year`,
      `<div class="record-count"><em>⏳ Loading data from server…</em></div>`,
      `<div id="report-body"></div>`,
      'per-year'
    ));
    win.document.close();

    this.http.get<any>(`${this.api}?${p}`, this.getHeaders()).subscribe({
      next: res => {
        if (!res.success) { win.document.getElementById('report-body')!.innerHTML = '<p>No data found.</p>'; return; }

        const rows: any[]        = res.rows || [];
        const allYears: string[] = (res.years || []).sort().reverse();
        const programs: string[] = (res.programs || []).sort();

        const pivot: Record<string, Record<string, Record<string, number>>> = {};
        for (const r of rows) {
          if (!pivot[r.program]) pivot[r.program] = {};
          if (!pivot[r.program][r.yearLevel]) pivot[r.program][r.yearLevel] = {};
          pivot[r.program][r.yearLevel][r.schoolYear] = r.count;
        }

        const yearHeaders = allYears.map(y => `<th class="tc ay-th">${y}</th>`).join('');
        let body = `
          <div class="section-heading">📊 Students Enrolled per Program per School Year</div>
          <table>
            <thead>
              <tr>
                <th>Program</th>
                <th>Year Level</th>
                ${yearHeaders}
                <th class="tc total-td">Total</th>
              </tr>
            </thead>
            <tbody>`;

        const yearLevelOrder = ['1st Year','2nd Year','3rd Year','4th Year','5th Year','Grade 11','Grade 12'];
        let grandTotal: Record<string, number> = {};
        allYears.forEach(y => grandTotal[y] = 0);
        let grandSum = 0;

        for (const prog of programs) {
          const ylMap = pivot[prog] || {};
          const yearLevels = yearLevelOrder.filter(yl => ylMap[yl]);
          if (!yearLevels.length) continue;

          let progTotalByAY: Record<string, number> = {};
          allYears.forEach(y => progTotalByAY[y] = 0);

          yearLevels.forEach((yl, idx) => {
            const ayCounts = ylMap[yl] || {};
            let rowTotal = 0;
            const cells = allYears.map(y => {
              const v = ayCounts[y] || 0;
              progTotalByAY[y] += v;
              grandTotal[y]    += v;
              rowTotal         += v;
              return `<td class="tc ${v ? 'has-count' : 'zero-count'}">${v || '—'}</td>`;
            }).join('');
            const progCell = idx === 0
              ? `<td class="prog-cell" rowspan="${yearLevels.length}"><strong>${prog}</strong></td>`
              : '';
            body += `<tr>${progCell}<td class="yl-cell">${yl}</td>${cells}<td class="tc total-td"><strong>${rowTotal}</strong></td></tr>`;
          });

          const subCells = allYears.map(y => {
            const v = progTotalByAY[y];
            return `<td class="tc sub-total">${v || '—'}</td>`;
          }).join('');
          const subSum = Object.values(progTotalByAY).reduce((a, b) => a + b, 0);
          grandSum += subSum;
          body += `<tr class="prog-sub-row"><td colspan="2" class="tot-lbl">↳ ${prog} Total</td>${subCells}<td class="tc total-td sub-total"><strong>${subSum}</strong></td></tr>`;
        }

        const grandCells = allYears.map(y => `<td class="tc total-td"><strong>${grandTotal[y] || 0}</strong></td>`).join('');
        body += `<tr class="tot-row"><td colspan="2" class="tot-lbl">GRAND TOTAL</td>${grandCells}<td class="tc total-td"><strong>${grandSum}</strong></td></tr>`;
        body += `</tbody></table>`;

        const progLabel = this.rProgId !== 'all' ? this.getProgNameById(this.rProgId) : `All ${this.courseTab} Programs`;
        const yearLabel = this.rYearLevel !== 'all' ? ` · ${this.rYearLevel}` : '';
        const subtitle  = `<div class="record-count">Program: <strong>${progLabel}</strong>${yearLabel} · School years: <strong>${allYears.join(', ') || 'N/A'}</strong> · Total students: <strong>${grandSum}</strong></div>`;

        const bodyEl = win.document.getElementById('report-body');
        if (bodyEl) bodyEl.innerHTML = subtitle + body;
        const loadEl = win.document.querySelector('.record-count');
        if (loadEl) loadEl.remove();
        setTimeout(() => win.print(), 500);
      },
      error: () => {
        const el = win.document.getElementById('report-body');
        if (el) el.innerHTML = '<p style="color:red">Failed to load data.</p>';
      }
    });
  }

  // ── Report: Students enrolled per subject per school year ─
  private reportSubjectsPerYear(): void {
    const win = window.open('', '_blank', 'width=1100,height=700');
    if (!win) return;

    const p = new URLSearchParams({
      action: 'report_subjects_per_year',
      level:  this.courseTab,
      ...(this.rProgId !== 'all' && { program_id: this.rProgId }),
      ...(this.rYearLevel !== 'all' && { year_level: this.rYearLevel }),
    });

    win.document.write(this.reportHtml(
      `${this.courseTab} — Students per Subject per Year`,
      `<div class="record-count"><em>⏳ Loading data from server…</em></div>`,
      `<div id="report-body"></div>`,
      'per-year'
    ));
    win.document.close();

    this.http.get<any>(`${this.api}?${p}`, this.getHeaders()).subscribe({
      next: res => {
        if (!res.success) { win.document.getElementById('report-body')!.innerHTML = '<p>No data found.</p>'; return; }

        const rows: any[]        = res.rows || [];
        const allYears: string[] = (res.years || []).sort().reverse();

        const grouped: Record<string, Record<string, { name: string; credits: number; yearLevel: string; byCounts: Record<string, number> }>> = {};
        for (const r of rows) {
          if (!grouped[r.program]) grouped[r.program] = {};
          const key = r.subjectCode;
          if (!grouped[r.program][key]) {
            grouped[r.program][key] = { name: r.subjectName, credits: r.credits, yearLevel: r.yearLevel, byCounts: {} };
          }
          grouped[r.program][key].byCounts[r.schoolYear] = (grouped[r.program][key].byCounts[r.schoolYear] || 0) + r.count;
        }

        const yearHeaders = allYears.map(y => `<th class="tc ay-th">${y}</th>`).join('');
        let body = `<div class="section-heading">📚 Students Enrolled per Subject per School Year</div>`;

        let grandTotal: Record<string, number> = {};
        allYears.forEach(y => grandTotal[y] = 0);
        let grandSum = 0;

        for (const prog of Object.keys(grouped).sort()) {
          const subjects    = grouped[prog];
          const subjectKeys = Object.keys(subjects).sort();

          body += `
            <h3 class="prog-heading">${prog}</h3>
            <table>
              <thead>
                <tr>
                  <th>Code</th><th>Subject</th><th class="tc">Units</th><th>Year Level</th>
                  ${yearHeaders}
                  <th class="tc total-td">Total</th>
                </tr>
              </thead>
              <tbody>`;

          let progTotals: Record<string, number> = {};
          allYears.forEach(y => progTotals[y] = 0);
          let progSum = 0;

          for (const code of subjectKeys) {
            const subj = subjects[code];
            let rowTotal = 0;
            const cells = allYears.map(y => {
              const v = subj.byCounts[y] || 0;
              progTotals[y] += v;
              grandTotal[y] += v;
              rowTotal      += v;
              return `<td class="tc ${v ? 'has-count' : 'zero-count'}">${v || '—'}</td>`;
            }).join('');
            progSum  += rowTotal;
            grandSum += rowTotal;
            body += `<tr>
              <td><span class="code-pill">${code}</span></td>
              <td>${subj.name}</td>
              <td class="tc dim">${subj.credits}</td>
              <td class="dim">${subj.yearLevel || '—'}</td>
              ${cells}
              <td class="tc total-td"><strong>${rowTotal}</strong></td>
            </tr>`;
          }

          const pSubCells = allYears.map(y => `<td class="tc sub-total">${progTotals[y] || '—'}</td>`).join('');
          body += `<tr class="tot-row"><td colspan="4" class="tot-lbl">↳ ${prog} Total</td>${pSubCells}<td class="tc total-td sub-total"><strong>${progSum}</strong></td></tr>`;
          body += `</tbody></table>`;
        }

        body += `<div class="grand-total-banner">
          GRAND TOTAL — ${allYears.map(y => `<span>${y}: <strong>${grandTotal[y] || 0}</strong></span>`).join(' &nbsp;·&nbsp; ')}
          &nbsp;&nbsp; ALL YEARS: <strong>${grandSum}</strong>
        </div>`;

        const progLabel = this.rProgId !== 'all' ? this.getProgNameById(this.rProgId) : `All ${this.courseTab} Programs`;
        const yearLabel = this.rYearLevel !== 'all' ? ` · ${this.rYearLevel}` : '';
        const subtitle  = `<div class="record-count">Program: <strong>${progLabel}</strong>${yearLabel} · Total enrollments: <strong>${grandSum}</strong></div>`;

        const bodyEl = win.document.getElementById('report-body');
        if (bodyEl) bodyEl.innerHTML = subtitle + body;
        const loadEl = win.document.querySelector('.record-count');
        if (loadEl) loadEl.remove();
        setTimeout(() => win.print(), 500);
      },
      error: () => {
        const el = win.document.getElementById('report-body');
        if (el) el.innerHTML = '<p style="color:red">Failed to load data.</p>';
      }
    });
  }

  // ── Shared report HTML shell ──────────────────────────────
  private reportHtml(title: string, subtitle: string, body: string, mode = 'default'): string {
    return `<!DOCTYPE html><html><head><meta charset="UTF-8">
    <title>${title}</title>
    <style>
      * { margin:0; padding:0; box-sizing:border-box; }
      body { font-family: Arial, sans-serif; font-size:11px; color:#1a1a2e; padding:20px; }
      .report-header { text-align:center; border-bottom:2px solid #1a1a2e; padding-bottom:10px; margin-bottom:14px; }
      .report-header h1 { font-size:16px; font-weight:900; letter-spacing:.04em; }
      .report-header h2 { font-size:12px; font-weight:600; color:#475569; margin-top:3px; }
      .report-header p  { font-size:10px; color:#94a3b8; margin-top:3px; }
      .record-count { font-size:11px; color:#475569; margin-bottom:10px; }
      table { width:100%; border-collapse:collapse; margin-bottom:10px; font-size:10px; }
      th { background:#1a1a2e; color:#fff; padding:5px 7px; text-align:left; font-size:9px; text-transform:uppercase; letter-spacing:.04em; }
      td { padding:4px 7px; border-bottom:1px solid #e2e8f0; }
      tr:nth-child(even) td { background:#f8fafc; }
      .tc { text-align:center; }
      .tot-row td { background:#f0fdf4 !important; font-weight:700; border-top:2px solid #86efac; }
      .tot-lbl { color:#166534; font-size:9px; text-transform:uppercase; letter-spacing:.05em; }
      .code-pill { background:#e0e7ff; color:#3730a3; font-size:8px; font-weight:700; padding:1px 5px; border-radius:3px; white-space:nowrap; }
      .prog-heading { margin:16px 0 4px; font-size:12px; color:#1e3a5f; border-bottom:1px solid #cbd5e1; padding-bottom:3px; }
      .footer { margin-top:16px; font-size:9px; color:#94a3b8; text-align:center; border-top:1px solid #e2e8f0; padding-top:6px; }
      .ay-th { font-size:8px; white-space:nowrap; }
      .prog-cell { font-size:10px; vertical-align:top; padding-top:6px; border-right:2px solid #6366f1; background:#f5f3ff !important; }
      .yl-cell { font-size:10px; color:#475569; }
      .has-count { color:#166534; font-weight:700; }
      .zero-count { color:#cbd5e1; font-size:9px; }
      .sub-total { color:#1e40af; font-weight:700; }
      .prog-sub-row td { background:#eff6ff !important; border-top:1px solid #bfdbfe; font-size:9px; }
      .total-td { color:#1a1a2e; }
      .dim { font-size:9px; color:#94a3b8; }
      .grand-total-banner { background:#0f172a; color:#fbbf24; font-weight:900; font-size:11px; padding:8px 14px; border-radius:4px; margin-top:10px; text-align:right; }
      .grand-total-banner strong { color:#fff; }
      .section-heading { font-size:11px; font-weight:800; color:#1a1a2e; background:#f1f5f9; border-left:3px solid #6366f1; padding:5px 10px; margin:0 0 8px; border-radius:0 4px 4px 0; }
      @media print { body { padding:10px; } }
    </style></head><body>
    <div class="report-header">
      <h1>ST. BENILDE CENTER FOR GLOBAL COMPETENCE, INC.</h1>
      <h2>${title}</h2>
      <p>Generated: ${new Date().toLocaleDateString('en-PH',{year:'numeric',month:'long',day:'numeric',hour:'2-digit',minute:'2-digit'})}</p>
    </div>
    ${subtitle}
    ${body}
    <div class="footer">BASIC — St. Benilde School Information System · Registrar's Office</div>
    </body></html>`;
  }

  // ═══════════════════════════════════════════════════════
  // LIFECYCLE
  // ═══════════════════════════════════════════════════════
  ngOnInit(): void {
    this.loadPrograms('College');
  }
}