import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';

interface ScheduleClass {
  day: string;
  time: string;
  courseName: string;
  courseCode: string;
  instructor: string;
  room: string;
}

@Component({
  selector: 'app-schedule',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './schedule.html',
  styleUrls: ['./schedule.css']
})
export class Schedule {
  scheduleItems: ScheduleClass[] = [
    { day: 'Monday', time: '8:00 AM - 9:30 AM', courseName: 'Introduction to Computer Science', courseCode: 'CS101', instructor: 'Dr. Juan Dela Cruz', room: 'IT Building Room 101' },
    { day: 'Monday', time: '1:00 PM - 2:30 PM', courseName: 'Web Development', courseCode: 'CS103', instructor: 'Eng. Carlos Reyes', room: 'IT Building Room 301' },
    { day: 'Tuesday', time: '10:00 AM - 11:30 AM', courseName: 'Data Structures', courseCode: 'CS102', instructor: 'Prof. Maria Santos', room: 'IT Building Room 205' },
    { day: 'Wednesday', time: '8:00 AM - 9:30 AM', courseName: 'Introduction to Computer Science', courseCode: 'CS101', instructor: 'Dr. Juan Dela Cruz', room: 'IT Building Room 101' },
    { day: 'Wednesday', time: '1:00 PM - 2:30 PM', courseName: 'Web Development', courseCode: 'CS103', instructor: 'Eng. Carlos Reyes', room: 'IT Building Room 301' },
    { day: 'Thursday', time: '10:00 AM - 11:30 AM', courseName: 'Data Structures', courseCode: 'CS102', instructor: 'Prof. Maria Santos', room: 'IT Building Room 205' },
    { day: 'Friday', time: '8:00 AM - 9:30 AM', courseName: 'Introduction to Computer Science', courseCode: 'CS101', instructor: 'Dr. Juan Dela Cruz', room: 'IT Building Room 101' },
    { day: 'Friday', time: '1:00 PM - 2:30 PM', courseName: 'Web Development', courseCode: 'CS103', instructor: 'Eng. Carlos Reyes', room: 'IT Building Room 301' }
  ];

  days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];

  getClassesByDay(day: string): ScheduleClass[] {
    return this.scheduleItems.filter(item => item.day === day);
  }
}