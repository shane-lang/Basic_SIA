import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { MatCardModule } from '@angular/material/card';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatButtonModule } from '@angular/material/button';
import { MatSelectModule } from '@angular/material/select';
import { MatSnackBar, MatSnackBarModule } from '@angular/material/snack-bar';

@Component({
  selector: 'app-add-subject',
  standalone: true,
  imports: [
    CommonModule,
    FormsModule,
    MatCardModule,
    MatFormFieldModule,
    MatInputModule,
    MatButtonModule,
    MatSelectModule,
    MatSnackBarModule
  ],
  templateUrl: './add-subject.html',
  styleUrl: './add-subject.css'
})
export class AddSubjectComponent {
  formData = {
    studentId: '',
    studentName: '',
    subjects: [] as string[]
  };

  availableSubjects = [
    'Mathematics 101',
    'Physics 201',
    'Chemistry 150',
    'Biology 120',
    'Computer Science 101',
    'English Literature',
    'History 200'
  ];

  selectedSubjects: string[] = [];

  constructor(private snackBar: MatSnackBar) {}

  addSubject() {
    if (!this.formData.studentId || !this.formData.studentName || this.selectedSubjects.length === 0) {
      this.snackBar.open('Please fill all fields', 'Close', { duration: 3000 });
      return;
    }

    this.snackBar.open(`Successfully added subjects for ${this.formData.studentName}`, 'Close', { duration: 3000 });
    this.resetForm();
  }

  toggleSubject(subject: string) {
    const index = this.selectedSubjects.indexOf(subject);
    if (index > -1) {
      this.selectedSubjects.splice(index, 1);
    } else {
      this.selectedSubjects.push(subject);
    }
  }

  isSubjectSelected(subject: string): boolean {
    return this.selectedSubjects.includes(subject);
  }

  resetForm() {
    this.formData = { studentId: '', studentName: '', subjects: [] };
    this.selectedSubjects = [];
  }
}