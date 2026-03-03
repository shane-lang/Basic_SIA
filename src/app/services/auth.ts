import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { BehaviorSubject, Observable } from 'rxjs';
import { isPlatformBrowser } from '@angular/common';
import { PLATFORM_ID, inject } from '@angular/core';

interface LoginResponse {
  success: boolean;
  token: string;
  role: string;
  user: any;
  message?: string;
}

@Injectable({
  providedIn: 'root'
})
export class AuthService {
  private apiUrl = 'http://localhost/sia-api/auth.php';
  private currentUserSubject = new BehaviorSubject<any>(null);
  public currentUser$ = this.currentUserSubject.asObservable();
  private platformId = inject(PLATFORM_ID);

  constructor(private http: HttpClient) {
    if (isPlatformBrowser(this.platformId)) {
      const stored = localStorage.getItem('currentUser');
      if (stored) {
        this.currentUserSubject.next(JSON.parse(stored));
      }
    }
  }

  login(email: string, password: string): Observable<LoginResponse> {
    return this.http.post<LoginResponse>(this.apiUrl, { 
      email, 
      password 
    });
  }

  logout(): void {
    if (isPlatformBrowser(this.platformId)) {
      localStorage.removeItem('currentUser');
      localStorage.removeItem('token');
      sessionStorage.removeItem('token');
      sessionStorage.removeItem('currentUser');
    }
    this.currentUserSubject.next(null);
  }

  setCurrentUser(user: any, token?: string): void {
    if (isPlatformBrowser(this.platformId)) {
      localStorage.setItem('currentUser', JSON.stringify(user));
      if (token) {
        localStorage.setItem('token', token);
        sessionStorage.setItem('token', token); // keep sessionStorage in sync
      }
    }
    this.currentUserSubject.next(user);
  }

  getToken(): string {
    if (!isPlatformBrowser(this.platformId)) return '';
    // Prefer sessionStorage (active session), fall back to localStorage (after refresh)
    return sessionStorage.getItem('token') || localStorage.getItem('token') || '';
  }

  getCurrentUser(): any {
    return this.currentUserSubject.value;
  }

  isLoggedIn(): boolean {
    return this.currentUserSubject.value !== null;
  }
}