import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { MatCardModule } from '@angular/material/card';
import { MatIconModule } from '@angular/material/icon';
import { MatButtonModule } from '@angular/material/button';
import { MatProgressBarModule } from '@angular/material/progress-bar';
import { MatChipsModule } from '@angular/material/chips';

interface StatCard {
  label: string;
  value: string | number;
  icon: string;
  color: string;
  bgColor: string;
  change: string;
  changeType: 'up' | 'down';
}

interface Activity {
  id: number;
  student: string;
  action: string;
  subject: string;
  date: string;
  status: 'completed' | 'pending' | 'failed';
}

@Component({
  selector: 'app-registrar-dashboard',
  standalone: true,
  imports: [
    CommonModule,
    MatCardModule,
    MatIconModule,
    MatButtonModule,
    MatProgressBarModule,
    MatChipsModule
  ],
  templateUrl: './registrar-dashboard.html',
  styleUrl: './registrar-dashboard.css'
})
export class RegistrarDashboardComponent implements OnInit {
  stats: StatCard[] = [];
  recentActivities: Activity[] = [];

  ngOnInit() {
    this.loadStats();
    this.loadActivities();
  }

  loadStats() {
    this.stats = [
      {
        label: 'Total Students',
        value: '1,234',
        icon: 'people',
        color: '#667eea',
        bgColor: '#667eea20',
        change: '+12%',
        changeType: 'up'
      },
      {
        label: 'Active Courses',
        value: '45',
        icon: 'library_books',
        color: '#764ba2',
        bgColor: '#764ba220',
        change: '+5%',
        changeType: 'up'
      },
      {
        label: 'Subject Additions',
        value: '89',
        icon: 'add_circle',
        color: '#00d4ff',
        bgColor: '#00d4ff20',
        change: '+8%',
        changeType: 'up'
      },
      {
        label: 'Subject Drops',
        value: '23',
        icon: 'remove_circle',
        color: '#ff6b6b',
        bgColor: '#ff6b6b20',
        change: '-2%',
        changeType: 'down'
      }
    ];
  }

  loadActivities() {
    this.recentActivities = [
      {
        id: 1,
        student: 'Juan Dela Cruz',
        action: 'Added Subject',
        subject: 'Mathematics 101',
        date: '2026-01-29 10:30 AM',
        status: 'completed'
      },
      {
        id: 2,
        student: 'Maria Garcia',
        action: 'Dropped Subject',
        subject: 'Physics 201',
        date: '2026-01-29 09:15 AM',
        status: 'completed'
      },
      {
        id: 3,
        student: 'Pedro Lopez',
        action: 'Added Subject',
        subject: 'Chemistry 150',
        date: '2026-01-28 3:45 PM',
        status: 'completed'
      },
      {
        id: 4,
        student: 'Ana Santos',
        action: 'Dropped Subject',
        subject: 'Biology 120',
        date: '2026-01-28 11:20 AM',
        status: 'pending'
      },
      {
        id: 5,
        student: 'Carlos Mendez',
        action: 'Added Subject',
        subject: 'English Literature',
        date: '2026-01-27 2:00 PM',
        status: 'failed'
      }
    ];
  }

  getStatusColor(status: string): string {
    switch (status) {
      case 'completed':
        return '#51cf66';
      case 'pending':
        return '#ffd43b';
      case 'failed':
        return '#ff6b6b';
      default:
        return '#a0aec0';
    }
  }
}