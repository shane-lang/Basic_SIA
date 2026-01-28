import { Component, OnDestroy, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import { MatSidenavModule } from '@angular/material/sidenav';
import { MatToolbarModule } from '@angular/material/toolbar';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatListModule } from '@angular/material/list';

@Component({
  selector: 'app-student-layout',
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
  ],
  templateUrl: './student-layout.html',
  styleUrl: './student-layout.css',
})
export class StudentLayout implements OnInit, OnDestroy {
  protected readonly title = signal('Student Portal');
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
}

