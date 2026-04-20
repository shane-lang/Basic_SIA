// src/app/auth.interceptor.ts
import { HttpInterceptorFn, HttpResponse } from '@angular/common/http';
import { inject } from '@angular/core';
import { tap } from 'rxjs';
import { AuthService } from './services/auth';
import { environment } from './environment';

// Key used to pass a logout reason to the login page via sessionStorage
export const LOGOUT_REASON_KEY = 'logoutReason';

export const authInterceptor: HttpInterceptorFn = (req, next) => {
  const auth  = inject(AuthService);
  const token = auth.getToken();

  const isSiaApi = req.url.startsWith(environment.apiBase);

  let cloned = req;

  if (token && isSiaApi) {
    cloned = cloned.clone({
      setHeaders: {
        'Authorization': `Bearer ${token}`,
        'X-Auth-Token':  token,
      }
    });
  }

  return next(cloned).pipe(
    tap({
      next: (event) => {
        if (event instanceof HttpResponse) {
          const newToken = event.headers.get('X-New-Token');
          if (newToken) {
            auth.updateToken(newToken);
          }
          // If the login response says session_replaced=true, a previous session
          // on this same device was kicked. Store a flag so the destination page
          // can show a "you were already logged in — previous session ended" notice.
          if ((event.body as any)?.session_replaced === true) {
            sessionStorage.setItem('sessionReplacedWarning', '1');
          }
        }
      },
      error: (err) => {
        if (err.status === 401) {
          // FIX: Do NOT trigger logout/navigation if the 401 came from the
          // login endpoint itself. Login errors (wrong password, etc.) must be
          // handled by the login component — not the interceptor.
          // Only trigger logout for 401s on authenticated API calls.
          const isLoginEndpoint = req.url.includes(environment.authApi) && !req.url.includes('action=');
          if (isLoginEndpoint) return;

          // Store a reason so the login page can show a descriptive message
          const code = err.error?.code ?? '';
          const msg  = err.error?.message ?? '';
          if (msg.toLowerCase().includes('another device') || code === 'SESSION_NOT_FOUND') {
            sessionStorage.setItem(LOGOUT_REASON_KEY, 'another_device');
          } else if (code === 'SESSION_EXPIRED') {
            sessionStorage.setItem(LOGOUT_REASON_KEY, 'expired');
          } else {
            sessionStorage.setItem(LOGOUT_REASON_KEY, 'signed_out');
          }
          auth.logout(auth.getPortal());
        }
      },
    })
  );
};