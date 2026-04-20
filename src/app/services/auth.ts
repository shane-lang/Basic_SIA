// src/app/services/auth.ts
import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Router } from '@angular/router';
import { Observable, tap } from 'rxjs';
import { environment } from '../environment';

export type Portal = 'student' | 'registrar' | 'accounting' | 'admin' | 'faculty';

export interface LoginResponse {
  success: boolean;
  message?: string;
  token?: string;
  role?: string;
  portal?: Portal;
  user?: {
    id: number;
    email: string;
    role: string;
    first_name: string;
    last_name: string;
  };
}

export const PORTAL_HOME: Record<Portal, string> = {
  student:    '/student/dashboard',
  registrar:  '/registrar/dashboard',
  accounting: '/accounting/dashboard',
  admin:      '/admin/dashboard',
  faculty:    '/faculty/dashboard',
};

export const PORTAL_LOGIN: Record<Portal, string> = {
  student:    '/student-login',
  registrar:  '/registrar/login',
  accounting: '/accounting/login',
  admin:      '/admin/login',
  faculty:    '/faculty/login',
};

const SESSION_KEYS = [
  'token', 'currentUser', 'portal', 'studentDbId', 'studentCategory',
  'enrollmentStep', 'pendingPaymentMethod', 'pendingPaymentPlan',
  'enrollWizardState', 'torReviewStudentId', 'tokenExpiresAt',
] as const;

// localStorage keys for student cross-browser session
const LS_TOKEN_KEY  = 'sia_student_token';
const LS_USER_KEY   = 'sia_student_user';
const LS_EXPIRY_KEY = 'sia_student_expiry';

// localStorage keys for staff portals (survive page refresh)
// Each portal gets its own key so they never cross-contaminate.
const LS_STAFF_TOKEN_KEY  = (portal: string) => `sia_${portal}_token`;
const LS_STAFF_USER_KEY   = (portal: string) => `sia_${portal}_user`;
const LS_STAFF_EXPIRY_KEY = (portal: string) => `sia_${portal}_expiry`;

function getOrCreateDeviceId(): string {
  try {
    const key = 'sia_device_id';
    let id = localStorage.getItem(key);
    if (!id) {
      const arr = new Uint8Array(32);
      crypto.getRandomValues(arr);
      id = Array.from(arr).map(b => b.toString(16).padStart(2, '0')).join('');
      localStorage.setItem(key, id);
    }
    return id;
  } catch {
    return '';
  }
}

@Injectable({ providedIn: 'root' })
export class AuthService {
  private readonly SESSION_TTL_MS = 8 * 60 * 60 * 1000;

  constructor(private http: HttpClient, private router: Router) {}

  login(email: string, password: string, portal: Portal): Observable<LoginResponse> {
    return this.http
      .post<LoginResponse>(`${environment.authApi}?action=login`, {
        email, password, portal,
        device_id: getOrCreateDeviceId(),
      })
      .pipe(
        tap(res => {
          if (res.success && res.token && res.user) {
            this.storeSession(res.token, res.user, portal);
          }
        })
      );
  }

