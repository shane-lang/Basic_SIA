import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';

interface Course {
  id: string;
  code: string;
  name: string;
  credits: number;
  instructor: string;
  schedule: string;
  room: string;
  capacity: number;
  enrolled: number;
  semester: string;
  description: string;
  department: string;
}

interface StudentCourse {
  id: string;
  courseId: string;
  code: string;
  name: string;
  credits: number;
  instructor: string;
  schedule: string;
  room: string;
  enrollmentDate: string;
  status: 'Pending' | 'Enrolled' | 'Completed' | 'Dropped';
  grade?: string;
}

interface StudentProfile {
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
  dateOfBirth: string;
  address: string;
  emergencyContact: string;
  enrollmentDate: string;
  paymentStatus: 'Pending' | 'Paid' | 'Overdue';
  approvalStatus: 'Pending' | 'Approved' | 'Rejected';
  enrollmentDeadline: string;
}

interface EnrollmentFormData {
  courseId: string;
  notes: string;
  priority: 'Low' | 'Medium' | 'High';
}

interface PaymentInfo {
  amount: number;
  status: 'Pending' | 'Paid';
  dueDate: string;
  paidDate?: string;
  reference?: string;
}

interface PrerequisiteValidation {
  courseId: string;
  canEnroll: boolean;
  reason?: string;
}

interface EnrollmentNotification {
  id: string;
  type: 'success' | 'warning' | 'error' | 'info';
  message: string;
  timestamp: Date;
}

@Component({
  selector: 'app-enrollment',
  imports: [CommonModule, FormsModule],
  templateUrl: './enrollment.html',
  styleUrl: './enrollment.css',
})
export class Enrollment {
  // Student type: new or existing
  isNewStudent = true;
  isReEnlistment = false;
  
  // Workflow status
  workflowStep: 'type-select' | 'program-select' | 'registration' | 'payment' | 'approval' | 'dashboard' = 'type-select';
  
  // Available Programs
  programs = [
    { id: 'IT', name: 'BS Information Technology', code: 'IT', description: 'Learn programming, web development, and IT systems' },
    { id: 'CS', name: 'BS Computer Science', code: 'CS', description: 'Advanced computer science and algorithms' },
    { id: 'BIS', name: 'BS Information Systems', code: 'BIS', description: 'Business and IT integration' },
    { id: 'CE', name: 'BS Civil Engineering', code: 'CE', description: 'Infrastructure and construction' }
  ];

  selectedProgram: string | null = null;

  // Registration Form Data
  registrationForm = {
    firstName: '',
    lastName: '',
    email: '',
    phone: '',
    dateOfBirth: '',
    address: '',
    emergencyContact: '',
    emergencyPhone: ''
  };

  // Student Profile
  student: StudentProfile = {
    id: '',
    firstName: '',
    lastName: '',
    email: '',
    phone: '',
    program: '',
    yearLevel: '1st Year',
    gpa: 0,
    studentType: 'New',
    enrollmentStatus: 'Pending',
    dateOfBirth: '',
    address: '',
    emergencyContact: '',
    enrollmentDate: '',
    paymentStatus: 'Pending',
    approvalStatus: 'Pending',
    enrollmentDeadline: ''
  };

  currentSemester = '1st Semester, AY 2024-2025';

