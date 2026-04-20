import { Component, OnDestroy, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Router, RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import { MatSidenavModule } from '@angular/material/sidenav';
import { MatToolbarModule } from '@angular/material/toolbar';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatListModule } from '@angular/material/list';
import { AuthService } from '../../services/auth';

@Component({
  selector: 'app-student-layout',
  imports: [CommonModule, RouterOutlet, RouterLink, RouterLinkActive,
    MatSidenavModule, MatToolbarModule, MatButtonModule, MatIconModule, MatListModule],
  templateUrl: './student-layout.html',
  styleUrl: './student-layout.css',
})
export class StudentLayout implements OnInit, OnDestroy {
  protected readonly title = signal('Student Portal');
  sidebarOpen = signal(true);
  isMobile = signal(false);
  private resizeListener?: () => void;

  currentUserName  = '';
  currentUserEmail = '';
  isSHS            = false;   // hides Add-Drop link for SHS students
  isFreeStudent    = false;   // SHS/TVET non-transferee — hides Payment Schedule nav

  constructor(private router: Router, private auth: AuthService) {}

  isLoggingOut = false;
  logoutCountdown = 3;
  private logoutCountdownTimer: any = null;

  private startLogoutRedirect(): void {
    if (this.isLoggingOut) return;
    this.isLoggingOut = true;
    this.logoutCountdown = 3;
    sessionStorage.clear();
    const countdown = setInterval(() => {
      this.logoutCountdown--;
      if (this.logoutCountdown <= 0) {
        clearInterval(countdown);
        window.location.replace('/#/student-login');
      }
    }, 1000);
    this.logoutCountdownTimer = countdown;
  }

  private logoutPollInterval: any = null;

  ngOnInit() {
    // Load user info from AuthService (reads localStorage for students)
    const u = this.auth.getCurrentUser();
    if (u) {
      this.currentUserName  = `${u.first_name || ''} ${u.last_name || ''}`.trim() || u.email;
      this.currentUserEmail = u.email || '';
    }

    // Detect SHS — enrollment page stores this in sessionStorage after loading student data
    const cat = (sessionStorage.getItem('studentCategory') ?? '').toUpperCase();
    const isTransferee = (sessionStorage.getItem('studentType') ?? '').toLowerCase().includes('transferee');
    this.isSHS         = cat === 'SHS';
    this.isFreeStudent = (cat === 'SHS' || cat === 'TVET') && !isTransferee;

    if (typeof window !== 'undefined') {
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

      // Same-browser cross-tab logout: storage event fires instantly
      window.addEventListener('storage', this._onStorageLogout);

      // Cross-browser logout: poll every 2 seconds to check if token was removed
      this.logoutPollInterval = setInterval(() => {
        const token = localStorage.getItem('sia_student_token');
        if (!token) {
          clearInterval(this.logoutPollInterval);
          this.startLogoutRedirect();
        }
      }, 2000);
    }
  }

  ngOnDestroy() {
    if (this.resizeListener && typeof window !== 'undefined') {
      window.removeEventListener('resize', this.resizeListener);
      window.removeEventListener('storage', this._onStorageLogout);
    }
    if (this.logoutPollInterval) clearInterval(this.logoutPollInterval);
    if (this.logoutCountdownTimer) clearInterval(this.logoutCountdownTimer);
  }

  // When sia_student_token is removed from localStorage (i.e. logout happened
  // in another browser), redirect this window to the login page too.
  private _onStorageLogout = (e: StorageEvent): void => {
    if (e.key === 'sia_student_token' && e.newValue === null) {
      clearInterval(this.logoutPollInterval);
      this.startLogoutRedirect();
    }
  };

  getSidenavMode(): 'side' | 'over' { return this.isMobile() ? 'over' : 'side'; }
  getSidebarOpenedClass(): boolean  { return this.sidebarOpen() && !this.isMobile(); }
  toggleSidebar()      { this.sidebarOpen.update(v => !v); }
  closeSidebarOnMobile() {
    if (typeof window !== 'undefined' && window.innerWidth <= 768) this.sidebarOpen.set(false);
  }

  logout(): void {
    this.auth.logout('student');
    this.startLogoutRedirect();
  }
}