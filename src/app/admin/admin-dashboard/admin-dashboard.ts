import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';

@Component({
  selector: 'app-admin-dashboard',
  imports: [CommonModule],
  templateUrl: './admin-dashboard.html',
  styleUrl: './admin-dashboard.css',
})
export class AdminDashboard {
  stats = [
    {
      label: 'Total Students',
      value: '1,245',
      icon: '👨‍🎓',
      color: '#3b82f6',
      change: '+12 this month'
    },
    {
      label: 'Total Faculty',
      value: '85',
      icon: '👨‍🏫',
      color: '#10b981',
      change: '+3 this month'
    },
    {
      label: 'Active Courses',
      value: '156',
      icon: '📚',
      color: '#f59e0b',
      change: '+8 this semester'
    },
    {
      label: 'Class Sections',
      value: '234',
      icon: '👥',
      color: '#8b5cf6',
      change: '+15 this semester'
    }
  ];

  recentActivities = [
    {
      activity: 'New student enrollment',
      timestamp: '2 hours ago',
      icon: '📝'
    },
    {
      activity: 'Faculty grade submission',
      timestamp: '4 hours ago',
      icon: '✏️'
    },
    {
      activity: 'New course added',
      timestamp: '6 hours ago',
      icon: '📚'
    },
    {
      activity: 'Class section created',
      timestamp: '1 day ago',
      icon: '👥'
    },
    {
      activity: 'System backup completed',
      timestamp: '2 days ago',
      icon: '💾'
    }
  ];

  semesterInfo = {
    current: '1st Semester, AY 2024-2025',
    startDate: 'June 3, 2024',
    endDate: 'October 25, 2024',
    enrollmentCutoff: 'August 15, 2024'
  };
}
