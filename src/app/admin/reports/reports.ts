import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';

@Component({
  selector: 'app-reports',
  imports: [CommonModule],
  templateUrl: './reports.html',
  styleUrl: './reports.css',
})
export class Reports {
  reports = [
    { name: 'Student Enrollment Report', icon: '📊', description: 'Complete list of enrolled students by course and semester', generated: '2024-12-10' },
    { name: 'Class Roster Report', icon: '📋', description: 'Detailed class rosters with student information', generated: '2024-12-10' },
    { name: 'Academic Performance Report', icon: '📈', description: 'Student GPA and academic standing analysis', generated: '2024-12-09' },
    { name: 'Faculty Workload Report', icon: '📚', description: 'Course load and teaching schedule for faculty', generated: '2024-12-08' },
    { name: 'Transcript Report', icon: '📄', description: 'Official student transcripts and academic history', generated: '2024-12-05' },
  ];
}
