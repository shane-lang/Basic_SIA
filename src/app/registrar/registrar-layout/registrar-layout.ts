import { Component, OnDestroy, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink, RouterLinkActive, RouterOutlet, Router } from '@angular/router';
import { MatSidenavModule } from '@angular/material/sidenav';
import { MatToolbarModule } from '@angular/material/toolbar';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatListModule } from '@angular/material/list';
import { MatDividerModule } from '@angular/material/divider';
import { MatMenuModule } from '@angular/material/menu';
import { MatBadgeModule } from '@angular/material/badge';

@Component({
  selector: 'app-registrar-layout',
  standalone: true,
  imports: [
    CommonModule,
    RouterOutlet,
    RouterLink,
    RouterLinkActive,
    MatSidenavModule,
    MatToolbarModule,
    MatButtonModule,
    MatIconModule,
    MatListModule,
    MatDividerModule,
    MatMenuModule,
    MatBadgeModule
  ],
  templateUrl: './registrar-layout.html',
  styleUrl: './registrar-layout.css'
})
export class RegistrarLayoutComponent implements OnInit, OnDestroy {
  protected readonly title = signal('Registrar Portal');
  sidebarOpen = signal(true);
  private resizeListener?: () => void;
  isMobile = signal(false);
  userName = 'Registrar Admin';
  userRole = 'Registry Officer';
  notificationCount = signal(5);

  menuItems = [
    { label: 'Dashboard', icon: 'dashboard', route: '/registrar/dashboard', color: '#667eea' },
    { label: 'Manage Subjects', icon: 'library_books', route: '/registrar/manage-subjects', color: '#764ba2' },
    { label: 'Add Subject', icon: 'add_circle_outline', route: '/registrar/add-subject', color: '#00d4ff' },
    { label: 'Drop Subject', icon: 'remove_circle_outline', route: '/registrar/drop-subject', color: '#ff6b6b' }
  ];

  constructor(private router: Router) {}

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

  logout(): void {
    localStorage.removeItem('currentUser');
    localStorage.removeItem('token');
    this.router.navigate(['/login']);
  }
}