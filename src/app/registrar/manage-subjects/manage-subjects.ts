import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { MatTableModule } from '@angular/material/table';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';
import { MatCardModule } from '@angular/material/card';
import { MatInputModule } from '@angular/material/input';
import { MatFormFieldModule } from '@angular/material/form-field';
import { FormsModule } from '@angular/forms';

interface Subject {
  id: number;
  code: string;
  name: string;
  credits: number;
  instructor: string;
  semester: string;
  studentCount: number;
}

@Component({
  selector: 'app-manage-subjects',
  standalone: true,
  imports: [
    CommonModule,
    MatTableModule,
    MatButtonModule,
    MatIconModule,
    MatCardModule,
    MatInputModule,
    MatFormFieldModule,
    FormsModule
  ],
  templateUrl: './manage-subjects.html',
  styleUrl: './manage-subjects.css'
})
export class ManageSubjectsComponent implements OnInit {
  displayedColumns: string[] = ['code', 'name', 'credits', 'instructor', 'semester', 'studentCount', 'actions'];
  subjects: Subject[] = [];
  filteredSubjects: Subject[] = [];
  searchTerm: string = '';

  ngOnInit() {
    this.loadSubjects();
  }

  loadSubjects() {
    this.subjects = [
      { id: 1, code: 'MATH101', name: 'Calculus I', credits: 3, instructor: 'Dr. Smith', semester: 'Spring 2026', studentCount: 45 },
      { id: 2, code: 'PHYS201', name: 'Physics II', credits: 4, instructor: 'Dr. Johnson', semester: 'Spring 2026', studentCount: 38 },
      { id: 3, code: 'CHEM150', name: 'Chemistry', credits: 3, instructor: 'Dr. Williams', semester: 'Spring 2026', studentCount: 52 },
      { id: 4, code: 'BIO120', name: 'Biology', credits: 3, instructor: 'Dr. Brown', semester: 'Spring 2026', studentCount: 41 },
      { id: 5, code: 'CS101', name: 'Intro to Programming', credits: 3, instructor: 'Dr. Davis', semester: 'Spring 2026', studentCount: 60 }
    ];
    this.filteredSubjects = this.subjects;
  }

  filterSubjects() {
    if (!this.searchTerm.trim()) {
      this.filteredSubjects = this.subjects;
    } else {
      const term = this.searchTerm.toLowerCase();
      this.filteredSubjects = this.subjects.filter(s =>
        s.code.toLowerCase().includes(term) ||
        s.name.toLowerCase().includes(term) ||
        s.instructor.toLowerCase().includes(term)
      );
    }
  }

  editSubject(subject: Subject) {
    alert(`Edit subject: ${subject.name}`);
  }

  deleteSubject(subject: Subject) {
    if (confirm(`Delete subject ${subject.name}?`)) {
      this.subjects = this.subjects.filter(s => s.id !== subject.id);
      this.filterSubjects();
    }
  }
}