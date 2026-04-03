import { Component, OnInit, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../environment';

interface ScholarshipApplication {
  id: number;
  student_id: number;
  student_number: string;
  first_name: string;
  last_name: string;
  program: string;
  year_level: string;
  student_category: string;
  enrollment_status: string;
  payment_status: string;
  payment_plan: string;
  total_assessment: number;
  scholar_type: string;
  grantor: string;
  scholarship_amount: number;
  semester: string;
  status: 'pending' | 'approved' | 'rejected' | 'superseded';
  notes: string;
  granted_by_email: string;
  reviewed_by_email: string;
  reviewed_at: string | null;
  reject_reason: string | null;
  created_at: string;
}

interface PreApproval {
  id: number;
  claim_code: string;
  scholar_type: string;
  grantor: string;
  notes: string;
  semester: string;
  is_used: number;
  is_revoked: number;
  used_by_student_id: number | null;
  used_at: string | null;
  revoked_at: string | null;
  revoke_reason: string | null;
  created_by_email: string;
  created_at: string;
  student_number: string | null;
  first_name: string | null;
  last_name: string | null;
}

@Component({
  selector: 'app-pending-scholarships',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './pending-scholarships.html',
  styleUrl: './pending-scholarships.css',
})
export class PendingScholarshipsComponent implements OnInit {
  private apiUrl = environment.accountingApi;

  activeTab: 'pending' | 'approved' | 'rejected' = 'pending';
  isLoading   = false;
  isProcessing = false;
  errorMsg    = '';
  successMsg  = '';

  scholarships: ScholarshipApplication[] = [];
  searchQuery = '';

  // Modal
  showModal     = false;
  modalAction: 'approve' | 'reject' = 'approve';
  selected: ScholarshipApplication | null = null;
  modalNotes    = '';
  rejectReason  = '';
  overrideAmount: number | null = null;
  fullTuition   = false;

  // ── Claim Codes (pre-approvals) ──────────────────────────
  mainTab: 'applications' | 'claim-codes' = 'applications';
  preapprovals: PreApproval[]            = [];
  preapprovalTab: 'active' | 'used' | 'all' = 'active';
  isLoadingCodes = false;
  showCreateForm = false;
  isCreating     = false;
  newScholarType = 'Full Scholarship';
  newGrantor     = '';
  newNotes       = '';
  newSemester    = '';
  createdCode    = '';
  showRevokeModal  = false;
  revokeTarget: PreApproval | null = null;
  revokeReason     = '';
  isRevoking       = false;

  scholarTypes = [
    'Full Scholarship', 'CHED Scholarship', 'TESDA Scholarship',
    'Local Government Unit (LGU) Scholarship', 'School-Based Scholarship',
    'Private Scholarship / Foundation', 'Sibling Discount',
    'Faculty/Staff Dependent Discount', 'Other',
  ];

  constructor(private http: HttpClient, private cdr: ChangeDetectorRef) {}

  ngOnInit(): void {
    this.load();
  }

  load(): void {
    this.isLoading = true;
    this.errorMsg  = '';
    this.http.get<any>(`${this.apiUrl}?action=get_pending_scholarships&status=${this.activeTab}`).subscribe({
      next: (res) => {
        this.isLoading    = false;
        this.scholarships = res?.scholarships ?? [];
        this.cdr.detectChanges();
      },
      error: () => {
        this.isLoading = false;
        this.errorMsg  = 'Failed to load scholarship applications.';
        this.cdr.detectChanges();
      }
    });
  }

  switchTab(tab: 'pending' | 'approved' | 'rejected'): void {
    this.activeTab  = tab;
    this.searchQuery = '';
    this.successMsg  = '';
    this.load();
  }

  get filtered(): ScholarshipApplication[] {
    const q = this.searchQuery.toLowerCase();
    return this.scholarships.filter(s =>
      !q ||
      (s.first_name + ' ' + s.last_name).toLowerCase().includes(q) ||
      s.student_number.toLowerCase().includes(q) ||
      s.scholar_type.toLowerCase().includes(q) ||
      s.grantor.toLowerCase().includes(q)
    );
  }

  openApprove(s: ScholarshipApplication): void {
    this.selected        = s;
    this.modalAction     = 'approve';
    this.modalNotes      = '';
    this.rejectReason    = '';
    this.overrideAmount  = s.scholarship_amount > 0 ? s.scholarship_amount : null;
    this.fullTuition     = s.scholarship_amount >= s.total_assessment && s.total_assessment > 0;
    this.showModal       = true;
    this.cdr.detectChanges();
  }

  openReject(s: ScholarshipApplication): void {
    this.selected     = s;
    this.modalAction  = 'reject';
    this.modalNotes   = '';
    this.rejectReason = '';
    this.showModal    = true;
    this.cdr.detectChanges();
  }

  closeModal(): void {
    this.showModal = false;
    this.selected  = null;
    this.cdr.detectChanges();
  }

  confirm(): void {
    if (!this.selected) return;
    if (this.modalAction === 'reject' && !this.rejectReason.trim()) return;

    this.isProcessing = true;

    if (this.modalAction === 'approve') {
      const payload: any = {
        scholarship_id:  this.selected.id,
        student_id:      this.selected.student_id,
        notes:           this.modalNotes,
        full_tuition:    this.fullTuition,
      };
      if (!this.fullTuition && this.overrideAmount && this.overrideAmount > 0) {
        payload.override_amount = this.overrideAmount;
      }
      this.http.post<any>(`${this.apiUrl}?action=approve_scholarship`, payload).subscribe({
        next: (res) => {
          this.isProcessing = false;
          if (res?.success) {
            this.successMsg = res.message;
            this.closeModal();
            this.load();
          } else {
            this.errorMsg = res?.message || 'Approval failed.';
          }
          this.cdr.detectChanges();
        },
        error: () => { this.isProcessing = false; this.errorMsg = 'Server error.'; this.cdr.detectChanges(); }
      });
    } else {
      this.http.post<any>(`${this.apiUrl}?action=reject_scholarship`, {
        scholarship_id: this.selected.id,
        student_id:     this.selected.student_id,
        reason:         this.rejectReason,
      }).subscribe({
        next: (res) => {
          this.isProcessing = false;
          if (res?.success) {
            this.successMsg = res.message;
            this.closeModal();
            this.load();
          } else {
            this.errorMsg = res?.message || 'Rejection failed.';
          }
          this.cdr.detectChanges();
        },
        error: () => { this.isProcessing = false; this.errorMsg = 'Server error.'; this.cdr.detectChanges(); }
      });
    }
  }

  coveragePercent(s: ScholarshipApplication): number {
    if (!s.total_assessment || !s.scholarship_amount) return 0;
    return Math.min(100, Math.round((s.scholarship_amount / s.total_assessment) * 100));
  }

  get effectiveAmount(): number {
    if (!this.selected) return 0;
    if (this.fullTuition) return this.selected.total_assessment;
    return this.overrideAmount ?? this.selected.scholarship_amount ?? 0;
  }

  get effectiveNetTotal(): number {
    if (!this.selected) return 0;
    return Math.max(0, this.selected.total_assessment - this.effectiveAmount);
  }

  isFullCoverage(s: ScholarshipApplication): boolean {
    return s.scholarship_amount >= s.total_assessment && s.total_assessment > 0;
  }

  fmt(n: number): string {
    return (n ?? 0).toLocaleString('en-PH', { minimumFractionDigits: 2 });
  }

  fmtDate(d: string | null): string {
    if (!d) return '—';
    return new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' });
  }
  // ── Claim Codes Methods ──────────────────────────────────
  switchMainTab(tab: 'applications' | 'claim-codes'): void {
    this.mainTab   = tab;
    this.createdCode = '';
    if (tab === 'claim-codes') this.loadCodes();
    this.cdr.detectChanges();
  }

  loadCodes(): void {
    this.isLoadingCodes = true;
    this.http.get<any>(`${this.apiUrl}?action=get_scholarship_preapprovals&status=${this.preapprovalTab}`).subscribe({
      next: (res) => {
        this.preapprovals   = res.preapprovals ?? [];
        this.isLoadingCodes = false;
        this.cdr.detectChanges();
      },
      error: () => { this.isLoadingCodes = false; this.cdr.detectChanges(); }
    });
  }

  switchCodeTab(tab: 'active' | 'used' | 'all'): void {
    this.preapprovalTab = tab;
    this.loadCodes();
  }

  openCreate(): void {
    this.showCreateForm = true;
    this.newScholarType = 'Full Scholarship';
    this.newGrantor = this.newNotes = this.newSemester = this.createdCode = '';
    this.cdr.detectChanges();
  }

  submitCreate(): void {
    if (!this.newScholarType) return;
    this.isCreating = true;
    this.http.post<any>(`${this.apiUrl}?action=create_scholarship_preapproval`, {
      scholar_type: this.newScholarType, grantor: this.newGrantor,
      notes: this.newNotes, semester: this.newSemester,
    }).subscribe({
      next: (res) => {
        this.isCreating = false;
        if (res.success) {
          this.createdCode    = res.claim_code;
          this.showCreateForm = false;
          this.successMsg     = 'Code generated: ' + res.claim_code;
          this.loadCodes();
        } else { this.errorMsg = res.message || 'Failed.'; }
        this.cdr.detectChanges();
      },
      error: () => { this.isCreating = false; this.errorMsg = 'Server error.'; this.cdr.detectChanges(); }
    });
  }

  openRevoke(p: PreApproval): void {
    this.revokeTarget = p; this.revokeReason = ''; this.showRevokeModal = true;
    this.cdr.detectChanges();
  }

  confirmRevoke(): void {
    if (!this.revokeTarget) return;
    this.isRevoking = true;
    this.http.post<any>(`${this.apiUrl}?action=revoke_scholarship_preapproval`, {
      id: this.revokeTarget.id, reason: this.revokeReason || 'Revoked by accounting',
    }).subscribe({
      next: (res) => {
        this.isRevoking = false; this.showRevokeModal = false; this.revokeTarget = null;
        if (res.success) { this.successMsg = 'Code revoked.'; this.loadCodes(); }
        else { this.errorMsg = res.message || 'Revoke failed.'; }
        this.cdr.detectChanges();
      },
      error: () => { this.isRevoking = false; this.errorMsg = 'Server error.'; this.cdr.detectChanges(); }
    });
  }

  copyCode(code: string): void {
    navigator.clipboard.writeText(code).then(() => { this.successMsg = 'Copied: ' + code; this.cdr.detectChanges(); });
  }


}