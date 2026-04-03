import { Component, OnDestroy, OnInit, signal } from '@angular/core';
import { CommonModule, isPlatformBrowser } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterLink, RouterLinkActive, RouterOutlet, Router } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { MatSidenavModule } from '@angular/material/sidenav';
import { MatToolbarModule } from '@angular/material/toolbar';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatListModule } from '@angular/material/list';
import { MatBadgeModule } from '@angular/material/badge';
import { PLATFORM_ID, inject } from '@angular/core';
import { environment } from '../../environment';
import { AuthService } from '../../services/auth';


@Component({
  selector: 'app-registrar-layout',
  standalone: true,
  imports: [
    CommonModule,
    FormsModule,
    MatBadgeModule,
    RouterOutlet, RouterLink, RouterLinkActive,
    MatSidenavModule, MatToolbarModule,
    MatButtonModule, MatIconModule, MatListModule,
  ],
  templateUrl: './registrar-layout.html',
  styleUrl: './registrar-layout.css',
})
export class RegistrarLayout implements OnInit, OnDestroy {
  sidebarOpen = signal(true);
  isMobile    = signal(false);
  userRole    = signal('Registrar');
  userName    = signal('Registrar');
  pendingTorCount = signal(0);
  pendingCoeCount = signal(0);

  private resizeListener?: () => void;
  private coeInterval?: any;
  private platformId = inject(PLATFORM_ID);

  constructor(
    private router: Router,
    private http: HttpClient,
    private auth: AuthService,
  ) {}

  ngOnInit() {
    if (isPlatformBrowser(this.platformId)) {
      const user = JSON.parse(sessionStorage.getItem('currentUser') || '{}');
      if (!['registrar', 'admin'].includes(user?.role)) {
        this.router.navigate(['/login']);
        return;
      }
      this.userName.set(user.first_name || 'Registrar');

      const isMobileView = window.innerWidth <= 768;
      this.isMobile.set(isMobileView);
      this.sidebarOpen.set(!isMobileView);

      this.resizeListener = () => {
        const m = window.innerWidth <= 768;
        this.isMobile.set(m);
        if (!m && !this.sidebarOpen()) this.sidebarOpen.set(true);
        else if (m) this.sidebarOpen.set(false);
      };
      window.addEventListener('resize', this.resizeListener);

      // Delay first poll slightly to ensure token is fully stored in sessionStorage
      setTimeout(() => {
        if (this.auth.getToken()) {
          this.refreshCounts();
          this.coeInterval = setInterval(() => {
            if (this.auth.getToken()) this.refreshCounts();
          }, 60000);
        }
      }, 500);
    }
  }

  ngOnDestroy() {
    if (this.resizeListener && isPlatformBrowser(this.platformId))
      window.removeEventListener('resize', this.resizeListener);
    if (this.coeInterval) clearInterval(this.coeInterval);
  }

  refreshCounts(): void {
    const token = this.auth.getToken();
    if (!token) return; // no token = skip silently

    // Count pending COE requests
    this.http.get<any>(`${environment.registrarApi}?action=coe_get_pending&status=Pending`)
      .subscribe({
        next: (res: any) => { if (res.success) this.pendingCoeCount.set((res.requests ?? []).length); },
        error: () => {}
      });

    // Count students with payment verified — awaiting registrar final approval
    this.http.get<any>(`${environment.registrarApi}?action=get_pending_registrations&limit=1`)
      .subscribe({
        next: (res: any) => { if (res.success) this.pendingTorCount.set(res.total ?? 0); },
        error: () => {}
      });
  }

  getSidenavMode(): 'side' | 'over' { return this.isMobile() ? 'over' : 'side'; }
  getSidebarOpenedClass(): boolean  { return this.sidebarOpen() && !this.isMobile(); }
  toggleSidebar()                   { this.sidebarOpen.update(v => !v); }
  closeSidebarOnMobile()            {
    if (isPlatformBrowser(this.platformId) && window.innerWidth <= 768)
      this.sidebarOpen.set(false);
  }

  logout() {
    this.auth.logout('registrar');
  }

  // ── Change Password modal ────────────────────────────────────────────────
  showCpModal   = false;
  cpCurrent     = ''; cpNew = ''; cpConfirm = '';
  cpShowCurrent = false; cpShowNew = false; cpShowConfirm = false;
  cpError       = ''; cpSuccess = ''; cpSubmitting = false;

  openCpModal(): void {
    this.showCpModal = true;
    this.cpCurrent = this.cpNew = this.cpConfirm = '';
    this.cpError = this.cpSuccess = '';
    this.cpShowCurrent = this.cpShowNew = this.cpShowConfirm = false;
  }

  closeCpModal(): void { this.showCpModal = false; }

  changePassword(): void {
    this.cpError = ''; this.cpSuccess = '';
    if (!this.cpCurrent || !this.cpNew || !this.cpConfirm) {
      this.cpError = 'All fields are required.'; return;
    }
    if (this.cpNew.length < 6) {
      this.cpError = 'New password must be at least 6 characters.'; return;
    }
    if (this.cpNew !== this.cpConfirm) {
      this.cpError = 'New password and confirmation do not match.'; return;
    }
    const token = sessionStorage.getItem('token') ?? '';
    this.cpSubmitting = true;
    this.http.post<any>(
      `${environment.authApi}?action=change_password`,
      { current_password: this.cpCurrent, new_password: this.cpNew, confirm_password: this.cpConfirm },
      { headers: { Authorization: `Bearer ${token}` } }
    ).subscribe({
      next: res => {
        this.cpSubmitting = false;
        if (res.success) {
          this.cpSuccess = res.message;
          this.cpCurrent = this.cpNew = this.cpConfirm = '';
        } else {
          this.cpError = res.message || 'Password change failed.';
        }
      },
      error: () => {
        this.cpSubmitting = false;
        this.cpError = 'Connection error. Please try again.';
      }
    });
  }
}

export { RegistrarLayout as RegistrarLayoutComponent };