// src/app/admin/admin.routes.ts
// ─────────────────────────────────────────────────────────────────────────────
// Child routes for the Admin portal.
// ─────────────────────────────────────────────────────────────────────────────
import { Routes } from '@angular/router';

export const ADMIN_ROUTES: Routes = [
  {
    path: 'dashboard',
    loadComponent: () =>
      import('./admin-dashboard/admin-dashboard').then(m => m.AdminDashboard),
  },
  {
    path: 'courses',
    loadComponent: () =>
      import('./courses/courses').then(m => m.Courses),
  },
  {
    path: 'faculty',
    loadComponent: () =>
      import('./faculty/faculty').then(m => m.Faculty),
  },
  {
    path: 'class-sections',
    loadComponent: () =>
      import('./class-sections/class-sections').then(m => m.ClassSections),
  },
  {
    path: 'grading',
    loadComponent: () =>
      import('./grading/grading').then(m => m.Grading),
  },
  {
    path: 'grade-submission',
    loadComponent: () =>
      import('./grade-submission/grade-submission').then(m => m.GradeSubmission),
  },
  {
    path: 'levels',
    loadComponent: () =>
      import('./levels/levels').then(m => m.Levels),
  },
 
  {
    path: 'analytics',
    loadComponent: () =>
      import('./analytics/analytics').then(m => m.Analytics),
  },
  {
    path: 'reports',
    loadComponent: () =>
      import('./reports/reports').then(m => m.Reports),
  },
  {
    path: 'audit-logs',
    loadComponent: () =>
      import('./audit-logs/audit-logs').then(m => m.AuditLogs),
  },
  {
    path: 'staff-accounts',
    loadComponent: () =>
      import('./staff-accounts/staff-accounts').then(m => m.StaffAccounts),
  },
  // Default redirect
  { path: '', redirectTo: 'dashboard', pathMatch: 'full' },
];