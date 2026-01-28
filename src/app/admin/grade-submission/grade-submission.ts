import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';

@Component({
  selector: 'app-grade-submission',
  imports: [CommonModule],
  templateUrl: './grade-submission.html',
  styleUrl: './grade-submission.css',
})
export class GradeSubmission {
  submissions = [
    { instructor: 'Dr. Maria Santos', course: 'CS101-A', students: 38, submitted: 38, status: 'Completed', deadline: '2024-12-15' },
    { instructor: 'Prof. John Reyes', course: 'MATH201-A', students: 42, submitted: 40, status: 'Pending', deadline: '2024-12-15' },
    { instructor: 'Dr. Ana Garcia', course: 'PHYS101-A', students: 35, submitted: 35, status: 'Completed', deadline: '2024-12-15' },
  ];
}
