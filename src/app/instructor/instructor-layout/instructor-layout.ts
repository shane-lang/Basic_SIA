import { Component, OnDestroy, OnInit, signal } from '@angular/core';
import { CommonModule, isPlatformBrowser } from '@angular/common';
import { RouterLink, RouterLinkActive, RouterOutlet, Router } from '@angular/router';
import { MatSidenavModule } from '@angular/material/sidenav';
import { MatToolbarModule } from '@angular/material/toolbar';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatListModule } from '@angular/material/list';
import { PLATFORM_ID, inject } from '@angular/core';

@Component({
  selector: 'app-instructor-layout',
  standalone: true,
  imports: [
    CommonModule, RouterOutlet, RouterLink, RouterLinkActive,
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

  constructor(private router: Router) {}

  ngOnInit() {
    if (isPlatformBrowser(this.platformId)) {
      const user = JSON.parse(sessionStorage.getItem('currentUser') || '{}');
      if (user?.role !== 'faculty') { this.router.navigate(['/login']); return; }

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
    if (isPlatformBrowser(this.platformId)) {
      sessionStorage.clear();
      localStorage.removeItem('currentUser');
      localStorage.removeItem('token');
    }
    this.router.navigate(['/login']);
  }

  get initials(): string {
    return this.userName().split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2);
  }
}