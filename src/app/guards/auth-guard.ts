import { Injectable } from '@angular/core';
import { Router, CanActivate, ActivatedRouteSnapshot } from '@angular/router';
import { isPlatformBrowser } from '@angular/common';
import { PLATFORM_ID, inject } from '@angular/core';

@Injectable({
  providedIn: 'root'
})
export class AuthGuard implements CanActivate {
  private platformId = inject(PLATFORM_ID);

  constructor(private router: Router) {}

  canActivate(route: ActivatedRouteSnapshot): boolean {
    if (!isPlatformBrowser(this.platformId)) {
      return false;
    }

    const userStr = localStorage.getItem('currentUser');
    const user = userStr ? JSON.parse(userStr) : null;
    
    if (!user) {
      this.router.navigate(['/login']);
      return false;
    }

    const requiredRole = route.data['role'];
    if (requiredRole && user.role !== requiredRole) {
      this.router.navigate(['/login']);
      return false;
    }

    return true;
  }
}