import { Routes } from '@angular/router';
import { LoginComponent } from './login/login';
import { PortalLoginComponent } from './login/portal-login/portal-login';
import { authGuard, studentLoginGuard } from './guards/auth-guard';
import { StudentLayout } from './student_view/student-layout/student-layout';
import { Admin } from './admin/admin';
import { AccountingLayout } from './accounting/accounting-layout/accounting-layout';
import { RegistrarLayout } from './registrar/registrar-layout/registrar-layout';
import { InstructorLayout } from './instructor/instructor-layout/instructor-layout';

//  student sub-components
import { StudentDashboard } from './student_view/dashboard/dashboard';
import { Enrollment } from './student_view/enrollment/enrollment';
import { Profile } from './student_view/profile/profile';
import { Schedule } from './student_view/schedule/schedule';
import { About } from './student_view/about/about';
import { PaymentSchedule } from './student_view/payment-schedule/payment-schedule';
import { Grades } from './student_view/grades/grades';
import { StudentAddDropComponent } from './student_view/student-add-drop/student-add-drop';
import { Curriculum } from './student_view/curriculum/curriculum';
import { CoeComponent } from './student_view/coe-student/coe-student';

//  admin sub-components
import { AdminDashboard } from './admin/admin-dashboard/admin-dashboard';
import { Students } from './admin/students/students';
import { Courses } from './admin/courses/courses';
import { Faculty } from './admin/faculty/faculty';
import { Grading } from './admin/grading/grading';
import { Analytics } from './admin/analytics/analytics';
import { AuditLogs } from './admin/audit-logs/audit-logs';
import { Settings } from './admin/settings/settings';
import { Rooms } from './admin/rooms/rooms';
import { Levels } from './admin/levels/levels';
import { ClassSections } from './admin/class-sections/class-sections';
import { Reports } from './admin/reports/reports';
import { AnnouncementsAdmin } from './admin/announcements/announcements';
import { StaffAccounts } from './admin/staff-accounts/staff-accounts';

//  accounting sub-components

import { Gcash } from './accounting/gcash/gcash';
import { PermitGeneration } from './accounting/permit-generation/permit-generation';
import { Report } from './accounting/report/report';
import { FeeConfigComponent } from './accounting/fee-config/fee-config';
import { CashierComponent } from './accounting/cashier/cashier';
import { Accounting } from './accounting/accounting/accounting';
// ── NEW: Scholarship & Subject Fee Log ──────────────────────────────────────
import { ScholarshipComponent } from './accounting/scholarship/scholarship';
import { SubjectFeeLogComponent } from './accounting/subject-fee-log/subject-fee-log';
import { PendingScholarshipsComponent } from './accounting/pending-scholarships/pending-scholarships';

// registrar sub-components
import { RegistrarDashboardComponent } from './registrar/registrar-dashboard/registrar-dashboard';
import { ManageSubjectsComponent } from './registrar/manage-subjects/manage-subjects';
import { AddSubjectComponent } from './registrar/add-subject/add-subject';
import { DropSubjectComponent } from './registrar/drop-subject/drop-subject';
import { StudentEnrollmentReviewComponent } from './registrar/student-enrollment-review/student-enrollment-review';
import { GradeSubmission } from './registrar/grade-submission/grade-submission';
import { TorEvaluation } from './registrar/tor-evaluation/tor-evaluation';
import { AddDropComponent } from './registrar/add-drop/add-drop';
import { MasterlistComponent } from './registrar/masterlist/masterlist';
import { StudentMasterlistComponent } from './registrar/student-masterlist/student-masterlist';
import { StudentInfoComponent } from './registrar/student-info/student-info';
import { CoeGeneratorComponent } from './registrar/coe-generator/coe-generator';
import { PendingRegistrationsComponent } from './registrar/pending-registrations/pending-registrations';
// ── NEW: Enrollment History ──────────────────────────────────────────────────
import { EnrollmentHistoryComponent } from './registrar/enrollment-history/enrollment-history';
// ── NEW: Block Capacity Viewer ───────────────────────────────────────────────
import { BlockCapacityComponent } from './registrar/block-capacity/block-capacity';


// instructor sub-components
import { InstructorCourses } from './instructor/courses/courses';
import { InstructorGrading } from './instructor/grading/grading';
import { InstructorProfile } from './instructor/profile/profile';