  // Students → localStorage (shared across all tabs/browsers) AND sessionStorage
  //            (so existing components that read sessionStorage keep working)
  // Others  → sessionStorage only (per-tab)
  storeSession(token: string, user: LoginResponse['user'], portal: Portal | string): void {
    const isStudent = (user?.role ?? portal) === 'student';
    const expiresAt = Date.now() + this.SESSION_TTL_MS;

    console.log('[SIA] storeSession called. role:', user?.role, '| portal:', portal, '| isStudent:', isStudent);

    if (isStudent) {
      // FIX: Clear all staff portal tokens so a previous staff session never
      // leaks into the student portal when getToken() rehydrates from localStorage.
      const staffPortals: Portal[] = ['faculty', 'admin', 'registrar', 'accounting'];
      staffPortals.forEach(p => {
        try {
          localStorage.removeItem(LS_STAFF_TOKEN_KEY(p));
          localStorage.removeItem(LS_STAFF_USER_KEY(p));
          localStorage.removeItem(LS_STAFF_EXPIRY_KEY(p));
        } catch { /* ignore */ }
      });
      try {
        localStorage.setItem(LS_TOKEN_KEY,  token);
        localStorage.setItem(LS_USER_KEY,   JSON.stringify(user));
        localStorage.setItem(LS_EXPIRY_KEY, String(expiresAt));
        console.log('[SIA] localStorage SET. token saved:', !!localStorage.getItem(LS_TOKEN_KEY));
      } catch (e) {
        console.error('[SIA] localStorage FAILED:', e);
      }
      sessionStorage.setItem('token',          token);
      sessionStorage.setItem('currentUser',    JSON.stringify(user));
      sessionStorage.setItem('portal',         'student');
      sessionStorage.setItem('tokenExpiresAt', String(expiresAt));
    } else {
      // FIX: Clear student localStorage so a previous student session never causes
      // getToken() to return a student token for a staff user on route rehydration.
      try {
        localStorage.removeItem(LS_TOKEN_KEY);
        localStorage.removeItem(LS_USER_KEY);
        localStorage.removeItem(LS_EXPIRY_KEY);
      } catch { /* ignore */ }
      // Staff portals: save to BOTH localStorage (survives refresh) and sessionStorage (fast path)
      const p = portal as string;
      try {
        localStorage.setItem(LS_STAFF_TOKEN_KEY(p),  token);
        localStorage.setItem(LS_STAFF_USER_KEY(p),   JSON.stringify(user));
        localStorage.setItem(LS_STAFF_EXPIRY_KEY(p), String(expiresAt));
      } catch { /* ignore */ }
      sessionStorage.setItem('token',          token);
      sessionStorage.setItem('currentUser',    JSON.stringify(user));
      sessionStorage.setItem('portal',         p);
      sessionStorage.setItem('tokenExpiresAt', String(expiresAt));
    }
  }

  logout(portal?: Portal): void {
    // ── 1. Invalidate the server-side session (fire-and-forget) ─────────────
    // Grab the token before we wipe storage. We try every possible location
    // because the caller may not have a live sessionStorage at this point
    // (e.g. token-rotation redirect, tab-close beacon, interceptor-triggered
    // logout after a 401).
    const rawToken =
      sessionStorage.getItem('token') ??
      (() => {
        try { return localStorage.getItem(LS_TOKEN_KEY); } catch { return null; }
      })() ??
      (() => {
        for (const p of (['faculty', 'admin', 'registrar', 'accounting'] as Portal[])) {
          try {
            const t = localStorage.getItem(LS_STAFF_TOKEN_KEY(p));
            if (t) return t;
          } catch { /* ignore */ }
        }
        return null;
      })() ?? '';

    if (rawToken) {
      // Use fetch (not HttpClient) so the call survives Angular teardown.
      // keepalive:true allows the request to outlive the page unload event.
      fetch(`${environment.authApi}?action=logout`, {
        method: 'POST',
        keepalive: true,
        headers: {
          'Content-Type':  'application/json',
          'Authorization': `Bearer ${rawToken}`,
          'X-Auth-Token':  rawToken,
        },
      }).catch(() => { /* best-effort — ignore network errors */ });
    }

    // ── 2. Clear sessionStorage ───────────────────────────────────────────────
    SESSION_KEYS.forEach(k => sessionStorage.removeItem(k));
    Object.keys(sessionStorage)
      .filter(k => /^(gcashSubmitted|paySchedulePending|torHardCopyDismissed)_/.test(k))
      .forEach(k => sessionStorage.removeItem(k));

    // ── 3. Clear student localStorage ────────────────────────────────────────
    try {
      localStorage.removeItem(LS_TOKEN_KEY);
      localStorage.removeItem(LS_USER_KEY);
      localStorage.removeItem(LS_EXPIRY_KEY);
    } catch { /* ignore */ }

    // ── 4. Clear ALL staff portal localStorage ───────────────────────────────
    const staffPortals: Portal[] = ['faculty', 'admin', 'registrar', 'accounting'];
    staffPortals.forEach(p => {
      try {
        localStorage.removeItem(LS_STAFF_TOKEN_KEY(p));
        localStorage.removeItem(LS_STAFF_USER_KEY(p));
        localStorage.removeItem(LS_STAFF_EXPIRY_KEY(p));
      } catch { /* ignore */ }
    });

    // ── 5. Redirect to the correct login page ─────────────────────────────────
    // FIX: If no portal was passed to logout(), read it from sessionStorage
    // instead of hardcoding 'student'. This prevents the bug where calling
    // auth.logout() without arguments always redirects to /student-login
    // regardless of which portal the user was on.
    const resolvedPortal: Portal = portal
      ?? (sessionStorage.getItem('portal') as Portal | null)
      ?? 'student';
    const loginRoute = PORTAL_LOGIN[resolvedPortal] ?? PORTAL_LOGIN['student'];
    this.router.navigate([loginRoute]);
  }

