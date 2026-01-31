import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { MatTableModule } from '@angular/material/table';
import { MatButtonModule } from '@angular/material/button';
import { MatInputModule } from '@angular/material/input';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatIconModule } from '@angular/material/icon';
import { MatChipsModule } from '@angular/material/chips';
import { MatCardModule } from '@angular/material/card';
import { MatDividerModule } from '@angular/material/divider';
import { MatBadgeModule } from '@angular/material/badge';
import { PaymentService } from '../../services/payment.service';

interface StudentEnrollment {
  id: string;
  firstName: string;
  lastName: string;
  email: string;
  phone: string;
  program: string;
  yearLevel: string;
  gpa: number;
  studentType: 'New' | 'Continuing' | 'Returning';
  enrollmentStatus: 'Pending' | 'Enrolled' | 'Completed' | 'Dropped';
  paymentStatus: 'Pending' | 'Paid' | 'Overdue';
  approvalStatus: 'Pending' | 'Approved' | 'Rejected';
  enrollmentDate: string;
  paymentAmount?: number;
  paymentDate?: string;
  paymentReference?: string;
}

interface EnrolledCourse {
  id: string;
  code: string;
  name: string;
  credits: number;
  instructor: string;
  schedule: string;
  room: string;
  enrollmentDate: string;
  status: 'Pending' | 'Enrolled' | 'Completed' | 'Dropped';
}

@Component({
  selector: 'app-student-enrollment-review',
  standalone: true,
  imports: [
    CommonModule,
    FormsModule,
    MatTableModule,
    MatButtonModule,
    MatInputModule,
    MatFormFieldModule,
    MatIconModule,
    MatChipsModule,
    MatCardModule,
    MatDividerModule,
    MatBadgeModule
  ],
  templateUrl: './student-enrollment-review.html',
  styleUrl: './student-enrollment-review.css'
})
export class StudentEnrollmentReviewComponent implements OnInit {
  // Search and filter
  searchQuery = '';
  filterStatus: string | null = null;
  filterPaymentStatus: string | null = null;

  // Selected student(s)
  selectedStudent: StudentEnrollment | null = null;
  selectedStudentCourses: EnrolledCourse[] = [];
  showStudentDetails = false;
  selectedStudentIds: Set<string> = new Set();

  // All enrolled students (mock data)
  allStudents: StudentEnrollment[] = [];

  // Stats
  totalPendingEnrollments = 0;
  totalApprovedEnrollments = 0;
  totalUnpaidEnrollments = 0;
  totalEnrollments = 0;

  constructor(private paymentService: PaymentService) {}

  ngOnInit(): void {
    this.loadMockData();
    this.updatePaymentStatusFromAccounting();
    this.updateStatistics();
  }

