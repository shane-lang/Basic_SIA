import { Component, signal, OnInit, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterLink, RouterLinkActive, RouterOutlet, Router } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { environment } from '../environment';
import { MatSidenavModule } from '@angular/material/sidenav';
import { MatToolbarModule } from '@angular/material/toolbar';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatListModule } from '@angular/material/list';
import { AuthService } from '../services/auth';

@Component({
  selector: 'app-root',
  imports: [
    CommonModule,
    FormsModule,
    RouterOutlet,
    RouterLink,
    RouterLinkActive,
    MatSidenavModule,
    MatToolbarModule,
    MatButtonModule,
    MatIconModule,
    MatListModule,
  ],
  templateUrl: './admin.html',
  styleUrl: './admin.css',
})
export class Admin implements OnInit, OnDestroy {
  constructor(private http: HttpClient, private router: Router, private auth: AuthService) {}
  protected readonly title = signal('Admin Portal');
  sidebarOpen = signal(true);
  private resizeListener?: () => void;
  isMobile = signal(false);

  ngOnInit() {
    if (typeof window !== 'undefined') {
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
    if (typeof window !== 'undefined' && window.innerWidth <= 768) {
      this.sidebarOpen.set(false);
    }
  }

  logout() {
    this.auth.logout('admin');
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