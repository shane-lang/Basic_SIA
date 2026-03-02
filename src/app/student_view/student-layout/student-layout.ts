import { Component, OnDestroy, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Router, RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import { MatSidenavModule } from '@angular/material/sidenav';
import { MatToolbarModule } from '@angular/material/toolbar';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatListModule } from '@angular/material/list';

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

  // Current user info from localStorage
  currentUserName = '';
  currentUserEmail = '';

  constructor(private router: Router) {}

  ngOnInit() {
    // Load user info for display in sidebar
    const stored = sessionStorage.getItem('currentUser');
    if (stored) {
      const u = JSON.parse(stored);
      this.currentUserName  = `${u.first_name || ''} ${u.last_name || ''}`.trim() || u.email;
      this.currentUserEmail = u.email || '';
    }

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
    }
  }

  ngOnDestroy() {
    if (this.resizeListener && typeof window !== 'undefined')
      window.removeEventListener('resize', this.resizeListener);
  }

  getSidenavMode(): 'side' | 'over' { return this.isMobile() ? 'over' : 'side'; }
  getSidebarOpenedClass(): boolean  { return this.sidebarOpen() && !this.isMobile(); }
  toggleSidebar()      { this.sidebarOpen.update(v => !v); }
  closeSidebarOnMobile() {
    if (typeof window !== 'undefined' && window.innerWidth <= 768) this.sidebarOpen.set(false);
  }

  logout(): void {
    sessionStorage.removeItem('currentUser');
    sessionStorage.removeItem('token');
    sessionStorage.removeItem('studentDbId');
    sessionStorage.removeItem('pendingPaymentMethod');
    this.router.navigate(['/login']);
  }
}