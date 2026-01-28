import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';

interface Course {
  code: string;
  name: string;
  instructor: string;
  credits: number;
  schedule: string;
  time: string; // Added this to fix "Property 'time' does not exist"
  room: string;
  progress: number;
}

interface Announcement {
  title: string;
  message: string;
  date: string;
  icon: string;
}

@Component({
  selector: 'app-dashboard',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './dashboard.html',
  styleUrls: ['./dashboard.css']
})
export class StudentDashboard {
  studentName: string = 'Alex Rivera';
  studentId: string = '2024-00123';
  gpa: string = '3.85';
  totalCredits: number = 9;
  nextClassTime: string = '10:30 AM';
  weekDays: string[] = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];

  // Added back to satisfy the .spec.ts (testing) file error
  dashboardCards = [
    { title: 'GPA', value: '3.85', icon: '🎯', color: '#4CAF50' }
  ];

  enrolledCourses: Course[] = [
    {
      code: 'CS101',
      name: 'Introduction to Programming',
      instructor: 'Dr. Smith',
      credits: 3,
      schedule: 'Mon/Wed',
      time: '10:30 AM - 12:00 PM', // Matches template usage
      room: 'Lab 1',
      progress: 65
    },
    {
      code: 'MATH202',
      name: 'Calculus II',
      instructor: 'Prof. Johnson',
      credits: 3,
      schedule: 'Tue/Thu',
      time: '1:00 PM - 2:30 PM',
      room: 'Room 302',
      progress: 40
    }
  ];

  announcements: Announcement[] = [
    {
      title: 'Library Hours Extended',
      message: 'The library is now open 24/7 for finals.',
      date: 'Jan 28, 2025',
      icon: '📖'
    }
  ];

  get nextClass() {
    return this.enrolledCourses[0];
  }

  getClassesByDay(day: string) {
    // Simple filter: checks if the day (e.g., "Monday") starts with the schedule prefix (e.g., "Mon")
    return this.enrolledCourses.filter(c => c.schedule.includes(day.substring(0, 3)));
  }
}