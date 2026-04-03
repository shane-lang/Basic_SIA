import { Component, OnInit, OnDestroy, ChangeDetectorRef } from '@angular/core';
import { CommonModule, isPlatformBrowser } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router, ActivatedRoute, RouterLink } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { PLATFORM_ID, inject } from '@angular/core';
import { environment } from '../../environment';
import { AuthService } from '../../services/auth';

type Portal = 'student' | 'admin' | 'accounting' | 'registrar' | 'faculty';

interface PortalConfig {
  label: string;
  icon: string;
  accent: string;
  bg: string;
  redirectTo: string;
  showEnroll: boolean;   // true lang para sa student portal
}

const PORTALS: Record<Portal, PortalConfig> = {
  student: {
    label:       'Student Portal',
    icon:        '🎓',
    accent:      '#2563eb',
    bg:          'linear-gradient(135deg,#dbeafe,#eff6ff)',
    redirectTo:  '/student',
    showEnroll:  true,   // ← enrollment button visible
  },
  admin: {
    label:       'Admin Portal',
    icon:        '🛡️',
    accent:      '#7c3aed',
    bg:          'linear-gradient(135deg,#ede9fe,#f5f3ff)',
    redirectTo:  '/admin',
    showEnroll:  false,
  },
  accounting: {
    label:       'Accounting Portal',
    icon:        '💰',
    accent:      '#d97706',
    bg:          'linear-gradient(135deg,#fde68a,#fffbeb)',
    redirectTo:  '/accounting',
    showEnroll:  false,
  },
  registrar: {
    label:       'Registrar Portal',
    icon:        '📋',
    accent:      '#059669',
    bg:          'linear-gradient(135deg,#d1fae5,#ecfdf5)',
    redirectTo:  '/registrar',
    showEnroll:  false,
  },
  faculty: {
    label:       'Faculty Portal',
    icon:        '👨‍🏫',
    accent:      '#0891b2',
    bg:          'linear-gradient(135deg,#cffafe,#ecfeff)',
    redirectTo:  '/instructor',
    showEnroll:  false,
  },
};

const PORTAL_LIST: { key: Portal; label: string; icon: string }[] = [
  { key: 'student',    label: 'Student',    icon: '🎓' },
  { key: 'admin',      label: 'Admin',      icon: '🛡️' },
  { key: 'accounting', label: 'Accounting', icon: '💰' },
  { key: 'registrar',  label: 'Registrar',  icon: '📋' },
  { key: 'faculty',    label: 'Faculty',    icon: '👨‍🏫' },
];

@Component({
  selector: 'app-portal-login',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterLink],
  templateUrl: './portal-login.html',
  styleUrl: './portal-login.css',
})
export class PortalLoginComponent implements OnInit, OnDestroy {
  private authUrl    = environment.authApi;
  private platformId = inject(PLATFORM_ID);

  portal: Portal | null = null;
  config: PortalConfig | null = null;
  portalList = PORTAL_LIST;
  showSelector = false;
view: 'login'  | 'forgot' | 'reset' = 'login';
  email         = '';
  password      = '';
  showPw        = false;
  loading       = false;
  errorMessage  = '';
  logoutBanner  = '';   // shown when redirected from session expiry/kick

  // ── OTP 2FA state ───────────────────────────────────────────
  showOtp        = false;
  otpToken       = '';
  otpInput       = '';
  otpCode        = '';           // legacy - kept for compat
  otpError       = '';
  otpVerifying   = false;
  otpLoading     = false;
  otpPreview     = '';
  otpHint        = '';
  otpCountdown   = 300;          // 5 minutes in seconds
  otpTimer: any  = null;
  pendingUserId  = 0;

  // ── Welcome screen state ────────────────────────────────────
  showWelcome      = false;
  welcomeName      = '';
  welcomeRole      = '';
  welcomeCountdown = 3;
  welcomeTimer: any = null;

  // ── Forgot / Reset Password state ───────────────────────────
  showForgot        = '';    // '' | 'email' | 'reset'
  fpEmail           = '';
  fpOtp             = '';
  fpNewPassword     = '';
  fpConfirmPassword = '';
  fpLoading         = false;
  fpError           = '';
  fpSuccess         = '';
  fpResendCountdown = 0;
  private fpResendTimer: any = null;

  constructor(
    private http:   HttpClient,
    private router: Router,
    private route:  ActivatedRoute,
    private cdr:    ChangeDetectorRef,
    private auth:   AuthService,
  ) {}

