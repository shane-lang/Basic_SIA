import { Component, ChangeDetectorRef } from '@angular/core';
import { CommonModule, isPlatformBrowser } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { MatButtonModule } from '@angular/material/button';
import { MatInputModule } from '@angular/material/input';
import { MatCardModule } from '@angular/material/card';
import { MatFormFieldModule } from '@angular/material/form-field';
import { PLATFORM_ID, inject } from '@angular/core';

@Component({
  selector: 'app-login',
  standalone: true,
  imports: [
    CommonModule, FormsModule,
    MatButtonModule, MatInputModule, MatCardModule, MatFormFieldModule
  ],
  templateUrl: './login.html',
  styleUrls: ['./login.css']
})
export class LoginComponent {
  private apiUrl        = 'http://localhost/sia-api/enrollment.php';
  private authUrl       = 'http://localhost/sia-api/auth.php';
  private platformId    = inject(PLATFORM_ID);

  // ── Login ──────────────────────────────────────────────────
  email         = '';
  password      = '';
  errorMessage  = '';
  successMessage = '';
  loading       = false;

  // ── Enrollment Modal ───────────────────────────────────────
  showModal     = false;
  modalStep: 'program-select' | 'payment-method' | 'registration' | 'account' | 'done' = 'program-select';
  isNewStudent  = true;  // Modal is for new students only
  selectedProgram: string | null = null;
  selectedPaymentMethod: 'gcash' | 'cash' | null = null;
  isSubmitting  = false;
  modalError    = '';
  createdEmail  = '';   // show after done
  createdPassword = ''; // show after done

  programs = [
    { id: 'IT',  name: 'BS Information Technology', code: 'IT',  description: 'Programming, web development, and IT systems' },
    { id: 'CS',  name: 'BS Computer Science',        code: 'CS',  description: 'Advanced computer science and algorithms' },
    { id: 'BIS', name: 'BS Information Systems',     code: 'BIS', description: 'Business and IT integration' },
    { id: 'CE',  name: 'BS Civil Engineering',        code: 'CE',  description: 'Infrastructure and construction' }
  ];

  regForm = {
    firstName: '', lastName: '', email: '', phone: '',
    dateOfBirth: '', address: '', emergencyContact: '', emergencyPhone: ''
  };

  accountForm = {
    username: '', password: '', confirmPassword: ''
  };

  constructor(
    private http: HttpClient,
    private router: Router,
    private cdr: ChangeDetectorRef
  ) {}

  // ── Login ──────────────────────────────────────────────────
  login(): void {
    if (!this.email || !this.password) {
      this.errorMessage = 'Please enter email and password';
      return;
    }
    this.loading = true;
    this.errorMessage = '';
    this.successMessage = '';

    this.http.post<any>(this.authUrl, { email: this.email, password: this.password }).subscribe({
      next: (res) => {
        if (res.success) {
          this.successMessage = 'Login successful! Redirecting...';
          if (isPlatformBrowser(this.platformId)) {
            localStorage.setItem('currentUser', JSON.stringify(res.user));
            localStorage.setItem('token', res.token);
          }
          setTimeout(() => this.redirectByRole(res.user.role), 1000);
        } else {
          this.errorMessage = res.message || 'Login failed';
          this.loading = false;
          this.cdr.detectChanges();
        }
      },
      error: () => {
        this.errorMessage = 'Connection error. Make sure XAMPP is running.';
        this.loading = false;
        this.cdr.detectChanges();
      }
    });
  }

  private redirectByRole(role: string): void {
    const routes: { [key: string]: string } = {
      student: '/student', admin: '/admin',
      accounting: '/accounting', registrar: '/registrar', faculty: '/admin'
    };
    this.router.navigate([routes[role] || '/login']);
  }

  // ── Modal open / close ─────────────────────────────────────
  openModal(): void {
    this.showModal      = true;
    this.modalStep      = 'program-select';  // New student only
    this.selectedProgram = null;
    this.selectedPaymentMethod = null;
    this.modalError     = '';
    this.regForm        = { firstName: '', lastName: '', email: '', phone: '', dateOfBirth: '', address: '', emergencyContact: '', emergencyPhone: '' };
    this.accountForm    = { username: '', password: '', confirmPassword: '' };
    this.cdr.detectChanges();
  }

  closeModal(): void {
    if (this.isSubmitting) return;
    this.showModal = false;
    this.cdr.detectChanges();
  }

