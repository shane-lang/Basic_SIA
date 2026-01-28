import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';

@Component({
  selector: 'app-settings',
  imports: [CommonModule],
  templateUrl: './settings.html',
  styleUrl: './settings.css',
})
export class Settings {
  settingsSections = [
    {
      title: 'Academic Settings',
      settings: [
        { name: 'Academic Year', value: '2024-2025', type: 'text' },
        { name: 'Semester Duration', value: '18 weeks', type: 'text' },
        { name: 'Passing Grade', value: 'D (60%)', type: 'select' },
      ]
    },
    {
      title: 'System Settings',
      settings: [
        { name: 'Maintenance Mode', value: 'Off', type: 'toggle' },
        { name: 'Grade Submission Deadline', value: '2024-12-15', type: 'date' },
        { name: 'System Email', value: 'noreply@school.edu', type: 'email' },
      ]
    },
  ];
}