  ngOnInit(): void {
    // ── Already logged in? Redirect immediately ──────────────────────────────
    // This runs BEFORE showing any login form — if there's a valid token in
    // sessionStorage, the user should never see the login page again.
    if (isPlatformBrowser(this.platformId)) {
      const token = this.auth.getToken();
      const user  = JSON.parse(sessionStorage.getItem('currentUser') ?? 'null');
      if (token && user?.role) {
        this.redirectByRole(user.role);
        return;
      }
    }

    // Show logout reason banner if redirected from session expiry/kick
    if (isPlatformBrowser(this.platformId)) {
      const reason = sessionStorage.getItem('logoutReason');
      if (reason === 'another_device') this.logoutBanner = 'You were signed in from another device. This session has ended.';
      else if (reason === 'expired')   this.logoutBanner = 'Your session expired. Please sign in again.';
      else if (reason === 'signed_out') this.logoutBanner = 'You have been signed out.';
      sessionStorage.removeItem('logoutReason');
    }

    const portalData = this.route.snapshot.data['portal'] as Portal | null;
    if (portalData) {
      this.setPortal(portalData);
    } else {
      this.showSelector = true;
    }
  }

  setPortal(p: Portal): void {
    this.portal       = p;
    this.config       = PORTALS[p];
    this.showSelector = false;
    this.errorMessage = '';
  }

  goEnroll(): void {
    // Pumunta sa original login page na may enrollment wizard
    this.router.navigate(['/login'], { fragment: 'enroll' });
  }

  onSubmit(): void {
    if (!this.email || !this.password) {
      this.errorMessage = 'Please enter your email and password.';
      return;
    }
    if (!this.portal) return;

    // Guard: if a valid session already exists, redirect instead of logging in again
    if (isPlatformBrowser(this.platformId)) {
      const token = this.auth.getToken();
      const user  = JSON.parse(sessionStorage.getItem('currentUser') ?? 'null');
      if (token && user?.role) {
        this.redirectByRole(user.role);
        return;
      }
    }

    this.loading      = true;
    this.errorMessage = '';

    this.http.post<any>(this.authUrl, {
      email:    this.email,
      password: this.password,
      portal:   this.portal,
    }).subscribe({
      next: (res) => {
        this.loading = false;
        if (res.success) {
          if (isPlatformBrowser(this.platformId)) {
            this.auth.storeSession(res.token, res.user, this.portal!);
            if (res.session_replaced) sessionStorage.setItem('sessionReplacedWarning', '1');
          }
          this.router.navigate([this.config!.redirectTo]);
        } else {
          this.errorMessage = res.message || 'Login failed.';
          this.cdr.detectChanges();
        }
      },
      error: (err) => {
        this.loading = false;
        if (err.status === 0) {
          this.errorMessage = 'Connection error. Make sure XAMPP is running.';
        } else {
          this.errorMessage = err.error?.message || `Server error (${err.status}). Please try again.`;
        }
        this.cdr.detectChanges();
      },
    });
  }

  private redirectByRole(role: string): void {
    const map: Record<string, string> = {
      student:    '/student',
      admin:      '/admin',
      accounting: '/accounting',
      registrar:  '/registrar',
      faculty:    '/instructor',
    };
    this.router.navigate([map[role] || '/login']);
  }

  cancelOtp(): void {
    this.showOtp = false;
    this.otpInput = '';
    this.otpToken = '';
    this.otpError = '';
    if (this.otpTimer) clearInterval(this.otpTimer);
  }

  startOtpCountdown(): void {
    if (this.otpTimer) clearInterval(this.otpTimer);
    this.otpTimer = setInterval(() => {
      this.otpCountdown--;
      this.cdr.detectChanges();
      if (this.otpCountdown <= 0) {
        clearInterval(this.otpTimer);
        this.otpError = 'OTP expired. Please log in again.';
        this.showOtp  = false;
        this.cdr.detectChanges();
      }
    }, 1000);
  }