export const routes: Routes = [

  // ── /login redirect → /student-login ─────────────────────────────────────
  { path: 'login', redirectTo: 'student-login', pathMatch: 'full' },

  // ── Student login — guarded so active sessions auto-redirect to dashboard ──
  { path: 'student-login', component: LoginComponent, canActivate: [studentLoginGuard] },

  // ── Staff portal logins ───────────────────────────────────────────────────
  { path: 'admin/login',      component: PortalLoginComponent, data: { portal: 'admin' } },
  { path: 'accounting/login', component: PortalLoginComponent, data: { portal: 'accounting' } },
  { path: 'registrar/login',  component: PortalLoginComponent, data: { portal: 'registrar' } },
  { path: 'faculty/login',    component: PortalLoginComponent, data: { portal: 'faculty' } },

  // Student Routes
  {
    path: 'student',
    component: StudentLayout,
    canActivate: [authGuard('student')],
    data: { role: 'student' },
    children: [
      { path: 'dashboard',        component: StudentDashboard },
      { path: 'enrollment',       component: Enrollment },
      { path: 'profile',          component: Profile },
      { path: 'payment-schedule', component: PaymentSchedule },
      { path: 'schedule',         component: Schedule },
      { path: 'grades',           component: Grades },
      { path: 'add-drop',         component: StudentAddDropComponent },
      { path: 'curriculum',       component: Curriculum },
      { path: 'about',            component: About },
      { path: 'coe',              component: CoeComponent },
      { path: '', redirectTo: 'dashboard', pathMatch: 'full' }
    ]
  },

  // Admin Routes — accessible by both 'admin' and 'registrar' roles
  {
    path: 'admin',
    component: Admin,
    canActivate: [authGuard('admin')],
    data: { role: 'admin' },
    children: [
      { path: 'dashboard',      component: AdminDashboard },
      { path: 'students',       component: Students },
      { path: 'courses',        component: Courses },
      { path: 'faculty',        component: Faculty },
      { path: 'grading',        component: Grading },
      { path: 'analytics',      component: Analytics },
      { path: 'reports',        component: Reports },
      { path: 'audit-logs',     component: AuditLogs },
      { path: 'settings',       component: Settings },
      { path: 'class-sections', component: ClassSections },
      { path: 'levels',         component: Levels },
      { path: 'rooms',          component: Rooms },
      { path: 'announcements',  component: AnnouncementsAdmin },
      { path: 'staff-accounts', component: StaffAccounts },
      { path: '', redirectTo: 'students', pathMatch: 'full' }
    ]
  },

  // Accounting Routes
  {
    path: 'accounting',
    component: AccountingLayout,
    canActivate: [authGuard('accounting')],
    data: { role: 'accounting' },
    children: [
     
      { path: 'gcash',         component: Gcash },
      { path: 'accounting',    component: Accounting },
      { path: 'permits',       component: PermitGeneration },
      { path: 'report',        component: Report },
      { path: 'fee-config',    component: FeeConfigComponent },
      { path: 'cashier',       component: CashierComponent },
      // ── NEW ─────────────────────────────────────────────────────────────
      { path: 'scholarship',          component: ScholarshipComponent },
      { path: 'subject-fees',         component: SubjectFeeLogComponent },
      { path: 'pending-scholarships', component: PendingScholarshipsComponent },
      { path: '', redirectTo: 'accounting', pathMatch: 'full' }
    ]
  },

  // Registrar Routes
  {
    path: 'registrar',
    component: RegistrarLayout,
    canActivate: [authGuard('registrar')],
    data: { role: 'registrar' },
    children: [
      { path: 'dashboard',                 component: RegistrarDashboardComponent },
      { path: 'manage-subjects',           component: ManageSubjectsComponent },
      { path: 'add-subject',               component: AddSubjectComponent },
      { path: 'drop-subject',              component: DropSubjectComponent },
      { path: 'students',                  component: StudentEnrollmentReviewComponent },
      { path: 'student-enrollment-review', component: StudentEnrollmentReviewComponent },
      { path: 'tor-evaluation',            component: TorEvaluation },
      { path: 'add-drop',                  component: AddDropComponent },
      { path: 'grade-submission',          component: GradeSubmission },
      { path: 'masterlist',                component: MasterlistComponent },
      { path: 'student_masterlist',        component: StudentMasterlistComponent },
      { path: 'student-info',              component: StudentInfoComponent },
      { path: 'coe',                       component: CoeGeneratorComponent },
      { path: 'pending-registrations',     component: PendingRegistrationsComponent },
   
      // ── NEW ─────────────────────────────────────────────────────────────
      { path: 'enrollment-history',        component: EnrollmentHistoryComponent },
      { path: 'block-capacity',            component: BlockCapacityComponent },
      { path: '', redirectTo: 'tor-evaluation', pathMatch: 'full' }
    ]
  },

  // Instructor / Faculty Routes
  {
    path: 'instructor',
    component: InstructorLayout,
    canActivate: [authGuard('faculty')],
    data: { role: 'faculty' },
    children: [
      { path: 'courses', component: InstructorCourses },
      { path: 'grading', component: InstructorGrading },
      { path: 'profile', component: InstructorProfile },
      { path: '', redirectTo: 'courses', pathMatch: 'full' }
    ]
  },

  { path: '',   redirectTo: '/student-login', pathMatch: 'full' },
  { path: '**', redirectTo: '/student-login' }
];