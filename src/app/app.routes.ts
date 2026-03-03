import { Routes } from '@angular/router';
import { LoginComponent } from './login/login';
import { AuthGuard } from './guards/auth-guard';
import { StudentLayout } from './student_view/student-layout/student-layout';
import { Admin } from './admin/admin';
import { AccountingLayout } from './accounting/accounting-layout/accounting-layout';
import { RegistrarLayout } from './registrar/registrar-layout/registrar-layout';

//  student sub-components
import { StudentDashboard } from './student_view/dashboard/dashboard';
import { Enrollment } from './student_view/enrollment/enrollment';
import { Profile } from './student_view/profile/profile';
import { Schedule } from './student_view/schedule/schedule';
import { About } from './student_view/about/about';
import { PaymentSchedule } from './student_view/payment-schedule/payment-schedule';
import { Grades } from './student_view/grades/grades';

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

//  accounting sub-components
import { ASR } from './accounting/asr/asr';
import { Gcash } from './accounting/gcash/gcash';
import { PermitGeneration } from './accounting/permit-generation/permit-generation';
import { Report } from './accounting/report/report';

// registrar sub-components
import { RegistrarDashboardComponent } from './registrar/registrar-dashboard/registrar-dashboard';
import { ManageSubjectsComponent } from './registrar/manage-subjects/manage-subjects';
import { AddSubjectComponent } from './registrar/add-subject/add-subject';
import { DropSubjectComponent } from './registrar/drop-subject/drop-subject';
import { StudentEnrollmentReviewComponent } from './registrar/student-enrollment-review/student-enrollment-review';
import { ClassSections } from './admin/class-sections/class-sections';
import { GradeSubmission } from './registrar/grade-submission/grade-submission';
import { Reports } from './admin/reports/reports';
import { Accounting } from './accounting/accounting/accounting';
import { TorEvaluation } from './registrar/tor-evaluation/tor-evaluation';
import { AddDropComponent } from './registrar/add-drop/add-drop';
import { StudentAddDropComponent } from './student_view/student-add-drop/student-add-drop';

export const routes: Routes = [
  { path: 'login', component: LoginComponent },

  // Student Routes
  {
    path: 'student',
    component: StudentLayout,
    canActivate: [AuthGuard],
    data: { role: 'student' },
    children: [
      { path: 'dashboard',  component: StudentDashboard },
      { path: 'enrollment', component: Enrollment },
      { path: 'profile',    component: Profile },
      { path: 'payment-schedule', component: PaymentSchedule },
      { path: 'schedule',   component: Schedule },
      { path: 'grades',     component: Grades },
      { path: 'add-drop',   component: StudentAddDropComponent },
      { path: 'about',      component: About },
      { path: '', redirectTo: 'dashboard', pathMatch: 'full' }
    ]
  },

  // Admin Routes
  {
    path: 'admin',
    component: Admin,
    canActivate: [AuthGuard],
    data: { role: 'admin' },
    children: [
      { path: 'dashboard',       component: AdminDashboard },
      { path: 'students',        component: Students },
      { path: 'courses',         component: Courses },
      { path: 'faculty',         component: Faculty },
      { path: 'grading',         component: Grading },
  
      { path: 'analytics',       component: Analytics },
      { path: 'reports',         component: Reports },
      { path: 'audit-logs',      component: AuditLogs },
      { path: 'settings',        component: Settings },
      { path: 'class-sections',  component: ClassSections },
      { path: 'levels',          component: Levels },
      { path: 'rooms',  component: Rooms },
      { path: '', redirectTo: 'courses', pathMatch: 'full' }
    ]
  },

  // Accounting Routes
  {
    path: 'accounting',
    component: AccountingLayout,
    canActivate: [AuthGuard],
    data: { role: 'accounting' },
    children: [
      { path: 'asr',        component: ASR },
      { path: 'gcash',      component: Gcash },
      { path: 'accounting', component: Accounting },
      { path: 'permits',    component: PermitGeneration },
      { path: 'report',     component: Report },
      { path: '', redirectTo: 'asr', pathMatch: 'full' }
    ]
  },

  // Registrar Routes
  {
    path: 'registrar',
    component: RegistrarLayout,
    canActivate: [AuthGuard],
    data: { role: 'registrar' },
    children: [
      { path: 'dashboard',                  component: RegistrarDashboardComponent },
      { path: 'manage-subjects',            component: ManageSubjectsComponent },
      { path: 'add-subject',                component: AddSubjectComponent },
      { path: 'drop-subject',               component: DropSubjectComponent },
      { path: 'students',                   component: StudentEnrollmentReviewComponent },
      { path: 'student-enrollment-review',  component: StudentEnrollmentReviewComponent },
      { path: 'tor-evaluation',             component: TorEvaluation },
      { path: 'add-drop',                   component: AddDropComponent },
      { path: 'grade-submission',           component: GradeSubmission },
      { path: '', redirectTo: 'dashboard', pathMatch: 'full' }
    ]
  },

  { path: '',   redirectTo: '/login', pathMatch: 'full' },
  { path: '**', redirectTo: '/login' }
];