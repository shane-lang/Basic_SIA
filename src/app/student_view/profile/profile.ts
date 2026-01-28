import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';

interface StudentProfile {
  id: string;
  firstName: string;
  lastName: string;
  email: string;
  phone: string;
  program: string;
  yearLevel: string;
  gpa: number;
  enrollmentStatus: string;
  dateOfBirth: string;
  address: string;
  emergencyContact: string;
  emergencyPhone: string;
  profilePicture: string;
  enrollmentDate: string;
}

@Component({
  selector: 'app-profile',
  imports: [CommonModule],
  templateUrl: './profile.html',
  styleUrl: './profile.css',
})
export class Profile {
  student: StudentProfile = {
    id: 'STU-2024-0001',
    firstName: 'Juan',
    lastName: 'Dela Cruz',
    email: 'juan.delacruz@university.edu',
    phone: '09123456789',
    program: 'Bachelor of Science in Computer Science',
    yearLevel: '3rd Year',
    gpa: 3.85,
    enrollmentStatus: 'Active',
    dateOfBirth: '2004-05-15',
    address: '123 Sample Street, Manila, Philippines',
    emergencyContact: 'Maria Dela Cruz',
    emergencyPhone: '09987654321',
    profilePicture: 'https://ui-avatars.com/api/?name=Juan+Dela+Cruz&size=150',
    enrollmentDate: '2021-08-01'
  };

  getGPAStatus(): string {
    if (this.student.gpa >= 3.5) return 'Excellent';
    if (this.student.gpa >= 3.0) return 'Very Good';
    if (this.student.gpa >= 2.5) return 'Good';
    return 'Fair';
  }
}
