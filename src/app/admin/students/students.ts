import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';

@Component({
  selector: 'app-students',
  imports: [CommonModule],
  templateUrl: './students.html',
  styleUrl: './students.css',
})
export class Students {
  students = [
    { id: 'STU-2024-001', name: 'Juan Dela Cruz', email: 'juan@school.edu', grade: '1st Year', status: 'Active' },
    { id: 'STU-2024-002', name: 'Maria Santos', email: 'maria@school.edu', grade: '2nd Year', status: 'Active' },
    { id: 'STU-2024-003', name: 'Carlos Reyes', email: 'carlos@school.edu', grade: '3rd Year', status: 'Active' },
    { id: 'STU-2024-004', name: 'Ana Garcia', email: 'ana@school.edu', grade: '4th Year', status: 'Inactive' },
  ];
}