  loadMockData(): void {
    // Mock data for enrolled students
    this.allStudents = [
      {
        id: 'STU-001234',
        firstName: 'Maria',
        lastName: 'Santos',
        email: 'maria.santos@university.edu',
        phone: '09171234567',
        program: 'BS Information Technology',
        yearLevel: '2nd Year',
        gpa: 3.45,
        studentType: 'Continuing',
        enrollmentStatus: 'Enrolled',
        paymentStatus: 'Paid',
        approvalStatus: 'Approved',
        enrollmentDate: '2025-01-15',
        paymentAmount: 25000,
        paymentDate: '2025-01-10',
        paymentReference: 'GCH-20250110-001'
      },
      {
        id: 'STU-001235',
        firstName: 'Juan',
        lastName: 'Dela Cruz',
        email: 'juan.cruz@university.edu',
        phone: '09171234568',
        program: 'BS Computer Science',
        yearLevel: '1st Year',
        gpa: 0,
        studentType: 'New',
        enrollmentStatus: 'Pending',
        paymentStatus: 'Pending',
        approvalStatus: 'Pending',
        enrollmentDate: '2025-01-20',
        paymentAmount: 30000
      },
      {
        id: 'STU-001236',
        firstName: 'Anna',
        lastName: 'Garcia',
        email: 'anna.garcia@university.edu',
        phone: '09171234569',
        program: 'BS Information Systems',
        yearLevel: '3rd Year',
        gpa: 3.8,
        studentType: 'Continuing',
        enrollmentStatus: 'Enrolled',
        paymentStatus: 'Paid',
        approvalStatus: 'Approved',
        enrollmentDate: '2025-01-12',
        paymentAmount: 25000,
        paymentDate: '2025-01-08',
        paymentReference: 'GCH-20250108-002'
      },
      {
        id: 'STU-001237',
        firstName: 'Luis',
        lastName: 'Rodriguez',
        email: 'luis.rodriguez@university.edu',
        phone: '09171234570',
        program: 'BS Civil Engineering',
        yearLevel: '4th Year',
        gpa: 3.2,
        studentType: 'Continuing',
        enrollmentStatus: 'Pending',
        paymentStatus: 'Overdue',
        approvalStatus: 'Pending',
        enrollmentDate: '2025-01-18',
        paymentAmount: 25000
      },
      {
        id: 'STU-001238',
        firstName: 'Maria',
        lastName: 'Lopez',
        email: 'maria.lopez@university.edu',
        phone: '09171234571',
        program: 'BS Information Technology',
        yearLevel: '2nd Year',
        gpa: 3.6,
        studentType: 'Continuing',
        enrollmentStatus: 'Enrolled',
        paymentStatus: 'Paid',
        approvalStatus: 'Approved',
        enrollmentDate: '2025-01-16',
        paymentAmount: 25000,
        paymentDate: '2025-01-14',
        paymentReference: 'GCH-20250114-003'
      },
      {
        id: 'STU-001239',
        firstName: 'Pedro',
        lastName: 'Reyes',
        email: 'pedro.reyes@university.edu',
        phone: '09171234572',
        program: 'BS Computer Science',
        yearLevel: '1st Year',
        gpa: 0,
        studentType: 'New',
        enrollmentStatus: 'Enrolled',
        paymentStatus: 'Paid',
        approvalStatus: 'Approved',
        enrollmentDate: '2025-01-19',
        paymentAmount: 30000,
        paymentDate: '2025-01-17',
        paymentReference: 'GCH-20250117-004'
      }
    ];
  }

  get filteredStudents(): StudentEnrollment[] {
    return this.allStudents.filter(student => {
      const searchLower = this.searchQuery.toLowerCase();
      const matchesSearch = searchLower === '' ||
                           student.firstName.toLowerCase().includes(searchLower) ||
                           student.lastName.toLowerCase().includes(searchLower) ||
                           student.id.toLowerCase().includes(searchLower) ||
                           student.email.toLowerCase().includes(searchLower);

      const matchesStatus = this.filterStatus === null || this.filterStatus === '' || student.enrollmentStatus === this.filterStatus;
      const matchesPayment = this.filterPaymentStatus === null || this.filterPaymentStatus === '' || student.paymentStatus === this.filterPaymentStatus;

      return matchesSearch && matchesStatus && matchesPayment;
    });
  }

  updateStatistics(): void {
    this.totalEnrollments = this.allStudents.length;
    this.totalPendingEnrollments = this.allStudents.filter(s => s.enrollmentStatus === 'Pending').length;
    this.totalApprovedEnrollments = this.allStudents.filter(s => s.approvalStatus === 'Approved').length;
    this.totalUnpaidEnrollments = this.allStudents.filter(s => s.paymentStatus !== 'Paid').length;
  }

  updatePaymentStatusFromAccounting(): void {
    // Update payment status for all students from the accounting payment service
    this.allStudents.forEach(student => {
      this.paymentService.getPaymentStatus(student.id).subscribe(payment => {
        if (payment) {
          student.paymentStatus = payment.status;
          student.paymentAmount = payment.amount;
          student.paymentDate = payment.paidDate;
          student.paymentReference = payment.paymentReference;
        }
      });
    });
  }

