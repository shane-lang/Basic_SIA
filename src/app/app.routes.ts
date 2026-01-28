import { Routes } from '@angular/router';
import { Admin } from './admin/admin';
import { AdminDashboard } from './admin/admin-dashboard/admin-dashboard';
import { Students } from './admin/students/students';
import { Faculty } from './admin/faculty/faculty';
import { Courses } from './admin/courses/courses';
import { ClassSections } from './admin/class-sections/class-sections';
import { Grading } from './admin/grading/grading';
import { GradeSubmission } from './admin/grade-submission/grade-submission';
import { Reports } from './admin/reports/reports';
import { Analytics } from './admin/analytics/analytics';
import { Settings } from './admin/settings/settings';
import { AuditLogs } from './admin/audit-logs/audit-logs';
import { StudentLayout } from './student_view/student-layout/student-layout';
import { Enrollment } from './student_view/enrollment/enrollment';
import { Profile } from './student_view/profile/profile';
import { StudentDashboard } from './student_view/dashboard/dashboard';
import { Schedule } from './student_view/schedule/schedule';
import { About } from './student_view/about/about';
import { AccountingLayout } from './accounting/accounting-layout/accounting-layout';
import { Gcash } from './accounting/gcash/gcash';
import { ASR } from './accounting/asr/asr';
import { Report } from './accounting/report/report';
import { PermitGeneration } from './accounting/permit-generation/permit-generation';

export const routes: Routes = [
  {
    // "Switch" entry point: default app route goes to Student portal
    // (Admin portal is mounted separately under /admin)
    path: '',
    pathMatch: 'full',
    redirectTo: 'student/dashboard',
  },
  {
    path: 'student',
    component: StudentLayout,
    children: [
      { path: '', pathMatch: 'full', redirectTo: 'dashboard' },
      { path: 'dashboard', component: StudentDashboard },
      { path: 'profile', component: Profile },
      { path: 'enrollment', component: Enrollment },
      { path: 'schedule', component: Schedule },
      { path: 'about', component: About },
    ],
  },
  {
    path: 'admin',
    component: Admin,
    children: [
      { path: '', redirectTo: 'dashboard', pathMatch: 'full' },
      { path: 'dashboard', component: AdminDashboard },
      { path: 'students', component: Students },
      { path: 'faculty', component: Faculty },
      { path: 'courses', component: Courses },
      { path: 'class-sections', component: ClassSections },
      { path: 'grading', component: Grading },
      { path: 'grade-submission', component: GradeSubmission },
      { path: 'reports', component: Reports },
      { path: 'analytics', component: Analytics },
      { path: 'settings', component: Settings },
      { path: 'audit-logs', component: AuditLogs },
    ],
  },
  {
    path: 'accounting',
    component: AccountingLayout,
    children: [
      { path: '', redirectTo: 'dashboard', pathMatch: 'full' },
      { path: 'dashboard', component: Gcash }, // Replace with Accounting Dashboard
      { path: 'gcash', component: Gcash },
      { path: 'transactions', component: Gcash }, // Replace with Transactions component
      { path: 'reports', component: Report },
      { path: 'permits', component: PermitGeneration }, // Replace with Permit Reports component
      { path: 'income', component: Analytics }, // Replace with Income Analysis component
      { path: 'settings', component: Settings },
    ],
  },
  // Fallback
  { path: '**', redirectTo: 'student/dashboard' },
];