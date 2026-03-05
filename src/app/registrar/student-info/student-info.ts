import { Component, OnInit, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Router } from '@angular/router';
import { Student } from '../masterlist/masterlist';

@Component({
  selector: 'app-student-info',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './student-info.html',
  styleUrl: './student-info.css',
})
export class StudentInfoComponent implements OnInit {
  student: Student | null = null;

  constructor(private router: Router, private cdr: ChangeDetectorRef) {}

  ngOnInit(): void {
    const raw = sessionStorage.getItem('selectedStudent');
    if (raw) {
      try { this.student = JSON.parse(raw); }
      catch { this.router.navigate(['/registrar/student-masterlist']); }
    } else {
      this.router.navigate(['/registrar/student-masterlist']);
    }
    this.cdr.detectChanges();
  }

  goBack(): void {
    this.router.navigate(['/registrar/student-masterlist']);
  }

  get previousSchools(): { level: string; school: string; year: string }[] {
    if (!this.student?.lastSchoolAttended) return [];
    return this.student.lastSchoolAttended.split(';').map(entry => {
      const clean = entry.trim();
      const match = clean.match(/^(.*?)\s*-\s*(.*?)\s*\(([^)]*)\)\s*$/);
      if (match) return { level: match[1].trim(), school: match[2].trim(), year: match[3].trim() };
      return { level: '—', school: clean, year: '—' };
    }).filter(s => s.school);
  }

  categoryClass(c: string): string {
    return c==='College'?'cat-college': c==='SHS'?'cat-shs': c==='TVET'?'cat-tvet':'';
  }
  statusClass(s: string): string {
    const m: Record<string,string> = { Enrolled:'st-enrolled', Pending:'st-pending', Approved:'st-approved', Rejected:'st-rejected', Completed:'st-completed' };
    return m[s] || '';
  }
}