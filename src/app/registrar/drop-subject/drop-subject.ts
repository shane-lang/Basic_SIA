import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { MatCardModule } from '@angular/material/card';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatButtonModule } from '@angular/material/button';
import { MatSelectModule } from '@angular/material/select';
import { MatSnackBar, MatSnackBarModule } from '@angular/material/snack-bar';
import { MatTableModule } from '@angular/material/table';

interface StudentSubject {
  id: number;
  studentId: string;
  studentName: string;
  subjectCode: string;
  subjectName: string;
  enrollmentDate: string;
}

@Component({
  selector: 'app-drop-subject',
  standalone: true,
  imports: [
    CommonModule,
    FormsModule,
    MatCardModule,
    MatFormFieldModule,
    MatInputModule,
    MatButtonModule,
    MatSelectModule,
    MatSnackBarModule,
    MatTableModule
  ],
  templateUrl: './drop-subject.html',
  styleUrl: './drop-subject.css'
})
export class DropSubjectComponent {
  studentId: string = '';
  studentSubjects: StudentSubject[] = [];
  filteredSubjects: StudentSubject[] = [];
  displayedColumns: string[] = ['subjectCode', 'subjectName', 'enrollmentDate', 'action'];
  
  allSubjects: StudentSubject[] = [
    { id: 1, studentId: 'STU001', studentName: 'Juan Dela Cruz', subjectCode: 'MATH101', subjectName: 'Calculus I', enrollmentDate: '2026-01-15' },
    { id: 2, studentId: 'STU001', studentName: 'Juan Dela Cruz', subjectCode: 'PHYS201', subjectName: 'Physics II', enrollmentDate: '2026-01-15' },
    { id: 3, studentId: 'STU002', studentName: 'Maria Garcia', subjectCode: 'CHEM150', subjectName: 'Chemistry', enrollmentDate: '2026-01-10' },
    { id: 4, studentId: 'STU002', studentName: 'Maria Garcia', subjectCode: 'BIO120', subjectName: 'Biology', enrollmentDate: '2026-01-10' }
  ];

  constructor(private snackBar: MatSnackBar) {}

  searchStudent() {
    if (!this.studentId.trim()) {
      this.snackBar.open('Please enter a student ID', 'Close', { duration: 3000 });
      return;
    }

    this.filteredSubjects = this.allSubjects.filter(s => s.studentId === this.studentId);
    if (this.filteredSubjects.length === 0) {
      this.snackBar.open('No subjects found for this student', 'Close', { duration: 3000 });
    }
  }

  dropSubject(subject: StudentSubject) {
    if (confirm(`Drop ${subject.subjectName} for ${subject.studentName}?`)) {
      this.allSubjects = this.allSubjects.filter(s => s.id !== subject.id);
      this.searchStudent();
      this.snackBar.open(`Successfully dropped ${subject.subjectName}`, 'Close', { duration: 3000 });
    }
  }

  resetSearch() {
    this.studentId = '';
    this.filteredSubjects = [];
  }
}