import { Component, OnInit, OnDestroy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { HttpClient } from '@angular/common/http';
import { Router, NavigationEnd } from '@angular/router';
import { Subscription, filter } from 'rxjs';

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
  imports: [CommonModule],
  templateUrl: './profile.html',
  styleUrl: './profile.css',
})
export class Profile implements OnInit, OnDestroy {
  private apiUrl = 'http://localhost/sia-api/enrollment.php';
  private routerSub!: Subscription;

  isLoading = true;
  errorMessage = '';

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

  loadProfile(): void {
    this.isLoading = true; this.errorMessage = ''; this.cdr.detectChanges();
    const storedUser = sessionStorage.getItem('currentUser');
    if (!storedUser) { this.errorMessage = 'Not logged in.'; this.isLoading = false; this.cdr.detectChanges(); return; }
    const user = JSON.parse(storedUser);
    this.http.get<any>(`${this.apiUrl}?action=get_profile&user_id=${user.id}`).subscribe({
      next: (res) => {
        if (res.success) { this.student = res.student; }
        else { this.errorMessage = 'Profile not found. Please complete enrollment first.'; }
        this.isLoading = false; this.cdr.detectChanges();
      },
      error: () => {
        this.errorMessage = 'Failed to load profile. Please check your connection.';
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
}