  viewStudentDetails(student: StudentEnrollment): void {
    this.selectedStudent = student;
    this.selectedStudentCourses = this.getStudentCourses(student.id);
    this.showStudentDetails = true;
  }

  closeStudentDetails(): void {
    this.showStudentDetails = false;
    this.selectedStudent = null;
    this.selectedStudentCourses = [];
  }

  getStudentCourses(studentId: string): EnrolledCourse[] {
    // Mock data for student courses
    const coursesByStudent: { [key: string]: EnrolledCourse[] } = {
      'STU-001234': [
        {
          id: '1',
          code: 'CS111',
          name: 'Introduction to Programming',
          credits: 3,
          instructor: 'Engr. Maria Santos',
          schedule: 'MWF 8:00 AM - 9:30 AM',
          room: 'CS Building Room 101',
          enrollmentDate: '2025-01-15',
          status: 'Enrolled'
        },
        {
          id: '2',
          code: 'CS112',
          name: 'Web Development Basics',
          credits: 3,
          instructor: 'Engr. Juan Reyes',
          schedule: 'TTh 10:00 AM - 11:30 AM',
          room: 'CS Building Room 205',
          enrollmentDate: '2025-01-15',
          status: 'Enrolled'
        },
        {
          id: '3',
          code: 'MATH101',
          name: 'Discrete Mathematics',
          credits: 4,
          instructor: 'Engr. Anna Garcia',
          schedule: 'MWF 9:45 AM - 11:15 AM',
          room: 'Science Building Room 301',
          enrollmentDate: '2025-01-15',
          status: 'Pending'
        }
      ],
      'STU-001235': [
        {
          id: '1',
          code: 'CS111',
          name: 'Introduction to Programming',
          credits: 3,
          instructor: 'Engr. Maria Santos',
          schedule: 'MWF 8:00 AM - 9:30 AM',
          room: 'CS Building Room 101',
          enrollmentDate: '2025-01-20',
          status: 'Pending'
        }
      ],
      'STU-001236': [
        {
          id: '2',
          code: 'CS112',
          name: 'Web Development Basics',
          credits: 3,
          instructor: 'Engr. Juan Reyes',
          schedule: 'TTh 10:00 AM - 11:30 AM',
          room: 'CS Building Room 205',
          enrollmentDate: '2025-01-12',
          status: 'Enrolled'
        },
        {
          id: '4',
          code: 'CS113',
          name: 'Database Fundamentals',
          credits: 3,
          instructor: 'Engr. Luis Rodriguez',
          schedule: 'TTh 1:00 PM - 2:30 PM',
          room: 'CS Building Room 312',
          enrollmentDate: '2025-01-12',
          status: 'Enrolled'
        }
      ],
      'STU-001237': [
        {
          id: '5',
          code: 'ENG101',
          name: 'English Composition',
          credits: 3,
          instructor: 'Prof. Sarah Kim',
          schedule: 'MWF 1:00 PM - 2:30 PM',
          room: 'Liberal Arts Building Room 115',
          enrollmentDate: '2025-01-18',
          status: 'Pending'
        }
      ],
      'STU-001238': [
        {
          id: '1',
          code: 'CS111',
          name: 'Introduction to Programming',
          credits: 3,
          instructor: 'Engr. Maria Santos',
          schedule: 'MWF 8:00 AM - 9:30 AM',
          room: 'CS Building Room 101',
          enrollmentDate: '2025-01-16',
          status: 'Enrolled'
        },
        {
          id: '3',
          code: 'MATH101',
          name: 'Discrete Mathematics',
          credits: 4,
          instructor: 'Engr. Anna Garcia',
          schedule: 'MWF 9:45 AM - 11:15 AM',
          room: 'Science Building Room 301',
          enrollmentDate: '2025-01-16',
          status: 'Enrolled'
        }
      ],
      'STU-001239': [
        {
          id: '1',
          code: 'CS111',
          name: 'Introduction to Programming',
          credits: 3,
          instructor: 'Engr. Maria Santos',
          schedule: 'MWF 8:00 AM - 9:30 AM',
          room: 'CS Building Room 101',
          enrollmentDate: '2025-01-19',
          status: 'Enrolled'
        },
        {
          id: '2',
          code: 'CS112',
          name: 'Web Development Basics',
          credits: 3,
          instructor: 'Engr. Juan Reyes',
          schedule: 'TTh 10:00 AM - 11:30 AM',
          room: 'CS Building Room 205',
          enrollmentDate: '2025-01-19',
          status: 'Enrolled'
        }
      ]
    };

    return coursesByStudent[studentId] || [];
  }

