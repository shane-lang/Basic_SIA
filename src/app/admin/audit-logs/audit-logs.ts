import { Component, OnInit, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient, HttpHeaders } from '@angular/common/http';
import { environment } from '../../environment';
import { MaskEmailPipe } from '../../pipes/mask-email.pipe';

interface AuditLog {
  id: number;
  user_id: number;
  user_email: string;
  user_role: string;
  action: string;
  target_type: string;
  target_id: number;
  description: string;
  old_values: any;
  new_values: any;
  ip_address: string;
  user_agent: string;
  created_at: string;
}

interface AuditStats {
  totalAll: number;
  today: number;
  topActions: { action: string; cnt: number }[];
  byRole: { user_role: string; cnt: number }[];
  daily: { day: string; cnt: number }[];
}

@Component({
  selector: 'app-audit-logs',
  standalone: true,
  imports: [CommonModule, FormsModule, MaskEmailPipe],
  templateUrl: './audit-logs.html',
  styleUrl: './audit-logs.css',
})
export class AuditLogs implements OnInit {
  private apiUrl = environment.adminApi;

  logs: AuditLog[]    = [];
  stats: AuditStats | null = null;
  selectedLog: AuditLog | null = null;
  showDetailModal = false;
  isLoading    = false;
  showStats    = true;

  // Filters
  filterAction   = '';
  filterRole     = '';
  filterUser     = '';
  filterDateFrom = '';
  filterDateTo   = '';

  // Pagination
  currentPage  = 1;
  totalPages   = 1;
  totalLogs    = 0;
  pageSize     = 25;

  searchTimeout: any;

  constructor(private http: HttpClient, private cdr: ChangeDetectorRef) {}

  ngOnInit(): void {
    this.loadStats();
    this.loadLogs();
  }

  loadStats(): void {
    this.http.get<any>(`${this.apiUrl}?action=get_audit_stats`).subscribe({
      next: (res) => {
        if (res.success) { this.stats = res; this.cdr.detectChanges(); }
      }
    });
  }

  loadLogs(page = 1): void {
    this.isLoading   = true;
    this.currentPage = page;

    const params = new URLSearchParams({
      action: 'get_audit_logs',
      page: String(page),
      limit: String(this.pageSize),
      ...(this.filterAction   && { filter_action: this.filterAction }),
      ...(this.filterRole     && { filter_role:   this.filterRole }),
      ...(this.filterUser     && { filter_user:   this.filterUser }),
      ...(this.filterDateFrom && { date_from:     this.filterDateFrom }),
      ...(this.filterDateTo   && { date_to:       this.filterDateTo }),
    });

    this.http.get<any>(`${this.apiUrl}?${params}`).subscribe({
      next: (res) => {
        this.isLoading  = false;
        this.logs       = res.success ? (res.logs || []) : [];
        this.totalPages = res.totalPages || 1;
        this.totalLogs  = res.total || 0;
        this.cdr.detectChanges();
      },
      error: () => { this.isLoading = false; this.cdr.detectChanges(); }
    });
  }

  onFilterChange(): void {
    clearTimeout(this.searchTimeout);
    this.searchTimeout = setTimeout(() => this.loadLogs(1), 350);
  }

  clearFilters(): void {
    this.filterAction = ''; this.filterRole = '';
    this.filterUser = ''; this.filterDateFrom = ''; this.filterDateTo = '';
    this.loadLogs(1);
  }

  openDetail(log: AuditLog): void {
    this.selectedLog    = log;
    this.showDetailModal = true;
  }
  closeDetail(): void { this.showDetailModal = false; this.selectedLog = null; }

  prevPage(): void { if (this.currentPage > 1) this.loadLogs(this.currentPage - 1); }
  nextPage(): void { if (this.currentPage < this.totalPages) this.loadLogs(this.currentPage + 1); }

  getPages(): number[] {
    const pages: number[] = [];
    const start = Math.max(1, this.currentPage - 2);
    const end   = Math.min(this.totalPages, start + 4);
    for (let i = start; i <= end; i++) pages.push(i);
    return pages;
  }

  actionClass(action: string): string {
    if (!action) return 'act-default';
    if (action.startsWith('CREATE')) return 'act-create';
    if (action.startsWith('UPDATE')) return 'act-update';
    if (action.startsWith('DELETE')) return 'act-delete';
    if (action.startsWith('VIEW'))   return 'act-view';
    if (action.includes('LOGIN'))    return 'act-login';
    return 'act-default';
  }

  roleClass(role: string): string {
    const map: Record<string, string> = {
      admin: 'role-admin', registrar: 'role-reg',
      accounting: 'role-acc', student: 'role-stu',
    };
    return map[role?.toLowerCase()] || 'role-default';
  }

  formatAction(action: string): string {
    return action.replace(/_/g, ' ').toLowerCase().replace(/\b\w/g, c => c.toUpperCase());
  }

  formatJson(obj: any): string {
    if (!obj) return '—';
    try { return JSON.stringify(obj, null, 2); } catch { return String(obj); }
  }

  getMaxDaily(): number {
    if (!this.stats?.daily?.length) return 1;
    return Math.max(...this.stats.daily.map(d => d.cnt), 1);
  }

  barWidth(cnt: number): number {
    return Math.round((cnt / this.getMaxDaily()) * 100);
  }
}