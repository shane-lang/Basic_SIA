// =============================================================================
// password-gate.service.ts
//
// Reusable service that:
//   1. Checks a short-lived sessionStorage cache so the student only types
//      their password ONCE per browser session (resets every 30 minutes of
//      inactivity or on page-close).
//   2. Opens the PasswordGateModalComponent in a dynamic overlay if a fresh
//      verification is needed.
//   3. POSTs to auth.php?action=verify_password with the Bearer token.
//
// Usage in any student component:
//   constructor(private gate: PasswordGateService) {}
//
//   async ngOnInit() {
//     const ok = await this.gate.requirePassword('COE');
//     if (!ok) return;
//     // ... load sensitive data
//   }
// =============================================================================

import {
  Injectable,
  ApplicationRef,
  createComponent,
  EnvironmentInjector,
  ComponentRef,
} from '@angular/core';
import { HttpClient, HttpHeaders } from '@angular/common/http';
import { firstValueFrom } from 'rxjs';
import { environment } from '../../environment';
import { PasswordGateModalComponent } from './password-gate-modal.component';

// Each document label gets its own cache key so verifying for COE does NOT
// unlock Grades or Payment Schedule — each requires a separate password entry.
const CACHE_PREFIX = 'pgv_ts_';        // prefix + slugified label = per-document key
const CACHE_TTL_MS = 30 * 60 * 1000;  // 30-minute TTL per document

function cacheKey(label: string): string {
  return CACHE_PREFIX + label.toLowerCase().replace(/[^a-z0-9]/g, '_');
}

@Injectable({ providedIn: 'root' })
export class PasswordGateService {

  constructor(
    private http:    HttpClient,
    private appRef:  ApplicationRef,
    private envInj:  EnvironmentInjector,
  ) {}

  // ── Public API ─────────────────────────────────────────────────────────────
  /**
   * Resolves to true if the student has successfully verified their password
   * (either from cache or by entering it in the modal). Returns false if they
   * dismiss the modal without verifying.
   *
   * @param documentLabel  Human-readable name shown in the modal title,
   *                       e.g. 'COE', 'SOA', 'Receipts', 'Grades'
   */
  requirePassword(documentLabel: string): Promise<boolean> {
    if (this.isCacheValid(documentLabel)) return Promise.resolve(true);
    return this.showModal(documentLabel);
  }

  /**
   * Verify the password directly (called by the modal component via callback).
   * Returns an error message string, or null on success.
   */
  async verifyPassword(password: string): Promise<string | null> {
    const token = this.getToken();
    if (!token) return 'You are not logged in.';

    const headers = new HttpHeaders({
      'Content-Type':  'application/json',
      'Authorization': `Bearer ${token}`,
      'X-Auth-Token':  token,
    });

    try {
      const res: any = await firstValueFrom(
        this.http.post(
          `${environment.authApi}?action=verify_password`,
          { password },
          { headers },
        )
      );

      if (res?.success) {
        sessionStorage.setItem(cacheKey(this._pendingLabel), String(Date.now()));
        return null;           // success
      }
      return res?.message ?? 'Incorrect password.';

    } catch (err: any) {
      const msg = err?.error?.message;
      return msg ?? 'Could not connect to the server.';
    }
  }

  // ── Private helpers ────────────────────────────────────────────────────────
  private isCacheValid(label: string): boolean {
    const ts = Number(sessionStorage.getItem(cacheKey(label)) ?? 0);
    return ts > 0 && (Date.now() - ts) < CACHE_TTL_MS;
  }

  private getToken(): string {
    try {
      // FIX PG-TOKEN-01: auth.storeSession() saves the token under 'token',
      // not 'authToken'. Using the wrong key always returned '' which caused
      // the "You are not logged in" error even for authenticated students.
      // Priority 1: sessionStorage 'token' — set for all portals on login
      const ssTok = sessionStorage.getItem('token');
      if (ssTok) return ssTok;
      // Priority 2: localStorage 'sia_student_token' — student sessions survive tab close
      const lsTok = localStorage.getItem('sia_student_token');
      if (lsTok) return lsTok;
      return '';
    } catch { return ''; }
  }

  private _pendingLabel = '';

  private showModal(documentLabel: string): Promise<boolean> {
    this._pendingLabel = documentLabel;
    return new Promise(resolve => {
      // Dynamically mount the modal outside the host component's tree
      const modalRef: ComponentRef<PasswordGateModalComponent> =
        createComponent(PasswordGateModalComponent, {
          environmentInjector: this.envInj,
        });

      modalRef.instance.documentLabel = documentLabel;

      // When verified → resolve true and tear down
      modalRef.instance.onVerified = () => {
        resolve(true);
        this.destroyModal(modalRef);
      };

      // When dismissed without verifying → resolve false and tear down
      modalRef.instance.onDismiss = () => {
        resolve(false);
        this.destroyModal(modalRef);
      };

      this.appRef.attachView(modalRef.hostView);
      document.body.appendChild(modalRef.location.nativeElement);
      modalRef.changeDetectorRef.detectChanges();
    });
  }

  private destroyModal(ref: ComponentRef<any>): void {
    this.appRef.detachView(ref.hostView);
    ref.destroy();
  }
}