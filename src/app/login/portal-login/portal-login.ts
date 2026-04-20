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

  // ── Inline field validation errors ──────────────────────────────────────
  fieldErrors: { email?: string; password?: string } = {};

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
    // ── Set the portal first so banners render with the correct theme ─────────
    const portalData = this.route.snapshot.data['portal'] as Portal | null;
    if (portalData) {
      this.setPortal(portalData);
    } else {
      this.showSelector = true;
    }

    if (isPlatformBrowser(this.platformId)) {
      // ── Show reason banners (session expiry, kick, wrong role) ───────────────
      const reason = sessionStorage.getItem('logoutReason');
      if (reason === 'another_device') {
        this.logoutBanner = 'You were signed in from another device. This session has ended.';
      } else if (reason === 'expired') {
        this.logoutBanner = 'Your session expired. Please sign in again.';
      } else if (reason === 'signed_out') {
        this.logoutBanner = 'You have been signed out.';
      } else if (reason === 'wrong_role') {
        // FIX: Show a clear error instead of silently redirecting to a different portal.
        // The auth guard sets this when a logged-in user visits the wrong portal URL.
        // We display it as errorMessage (red box) so it's more prominent than the yellow banner.
        this.errorMessage = 'This account does not have access to this portal. Please log in with the correct account.';
      }
      sessionStorage.removeItem('logoutReason');

      // ── Already logged in with the CORRECT role? Auto-redirect to dashboard ──
      // Only redirect when the stored role actually belongs to this portal.
      // If the role does NOT match, do NOT redirect — just show the login form
      // so the user can log in with a different account. No silent cross-portal jumps.
      const token = this.auth.getToken();
      const user  = JSON.parse(sessionStorage.getItem('currentUser') ?? 'null');
      if (token && user?.role && reason !== 'wrong_role') {
        const portalRole = portalData ?? 'student';
        const ROLE_ALLOWED: Record<string, string[]> = {
          admin:      ['admin', 'registrar'],
          registrar:  ['registrar', 'admin'],
          accounting: ['accounting'],
          faculty:    ['faculty'],
          student:    ['student'],
        };
        if ((ROLE_ALLOWED[portalRole] ?? [portalRole]).includes(user.role)) {
          this.redirectByRole(user.role);
          return;
        }
        // Mismatched role — ignore stale session, stay on this portal's login
      }
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
    // ── Inline field validation ─────────────────────────────────────────────
    this.fieldErrors = {};
    this.errorMessage = '';

    const emailTrimmed = this.email.trim();
    const emailRegex   = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    if (!emailTrimmed) {
      this.fieldErrors.email = 'Email address is required.';
    } else if (!emailRegex.test(emailTrimmed)) {
      this.fieldErrors.email = 'Please enter a valid email address.';
    }

    if (!this.password) {
      this.fieldErrors.password = 'Password is required.';
    } else if (this.password.length < 6) {
      this.fieldErrors.password = 'Password must be at least 6 characters.';
    }

    if (this.fieldErrors.email || this.fieldErrors.password) {
      this.cdr.detectChanges();
      return;
    }

    if (!this.portal) return;

    // Guard: if a valid session for THIS portal already exists, redirect instead of logging in again
    if (isPlatformBrowser(this.platformId)) {
      const token = this.auth.getToken();
      const user  = JSON.parse(sessionStorage.getItem('currentUser') ?? 'null');
      if (token && user?.role) {
        const ROLE_ALLOWED: Record<string, string[]> = {
          admin:      ['admin', 'registrar'],
          registrar:  ['registrar', 'admin'],
          accounting: ['accounting'],
          faculty:    ['faculty'],
          student:    ['student'],
        };
        if ((ROLE_ALLOWED[this.portal!] ?? [this.portal]).includes(user.role)) {
          this.redirectByRole(user.role);
          return;
        }
        // Stale session from a different portal — ignore it and proceed with login
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
          // FIX: Redirect based on the role returned by the server, NOT config.redirectTo.
          // config.redirectTo is based on the selected portal tab — if the backend returns
          // a different role (e.g. user clicked student-login but actually has admin role),
          // we must send them to the correct dashboard and not blindly follow the portal config.
          this.redirectByRole(res.user?.role ?? res.role ?? '');
        } else {
          this.errorMessage = res.message || 'Login failed.';
          this.cdr.detectChanges();
        }
      },
      error: (err) => {
        this.loading = false;
        if (err.status === 0) {
          this.errorMessage = 'Connection error. Make sure XAMPP is running.';
        } else if (err.status === 403) {
          // FIX: 403 = logged in but wrong role for this portal.
          // Show the error HERE on this same portal's login page.
          // Do NOT navigate away — the user is already on the correct login page.
          this.errorMessage = err.error?.message || 'This account does not have access to this portal.';
        } else if (err.status === 401) {
          this.errorMessage = err.error?.message || 'Invalid email or password.';
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
    const loginMap: Record<string, string> = {
      student:    '/student-login',
      admin:      '/admin/login',
      accounting: '/accounting/login',
      registrar:  '/registrar/login',
      faculty:    '/faculty/login',
    };
    // If already on the correct portal login page, don't redirect in a loop
    const dest = map[role];
    if (dest) {
      this.router.navigate([dest]);
    } else {
      // Unknown role — send back to the current portal's login, not student-login
      const fallback = this.portal ? loginMap[this.portal] ?? '/student-login' : '/student-login';
      this.router.navigate([fallback]);
    }
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

  clearFieldError(field: 'email' | 'password'): void {
    if (this.fieldErrors[field]) {
      delete this.fieldErrors[field];
    }
  }

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