import { Component, OnInit, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../environment';
import { AuthService } from '../../services/auth';

interface PendingStudent {
  id: number;
  studentNumber: string;
  firstName: string;
  lastName: string;
  middleName: string;
  fullName: string;
  email: string;
  phone: string;
  program: string;
  yearLevel: string;
  semester: string;
  studentType: string;
  studentCategory: string;
  enrollmentStatus: string;
  registrarConfirmed: string;
  registrarNotes: string;
  lrnNo: string;
  sex: string;
  dateOfBirth: string;
  address: string;
  strand: string;
  lastSchoolAttended: string;
  registeredAt: string;
  // ── Payment & Assessment ──
  totalAssessment: number;
  subtotal: number;
  discount: number;
  installmentFee: number;
  totalPaid: number;
  balance: number;
  tuitionFee: number;
  paymentStatus: string;
  paymentPlan: string;
  accountingNotes: string;
  // ── Scholarship ──
  isScholar: number;
  scholarType: string;
  scholarGrantor: string;
  scholarDiscount: number;
  // ── Guardian contact ──
  guardianEmail: string;
}

interface EnrolledSubject {
  course_code: string;
  course_name: string;
  units: number;
  status: string;
}

// ── COE data returned from coe_get_pending ────────────────────────────────
interface CoeRecord {
  id: number;
  student_id: number;
  control_number: string;
  purpose: string;
  copies: number;
  status: string;
  registrar_notes: string;
  approved_by: number;
  approved_at: string;
  requested_at: string;
  // joined fields
  first_name: string;
  last_name: string;
  student_number: string;
  program: string;
  year_level: string;
  semester: string;
  student_category: string;
  enrollment_status: string;
  approved_by_name: string;
}

@Component({
  selector: 'app-pending-registrations',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './pending-registrations.html',
  styleUrl: './pending-registrations.css',
})
export class PendingRegistrationsComponent implements OnInit {
  private api = environment.registrarApi;

  students: PendingStudent[] = [];
  selected: PendingStudent | null = null;
  isLoading     = false;
  searchQuery   = '';
  page          = 1;
  limit         = 20;
  total         = 0;
  private searchTimeout: any = null;

  // Enrolled subjects for selected student
  enrolledSubjects: EnrolledSubject[] = [];
  isLoadingSubjects = false;

  // Action modal
  showModal     = false;
  modalAction   = '';   // 'confirm' | 'reject'
  modalNotes    = '';
  isSubmitting  = false;

  notifications: { id: number; type: string; message: string }[] = [];
  private _notifId = 0;

  // ── COE auto-display after approval ─────────────────────────────────────
  justApprovedCoe: CoeRecord | null = null;      // set right after confirm
  showCoePanel    = false;                        // toggle the inline COE panel
  isLoadingCoe    = false;
  isGeneratingPdf = false;

  constructor(private http: HttpClient, private cdr: ChangeDetectorRef, private auth: AuthService) {}

  ngOnInit(): void { setTimeout(() => this.load(), 100); }

  load(): void {
    this.isLoading = true;
    const qs = new URLSearchParams({
      action: 'get_pending_registrations',
      page:   String(this.page),
      limit:  String(this.limit),
      q:      this.searchQuery,
    });
    this.http.get<any>(`${this.api}?${qs}`).subscribe({
      next: (res) => {
        this.students  = res.students ?? [];
        this.total     = res.total    ?? 0;
        this.isLoading = false;
        this.cdr.detectChanges();
      },
      error: () => { this.isLoading = false; this.notify('error', 'Failed to load registrations.'); }
    });
  }

  get totalPages(): number { return Math.ceil(this.total / this.limit) || 1; }
  goToPage(p: number): void { if (p < 1 || p > this.totalPages) return; this.page = p; this.load(); }
  search(): void { this.page = 1; this.load(); }
  clearSearch(): void { this.searchQuery = ''; this.page = 1; this.load(); }

  onSearchInput(): void {
    clearTimeout(this.searchTimeout);
    this.searchTimeout = setTimeout(() => { this.page = 1; this.load(); }, 350);
  }

  selectStudent(s: PendingStudent): void {
    if (this.selected?.id === s.id) {
      this.selected         = null;
      this.enrolledSubjects = [];
      this.justApprovedCoe  = null;
      this.showCoePanel     = false;
      this.cdr.detectChanges();
      return;
    }
    this.selected         = s;
    this.enrolledSubjects = [];
    this.justApprovedCoe  = null;
    this.showCoePanel     = false;
    this.cdr.detectChanges();
    this.loadEnrolledSubjects(s.id);
  }

  loadEnrolledSubjects(studentId: number): void {
    this.isLoadingSubjects = true;
    // FIX REG-SUBJECTS-01: Pass semester so api.php scopes query to current sem only
    const params: Record<string, string> = {
      action:     'enrollments',
      student_id: String(studentId),
    };
    if (this.selected?.semester) {
      params['semester'] = this.selected.semester;
    }
    const qs = new URLSearchParams(params);
    this.http.get<any>(`${environment.api}?${qs}`).subscribe({
      next: (res) => {
        const rows: any[] = Array.isArray(res) ? res : (res.enrollments ?? []);
        this.enrolledSubjects = rows.map((r: any) => ({
          course_code: r.course_code ?? r.code ?? '',
          course_name: r.course_name ?? r.name ?? '',
          units:       Number(r.units ?? r.credits ?? 0),
          status:      r.status ?? '',
        }));
        this.isLoadingSubjects = false;
        this.cdr.detectChanges();
      },
      error: () => {
        this.isLoadingSubjects = false;
        this.cdr.detectChanges();
      },
    });
  }

  openAction(action: 'confirm' | 'reject'): void {
    this.modalAction = action;
    this.modalNotes  = '';
    this.showModal   = true;
    this.cdr.detectChanges();
  }
  closeModal(): void { this.showModal = false; this.cdr.detectChanges(); }

  submitAction(): void {
    if (!this.selected) return;
    if (this.modalAction === 'reject' && !this.modalNotes.trim()) {
      this.notify('error', 'Please provide a reason for rejection.'); return;
    }
    this.isSubmitting = true;
    const endpoint = this.modalAction === 'confirm' ? 'confirm_registration' : 'reject_registration';
    const confirmedStudentId = this.selected.id;
    const confirmedStudentName = `${this.selected.firstName} ${this.selected.lastName}`;

    this.http.post<any>(`${this.api}?action=${endpoint}`, {
      student_id: confirmedStudentId,
      notes: this.modalNotes,
    }).subscribe({
      next: (res) => {
        this.isSubmitting = false;
        if (res.success) {
          this.notify('success', res.message);
          this.showModal = false;

          // ── After confirm: auto-fetch the newly created COE ──────────────
          if (this.modalAction === 'confirm') {
            this.fetchCoeAfterApproval(confirmedStudentId, confirmedStudentName);
          } else {
            this.selected = null;
            this.load();
          }
        } else {
          this.notify('error', res.message || 'Action failed.');
        }
        this.cdr.detectChanges();
      },
      error: () => { this.isSubmitting = false; this.notify('error', 'Connection error.'); }
    });
  }

  // ── Fetch the auto-approved COE right after confirm_registration ─────────
  fetchCoeAfterApproval(studentId: number, studentName: string): void {
    this.isLoadingCoe = true;
    this.cdr.detectChanges();

    this.http.get<any>(
      `${this.api}?action=coe_get_pending&status=Approved&student_id=${studentId}`
    ).subscribe({
      next: (res) => {
        this.isLoadingCoe = false;
        const records: CoeRecord[] = res.requests ?? [];
        const coe = records.find(r => r.student_id === studentId) ?? null;

        if (coe) {
          this.justApprovedCoe = coe;
          this.showCoePanel    = true;
          this.notify('success',
            `✅ Enrollment confirmed! COE #${coe.control_number} auto-issued for ${studentName}.`
          );
        } else {
          this.notify('success', `✅ Enrollment confirmed for ${studentName}. COE will appear in COE Generator.`);
        }
        // Refresh list without losing the panel
        this.load();
        // ── Auto-send enrollment report to parent/guardian ───────────────
        // Delay 3s to allow backend autoEnrollAll() to finish inserting
        // enrollment rows before notify.php queries for enrolled subjects.
        setTimeout(() => this.autoSendEnrollmentReport(studentId, studentName), 3000);
        this.cdr.detectChanges();
      },
      error: () => {
        this.isLoadingCoe = false;
        this.notify('success', `✅ Enrollment confirmed. Refresh COE Generator to view the certificate.`);
        this.load();
        // ── Auto-send enrollment report even if COE fetch failed ─────────
        setTimeout(() => this.autoSendEnrollmentReport(studentId, studentName), 3000);
        this.cdr.detectChanges();
      },
    });
  }

  // ── Silently send enrollment report (no confirm() prompt) ────────────────
  // Called automatically after approval — no user interaction needed.
  autoSendEnrollmentReport(studentId: number, studentName: string): void {
    if (this.isSendingEnrollReport[studentId]) return;
    this.isSendingEnrollReport[studentId] = true;
    this.cdr.detectChanges();

    this.http.post<any>(`${environment.notifyApi}?action=send_enrollment_report`, {
      student_id: studentId,
    }).subscribe({
      next: (res) => {
        this.isSendingEnrollReport[studentId] = false;
        if (res.success) {
          this.notify('success',
            `📧 Enrollment report sent to: ${(res.recipients || []).map((r: any) => r.email).join(', ')}`
          );
        } else {
          this.notify('warning',
            `⚠️ Enrollment approved but report not sent: ${res.message || 'Check guardian email.'}`
          );
        }
        this.cdr.detectChanges();
      },
      error: () => {
        this.isSendingEnrollReport[studentId] = false;
        this.notify('warning', '⚠️ Enrollment approved but report email could not be sent.');
        this.cdr.detectChanges();
      },
    });
  }

  // ── Generate COE PDF from the just-approved record ───────────────────────
  generateCoeFromApproved(): void {
    if (!this.justApprovedCoe) return;
    this.isGeneratingPdf = true;
    this.cdr.detectChanges();

    this.http.get<any>(
      `${this.api}?action=coe_detail_by_student&student_id=${this.justApprovedCoe.student_id}`
    ).subscribe({
      next: async (res) => {
        this.isGeneratingPdf = false;
        if (!res.success) {
          this.notify('error', res.message || 'Could not load COE data for PDF.');
          this.cdr.detectChanges();
          return;
        }
        try {
          const jsPDF = await this.loadJsPDF();
          this.buildCoePdf(jsPDF, res.coe);
          this.notify('success', `📄 COE PDF downloaded for ${this.justApprovedCoe!.first_name} ${this.justApprovedCoe!.last_name}`);
        } catch {
          this.notify('error', 'PDF generation failed. Try again from the COE Generator.');
        }
        this.cdr.detectChanges();
      },
      error: () => {
        this.isGeneratingPdf = false;
        this.notify('error', 'Network error. Try generating from COE Generator.');
        this.cdr.detectChanges();
      },
    });
  }

  dismissCoePanel(): void {
    this.justApprovedCoe = null;
    this.showCoePanel    = false;
    this.selected        = null;
    this.cdr.detectChanges();
  }

  // ── Minimal COE PDF builder (mirrors coe-generator logic) ────────────────
  private async loadJsPDF(): Promise<any> {
    return new Promise((resolve, reject) => {
      if ((window as any).jspdf?.jsPDF) { resolve((window as any).jspdf.jsPDF); return; }
      const s = document.createElement('script');
      s.src = 'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js';
      s.onload = () => {
        const j = (window as any).jspdf?.jsPDF;
        j ? resolve(j) : reject(new Error('jsPDF not found'));
      };
      s.onerror = () => reject(new Error('Failed to load jsPDF'));
      document.head.appendChild(s);
    });
  }

  private buildCoePdf(jsPDF: any, d: any): void {
    const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' });
    const W = 210, ML = 20, MR = 20, CW = W - ML - MR;
    const B  = () => doc.setFont('helvetica', 'bold');
    const N  = () => doc.setFont('helvetica', 'normal');
    const sz = (n: number) => doc.setFontSize(n);
    const lw = (n: number) => doc.setLineWidth(n);
    const tx = (t: string, x: number, y: number) => doc.text(t, x, y);
    const txC = (t: string, x: number, y: number) => doc.text(t, x, y, { align: 'center' });
    const txR = (t: string, x: number, y: number) => doc.text(t, x, y, { align: 'right' });

    let y = 20;

    // Header
    B(); sz(14); txC('ST. BENILDE BASIC SYSTEM', W / 2, y); y += 7;
    N(); sz(10); txC("REGISTRAR'S OFFICE", W / 2, y); y += 5;
    lw(0.5); doc.line(ML, y, W - MR, y); y += 6;

    B(); sz(13); txC('CERTIFICATE OF ENROLLMENT', W / 2, y); y += 10;

    // Control number
    N(); sz(9);
    tx(`Control No.: ${d.control_number ?? '—'}`, ML, y);
    txR(`Date Issued: ${d.approved_at ? new Date(d.approved_at).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' }) : '—'}`, W - MR, y);
    y += 8;

    // Body text
    N(); sz(11);
    const studentName = `${d.last_name ?? ''}, ${d.first_name ?? ''} ${d.middle_name ?? ''}`.trim();
    const bodyLines = doc.splitTextToSize(
      `This is to certify that ${studentName} with Student No. ${d.student_number ?? '—'} ` +
      `is officially enrolled at this institution for the ${d.semester ?? '—'}, ${d.academic_year ?? ''}.`,
      CW
    );
    doc.text(bodyLines, ML, y); y += bodyLines.length * 6 + 4;

    // Academic info
    const fields: [string, string][] = [
      ['Program / Course', d.program ?? '—'],
      ['Year Level', d.year_level ?? '—'],
      ['Student Type', d.student_type ?? '—'],
      ['Semester / AY', `${d.semester ?? '—'} / ${d.academic_year ?? '—'}`],
    ];
    lw(0.3); doc.setFillColor(245, 247, 250);
    doc.roundedRect(ML, y - 4, CW, fields.length * 8 + 4, 2, 2, 'FD');
    B(); sz(9);
    fields.forEach(([label, val]) => {
      tx(label + ':', ML + 4, y + 2); N(); tx(val, ML + 55, y + 2); B(); y += 8;
    });
    y += 4;

    // Subjects
    const subjects: any[] = d.subjects ?? [];
    if (subjects.length > 0) {
      B(); sz(10); tx('Enrolled Subjects:', ML, y); y += 5; N(); sz(8.5);
      subjects.forEach((s: any, i: number) => {
        tx(`${i + 1}. ${s.course_code ?? ''} — ${s.course_name ?? ''}  (${s.units ?? 0} units)`, ML + 3, y);
        y += 5.5;
      });
      y += 3;
    }

    // Purpose
    B(); sz(9); tx('Purpose:', ML, y); N(); tx(d.purpose ?? 'General Purpose', ML + 25, y); y += 10;

    // Signature
    lw(0.3); doc.line(ML, y + 20, ML + 60, y + 20);
    B(); sz(9); tx(d.issued_by ?? 'Registrar', ML, y + 24);
    N(); sz(8); tx('Registrar / Authorized Signatory', ML, y + 29);

    const fname = `COE_${d.student_number ?? 'student'}_${d.control_number ?? 'draft'}.pdf`;
    doc.save(fname);
  }

  // ── Notifications ────────────────────────────────────────────────────────
  notify(type: string, message: string): void {
    const id = this._notifId++;
    this.notifications.push({ id, type, message });
    setTimeout(() => {
      this.notifications = this.notifications.filter(n => n.id !== id);
      this.cdr.detectChanges();
    }, 5000);
  }

  // ── Status helpers ───────────────────────────────────────────────────────
  statusClass(s: string): string {
    const m: Record<string, string> = { Confirmed: 'badge-green', Pending: 'badge-yellow', Rejected: 'badge-red' };
    return m[s] ?? 'badge-gray';
  }

  paymentStatusClass(s: string): string {
    const ps = (s ?? '').toLowerCase();
    if (ps === 'paid') return 'pay-paid';
    if (ps === 'partial' || ps === 'partially paid') return 'pay-partial';
    if (ps === 'free' || ps === 'scholar') return 'pay-free';
    return 'pay-unpaid';
  }

  paymentBannerClass(s: PendingStudent): string {
    return this.isPaymentCleared(s) ? 'pay-partial' : 'pay-unpaid';
  }

  paymentStatusLabel(s: PendingStudent): string {
    const ps = (s.paymentStatus ?? '').toLowerCase();
    if (ps === 'paid') return '✓ Fully Paid';
    if (ps === 'free' || ps === 'scholar') return '🎓 Free (Scholar)';
    if (s.isScholar && (!ps || ps === 'unpaid')) return '🎓 Scholar (Pending)';
    if ((s.paymentPlan ?? '').toLowerCase() === 'installment' && s.totalPaid > 0) {
      return '◑ DP Paid (Installment)';
    }
    if (ps === 'partial' || ps === 'partially paid') return '◑ Partially Paid';
    return '✕ Unpaid';
  }

  isPaymentCleared(s: PendingStudent): boolean {
    const paid    = Number(s.totalPaid) || 0;
    const balance = Number(s.balance)   || 0;
    const ps      = (s.paymentStatus ?? '').toLowerCase();
    if (ps === 'paid' || balance <= 0) return true;
    if (s.isScholar === 1 && balance <= 0) return true;
    if (paid > 0) return true;
    return false;
  }

  fmt(n: number): string {
    return (n ?? 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  coveragePct(s: PendingStudent): number {
    if (!s.totalAssessment) return s.isScholar ? 100 : 0;
    return Math.min(100, Math.round((s.scholarDiscount / s.totalAssessment) * 100));
  }

  totalEnrolledUnits(): number {
    return this.enrolledSubjects.reduce((sum, s) => sum + (s.units || 0), 0);
  }

  // ── Guardian email inline edit ───────────────────────────────────────────
  editingGuardianEmail  = false;
  tempGuardianEmail     = '';
  isSavingGuardianEmail = false;

  // ── Send enrollment report ───────────────────────────────────────────────
  isSendingEnrollReport: { [key: number]: boolean } = {};

  sendEnrollmentReport(studentId: number, studentName: string): void {
    if (this.isSendingEnrollReport[studentId]) return;
    if (!confirm(`Send Enrollment Report to the parent/guardian of ${studentName}?\n\nMake sure a guardian email is saved in their record.`)) return;

    this.isSendingEnrollReport[studentId] = true;
    this.cdr.detectChanges();

    this.http.post<any>(`${environment.notifyApi}?action=send_enrollment_report`, {
      student_id: studentId,
    }).subscribe({
      next: (res) => {
        this.isSendingEnrollReport[studentId] = false;
        if (res.success) {
          this.notify('success',
            `📧 Enrollment report sent to: ${(res.recipients || []).map((r: any) => r.email).join(', ')}`
          );
        } else {
          this.notify('error',
            `Failed to send enrollment report: ${res.message || 'Unknown error. Check guardian email.'}`
          );
        }
        this.cdr.detectChanges();
      },
      error: () => {
        this.isSendingEnrollReport[studentId] = false;
        this.notify('error', 'Network error. Could not send enrollment report.');
        this.cdr.detectChanges();
      }
    });
  }

  startEditGuardianEmail(): void {
    this.tempGuardianEmail    = this.selected?.guardianEmail ?? '';
    this.editingGuardianEmail = true;
    this.cdr.detectChanges();
  }

  cancelEditGuardianEmail(): void {
    this.editingGuardianEmail = false;
    this.tempGuardianEmail    = '';
    this.cdr.detectChanges();
  }

  saveGuardianEmail(): void {
    if (!this.selected) return;
    const email = this.tempGuardianEmail.trim();
    if (!email || !/^[^\@\s]+@[^\@\s]+\.[^\@\s]+$/.test(email)) {
      this.notify('error', 'Please enter a valid email address.'); return;
    }
    this.isSavingGuardianEmail = true;
    this.cdr.detectChanges();
    this.http.post<any>(`${this.api}?action=update_guardian_email`, {
      student_id:     this.selected.id,
      guardian_email: email,
    }).subscribe({
      next: (res) => {
        this.isSavingGuardianEmail = false;
        if (res.success) {
          this.selected!.guardianEmail = email;
          const idx = this.students.findIndex(s => s.id === this.selected!.id);
          if (idx !== -1) this.students[idx].guardianEmail = email;
          this.editingGuardianEmail = false;
          this.notify('success', 'Guardian email saved.');
        } else {
          this.notify('error', res.message || 'Could not save guardian email.');
        }
        this.cdr.detectChanges();
      },
      error: () => {
        this.isSavingGuardianEmail = false;
        this.notify('error', 'Network error. Could not save guardian email.');
        this.cdr.detectChanges();
      }
    });
  }

  formatDate(d: string): string {
    if (!d) return '—';
    return new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' });
  }
}