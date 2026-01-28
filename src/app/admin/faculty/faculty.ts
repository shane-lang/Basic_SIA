import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';

@Component({
  selector: 'app-faculty',
  imports: [CommonModule],
  templateUrl: './faculty.html',
  styleUrl: './faculty.css',
})
export class Faculty {
  faculty = [
    { id: 'FAC-001', name: 'Dr. Maria Santos', email: 'maria@school.edu', department: 'Computer Science', specialization: 'Artificial Intelligence', status: 'Active' },
    { id: 'FAC-002', name: 'Prof. John Reyes', email: 'john@school.edu', department: 'Mathematics', specialization: 'Calculus', status: 'Active' },
    { id: 'FAC-003', name: 'Dr. Ana Garcia', email: 'ana@school.edu', department: 'Physics', specialization: 'Quantum Physics', status: 'Active' },
  ];
}