  approveEnrollment(student: StudentEnrollment): void {
    if (student.approvalStatus === 'Pending') {
      // Check if student has paid
      if (student.paymentStatus !== 'Paid') {
        this.addNotification('warning', `Cannot approve: ${student.firstName} ${student.lastName} has not completed payment (Status: ${student.paymentStatus})`);
        return;
      }
      student.approvalStatus = 'Approved';
      student.enrollmentStatus = 'Enrolled';
      this.addNotification('success', `Enrollment approved for ${student.firstName} ${student.lastName} (Payment verified)`);
    }
  }

  canApproveEnrollment(student: StudentEnrollment): boolean {
    return student.approvalStatus === 'Pending' && student.paymentStatus === 'Paid';
  }

  rejectEnrollment(student: StudentEnrollment): void {
    if (student.approvalStatus === 'Pending') {
      student.approvalStatus = 'Rejected';
      student.enrollmentStatus = 'Dropped';
      this.addNotification('warning', `Enrollment rejected for ${student.firstName} ${student.lastName}`);
    }
  }

  getStatusColor(status: string): string {
    const colors: { [key: string]: string } = {
      'Pending': '#ff9800',
      'Enrolled': '#4caf50',
      'Approved': '#2196f3',
      'Rejected': '#f44336',
      'Completed': '#9c27b0',
      'Dropped': '#9e9e9e',
      'Paid': '#4caf50',
      'Overdue': '#f44336'
    };
    return colors[status] || '#999';
  }

  getDisplayName(student: StudentEnrollment): string {
    return `${student.firstName} ${student.lastName}`;
  }

  toggleStudentSelection(studentId: string): void {
    if (this.selectedStudentIds.has(studentId)) {
      this.selectedStudentIds.delete(studentId);
    } else {
      this.selectedStudentIds.add(studentId);
    }
  }

  isStudentSelected(studentId: string): boolean {
    return this.selectedStudentIds.has(studentId);
  }

  selectAllStudents(): void {
    this.filteredStudents.forEach(student => {
      this.selectedStudentIds.add(student.id);
    });
  }

  deselectAllStudents(): void {
    this.selectedStudentIds.clear();
  }

  get selectedStudentCount(): number {
    return this.selectedStudentIds.size;
  }

  get areAllStudentsSelected(): boolean {
    return this.selectedStudentIds.size === this.filteredStudents.length && this.filteredStudents.length > 0;
  }

  getTotalEnrolledCredits(courses: EnrolledCourse[]): number {
    return courses
      .filter(c => c.status === 'Enrolled' || c.status === 'Pending')
      .reduce((sum, course) => sum + course.credits, 0);
  }

  notifications: Array<{ type: string; message: string; id: number }> = [];
  notificationCounter = 0;

  addNotification(type: string, message: string): void {
    const id = this.notificationCounter++;
    this.notifications.push({ type, message, id });

    setTimeout(() => {
      this.notifications = this.notifications.filter(n => n.id !== id);
    }, 3000);
  }

  dismissNotification(id: number): void {
    this.notifications = this.notifications.filter(n => n.id !== id);
  }

  clearSearch(): void {
    this.searchQuery = '';
  }

  resetFilters(): void {
    this.filterStatus = null;
    this.filterPaymentStatus = null;
  }
}