  availableCourses: Course[] = [
    {
      id: '1',
      code: 'CS111',
      name: 'Introduction to Programming',
      credits: 3,
      instructor: 'Engr. Maria Santos',
      schedule: 'MWF 8:00 AM - 9:30 AM',
      room: 'Room 301 (Science Building)',
      capacity: 50,
      enrolled: 48,
      semester: '1st Semester, AY 2024-2025',
      description: 'Fundamentals of programming using Python',
      department: 'Information Technology'
    },
    {
      id: '2',
      code: 'CS112',
      name: 'Web Development Basics',
      credits: 3,
      instructor: 'Engr. Juan Reyes',
      schedule: 'TTh 10:00 AM - 11:30 AM',
      room: 'Lab 202 (IT Building)',
      capacity: 35,
      enrolled: 33,
      semester: '1st Semester, AY 2024-2025',
      description: 'HTML, CSS, and JavaScript fundamentals',
      department: 'Information Technology'
    },
    {
      id: '3',
      code: 'MATH101',
      name: 'Discrete Mathematics',
      credits: 4,
      instructor: 'Engr. Anna Garcia',
      schedule: 'MWF 9:45 AM - 11:15 AM',
      room: 'Room 205 (Science Building)',
      capacity: 40,
      enrolled: 38,
      semester: '1st Semester, AY 2024-2025',
      description: 'Sets, logic, and mathematical proofs',
      department: 'Mathematics'
    },
    {
      id: '4',
      code: 'CS113',
      name: 'Database Fundamentals',
      credits: 3,
      instructor: 'Engr. Luis Rodriguez',
      schedule: 'TTh 1:00 PM - 2:30 PM',
      room: 'Room 401 (IT Building)',
      capacity: 40,
      enrolled: 39,
      semester: '1st Semester, AY 2024-2025',
      description: 'Relational databases and SQL',
      department: 'Information Technology'
    },
    {
      id: '5',
      code: 'ENG101',
      name: 'English Composition',
      credits: 3,
      instructor: 'Prof. Sarah Kim',
      schedule: 'MWF 1:00 PM - 2:30 PM',
      room: 'Room 101 (Liberal Arts Building)',
      capacity: 45,
      enrolled: 42,
      semester: '1st Semester, AY 2024-2025',
      description: 'Academic writing and communication skills',
      department: 'English'
    },
    {
      id: '6',
      code: 'PE101',
      name: 'Physical Education 1',
      credits: 2,
      instructor: 'Coach Robert Lee',
      schedule: 'MWF 3:00 PM - 4:00 PM',
      room: 'Sports Complex',
      capacity: 60,
      enrolled: 55,
      semester: '1st Semester, AY 2024-2025',
      description: 'Basic fitness and wellness',
      department: 'Physical Education'
    }
  ];

  enrolledCourses: StudentCourse[] = [];
  completedCourses: StudentCourse[] = [];

  currentView: 'dashboard' | 'enroll' | 'manage' = 'dashboard';
  showEnrollModal = false;
  showDropModal = false;
  showConfirmationModal = false;
  selectedCourseForEnroll: Course | null = null;
  selectedCourseForDrop: StudentCourse | null = null;
  searchQuery = '';
  filterDepartment: string | null = null;

  // Form data
  enrollmentForm: EnrollmentFormData = {
    courseId: '',
    notes: '',
    priority: 'Medium'
  };

  // Registration validation
  registrationError: string | null = null;
  isSubmittingRegistration = false;

  // Payment tracking
  paymentInfo: PaymentInfo = {
    amount: 25000, // Sample tuition fee
    status: 'Pending',
    dueDate: '2025-02-28'
  };
  isProcessingPayment = false;

  // Approval tracking
  isApprovalPending = false;
  approvalMessage = '';

  // Course limits
  minCredits = 9;
  maxCredits = 18;
  creditWarning = '';

  // Deadline tracking
  addDropDeadline = '2025-02-28';
  enrollmentDeadline = '2025-02-15';
  isDeadlineApproaching = false;
  daysUntilDeadline = 0;

  // Notifications
  notifications: EnrollmentNotification[] = [];

  // Prerequisite validation
  prerequisites: { [courseId: string]: PrerequisiteValidation } = {};

  selectProgram(programId: string): void {
    this.selectedProgram = programId;
    this.workflowStep = 'registration';
  }

  selectStudentType(type: 'new' | 'existing'): void {
    this.isNewStudent = type === 'new';
    this.isReEnlistment = type === 'existing';
    
    if (this.isNewStudent) {
      // New student workflow
      this.workflowStep = 'program-select';
      this.selectedProgram = null;
    } else {
      // Existing student workflow - search for their profile
      this.workflowStep = 'dashboard';
      this.loadExistingStudentProfile();
    }
  }

  loadExistingStudentProfile(): void {
    // Simulate loading existing student profile
    this.student = {
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
      dateOfBirth: '2003-05-15',
      address: 'Manila, Philippines',
      emergencyContact: 'Juan Santos',
      enrollmentDate: '2023-06-15',
      paymentStatus: 'Paid',
      approvalStatus: 'Approved',
      enrollmentDeadline: '2025-02-15'
    };
    this.calculateDeadlineInfo();
    this.addNotification('success', 'Existing student profile loaded successfully');
  }

  backToProgram(): void {
    this.workflowStep = 'program-select';
    this.selectedProgram = null;
    this.registrationError = null;
  }

