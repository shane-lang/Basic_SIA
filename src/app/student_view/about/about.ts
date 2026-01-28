import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';

@Component({
  selector: 'app-about',
  imports: [CommonModule],
  templateUrl: './about.html',
  styleUrl: './about.css',
})
export class About {
  siteName = 'BASIC SIA';
  tagline = 'Student Information & Academic Portal';
  version = '1.0.0';
  releaseDate = 'January 2025';

  features = [
    {
      icon: '📊',
      title: 'Dashboard',
      description: 'View your academic overview, GPA, and enrolled courses at a glance'
    },
    {
      icon: '👤',
      title: 'Profile Management',
      description: 'Manage your personal information, contact details, and profile settings'
    },
    {
      icon: '📝',
      title: 'Course Enrollment',
      description: 'Browse available courses and manage your course enrollment seamlessly'
    },
    {
      icon: '📅',
      title: 'Schedule Planner',
      description: 'View your weekly class schedule with location and instructor details'
    },
    {
      icon: '📱',
      title: 'Responsive Design',
      description: 'Access your portal on any device - desktop, tablet, or mobile'
    },
    {
      icon: '⚡',
      title: 'Fast & Reliable',
      description: 'Built with modern web technologies for optimal performance'
    }
  ];

  technologies = [
    { name: 'Angular', icon: '🅰️' },
    { name: 'TypeScript', icon: '📘' },
    { name: 'Angular Material', icon: '🎨' },
    { name: 'RxJS', icon: '🔄' },
    { name: 'HTML5 & CSS3', icon: '🌐' }
  ];

  team = [
    {
      name: 'Development Team',
      role: 'Software Engineers',
      description: 'Dedicated professionals building quality educational software'
    },
    {
      name: 'Design Team',
      role: 'UI/UX Designers',
      description: 'Creating intuitive and beautiful user interfaces'
    },
    {
      name: 'Support Team',
      role: 'Technical Support',
      description: 'Ensuring smooth user experience and quick issue resolution'
    }
  ];
}
