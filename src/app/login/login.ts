import { Component } from '@angular/core';
import { CommonModule, isPlatformBrowser } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { MatButtonModule } from '@angular/material/button';
import { MatInputModule } from '@angular/material/input';
import { MatCardModule } from '@angular/material/card';
import { MatFormFieldModule } from '@angular/material/form-field';
import { PLATFORM_ID, inject } from '@angular/core';

@Component({
  selector: 'app-login',
  standalone: true,
  imports: [
    CommonModule,
    FormsModule,
    MatButtonModule,
    MatInputModule,
    MatCardModule,
    MatFormFieldModule
  ],
  templateUrl: './login.html',
  styleUrls: ['./login.css']
})
export class LoginComponent {
  email: string = '';
  password: string = '';
  errorMessage: string = '';
  successMessage: string = '';
  loading: boolean = false;
  private platformId = inject(PLATFORM_ID);

  constructor(private http: HttpClient, private router: Router) {}

  login(): void {
    if (!this.email || !this.password) {
      this.errorMessage = 'Please enter email and password';
      return;
    }

    this.loading = true;
    this.errorMessage = '';
    this.successMessage = '';

    const loginData = {
      email: this.email,
      password: this.password
    };

    this.http.post('http://localhost/sia-api/auth.php', loginData).subscribe({
      next: (response: any) => {
        if (response.success) {
          this.successMessage = 'Login successful! Redirecting...';
          
          if (isPlatformBrowser(this.platformId)) {
            localStorage.setItem('currentUser', JSON.stringify(response.user));
            localStorage.setItem('token', response.token);
          }

          setTimeout(() => {
            this.redirectByRole(response.user.role);
          }, 1000);
        } else {
          this.errorMessage = response.message || 'Login failed';
          this.loading = false;
        }
      },
      error: (error) => {
        this.errorMessage = 'Connection error. Make sure XAMPP is running.';
        this.loading = false;
        console.error('Login error:', error);
      }
    });
  }

  private redirectByRole(role: string): void {
    const routes: { [key: string]: string } = {
      'student': '/student',
      'admin': '/admin',
      'accounting': '/accounting',
      'registrar': '/registrar',
      'faculty': '/admin'
    };

    this.router.navigate([routes[role] || '/login']);
  }
}