  submitRegistration(): void {
    // Validate form
    if (!this.registrationForm.firstName.trim() || !this.registrationForm.lastName.trim()) {
      this.registrationError = 'First name and last name are required';
      return;
    }
    if (!this.registrationForm.email.includes('@')) {
      this.registrationError = 'Valid email is required';
      return;
    }
    if (!this.registrationForm.phone.trim()) {
      this.registrationError = 'Phone number is required';
      return;
    }

    this.isSubmittingRegistration = true;
    
    // Simulate submission
    setTimeout(() => {
      // Create student profile
      const programName = this.programs.find(p => p.id === this.selectedProgram)?.name || '';
      const studentId = 'STU-' + Date.now().toString().slice(-6);
      
      this.student = {
        id: studentId,
        firstName: this.registrationForm.firstName,
        lastName: this.registrationForm.lastName,
        email: this.registrationForm.email,
        phone: this.registrationForm.phone,
        program: programName,
        yearLevel: '1st Year',
        gpa: 0,
        studentType: 'New',
        enrollmentStatus: 'Pending',
        dateOfBirth: this.registrationForm.dateOfBirth,
        address: this.registrationForm.address,
        emergencyContact: this.registrationForm.emergencyContact,
        enrollmentDate: new Date().toISOString().split('T')[0],
        paymentStatus: 'Pending',
        approvalStatus: 'Pending',
        enrollmentDeadline: this.enrollmentDeadline
      };

      this.isSubmittingRegistration = false;
      this.workflowStep = 'payment';
      this.enrolledCourses = [];
      this.registrationError = null;
      this.addNotification('success', 'Registration submitted successfully. Please complete payment.');
    }, 1500);
  }

  processPayment(): void {
    if (!this.student.id) return;
    
    this.isProcessingPayment = true;
    
    // Simulate payment processing
    setTimeout(() => {
      this.paymentInfo.status = 'Paid';
      this.paymentInfo.paidDate = new Date().toISOString().split('T')[0];
      this.paymentInfo.reference = 'REF-' + Date.now().toString().slice(-8);
      this.student.paymentStatus = 'Paid';
      this.isProcessingPayment = false;
      this.workflowStep = 'approval';
      this.isApprovalPending = true;
      this.addNotification('success', 'Payment processed successfully!');
      
      // Simulate approval after 2 seconds
      setTimeout(() => {
        this.approveEnrollment();
      }, 2000);
    }, 2000);
  }

  approveEnrollment(): void {
    if (!this.student.id) return;
    
    this.student.approvalStatus = 'Approved';
    this.student.enrollmentStatus = 'Enrolled';
    this.isApprovalPending = false;
    this.approvalMessage = 'Your registration has been approved!';
    this.calculateDeadlineInfo();
    this.addNotification('success', 'Registration approved! You can now enroll in courses.');
  }

  proceedToDashboard(): void {
    this.workflowStep = 'dashboard';
  }

  get totalEnrolledCredits(): number {
    return this.enrolledCourses
      .filter(c => c.status === 'Enrolled' || c.status === 'Pending')
      .reduce((sum, course) => sum + course.credits, 0);
  }

  get pendingCourses(): StudentCourse[] {
    return this.enrolledCourses.filter(c => c.status === 'Pending');
  }

  get approvedCourses(): StudentCourse[] {
    return this.enrolledCourses.filter(c => c.status === 'Enrolled');
  }

  get availableCoursesFiltered(): Course[] {
    return this.availableCourses.filter(course => {
      const isAlreadyEnrolled = this.enrolledCourses.some(
        ec => ec.courseId === course.id && (ec.status === 'Enrolled' || ec.status === 'Pending')
      );
      const matchesSearch = course.name.toLowerCase().includes(this.searchQuery.toLowerCase()) ||
                           course.code.toLowerCase().includes(this.searchQuery.toLowerCase());
      const matchesDept = this.filterDepartment === null || course.department === this.filterDepartment;
      return !isAlreadyEnrolled && matchesSearch && matchesDept;
    });
  }

  get departments(): string[] {
    return [...new Set(this.availableCourses.map(c => c.department))];
  }

  openEnrollModal(course: Course): void {
    this.selectedCourseForEnroll = course;
    this.enrollmentForm = {
      courseId: course.id,
      notes: '',
      priority: 'Medium'
    };
    this.showEnrollModal = true;
  }

  closeEnrollModal(): void {
    this.showEnrollModal = false;
    this.selectedCourseForEnroll = null;
  }

  openDropModal(course: StudentCourse): void {
    this.selectedCourseForDrop = course;
    this.showDropModal = true;
  }

  closeDropModal(): void {
    this.showDropModal = false;
    this.selectedCourseForDrop = null;
  }

