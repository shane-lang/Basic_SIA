// src/app/auth/guards/auth.guard.ts
// ─────────────────────────────────────────────────────────────────────────────
// Replaces the manual role check in accounting-layout.ts:
//   if (user?.role !== 'accounting') { this.router.navigate(['/login']); return; }
//
// Usage in routes:
//   canActivate: [authGuard('accounting')]
// ─────────────────────────────────────────────────────────────────────────────
import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { AuthService, Portal, PORTAL_LOGIN } from '../services/auth';

// Roles that are allowed to access each portal's routes.
// registrar can access admin routes (same backend PHP file).
const ALLOWED_ROLES: Record<Portal, Portal[]> = {
  admin:      ['admin', 'registrar'],
  registrar:  ['registrar', 'admin'],
  accounting: ['accounting'],
  student:    ['student'],
  faculty:    ['faculty'],
};

// Guard for the student LOGIN page.
// Reads localStorage directly — no dependency on sessionStorage hydration order.
// If a valid student session exists, redirect to dashboard immediately.
export const studentLoginGuard: CanActivateFn = () => {
  const router = inject(Router);
  try {
    const token     = localStorage.getItem('sia_student_token');
    const expiresAt = Number(localStorage.getItem('sia_student_expiry') ?? 0);
    const userRaw   = localStorage.getItem('sia_student_user');
    if (token && expiresAt && Date.now() < expiresAt && userRaw) {
      const user = JSON.parse(userRaw);
      if (user?.role === 'student') {
        router.navigate(['/student']);
        return false;
      }
    }
  } catch { /* localStorage blocked or parse error — allow login page */ }
  return true;
};

export const authGuard = (requiredRole: Portal): CanActivateFn => {
  return () => {
    const auth   = inject(AuthService);
    const router = inject(Router);

    if (!auth.isLoggedIn()) {
      router.navigate([PORTAL_LOGIN[requiredRole]]);
      return false;
    }

    const userRole = auth.getRole() as Portal;
    const allowed  = ALLOWED_ROLES[requiredRole] ?? [requiredRole];

    if (!allowed.includes(userRole)) {
      // FIX: Wrong role trying to access this portal.
      // Do NOT call auth.logout() here — it internally calls router.navigate()
      // to the user's own portal login, overriding our navigation below.
      // Instead: manually wipe sessionStorage, set the error reason, then
      // redirect back to THIS portal's login page so the user sees the error message.
      sessionStorage.setItem('logoutReason', 'wrong_role');
      // Clear session keys manually (same keys as AuthService.SESSION_KEYS)
      ['token','currentUser','portal','studentDbId','studentCategory',
       'enrollmentStep','pendingPaymentMethod','pendingPaymentPlan',
       'enrollWizardState','torReviewStudentId','tokenExpiresAt'
      ].forEach(k => sessionStorage.removeItem(k));
      // Clear all portal localStorage tokens to prevent rehydration of the wrong session
      ['student','faculty','admin','registrar','accounting'].forEach(p => {
        try {
          localStorage.removeItem(`sia_${p}_token`);
          localStorage.removeItem(`sia_${p}_user`);
          localStorage.removeItem(`sia_${p}_expiry`);
        } catch { /* ignore */ }
      });
      try {
        localStorage.removeItem('sia_student_token');
        localStorage.removeItem('sia_student_user');
        localStorage.removeItem('sia_student_expiry');
      } catch { /* ignore */ }
      router.navigate([PORTAL_LOGIN[requiredRole]]);
      return false;
    }

    return true;
  };
};

// ── Root / wildcard redirect guard ───────────────────────────────────────────
// Replaces the old { path: '**', redirectTo: '/student' } catch-all.
// Instead of always bouncing to /student (which then bounces non-students to
// /student-login), this guard reads the stored portal/role and sends the user
// directly to their correct login page — no double-bounce.
export const rootRedirectGuard: CanActivateFn = () => {
  const router = inject(Router);

  // Check each staff portal's localStorage token (survives page refresh)
  const staffPortals: Portal[] = ['accounting', 'admin', 'registrar', 'faculty'];
  for (const p of staffPortals) {
    try {
      const token     = localStorage.getItem(`sia_${p}_token`);
      const expiresAt = Number(localStorage.getItem(`sia_${p}_expiry`) ?? 0);
      if (token && expiresAt && Date.now() < expiresAt) {
        // Valid staff session — send to their dashboard
        const dest: Record<string, string> = {
          accounting: '/accounting',
          admin:      '/admin',
          registrar:  '/registrar',
          faculty:    '/instructor',
        };
        router.navigate([dest[p] ?? `/${p}`]);
        return false;
      }
    } catch { /* localStorage blocked */ }
  }

  // Check student localStorage token
  try {
    const token     = localStorage.getItem('sia_student_token');
    const expiresAt = Number(localStorage.getItem('sia_student_expiry') ?? 0);
    if (token && expiresAt && Date.now() < expiresAt) {
      router.navigate(['/student']);
      return false;
    }
  } catch { /* ignore */ }

  // No valid session found — read the sessionStorage portal to pick the right login page.
  // This handles the case where the guard fires after auth.logout() wipes localStorage.
  const storedPortal = sessionStorage.getItem('portal') as Portal | null;
  const loginMap: Record<string, string> = {
    accounting: '/accounting/login',
    admin:      '/admin/login',
    registrar:  '/registrar/login',
    faculty:    '/faculty/login',
    student:    '/student-login',
  };
  const loginRoute = storedPortal ? (loginMap[storedPortal] ?? '/student-login') : '/student-login';
  router.navigate([loginRoute]);
  return false;
};