import { Component, OnInit, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';

interface ScheduleEntry {
  courseId:   number;
  day:        string;
  time:       string;
  courseName: string;
  courseCode: string;
  instructor: string;
  room:       string;
  semester:   string;
  credits:    number;
  status:     string;
  grade:      string;
}

@Component({
  selector: 'app-schedule',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './schedule.html',
  styleUrl: './schedule.css',
})
export class Schedule implements OnInit {
  private apiUrl = 'http://localhost/sia-api/enrollment.php';

  constructor(private http: HttpClient, private cdr: ChangeDetectorRef) {}

  userId:      number = 0;
  studentDbId: number = 0;
  schedule: ScheduleEntry[] = [];
  isLoading = true;
  error     = '';

  currentSemester = ''; // loaded from enrolled course data

  readonly DAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

  // Color palette for courses
  private COLORS = [
    '#667eea', '#48bb78', '#ed8936', '#e53e3e',
    '#38b2ac', '#9f7aea', '#f6ad55', '#4299e1',
  ];
  private colorMap: Record<number, string> = {};

  ngOnInit(): void {
    const stored = sessionStorage.getItem('currentUser');
    if (!stored) {
      this.error     = 'Not logged in.';
      this.isLoading = false;
      this.cdr.detectChanges();
      return;
    }
    this.userId = JSON.parse(stored).id;

    // Prefer studentDbId saved by enrollment component (avoids lookup ambiguity)
    const savedDbId = sessionStorage.getItem('studentDbId');
    if (savedDbId) this.studentDbId = parseInt(savedDbId, 10);

    this.loadSchedule();
  }

  loadSchedule(): void {
    this.isLoading = true;
    this.cdr.detectChanges();

    // Use student_id directly if available, else fall back to user_id lookup
    const param = this.studentDbId > 0
      ? `student_id=${this.studentDbId}`
      : `user_id=${this.userId}`;

    this.http.get<any>(`${this.apiUrl}?action=get_schedule&${param}`)
      .subscribe({
        next: (res) => {
          this.isLoading = false;
          if (res.success) {
            this.schedule = res.schedule ?? [];
            // Pick semester from first loaded entry
            if (this.schedule.length > 0) this.currentSemester = this.schedule[0].semester;
            // Assign a color to each unique courseId
            let colorIdx = 0;
            this.schedule.forEach(e => {
              if (this.colorMap[e.courseId] === undefined) {
                this.colorMap[e.courseId] = this.COLORS[colorIdx % this.COLORS.length];
                colorIdx++;
              }
            });
          } else {
            this.error = res.message || 'Failed to load schedule.';
          }
          this.cdr.detectChanges();
        },
        error: () => {
          this.isLoading = false;
          this.error     = 'Cannot connect to server. Make sure XAMPP is running.';
          this.cdr.detectChanges();
        }
      });
  }

  // Courses for a specific day
  getCoursesForDay(day: string): ScheduleEntry[] {
    return this.schedule.filter(e => e.day === day);
  }

  // Whether a day has classes
  hasCourses(day: string): boolean {
    return this.getCoursesForDay(day).length > 0;
  }

  // Color for a course card
  getCourseColor(courseId: number): string {
    return this.colorMap[courseId] ?? '#667eea';
  }

  // Unique courses list (for summary)
  get uniqueCourses(): ScheduleEntry[] {
    const seen = new Set<number>();
    return this.schedule.filter(e => {
      if (seen.has(e.courseId)) return false;
      seen.add(e.courseId);
      return true;
    });
  }

  get totalCredits(): number {
    return this.uniqueCourses.reduce((sum, e) => sum + e.credits, 0);
  }

  get totalSubjects(): number {
    return this.uniqueCourses.length;
  }

  get activeDays(): string[] {
    return this.DAYS.filter(d => this.hasCourses(d));
  }
}