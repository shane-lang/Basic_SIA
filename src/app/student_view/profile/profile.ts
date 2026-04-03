import { Component, OnInit, OnDestroy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { Router, NavigationEnd } from '@angular/router';
import { Subscription, filter } from 'rxjs';
import { environment } from '../../environment';

interface StudentProfile {
  // Core
  id: string; dbId: number;
  firstName: string; lastName: string; middleName: string; suffix: string;
  email: string; phone: string;
  // Academic
  program: string; yearLevel: string; studentCategory: string; studentType: string;
  strand: string; learningDelivery: string; lastSchoolAttended: string;
  gpa: number; enrollmentStatus: string; enrollmentDate: string;
  // Payment
  paymentStatus: string; paymentMethod: string; approvalStatus: string;
  // Personal
  lrnNo: string; dateOfBirth: string; sex: string; religion: string;
  age: string | number; placeOfBirth: string; citizenship: string;
  address: string; motherTongue: string; isIndigenous: boolean; psaBirthCertNo: string;
  // Special Needs
  hasSpecialNeeds: boolean; specialNeedsDetails: string;
  hasAssistiveTech: boolean; assistiveTechDetails: string;
  // Guardian
  guardianName: string; guardianAddress: string;
  emergencyContact: string; emergencyPhone: string;
  // Scholar
  isScholar: boolean; scholarType: string; scholarGrantor: string; scholarshipAmount: number;
  // Photo
  profilePicture: string;
}

@Component({
  selector: 'app-profile',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './profile.html',
  styleUrl: './profile.css',
})
export class Profile implements OnInit, OnDestroy {
  private apiUrl = environment.enrollApi;
  private routerSub!: Subscription;

  isLoading = true;
  errorMessage = '';
  // Only registrar/admin can see ultra-sensitive fields (PSA cert, LRN, religion, etc.)
  canViewSensitive = (['registrar', 'admin'].includes(
    JSON.parse(sessionStorage.getItem('currentUser') || '{}')?.role ?? ''
  ));

  student: StudentProfile = {
    id: '', dbId: 0, firstName: '', lastName: '', middleName: '', suffix: '',
    email: '', phone: '', program: '', yearLevel: '', studentCategory: '', studentType: '',
    strand: '', learningDelivery: '', lastSchoolAttended: '',
    gpa: 0, enrollmentStatus: '', enrollmentDate: '',
    paymentStatus: '', paymentMethod: '', approvalStatus: '',
    lrnNo: '', dateOfBirth: '', sex: '', religion: '', age: '', placeOfBirth: '',
    citizenship: '', address: '', motherTongue: '', isIndigenous: false, psaBirthCertNo: '',
    hasSpecialNeeds: false, specialNeedsDetails: '', hasAssistiveTech: false, assistiveTechDetails: '',
    guardianName: '', guardianAddress: '', emergencyContact: '', emergencyPhone: '',
    isScholar: false, scholarType: '', scholarGrantor: '', scholarshipAmount: 0,
    profilePicture: 'https://ui-avatars.com/api/?name=Student&size=150',
  };
  constructor(private http: HttpClient, private router: Router, private cdr: ChangeDetectorRef) {}

  get isSHS(): boolean { return (this.student.studentCategory ?? '').toUpperCase() === 'SHS'; }
  get displayYearLevel(): string {
    const yl = this.student.yearLevel ?? '';
    if (this.isSHS) {
      if (yl === '1st Year' || yl === 'Grade 11') return 'Grade 11';
      if (yl === '2nd Year' || yl === 'Grade 12') return 'Grade 12';
    }
    return yl || '—';
  }

  ngOnInit(): void {
    this.loadProfile();
    this.routerSub = this.router.events
      .pipe(filter(e => e instanceof NavigationEnd))
      .subscribe((e: any) => {
        if (e.urlAfterRedirects?.includes('/profile') || e.url?.includes('/profile')) {
          this.loadProfile();
        }
      });
  }

  ngOnDestroy(): void { this.routerSub?.unsubscribe(); }

  // ── RA 10173 Right to Access — export own personal data ──────────────────
  downloadMyData(): void {
    const exportData = {
      notice: 'Personal data export under Republic Act No. 10173 (Data Privacy Act of 2012). Right to access exercised by the data subject.',
      exportedAt: new Date().toISOString(),
      personal: {
        firstName:        this.student.firstName,
        lastName:         this.student.lastName,
        middleName:       this.student.middleName,
        email:            this.student.email,
        phone:            this.student.phone,
        dateOfBirth:      this.student.dateOfBirth,
        sex:              this.student.sex,
        address:          this.student.address,
        program:          this.student.program,
        yearLevel:        this.student.yearLevel,
        studentCategory:  this.student.studentCategory,
        studentNumber:    this.student.id,
        enrollmentStatus: this.student.enrollmentStatus,
        enrollmentDate:   this.student.enrollmentDate,
        paymentStatus:    this.student.paymentStatus,
        isScholar:        this.student.isScholar,
        scholarType:      this.student.scholarType,
      }
    };

    const blob = new Blob([JSON.stringify(exportData, null, 2)], { type: 'application/json' });
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href     = url;
    a.download = `my-data-${this.student.id}-${new Date().toISOString().slice(0,10)}.json`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
  }

  loadProfile(): void {
    this.isLoading = true; this.errorMessage = ''; this.cdr.detectChanges();
    const storedUser = sessionStorage.getItem('currentUser');
    if (!storedUser) { this.errorMessage = 'Not logged in.'; this.isLoading = false; this.cdr.detectChanges(); return; }
    const user = JSON.parse(storedUser);

    // BUG-PROFILE-01 FIX: Prefer studentDbId (students.id PK) over user.id
    // (users.id FK). studentDbId is set by dashboard.ts on first load, and by
    // Student.service.ts fetchProfile(). If it hasn't been set yet (e.g. user
    // navigated directly to /profile before visiting dashboard), we fall back
    // to user_id and let the PHP side resolve it via the students.user_id column.
    // This prevents the "Profile not found" error on direct navigation.
    const storedDbId = sessionStorage.getItem('studentDbId');
    const apiParam   = storedDbId && parseInt(storedDbId, 10) > 0
      ? `student_id=${storedDbId}`
      : `user_id=${user.id}`;

    this.http.get<any>(`${this.apiUrl}?action=get_profile&${apiParam}`).subscribe({
      next: (res) => {
        if (res.success) {
          this.student = res.student;
          // Persist studentDbId for other components (curriculum, SOA, etc.)
          if (res.student?.dbId) {
            sessionStorage.setItem('studentDbId', String(res.student.dbId));
          }
        }
        else { this.errorMessage = res.message || 'Profile not found. Please complete enrollment first.'; }
        this.isLoading = false; this.cdr.detectChanges();
      },
      error: (err) => {
        // BUG-PROFILE-02 FIX: distinguish network errors from logical failures.
        // A network/CORS/500 error arrives here; a logical failure (profile not
        // found, unenrolled) arrives in next() with success:false. This message
        // now correctly says "connection" only for actual connectivity problems.
        this.errorMessage = 'Cannot connect to server. Please check XAMPP is running.';
        this.isLoading = false; this.cdr.detectChanges();
      }
    });
  }

  get fullName(): string {
    const parts = [this.student.firstName, this.student.middleName, this.student.lastName, this.student.suffix];
    return parts.filter(p => p).join(' ');
  }

  getGPAStatus(): string {
    if (this.student.gpa >= 3.5) return 'Excellent';
    if (this.student.gpa >= 3.0) return 'Very Good';
    if (this.student.gpa >= 2.5) return 'Good';
    return 'Fair';
  }

  // ── Change Password modal ────────────────────────────────────────────────
  showCpModal   = false;
  cpCurrent     = ''; cpNew = ''; cpConfirm = '';
  cpShowCurrent = false; cpShowNew = false; cpShowConfirm = false;
  cpError       = ''; cpSuccess = ''; cpSubmitting = false;

  openCpModal(): void {
    this.showCpModal = true;
    this.cpCurrent = this.cpNew = this.cpConfirm = '';
    this.cpError = this.cpSuccess = '';
    this.cpShowCurrent = this.cpShowNew = this.cpShowConfirm = false;
    this.cdr.detectChanges();
  }

  closeCpModal(): void { this.showCpModal = false; this.cdr.detectChanges(); }

  changePassword(): void {
    this.cpError = ''; this.cpSuccess = '';
    if (!this.cpCurrent || !this.cpNew || !this.cpConfirm) {
      this.cpError = 'All fields are required.'; return;
    }
    if (this.cpNew.length < 6) {
      this.cpError = 'New password must be at least 6 characters.'; return;
    }
    if (this.cpNew !== this.cpConfirm) {
      this.cpError = 'New password and confirmation do not match.'; return;
    }
    const token = sessionStorage.getItem('token')
                  ?? localStorage.getItem('sia_student_token')
                  ?? '';
    this.cpSubmitting = true; this.cdr.detectChanges();
    this.http.post<any>(
      `${environment.authApi}?action=change_password`,
      { current_password: this.cpCurrent, new_password: this.cpNew, confirm_password: this.cpConfirm },
      { headers: { Authorization: `Bearer ${token}` } }
    ).subscribe({
      next: res => {
        this.cpSubmitting = false;
        if (res.success) {
          this.cpSuccess = res.message;
          this.cpCurrent = this.cpNew = this.cpConfirm = '';
        } else {
          this.cpError = res.message || 'Password change failed.';
        }
        this.cdr.detectChanges();
      },
      error: () => {
        this.cpSubmitting = false;
        this.cpError = 'Connection error. Please try again.';
        this.cdr.detectChanges();
      }
    });
  }
}