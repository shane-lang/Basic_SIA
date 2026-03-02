import { Component, OnDestroy, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink, RouterLinkActive, RouterOutlet, Router } from '@angular/router';
import { MatSidenavModule } from '@angular/material/sidenav';
import { MatToolbarModule } from '@angular/material/toolbar';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatListModule } from '@angular/material/list';
import { MatBadgeModule } from '@angular/material/badge';
import { PLATFORM_ID, inject } from '@angular/core';
import { isPlatformBrowser } from '@angular/common';

@Component({
  selector: 'app-registrar-layout',
  standalone: true,
  imports: [
    CommonModule,
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

  private resizeListener?: () => void;
  private platformId = inject(PLATFORM_ID);

  constructor(private router: Router) {}

  ngOnInit() {
    if (isPlatformBrowser(this.platformId)) {
      const user = JSON.parse(sessionStorage.getItem('currentUser') || '{}');
      if (user?.role !== 'registrar') { this.router.navigate(['/login']); return; }
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
    }
  }

  ngOnDestroy() {
    if (this.resizeListener && isPlatformBrowser(this.platformId))
      window.removeEventListener('resize', this.resizeListener);
  }

  getSidenavMode(): 'side' | 'over' { return this.isMobile() ? 'over' : 'side'; }
  getSidebarOpenedClass(): boolean  { return this.sidebarOpen() && !this.isMobile(); }
  toggleSidebar()                   { this.sidebarOpen.update(v => !v); }
  closeSidebarOnMobile()            { if (isPlatformBrowser(this.platformId) && window.innerWidth <= 768) this.sidebarOpen.set(false); }

  logout() {
    if (isPlatformBrowser(this.platformId)) {
      sessionStorage.removeItem('currentUser');
      sessionStorage.removeItem('token');
    }
    this.router.navigate(['/login']);
  }
}

// Alias so app.routes.ts can import either name
export { RegistrarLayout as RegistrarLayoutComponent };