import { Component, OnInit, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';

interface EnrolledStudent {
  studentId: number;
  studentNumber: string;
  firstName: string;
  lastName: string;
  program: string;
  yearLevel: string;
  semester: string;
  totalAssessment: number;
  prelimDue: number; midtermDue: number; finalsDue: number;
  prelimPaid: number; midtermPaid: number; finalsPaid: number;
  prelimStatus: string; midtermStatus: string; finalsStatus: string;
}

interface ExamPermit {
  id: number;
  student_id: number;
  student_number: string;
  first_name: string; last_name: string;
  program: string; year_level: string; semester: string;
  exam_period: string; school_year: string;
  status: string;
  requested_at: string; approved_at: string;
  remarks: string;
  approved_by_first: string; approved_by_last: string;
}

@Component({
  selector: 'app-permit-generation',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './permit-generation.html',
  styleUrl: './permit-generation.css'
})
export class PermitGeneration implements OnInit {
  private apiUrl = 'http://localhost/sia-api/accounting.php';
  accountingUserId = 0;

  mainTab: 'notices' | 'permits' = 'notices';
  permitTab: 'pending' | 'approved' | 'rejected' = 'pending';

  // Notice sending
  students: EnrolledStudent[] = [];
  isLoadingStudents = false;
  searchStudents = '';
  showNoticeModal = false;
  noticeStudent: EnrolledStudent | null = null;
  noticeForm = { exam_period: 'Prelim', amount_due: 0, due_date: '', message: '' };
  isSendingNotice = false;
  noticeResult = '';
  noticeResultType: 'success'|'error' = 'success';

  // Permits
  permits: ExamPermit[] = [];
  isLoadingPermits = false;
  searchPermits = '';
  filterPeriod: 'all'|'Prelim'|'Midterm'|'Finals' = 'all';
  showPermitModal = false;
  modalAction: 'approve'|'reject' = 'approve';
  selectedPermit: ExamPermit | null = null;
  remarks = '';
  isProcessing = false;

  constructor(private http: HttpClient, private cdr: ChangeDetectorRef) {}

  ngOnInit(): void {
    const s = sessionStorage.getItem('currentUser');
    if (s) this.accountingUserId = JSON.parse(s).id;
    this.loadStudents();
  }

  // ── Students with payment schedules ──────────────────────────────────────
 loadStudents(): void {
  this.isLoadingStudents = true;
  this.http.get<any>(`${this.apiUrl}?action=get_all_enrolled_students`).subscribe({
    next: (res) => {
      console.log('[PermitGeneration] API response:', res);
      console.log('[PermitGeneration] students count:', res.students?.length);
      this.isLoadingStudents = false;
      this.students = res.success ? res.students : [];
      console.log('[PermitGeneration] this.students:', this.students);
      this.cdr.detectChanges();
    },
    error: (err) => { 
      console.error('[PermitGeneration] API error:', err);
      this.isLoadingStudents = false; 
      this.cdr.detectChanges(); 
    }
  });
}

  loadStudentSchedule(student: EnrolledStudent): void {
    this.http.get<any>(`${this.apiUrl}?action=get_payment_schedule&student_id=${student.studentId}`).subscribe({
      next: (res) => {
        if (res.success && res.schedule) {
          const s = res.schedule;
          student.totalAssessment = s.total_assessment;
          student.prelimDue   = s.prelim_due;   student.midtermDue  = s.midterm_due;  student.finalsDue  = s.finals_due;
          student.prelimPaid  = s.prelim_paid;  student.midtermPaid = s.midterm_paid; student.finalsPaid = s.finals_paid;
          student.prelimStatus  = s.prelim_status;
          student.midtermStatus = s.midterm_status;
          student.finalsStatus  = s.finals_status;
        }
        this.cdr.detectChanges();
      }
    });
  }

  get filteredStudents(): EnrolledStudent[] {
    const q = this.searchStudents.toLowerCase();
    return this.students.filter(s =>
      !q || (s.firstName+' '+s.lastName).toLowerCase().includes(q) || s.studentNumber.toLowerCase().includes(q)
    );
  }

  // ── Send Notice Modal ─────────────────────────────────────────────────────
  openNoticeModal(student: EnrolledStudent, period: string): void {
    this.noticeStudent = student;
    const due = period === 'Prelim' ? student.prelimDue : period === 'Midterm' ? student.midtermDue : student.finalsDue;
    const paid = period === 'Prelim' ? student.prelimPaid : period === 'Midterm' ? student.midtermPaid : student.finalsPaid;
    this.noticeForm = {
      exam_period: period,
      amount_due:  due - paid,
      due_date:    '',
      message:     `Dear ${student.firstName}, your ${period} payment of ₱${(due-paid).toLocaleString('en-PH',{minimumFractionDigits:2})} is now due. Please settle at the Accounting office.`
    };
    this.noticeResult = '';
    this.showNoticeModal = true;
    this.cdr.detectChanges();
  }

  closeNoticeModal(): void { this.showNoticeModal = false; this.noticeStudent = null; this.cdr.detectChanges(); }

  sendNotice(): void {
    if (!this.noticeStudent) return;
    this.isSendingNotice = true;
    this.http.post<any>(`${this.apiUrl}?action=send_payment_notice`, {
      student_id:          this.noticeStudent.studentId,
      exam_period:         this.noticeForm.exam_period,
      amount_due:          this.noticeForm.amount_due,
      due_date:            this.noticeForm.due_date,
      message:             this.noticeForm.message,
      accounting_user_id:  this.accountingUserId
    }).subscribe({
      next: (res) => {
        this.isSendingNotice = false;
        this.noticeResult     = res.message;
        this.noticeResultType = res.success ? 'success' : 'error';
        if (res.success && this.noticeStudent) {
          const period = this.noticeForm.exam_period.toLowerCase() as 'prelim'|'midterm'|'finals';
          (this.noticeStudent as any)[period+'Status'] = 'unpaid';
          this.loadStudentSchedule(this.noticeStudent);
          setTimeout(() => { this.closeNoticeModal(); }, 1800);
        }
        this.cdr.detectChanges();
      },
      error: () => { this.isSendingNotice = false; this.cdr.detectChanges(); }
    });
  }

  // ── Permits ───────────────────────────────────────────────────────────────
  loadPermits(): void {
    this.isLoadingPermits = true;
    this.http.get<any>(`${this.apiUrl}?action=get_exam_permits&status=${this.permitTab}`).subscribe({
      next: (res) => { this.permits = res.success ? res.permits : []; this.isLoadingPermits = false; this.cdr.detectChanges(); },
      error: () => { this.isLoadingPermits = false; this.cdr.detectChanges(); }
    });
  }

  unlockPeriod(student: EnrolledStudent, period: string): void {
    if (!confirm(`Unlock ${period} payment period for ${student.firstName} ${student.lastName}?`)) return;
    
    this.http.post<any>(`${this.apiUrl}?action=unlock_payment_period`, {
      student_id: student.studentId,
      exam_period: period,
      accounting_user_id: this.accountingUserId
    }).subscribe({
      next: (res) => {
        if (res.success) {
          const p = period.toLowerCase() as 'prelim'|'midterm'|'finals';
          (student as any)[p + 'Status'] = 'unpaid';
          this.loadStudentSchedule(student);
          alert(res.message);
        } else {
          alert(res.message || 'Failed to unlock period');
        }
        this.cdr.detectChanges();
      },
      error: () => { alert('Error connecting to server'); this.cdr.detectChanges(); }
    });
  }

  switchMainTab(tab: 'notices'|'permits'): void {
    this.mainTab = tab;
    if (tab === 'permits') this.loadPermits();
    this.cdr.detectChanges();
  }

  switchPermitTab(tab: 'pending'|'approved'|'rejected'): void {
    this.permitTab = tab; this.loadPermits();
  }

  get filteredPermits(): ExamPermit[] {
    const q = this.searchPermits.toLowerCase();
    return this.permits.filter(p => {
      const matchQ = !q || (p.first_name+' '+p.last_name).toLowerCase().includes(q) || p.student_number.toLowerCase().includes(q);
      const matchP = this.filterPeriod === 'all' || p.exam_period === this.filterPeriod;
      return matchQ && matchP;
    });
  }

  openPermitModal(permit: ExamPermit, action: 'approve'|'reject'): void {
    this.selectedPermit = permit; this.modalAction = action; this.remarks = '';
    this.showPermitModal = true; this.cdr.detectChanges();
  }

  closePermitModal(): void { this.showPermitModal = false; this.selectedPermit = null; this.cdr.detectChanges(); }

  confirmPermit(): void {
    if (!this.selectedPermit) return;
    this.isProcessing = true;
    this.http.post<any>(`${this.apiUrl}?action=process_exam_permit`, {
      permit_id:           this.selectedPermit.id,
      action:              this.modalAction,
      remarks:             this.remarks,
      accounting_user_id:  this.accountingUserId
    }).subscribe({
      next: (res) => {
        this.isProcessing = false;
        if (res.success) { this.permits = this.permits.filter(p => p.id !== this.selectedPermit!.id); this.closePermitModal(); }
        this.cdr.detectChanges();
      },
      error: () => { this.isProcessing = false; this.cdr.detectChanges(); }
    });
  }

  printPermit(p: ExamPermit): void {
    const win = window.open('', '_blank', 'width=850,height=700');
    if (!win) return;
    win.document.write(`<!DOCTYPE html><html><head><title>Exam Permit</title>
    <style>
      *{box-sizing:border-box;margin:0;padding:0;}
      body{font-family:Arial,sans-serif;padding:30px;font-size:13px;}
      .header{text-align:center;border-bottom:2px solid #000;padding-bottom:10px;margin-bottom:16px;}
      .school{font-size:18px;font-weight:bold;text-transform:uppercase;}
      .address{font-size:11px;margin-top:4px;}
      .permit-title{background:#ddd;font-weight:bold;font-size:14px;padding:6px;margin-top:10px;text-align:center;}
      .sem-ay{font-size:13px;margin-top:6px;}
      .info-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin:14px 0;}
      .field{border-bottom:1px solid #333;padding:3px 0;}
      .field-label{font-size:10px;color:#555;}
      .field-value{font-weight:bold;font-size:13px;}
      .note{color:#c00;font-style:italic;font-size:11px;margin:10px 0;}
      table{width:100%;border-collapse:collapse;margin-top:14px;}
      th,td{border:1px solid #333;padding:7px;font-size:12px;}
      th{background:#eee;font-weight:bold;}
      .sig-area{display:grid;grid-template-columns:1fr 1fr;gap:40px;margin-top:40px;}
      .sig-line{border-top:1px solid #333;margin-top:40px;text-align:center;font-size:11px;padding-top:4px;}
      @media print{body{padding:15px;}}
    </style></head><body>
    <div class="header">
      <div class="school">St. Benilde Center for Global Competence, Inc.</div>
      <div class="address">#247 Rizal Avenue, West Bajac-Bajac, Olongapo City | Tel/Fax: (047) 223-3031</div>
      <div class="permit-title">${p.exam_period.toUpperCase()} EXAMINATION PERMIT</div>
      <div class="sem-ay">${p.semester} &nbsp; A.Y. ${p.school_year}</div>
    </div>
    <div class="info-grid">
      <div class="field"><div class="field-label">Student No.:</div><div class="field-value">${p.student_number}</div></div>
      <div class="field"><div class="field-label">Name:</div><div class="field-value">${p.last_name}, ${p.first_name}</div></div>
      <div class="field"><div class="field-label">Course/Major:</div><div class="field-value">${p.program}</div></div>
      <div class="field"><div class="field-label">Year Level:</div><div class="field-value">${p.year_level}</div></div>
    </div>
    <div class="note">NOTE: ANY ERASURE WILL INVALIDATE THIS ${p.exam_period.toUpperCase()} PERMIT</div>
    <table>
      <tr><th>Subject</th><th>Date of Exam</th><th>Instructor's Signature</th></tr>
      <tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
      <tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
      <tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
      <tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
      <tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
    </table>
    <div class="sig-area">
      <div><div class="sig-line">${p.approved_by_first||''} ${p.approved_by_last||''}<br>Account Management Officer / Cashier</div></div>
    </div>
    <script>window.onload=()=>{window.print();}<\/script>
    </body></html>`);
    win.document.close();
  }

  periodClass(period: string) { return {Prelim:'badge-prelim',Midterm:'badge-midterm',Finals:'badge-finals'}[period]||''; }
  statusClass(s: string)     { return {unpaid:'st-unpaid',partial:'st-partial',paid:'st-paid',locked:'st-locked'}[s]||''; }
  fmt(n: number)             { return (n||0).toLocaleString('en-PH',{minimumFractionDigits:2}); }
  fmtDate(d: string)         { return d ? new Date(d).toLocaleDateString('en-PH',{year:'numeric',month:'short',day:'numeric'}) : '—'; }
}