  getToken(): string {
    // Fast path: sessionStorage (same tab — always up to date after login)
    const ssToken   = sessionStorage.getItem('token') ?? '';
    const ssExpiry  = Number(sessionStorage.getItem('tokenExpiresAt') ?? 0);
    if (ssToken && ssExpiry) {
      if (Date.now() > ssExpiry) { this.logout(this.getPortal()); return ''; }
      return ssToken;
    }

    // sessionStorage is empty — likely a page refresh.
    // Rehydrate from localStorage based on the current route.
    const currentPath = window.location.hash.replace('#', '') || window.location.pathname;

    // Check if this is a staff portal route (faculty, admin, registrar, accounting)
    // /instructor/* maps to faculty portal
    const staffPortal = (['faculty', 'admin', 'registrar', 'accounting'] as Portal[])
      .find(p => currentPath.startsWith(`/${p}`));
    const isInstructor = currentPath.startsWith('/instructor');

    if (staffPortal || isInstructor) {
      const portalKey = isInstructor ? 'faculty' : staffPortal!;
      try {
        const token     = localStorage.getItem(LS_STAFF_TOKEN_KEY(portalKey)) ?? '';
        const expiresAt = Number(localStorage.getItem(LS_STAFF_EXPIRY_KEY(portalKey)) ?? 0);
        if (token && expiresAt) {
          if (Date.now() > expiresAt) { this.logout(portalKey as Portal); return ''; }
          const userRaw = localStorage.getItem(LS_STAFF_USER_KEY(portalKey)) ?? '';
          sessionStorage.setItem('token',          token);
          sessionStorage.setItem('currentUser',    userRaw);
          sessionStorage.setItem('portal',         portalKey);
          sessionStorage.setItem('tokenExpiresAt', String(expiresAt));
          return token;
        }
      } catch { /* ignore */ }
      return '';
    }

    // Student routes: read from localStorage
    // FIX: Only rehydrate student token on explicitly student routes.
    // DO NOT rehydrate on '/' (root) because staff portals also pass through '/'
    // and this caused admins/faculty to get a stale student token redirected to /student.
    const isStudentRoute = currentPath.startsWith('/student')
      || currentPath === '/student-login';
    if (!isStudentRoute) return '';

    try {
      const token     = localStorage.getItem(LS_TOKEN_KEY) ?? '';
      const expiresAt = Number(localStorage.getItem(LS_EXPIRY_KEY) ?? 0);
      if (token && expiresAt) {
        if (Date.now() > expiresAt) { this.logout('student'); return ''; }
        const userRaw = localStorage.getItem(LS_USER_KEY) ?? '';
        sessionStorage.setItem('token',          token);
        sessionStorage.setItem('currentUser',    userRaw);
        sessionStorage.setItem('portal',         'student');
        sessionStorage.setItem('tokenExpiresAt', String(expiresAt));
        return token;
      }
    } catch { /* localStorage blocked */ }

    return '';
  }

  updateToken(newToken: string): void {
    try {
      if (localStorage.getItem(LS_TOKEN_KEY)) {
        localStorage.setItem(LS_TOKEN_KEY,  newToken);
        localStorage.setItem(LS_EXPIRY_KEY, String(Date.now() + this.SESSION_TTL_MS));
        return;
      }
    } catch { /* ignore */ }
    sessionStorage.setItem('token',          newToken);
    sessionStorage.setItem('tokenExpiresAt', String(Date.now() + this.SESSION_TTL_MS));
  }

  getCurrentUser(): LoginResponse['user'] | null {
    // sessionStorage first (same tab, always fresh after login/hydration)
    const ssRaw = sessionStorage.getItem('currentUser');
    if (ssRaw) try { return JSON.parse(ssRaw); } catch { /* ignore */ }
    // localStorage fallback (new tab before first getToken() call)
    try {
      const raw = localStorage.getItem(LS_USER_KEY);
      if (raw) return JSON.parse(raw);
    } catch { /* ignore */ }
    return null;
  }

  getRole(): string   { return this.getCurrentUser()?.role ?? ''; }
  getPortal(): Portal | undefined { return (sessionStorage.getItem('portal') as Portal) ?? undefined; }
  isLoggedIn(): boolean { return !!this.getToken(); }
  getHeaders(): { headers: { Authorization: string } } {
    return { headers: { Authorization: `Bearer ${this.getToken()}` } };
  }
}