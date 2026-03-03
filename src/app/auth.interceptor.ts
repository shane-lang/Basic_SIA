import { HttpInterceptorFn, HttpErrorResponse } from '@angular/common/http';
import { inject } from '@angular/core';
import { Router } from '@angular/router';
import { catchError, throwError } from 'rxjs';

/**
 * Functional HTTP interceptor for standalone Angular apps.
 * 1. Attaches Bearer token to every SIA API request.
 * 2. On 401 response → clears session and redirects to /login automatically.
 */
export const authInterceptorFn: HttpInterceptorFn = (req, next) => {
  const router = inject(Router);

  const isSiaApi = req.url.includes('localhost/sia-api') ||
                   req.url.includes('127.0.0.1/sia-api');

  // Attach token if available — sessionStorage is cleared on refresh, so fall back to localStorage
  if (isSiaApi) {
    const token = sessionStorage.getItem('token') || localStorage.getItem('token');
    if (token) {
      req = req.clone({
        setHeaders: { Authorization: `Bearer ${token}` }
      });
    }
  }

  return next(req).pipe(
    catchError((error: HttpErrorResponse) => {
      // On 401 from SIA API: clear session and go to login
      if (error.status === 401 && isSiaApi) {
        const isPublicAction = req.url.includes('action=register') ||
                               req.url.includes('action=get_programs') ||
                               req.url.includes('action=get_fee_preview') ||
                               req.url.includes('action=get_shs_fee') ||
                               req.url.includes('action=get_tvet_fee') ||
                               req.url.includes('action=upload_tor') ||
                               req.url.includes('action=submit_tor') ||
                               req.url.includes('action=get_tor_evaluation') ||
                               req.url.includes('action=get_program_courses');

        if (!isPublicAction) {
          // Clear stale session data from both storages
          sessionStorage.removeItem('token');
          sessionStorage.removeItem('currentUser');
          sessionStorage.removeItem('studentDbId');
          sessionStorage.removeItem('studentCategory');
          localStorage.removeItem('token');
          localStorage.removeItem('currentUser');
          // Redirect to login
          router.navigate(['/login']);
        }
      }
      return throwError(() => error);
    })
  );
};