// src/app/accounting/accounting-layout/accounting-layout.ts
// ─────────────────────────────────────────────────────────────────────────────
// UPDATED: Removed manual role check (now handled by authGuard in routes).
//          logout() now uses AuthService so it redirects to /accounting/login.
// ─────────────────────────────────────────────────────────────────────────────
import { Component, OnDestroy, OnInit, signal, inject } from '@angular/core';
import { CommonModule, isPlatformBrowser } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { PLATFORM_ID } from '@angular/core';
import { MatSidenavModule } from '@angular/material/sidenav';
import { MatToolbarModule } from '@angular/material/toolbar';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatListModule } from '@angular/material/list';
import { MatBadgeModule } from '@angular/material/badge';
import { AuthService } from '../../services/auth';
import { environment } from '../../environment';

@Component({
  selector: 'app-accounting-layout',
  standalone: true,
  imports: [
    CommonModule,
    FormsModule,
    MatBadgeModule,
    RouterOutlet,
    RouterLink,
    RouterLinkActive,
    MatSidenavModule,
    MatToolbarModule,
    MatButtonModule,
    MatIconModule,
    MatListModule,
  ],
  templateUrl: './accounting-layout.html',
  styleUrl: './accounting-layout.css',
})
export class AccountingLayout implements OnInit, OnDestroy {
  protected readonly title = signal('Accounting Portal');
  sidebarOpen = signal(true);
  private resizeListener?: () => void;
  isMobile = signal(false);
  userRole = signal('Accountant');
  userName = signal('Accounting');
  notificationCount = signal(3);

  private platformId = inject(PLATFORM_ID);
  private auth       = inject(AuthService);
  private http       = inject(HttpClient);

  ngOnInit() {
    if (isPlatformBrowser(this.platformId)) {
      // ── Removed manual role check: authGuard handles this now ────────────
      const user = this.auth.getCurrentUser();
      this.userName.set(user?.first_name || 'Accounting');

      const isMobileView = window.innerWidth <= 768;
      this.isMobile.set(isMobileView);
      this.sidebarOpen.set(!isMobileView);

      this.resizeListener = () => {
        const isMobileView = window.innerWidth <= 768;
        this.isMobile.set(isMobileView);
        if (!isMobileView && !this.sidebarOpen()) {
          this.sidebarOpen.set(true);
        } else if (isMobileView) {
          this.sidebarOpen.set(false);
        }
      };
      window.addEventListener('resize', this.resizeListener);
    }
  }

  getSidenavMode(): 'side' | 'over' {
    return this.isMobile() ? 'over' : 'side';
  }

  getSidebarOpenedClass(): boolean {
    return this.sidebarOpen() && !this.isMobile();
  }

  ngOnDestroy() {
    if (this.resizeListener && typeof window !== 'undefined') {
      window.removeEventListener('resize', this.resizeListener);
    }
  }

  toggleSidebar() {
    this.sidebarOpen.update(value => !value);
  }

  closeSidebarOnMobile() {
    if (isPlatformBrowser(this.platformId) && window.innerWidth <= 768) {
      this.sidebarOpen.set(false);
    }
  }

  logout() {
    // ── Updated: uses AuthService so it redirects to /accounting/login ───
    this.auth.logout('accounting');
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