  // ── Modal navigation ───────────────────────────────────────
  // selectType removed — modal is for new students only, isNewStudent always true
  selectType(type: 'new' | 'existing'): void { this.isNewStudent = true; this.modalStep = 'program-select'; this.cdr.detectChanges(); }

  selectProgram(id: string): void {
    this.selectedProgram = id;
    this.modalStep       = 'payment-method';
    this.modalError      = '';
    this.cdr.detectChanges();
  }

  selectPaymentMethod(method: 'gcash' | 'cash'): void {
    this.selectedPaymentMethod = method;
    this.modalStep             = 'registration';
    this.modalError            = '';
    this.cdr.detectChanges();
  }

  backTo(step: 'program-select' | 'payment-method' | 'registration'): void {
    this.modalStep  = step;
    this.modalError = '';
    this.cdr.detectChanges();
  }

  getProgramName(): string {
    return this.programs.find(p => p.id === this.selectedProgram)?.name ?? '';
  }

  // ── Step 3: Submit registration form → go to account creation ──
  nextToAccount(): void {
    const f = this.regForm;
    if (!f.firstName || !f.lastName || !f.email) {
      this.modalError = 'First name, last name, and email are required.';
      this.cdr.detectChanges();
      return;
    }
    this.modalError = '';
    // Pre-fill username suggestion
    this.accountForm.username = f.email;
    this.modalStep = 'account';
    this.cdr.detectChanges();
  }

  // ── Step 4: Create account + register student ──────────────
  submitEnrollment(): void {
    const a = this.accountForm;
    if (!a.username || !a.password) {
      this.modalError = 'Username/email and password are required.';
      this.cdr.detectChanges();
      return;
    }
    if (a.password !== a.confirmPassword) {
      this.modalError = 'Passwords do not match.';
      this.cdr.detectChanges();
      return;
    }
    if (a.password.length < 6) {
      this.modalError = 'Password must be at least 6 characters.';
      this.cdr.detectChanges();
      return;
    }

    this.isSubmitting = true;
    this.modalError   = '';
    this.cdr.detectChanges();

    const programName = this.getProgramName();

    // Step 1: Create user account via auth.php
    this.http.post<any>(`${this.authUrl}?action=register`, {
      email:    a.username,
      password: a.password,
      role:     'student',
      first_name: this.regForm.firstName,
      last_name:  this.regForm.lastName
    }).subscribe({
      next: (res) => {
        if (!res.success) {
          this.isSubmitting = false;
          this.modalError   = res.message || 'Failed to create account.';
          this.cdr.detectChanges();
          return;
        }

        const userId = res.user_id;

        // Step 2: Register student profile
        this.http.post<any>(`${this.apiUrl}?action=register_student`, {
          user_id:          userId,
          firstName:        this.regForm.firstName,
          lastName:         this.regForm.lastName,
          email:            this.regForm.email,
          phone:            this.regForm.phone,
          dateOfBirth:      this.regForm.dateOfBirth,
          address:          this.regForm.address,
          emergencyContact: this.regForm.emergencyContact,
          emergencyPhone:   this.regForm.emergencyPhone,
          program:          programName,
          studentType:      this.isNewStudent ? 'New' : 'Continuing',
          paymentMethod:    this.selectedPaymentMethod
        }).subscribe({
          next: (sRes) => {
            this.isSubmitting    = false;
            this.createdEmail    = a.username;
            this.createdPassword = a.password;
            // ── Save payment method to localStorage so enrollment page reads it correctly ──
            const pm = this.selectedPaymentMethod === 'cash' ? 'Cash' : 'GCash';
            localStorage.setItem('pendingPaymentMethod', pm);
            this.modalStep = 'done';
            this.cdr.detectChanges();
          },
          error: (err) => {
            this.isSubmitting = false;
            // Show the actual server error if available
            this.modalError = err?.error?.message || 'Student profile registration failed. Please try again.';
            this.cdr.detectChanges();
          }
        });
      },
      error: () => {
        this.isSubmitting = false;
        this.modalError   = 'Cannot connect to server. Check XAMPP is running.';
        this.cdr.detectChanges();
      }
    });
  }

  loginWithNewAccount(): void {
    this.showModal = false;
    this.email     = this.createdEmail;
    this.password  = this.createdPassword;
    this.cdr.detectChanges();
    this.login();
  }
}