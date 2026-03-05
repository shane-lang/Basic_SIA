import { Component, OnInit, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { HttpClient, HttpHeaders } from '@angular/common/http';
import { Router } from '@angular/router';

interface Course {
  id: number;
  code: string;
  name: string;
  credits: number;
  semester: string;
  department: string;
  program: string;
  yearLevel: string;
  isLab: boolean;
  enrolledCount: number;
  submittedCount: number;
  releasedCount: number;
  prelimDone: number;
  midtermDone: number;
  finalDone: number;
  gradeCompletion: number;
}

@Component({
  selector: 'app-instructor-courses',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './courses.html',
  styleUrl: './courses.css',
})
export class InstructorCourses implements OnInit {
  private api = 'http://localhost/sia-api/faculty.php';

  courses: Course[] = [];
  isLoading = true;
  facultyInfo: { name: string; department: string; specialty: string } | null = null;

  private getHeaders() {
    const token = sessionStorage.getItem('token') || localStorage.getItem('token') || '';
    return { headers: new HttpHeaders({ Authorization: `Bearer ${token}` }) };
  }

  constructor(private http: HttpClient, private router: Router, private cdr: ChangeDetectorRef) {}

  ngOnInit(): void { this.loadCourses(); }

  loadCourses(): void {
    this.isLoading = true;
    this.http.get<any>(`${this.api}?action=get_my_courses`, this.getHeaders()).subscribe({
      next: (res) => {
        this.isLoading = false;
        if (res.success) {
          this.courses = res.courses || [];
          this.facultyInfo = res.faculty || null;
        }
        this.cdr.detectChanges();
      },
      error: () => { this.isLoading = false; this.cdr.detectChanges(); }
    });
  }

  openGrading(course: Course): void {
    this.router.navigate(['/instructor/grading'], { queryParams: { courseId: course.id } });
  }

  get totalStudents(): number { return this.courses.reduce((s, c) => s + c.enrolledCount, 0); }
  get totalGraded(): number   { return this.courses.filter(c => c.gradeCompletion === 100).length; }

  completionColor(pct: number): string {
    if (pct === 100) return '#16a34a';
    if (pct >= 50)  return '#d97706';
    return '#dc2626';
  }
}