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
      // Logged in but wrong portal — send to their correct login
      const loginRoute = PORTAL_LOGIN[userRole] ?? '/student-login';
      router.navigate([loginRoute]);
      return false;
    }

    return true;
  };
};