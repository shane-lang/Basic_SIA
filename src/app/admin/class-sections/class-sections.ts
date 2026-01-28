import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';

@Component({
  selector: 'app-class-sections',
  imports: [CommonModule],
  templateUrl: './class-sections.html',
  styleUrl: './class-sections.css',
})
export class ClassSections {
  sections = [
    { code: 'CS101-A', course: 'CS101', section: 'A', instructor: 'Dr. Maria Santos', capacity: 40, enrolled: 38, schedule: 'MWF 10:00-11:30' },
    { code: 'CS101-B', course: 'CS101', section: 'B', instructor: 'Prof. John Smith', capacity: 40, enrolled: 35, schedule: 'TTh 14:00-15:30' },
    { code: 'MATH201-A', course: 'MATH201', section: 'A', instructor: 'Prof. John Reyes', capacity: 45, enrolled: 42, schedule: 'MWF 08:00-09:30' },
  ];
}