  verifyOtp(): void {
    if (!this.otpInput || this.otpInput.length !== 6) {
      this.otpError = 'Please enter the 6-digit OTP.';
      return;
    }
    this.otpVerifying = true;
    this.otpError     = '';
    this.cdr.detectChanges();

    this.http.post<any>(`${this.authUrl}?action=verify_otp`, {
      otp_token: this.otpToken,
      otp_code:  this.otpInput.trim(),
    }).subscribe({
      next: (res) => {
        this.otpVerifying = false;
        if (res.success) {
          if (this.otpTimer) clearInterval(this.otpTimer);
          if (isPlatformBrowser(this.platformId)) {
            this.auth.storeSession(res.token, res.user, this.portal!);
          }
          // Show welcome screen
          this.welcomeName     = `${res.user?.first_name ?? ''} ${res.user?.last_name ?? ''}`.trim() || res.user?.email || '';
          this.welcomeRole     = this.config!.label;
          this.welcomeCountdown = 3;
          this.showOtp         = false;
          this.showWelcome     = true;
          this.cdr.detectChanges();
          if (this.welcomeTimer) clearInterval(this.welcomeTimer);
          this.welcomeTimer = setInterval(() => {
            this.welcomeCountdown--;
            this.cdr.detectChanges();
            if (this.welcomeCountdown <= 0) {
              clearInterval(this.welcomeTimer);
              this.router.navigate([this.config!.redirectTo]);
            }
          }, 1000);
        } else {
          this.otpError = res.message || 'Incorrect OTP. Please try again.';
          this.cdr.detectChanges();
        }
      },
      error: (err) => {
        this.otpVerifying = false;
        this.otpError = err.error?.message || 'Verification failed. Try again.';
        this.cdr.detectChanges();
      }
    });
  }

  ngOnDestroy(): void {
    if (this.otpTimer)      clearInterval(this.otpTimer);
    if (this.welcomeTimer)  clearInterval(this.welcomeTimer);
    if (this.fpResendTimer) clearInterval(this.fpResendTimer);
  }

  proceedNow(): void {
    if (this.welcomeTimer) clearInterval(this.welcomeTimer);
    this.router.navigate([this.config!.redirectTo]);
  }

  togglePw(): void { this.showPw = !this.showPw; }

  // ══════════════════════════════════════════════════════════
  // FORGOT / RESET PASSWORD
  // ══════════════════════════════════════════════════════════

  sendForgotOtp(isResend = false): void {
    const email = this.fpEmail.trim();
    if (!email) { this.fpError = 'Please enter your email address.'; return; }
    this.fpLoading = true; this.fpError = ''; this.fpSuccess = ''; this.cdr.detectChanges();
    this.http.post<any>(`${this.authUrl}?action=forgot_password`, { email }).subscribe({
      next: (res) => {
        this.fpLoading = false;
        if (res.success) {
          this.fpSuccess    = res.message || 'If that email exists, a reset code has been sent.';
          this.showForgot   = 'reset';
          this._startFpResendCountdown();
        } else {
          this.fpError = res.message || 'Request failed. Please try again.';
        }
        this.cdr.detectChanges();
      },
      error: () => {
        this.fpLoading = false;
        this.fpError   = 'Connection error. Make sure XAMPP is running.';
        this.cdr.detectChanges();
      },
    });
  }

  submitResetPassword(): void {
    if (!this.fpOtp.trim())             { this.fpError = 'Please enter the reset code.';            return; }
    if (!this.fpNewPassword)            { this.fpError = 'Please enter a new password.';             return; }
    if (this.fpNewPassword.length < 6)  { this.fpError = 'Password must be at least 6 characters.'; return; }
    if (this.fpNewPassword !== this.fpConfirmPassword) { this.fpError = 'Passwords do not match.';  return; }
    this.fpLoading = true; this.fpError = ''; this.cdr.detectChanges();
    this.http.post<any>(`${this.authUrl}?action=reset_password`, {
      email:            this.fpEmail.trim(),
      otp:              this.fpOtp.trim(),
      new_password:     this.fpNewPassword,
      confirm_password: this.fpConfirmPassword,
    }).subscribe({
      next: (res) => {
        this.fpLoading = false;
        if (res.success) {
          this.fpSuccess  = 'Password reset successfully. You can now log in.';
          this.fpError    = '';
          setTimeout(() => {
            this.showForgot   = '';
            this.fpOtp        = '';
            this.fpNewPassword = '';
            this.fpConfirmPassword = '';
            this.fpError    = '';
            this.fpSuccess  = '';
            this.cdr.detectChanges();
          }, 2500);
        } else {
          this.fpError = res.message || 'Reset failed. Please request a new code.';
        }
        this.cdr.detectChanges();
      },
      error: () => {
        this.fpLoading = false;
        this.fpError   = 'Connection error. Make sure XAMPP is running.';
        this.cdr.detectChanges();
      },
    });
  }

  private _startFpResendCountdown(seconds = 60): void {
    this.fpResendCountdown = seconds;
    if (this.fpResendTimer) clearInterval(this.fpResendTimer);
    this.fpResendTimer = setInterval(() => {
      this.fpResendCountdown--;
      this.cdr.detectChanges();
      if (this.fpResendCountdown <= 0) clearInterval(this.fpResendTimer);
    }, 1000);
  }
}