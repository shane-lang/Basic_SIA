import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';

@Component({
  selector: 'app-courses',
  imports: [CommonModule],
  templateUrl: './courses.html',
  styleUrl: './courses.css',
})
export class Courses {
  courses = [
    { code: 'CS101', title: 'Introduction to Computer Science', credits: 3, department: 'Computer Science', status: 'Active' },
    { code: 'MATH201', title: 'Calculus II', credits: 4, department: 'Mathematics', status: 'Active' },
    { code: 'PHYS101', title: 'Physics I', credits: 3, department: 'Physics', status: 'Active' },
    { code: 'CHEM101', title: 'Chemistry I', credits: 3, department: 'Chemistry', status: 'Active' },
  ];
}
