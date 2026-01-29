import { Routes } from '@angular/router';
import { LoginComponent } from './login/login';
import { AuthGuard } from './guards/auth-guard';
import { StudentLayout } from './student_view/student-layout/student-layout';
import { Admin } from './admin/admin';
import { AccountingLayout } from './accounting/accounting-layout/accounting-layout';
import { RegistrarLayoutComponent } from './registrar/registrar-layout/registrar-layout';

//  student sub-components
import { StudentDashboard } from './student_view/dashboard/dashboard';
import { Enrollment } from './student_view/enrollment/enrollment';
import { Profile } from './student_view/profile/profile';
import { Schedule } from './student_view/schedule/schedule';
import { About } from './student_view/about/about';

//  admin sub-components
import { AdminDashboard } from './admin/admin-dashboard/admin-dashboard';
import { Students } from './admin/students/students';
import { Courses } from './admin/courses/courses';
import { Faculty } from './admin/faculty/faculty';
import { Grading } from './admin/grading/grading';
import { Analytics } from './admin/analytics/analytics';
import { AuditLogs } from './admin/audit-logs/audit-logs';
import { Settings } from './admin/settings/settings';

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

export const routes: Routes = [
  { path: 'login', component: LoginComponent },
  
  // Student Routes
  { 
    path: 'student', 
    component: StudentLayout, 
    canActivate: [AuthGuard], 
    data: { role: 'student' },
    children: [
      { path: 'dashboard', component: StudentDashboard },
      { path: 'enrollment', component: Enrollment },
      { path: 'profile', component: Profile },
      { path: 'schedule', component: Schedule },
      { path: 'about', component: About },
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
      { path: 'dashboard', component: Admin },
      { path: 'students', component: Students },
      { path: 'courses', component: Courses },
      { path: 'faculty', component: Faculty },
      { path: 'grading', component: Grading },
      { path: 'analytics', component: Analytics },
      { path: 'audit-logs', component: AuditLogs },
      { path: 'settings', component: Settings },
      { path: '', redirectTo: 'dashboard', pathMatch: 'full' }
    ]
  },
  
  // Accounting Routes
  { 
    path: 'accounting', 
    component: AccountingLayout, 
    canActivate: [AuthGuard], 
    data: { role: 'accounting' },
    children: [
      { path: 'asr', component: ASR },
      { path: 'gcash', component: Gcash },
      { path: 'permit-generation', component: PermitGeneration },
      { path: 'report', component: Report },
      { path: '', redirectTo: 'asr', pathMatch: 'full' }
    ]
  },

  // Registrar Routes
  { 
    path: 'registrar', 
    component: RegistrarLayoutComponent, 
    canActivate: [AuthGuard], 
    data: { role: 'registrar' },
    children: [
      { path: 'dashboard', component: RegistrarDashboardComponent },
      { path: 'manage-subjects', component: ManageSubjectsComponent },
      { path: 'add-subject', component: AddSubjectComponent },
      { path: 'drop-subject', component: DropSubjectComponent },
      { path: '', redirectTo: 'dashboard', pathMatch: 'full' }
    ]
  },
  
  { path: '', redirectTo: '/login', pathMatch: 'full' },
  { path: '**', redirectTo: '/login' }
];