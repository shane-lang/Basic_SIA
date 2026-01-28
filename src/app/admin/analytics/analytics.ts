import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';

@Component({
  selector: 'app-analytics',
  imports: [CommonModule],
  templateUrl: './analytics.html',
  styleUrl: './analytics.css',
})
export class Analytics {
  analyticsData = [
    { metric: 'Total Enrollments', value: '4,850', change: '+12.5%', icon: '📊' },
    { metric: 'Average GPA', value: '3.24', change: '+0.15', icon: '📈' },
    { metric: 'Completion Rate', value: '94.2%', change: '+2.3%', icon: '✅' },
    { metric: 'Faculty Utilization', value: '87%', change: '+5%', icon: '👥' },
  ];
}
