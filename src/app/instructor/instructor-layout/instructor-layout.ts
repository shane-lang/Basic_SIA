import { Component, OnDestroy, OnInit, signal } from '@angular/core';
import { CommonModule, isPlatformBrowser } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterLink, RouterLinkActive, RouterOutlet, Router } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../environment';
import { MatSidenavModule } from '@angular/material/sidenav';
import { MatToolbarModule } from '@angular/material/toolbar';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatListModule } from '@angular/material/list';
import { PLATFORM_ID, inject } from '@angular/core';
import { AuthService } from '../../services/auth';

@Component({
  selector: 'app-instructor-layout',
  standalone: true,
  imports: [
    CommonModule, FormsModule, RouterOutlet, RouterLink, RouterLinkActive,
    MatSidenavModule, MatToolbarModule, MatButtonModule, MatIconModule, MatListModule,
  ],
  templateUrl: './instructor-layout.html',
  styleUrl:    './instructor-layout.css',
})
export class InstructorLayout implements OnInit, OnDestroy {
  sidebarOpen = signal(true);
  isMobile    = signal(false);
  userName    = signal('Instructor');
  userDept    = signal('');
  facultyId   = signal('');

  private resizeListener?: () => void;
  private platformId = inject(PLATFORM_ID);

  constructor(private router: Router, private http: HttpClient, private auth: AuthService) {}

  ngOnInit() {
    if (isPlatformBrowser(this.platformId)) {
      const raw = sessionStorage.getItem('currentUser') ?? '{}';
      const user = JSON.parse(raw);
      if (user?.role !== 'faculty') { this.router.navigate(['/faculty/login']); return; }

      const fullName = [user.first_name, user.last_name].filter(Boolean).join(' ');
      this.userName.set(fullName || user.email || 'Instructor');
      this.userDept.set(user.department || '');
      this.facultyId.set(user.faculty_id || '');

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
    }
  }

  ngOnDestroy() {
    if (this.resizeListener && isPlatformBrowser(this.platformId))
      window.removeEventListener('resize', this.resizeListener);
  }

  getSidenavMode(): 'side' | 'over' { return this.isMobile() ? 'over' : 'side'; }
  getSidebarOpenedClass(): boolean  { return this.sidebarOpen() && !this.isMobile(); }
  toggleSidebar()                   { this.sidebarOpen.update(v => !v); }
  closeSidebarOnMobile()            {
    if (isPlatformBrowser(this.platformId) && window.innerWidth <= 768)
      this.sidebarOpen.set(false);
  }

  logout() {
    this.auth.logout('faculty');
  }

  get initials(): string {
    return this.userName().split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2);
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