import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';

@Component({
  selector: 'app-audit-logs',
  imports: [CommonModule],
  templateUrl: './audit-logs.html',
  styleUrl: './audit-logs.css',
})
export class AuditLogs {
  logs = [
    { timestamp: '2024-12-10 14:30:45', user: 'Admin User', action: 'Student Enrolled', details: 'STU-2024-001 enrolled in CS101', status: 'Success' },
    { timestamp: '2024-12-10 14:25:12', user: 'Faculty Maria', action: 'Grade Submitted', details: 'Grades submitted for CS101-A', status: 'Success' },
    { timestamp: '2024-12-10 13:15:33', user: 'Admin User', action: 'Course Created', details: 'New course PROG101 created', status: 'Success' },
    { timestamp: '2024-12-10 12:45:22', user: 'System', action: 'System Backup', details: 'Daily backup completed', status: 'Success' },
    { timestamp: '2024-12-10 11:20:15', user: 'Admin User', action: 'User Deleted', details: 'Inactive user removed', status: 'Success' },
  ];
}
