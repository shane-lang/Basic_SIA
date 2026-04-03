// src/app/accounting/accounting.routes.ts
// ─────────────────────────────────────────────────────────────────────────────
// Child routes for the Accounting portal.
// These load inside <router-outlet> of AccountingLayout.
// ─────────────────────────────────────────────────────────────────────────────
import { Routes } from '@angular/router';

export const ACCOUNTING_ROUTES: Routes = [
  {
    path: 'dashboard',
    loadComponent: () =>
      import('./accounting/accounting').then(m => m.Accounting),
  },
  
  {
    path: 'fee-config',
    loadComponent: () =>
      import('./fee-config/fee-config').then(m => m.FeeConfigComponent),
  },
  {
    path: 'gcash',
    loadComponent: () =>
      import('./gcash/gcash').then(m => m.Gcash),
  },
  {
    path: 'permit-generation',
    loadComponent: () =>
      import('./permit-generation/permit-generation').then(m => m.PermitGeneration),
  },
  {
    path: 'report',
    loadComponent: () =>
      import('./report/report').then(m => m.Report),
  },
  
  // Default redirect
  { path: '', redirectTo: 'dashboard', pathMatch: 'full' },
];