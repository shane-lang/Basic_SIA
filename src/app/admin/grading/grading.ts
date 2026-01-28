import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';

@Component({
  selector: 'app-grading',
  imports: [CommonModule],
  templateUrl: './grading.html',
  styleUrl: './grading.css',
})
export class Grading {
  gradingScales = [
    { gradePoint: 'A', range: '90-100', description: 'Excellent', equivalent: '4.0', quality: 'Excellent' },
    { gradePoint: 'B', range: '80-89', description: 'Good', equivalent: '3.0', quality: 'Good' },
    { gradePoint: 'C', range: '70-79', description: 'Satisfactory', equivalent: '2.0', quality: 'Satisfactory' },
    { gradePoint: 'D', range: '60-69', description: 'Passing', equivalent: '1.0', quality: 'Passing' },
    { gradePoint: 'F', range: '0-59', description: 'Failing', equivalent: '0.0', quality: 'Failing' },
  ];
}
