import { Component, OnInit, OnDestroy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { HttpClient } from '@angular/common/http';
import { Router, NavigationEnd } from '@angular/router';
import { Subscription, filter } from 'rxjs';

interface StudentProfile {
  id: string;
  dbId: number;
  firstName: string;
  lastName: string;
  email: string;
  phone: string;
  program: string;
  yearLevel: string;
  gpa: number;
  enrollmentStatus: string;
  studentType: string;
  paymentStatus: string;
  approvalStatus: string;
  dateOfBirth: string;
  address: string;
  emergencyContact: string;
  emergencyPhone: string;
  profilePicture: string;
  enrollmentDate: string;
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
    id: '', dbId: 0, firstName: '', lastName: '', email: '', phone: '',
    program: '', yearLevel: '', gpa: 0, enrollmentStatus: '', studentType: '',
    paymentStatus: '', approvalStatus: '', dateOfBirth: '', address: '',
    emergencyContact: '', emergencyPhone: '',
    profilePicture: 'https://ui-avatars.com/api/?name=Student&size=150',
    enrollmentDate: ''
  };

  constructor(
    private http: HttpClient,
    private router: Router,
    private cdr: ChangeDetectorRef
  ) {}

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

  ngOnDestroy(): void {
    this.routerSub?.unsubscribe();
  }

  loadProfile(): void {
    this.isLoading = true;
    this.errorMessage = '';
    this.cdr.detectChanges();

    const storedUser = localStorage.getItem('currentUser');
    if (!storedUser) {
      this.errorMessage = 'Not logged in.';
      this.isLoading = false;
      this.cdr.detectChanges();
      return;
    }

    const user = JSON.parse(storedUser);
    this.http.get<any>(`${this.apiUrl}?action=get_profile&user_id=${user.id}`).subscribe({
      next: (res) => {
        if (res.success) {
          this.student = res.student;
        } else {
          this.errorMessage = 'Profile not found. Please complete enrollment first.';
        }
        this.isLoading = false;
        this.cdr.detectChanges();
      },
      error: () => {
        this.errorMessage = 'Failed to load profile. Please check your connection.';
        this.isLoading = false;
        this.cdr.detectChanges();
      }
    });
  }

  getGPAStatus(): string {
    if (this.student.gpa >= 3.5) return 'Excellent';
    if (this.student.gpa >= 3.0) return 'Very Good';
    if (this.student.gpa >= 2.5) return 'Good';
    return 'Fair';
  }
}