  confirmEnrollment(): void {
    if (!this.selectedCourseForEnroll) return;

    // Validate course limit
    const newTotal = this.totalEnrolledCredits + this.selectedCourseForEnroll.credits;
    if (newTotal > this.maxCredits) {
      this.addNotification('error', `Cannot exceed ${this.maxCredits} credits. Current: ${this.totalEnrolledCredits}, Adding: ${this.selectedCourseForEnroll.credits}`);
      this.closeEnrollModal();
      return;
    }

    // Check deadline
    if (new Date() > new Date(this.enrollmentDeadline)) {
      this.addNotification('error', 'Enrollment deadline has passed. Contact registrar for late enrollment.');
      this.closeEnrollModal();
      return;
    }

    // Validate prerequisites (simplified)
    if (!this.validatePrerequisites(this.selectedCourseForEnroll.id)) {
      this.addNotification('warning', 'You may not meet prerequisites for this course. Please contact your advisor.');
    }

    const studentCourse: StudentCourse = {
      id: 'EN-' + Date.now(),
      courseId: this.selectedCourseForEnroll.id,
      code: this.selectedCourseForEnroll.code,
      name: this.selectedCourseForEnroll.name,
      credits: this.selectedCourseForEnroll.credits,
      instructor: this.selectedCourseForEnroll.instructor,
      schedule: this.selectedCourseForEnroll.schedule,
      room: this.selectedCourseForEnroll.room,
      enrollmentDate: new Date().toISOString().split('T')[0],
      status: 'Pending'
    };
    this.enrolledCourses.push(studentCourse);
    this.closeEnrollModal();
    this.showConfirmationModal = true;
    this.addNotification('success', `${studentCourse.code} added to your enrollment (Pending approval)`);
  }

  confirmDrop(): void {
    if (!this.selectedCourseForDrop) return;

    const courseIndex = this.enrolledCourses.findIndex(c => c.id === this.selectedCourseForDrop?.id);
    if (courseIndex !== -1) {
      this.enrolledCourses.splice(courseIndex, 1);
    }
    this.closeDropModal();
  }

  approveAllPendingCourses(): void {
    this.pendingCourses.forEach(course => {
      course.status = 'Enrolled';
    });
  }

  getAvailableSeats(course: Course): number {
    return course.capacity - course.enrolled;
  }

  isCourseAlmostFull(course: Course): boolean {
    return this.getAvailableSeats(course) < 5;
  }

  canEnroll(course: Course): boolean {
    return this.getAvailableSeats(course) > 0;
  }

  closeConfirmationModal(): void {
    this.showConfirmationModal = false;
  }

  // Helper methods
  calculateDeadlineInfo(): void {
    const today = new Date();
    const deadline = new Date(this.enrollmentDeadline);
    const daysLeft = Math.ceil((deadline.getTime() - today.getTime()) / (1000 * 60 * 60 * 24));
    this.daysUntilDeadline = daysLeft;
    this.isDeadlineApproaching = daysLeft <= 7 && daysLeft > 0;
  }

  validatePrerequisites(courseId: string): boolean {
    // Simplified prerequisite validation
    // In a real system, this would check student's completed courses
    return true;
  }

  addNotification(type: EnrollmentNotification['type'], message: string): void {
    this.notifications.push({
      id: 'notif-' + Date.now(),
      type,
      message,
      timestamp: new Date()
    });

    // Auto-remove notifications after 5 seconds
    setTimeout(() => {
      const index = this.notifications.findIndex(n => n.message === message);
      if (index !== -1) {
        this.notifications.splice(index, 1);
      }
    }, 5000);
  }

  dismissNotification(id: string): void {
    const index = this.notifications.findIndex(n => n.id === id);
    if (index !== -1) {
      this.notifications.splice(index, 1);
    }
  }

  canAddCourse(): boolean {
    return this.totalEnrolledCredits < this.maxCredits && 
           new Date() <= new Date(this.enrollmentDeadline);
  }

  canDropCourse(): boolean {
    return new Date() <= new Date(this.addDropDeadline);
  }

  checkCreditWarning(): void {
    if (this.totalEnrolledCredits < this.minCredits) {
      this.creditWarning = `Minimum ${this.minCredits} credits required. Current: ${this.totalEnrolledCredits}`;
    } else if (this.totalEnrolledCredits > this.maxCredits) {
      this.creditWarning = `Exceeds maximum ${this.maxCredits} credits. Current: ${this.totalEnrolledCredits}`;
    } else {
      this.creditWarning = '';
    }
  }

  getSelectedProgramName(): string {
    if (!this.selectedProgram) return '';
    const program = this.programs.find(p => p.id === this.selectedProgram);
    return program ? program.name : '';
  }

  getSelectedProgramCode(): string {
    if (!this.selectedProgram) return '';
    const program = this.programs.find(p => p.id === this.selectedProgram);
    return program ? program.code : '';
  }
}
