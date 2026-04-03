import { Component, OnInit, ChangeDetectorRef, Input } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../environment';

interface ScholarshipRecord {
  id: number;
  student_id: number;
  scholar_type: string;
  grantor: string;
  scholarship_amount: number;
  semester: string;
  is_active: number;
  notes: string;
  granted_by_email: string;
  revoked_at: string | null;
  revoked_by_email: string | null;
  revoke_reason: string | null;
  created_at: string;
  updated_at: string;
}

@Component({
  selector: 'app-scholarship',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './scholarship.html',
  styleUrl: './scholarship.css',
})
export class ScholarshipComponent implements OnInit {
  /** Pass student_id from a parent component or load from sessionStorage */
  @Input() studentId = 0;
  @Input() studentName = '';
  @Input() totalAssessment = 0;
  @Input() currentSemester = '';

  private apiUrl = environment.accountingApi;

  isLoading    = false;
  isSaving     = false;
  isRemoving   = false;
  errorMsg     = '';
  successMsg   = '';

  activeScholarship: ScholarshipRecord | null = null;
  history: ScholarshipRecord[] = [];

  showGrantForm = false;
  showRevokeModal = false;
  revokeReason = '';

  grantForm = {
    scholar_type:       'Full Scholarship',
    grantor:            '',
    scholarship_amount: 0,
    full_tuition:       false,
    notes:              '',
    semester:           '',
  };

  scholarTypes = [
    'Full Scholarship',
    'Partial Scholarship',
    'CHED Scholarship',
    'Government Scholarship',
    'Athletic Scholarship',
    'Academic Excellence Scholarship',
    'Financial Assistance',
    'Others',
  ];

  constructor(private http: HttpClient, private cdr: ChangeDetectorRef) {}

  ngOnInit(): void {
    if (!this.studentId) {
      const stored = sessionStorage.getItem('scholarshipStudent');
      if (stored) {
        const s = JSON.parse(stored);
        this.studentId        = s.studentId  ?? s.id ?? 0;
        this.studentName      = `${s.firstName ?? s.first_name ?? ''} ${s.lastName ?? s.last_name ?? ''}`.trim();
        this.totalAssessment  = s.totalAssessment ?? 0;
        this.currentSemester  = s.semester ?? '';
      }
    }
    this.grantForm.semester = this.currentSemester;
    if (this.studentId) this.loadScholarship();
  }

  loadScholarship(): void {
    this.isLoading = true;
    this.errorMsg  = '';
    this.http.get<any>(`${this.apiUrl}?action=get_student_scholarship&student_id=${this.studentId}`).subscribe({
      next: (res) => {
        if (res.success) {
          this.activeScholarship = res.scholarship ?? null;
          this.history           = res.history      ?? [];
        }
        this.isLoading = false;
        this.cdr.detectChanges();
      },
      error: () => { this.isLoading = false; this.errorMsg = 'Failed to load scholarship data.'; this.cdr.detectChanges(); }
    });
  }

  openGrantForm(): void {
    this.showGrantForm = true;
    this.grantForm = {
      scholar_type:       'Full Scholarship',
      grantor:            '',
      scholarship_amount: 0,
      full_tuition:       false,
      notes:              '',
      semester:           this.currentSemester,
    };
    this.successMsg = '';
    this.errorMsg   = '';
    this.cdr.detectChanges();
  }

  cancelGrant(): void {
    this.showGrantForm = false;
    this.cdr.detectChanges();
  }

  submitGrant(): void {
    if (!this.grantForm.grantor.trim()) {
      this.errorMsg = 'Grantor/source is required.'; return;
    }
    if (!this.grantForm.full_tuition && this.grantForm.scholarship_amount <= 0) {
      this.errorMsg = 'Enter a scholarship amount or select Full Tuition.'; return;
    }

    this.isSaving   = true;
    this.errorMsg   = '';
    this.successMsg = '';

    this.http.post<any>(`${this.apiUrl}?action=grant_scholarship`, {
      student_id:          this.studentId,
      scholar_type:        this.grantForm.scholar_type,
      grantor:             this.grantForm.grantor,
      scholarship_amount:  this.grantForm.full_tuition ? 0 : this.grantForm.scholarship_amount,
      full_tuition:        this.grantForm.full_tuition,
      semester:            this.grantForm.semester,
      notes:               this.grantForm.notes,
    }).subscribe({
      next: (res) => {
        this.isSaving = false;
        if (res.success) {
          this.successMsg    = res.message;
          this.showGrantForm = false;
          this.loadScholarship();
        } else {
          this.errorMsg = res.message || 'Failed to grant scholarship.';
        }
        this.cdr.detectChanges();
      },
      error: () => { this.isSaving = false; this.errorMsg = 'Server error. Please try again.'; this.cdr.detectChanges(); }
    });
  }

  openRevokeModal(): void {
    this.revokeReason  = '';
    this.showRevokeModal = true;
    this.cdr.detectChanges();
  }

  cancelRevoke(): void {
    this.showRevokeModal = false;
    this.cdr.detectChanges();
  }

  confirmRevoke(): void {
    if (!this.revokeReason.trim()) {
      this.errorMsg = 'Please enter a reason for removing the scholarship.'; return;
    }
    this.isRemoving = true;
    this.errorMsg   = '';

    this.http.post<any>(`${this.apiUrl}?action=remove_scholarship`, {
      student_id: this.studentId,
      reason:     this.revokeReason,
    }).subscribe({
      next: (res) => {
        this.isRemoving      = false;
        this.showRevokeModal = false;
        if (res.success) {
          this.successMsg = res.message;
          this.loadScholarship();
        } else {
          this.errorMsg = res.message || 'Failed to remove scholarship.';
        }
        this.cdr.detectChanges();
      },
      error: () => { this.isRemoving = false; this.errorMsg = 'Server error.'; this.cdr.detectChanges(); }
    });
  }

  fmt(n: number): string {
    return (n ?? 0).toLocaleString('en-PH', { minimumFractionDigits: 2 });
  }

  fmtDate(d: string | null): string {
    if (!d) return '—';
    return new Date(d).toLocaleDateString('en-PH', { year:'numeric', month:'short', day:'numeric', hour:'2-digit', minute:'2-digit' });
  }
}
