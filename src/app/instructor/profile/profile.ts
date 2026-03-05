import { Component, OnInit, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { HttpClient, HttpHeaders } from '@angular/common/http';

@Component({
  selector: 'app-instructor-profile',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './profile.html',
  styleUrl: './profile.css',
})
export class InstructorProfile implements OnInit {
  private api = 'http://localhost/sia-api/faculty.php';

  faculty: any = null;
  isLoading = true;

  private getHeaders() {
    const token = sessionStorage.getItem('token') || localStorage.getItem('token') || '';
    return { headers: new HttpHeaders({ Authorization: `Bearer ${token}` }) };
  }

  constructor(private http: HttpClient, private cdr: ChangeDetectorRef) {}

  ngOnInit(): void {
    this.http.get<any>(`${this.api}?action=get_profile`, this.getHeaders()).subscribe({
      next: (res) => {
        this.isLoading = false;
        if (res.success) this.faculty = res.faculty;
        this.cdr.detectChanges();
      },
      error: () => { this.isLoading = false; this.cdr.detectChanges(); }
    });
  }

  get initials(): string {
    if (!this.faculty) return '?';
    return ((this.faculty.first_name?.[0] ?? '') + (this.faculty.last_name?.[0] ?? '')).toUpperCase();
  }

  get fullName(): string {
    if (!this.faculty) return '';
    return `${this.faculty.first_name ?? ''} ${this.faculty.last_name ?? ''}`.trim();
  }
}