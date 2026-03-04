import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient, HttpHeaders } from '@angular/common/http';

@Component({
  selector: 'app-settings',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './settings.html',
  styleUrl: './settings.css',
})
export class Settings implements OnInit {
  private apiUrl = 'http://localhost/sia-api/enrollment.php';

  period = { is_open: false, start: '', end: '', label: '' };
  isOpen = false;
  isSaving = false;
  saveMsg = '';
  saveMsgType = '';
  isLoading = true;

  constructor(private http: HttpClient) {}
  ngOnInit(): void { this.loadPeriod(); }

  private getHeaders() {
    const token = localStorage.getItem('token') || sessionStorage.getItem('token') || '';
    return { headers: new HttpHeaders({ Authorization: `Bearer ${token}` }) };
  }

  loadPeriod(): void {
    this.isLoading = true;
    this.http.get<any>(`${this.apiUrl}?action=get_enrollment_period`, this.getHeaders()).subscribe({
      next: res => {
        if (res.success) {
          const p = res.period ?? {};
          this.period.is_open = p.is_open ?? false;
          this.period.start   = p.start ? this.toLocal(p.start) : '';
          this.period.end     = p.end   ? this.toLocal(p.end)   : '';
          this.period.label   = p.label ?? '';
          this.isOpen         = res.is_open ?? false;
        }
        this.isLoading = false;
      },
      error: () => { this.isLoading = false; }
    });
  }

  savePeriod(): void {
    this.isSaving = true; this.saveMsg = '';
    const body = { is_open: this.period.is_open, start: this.period.start, end: this.period.end, label: this.period.label };
    this.http.post<any>(`${this.apiUrl}?action=set_enrollment_period`, body, this.getHeaders()).subscribe({
      next: res => {
        this.isSaving = false;
        this.saveMsgType = res.success ? 'success' : 'error';
        this.saveMsg = res.success ? `✅ Saved! Enrollment is now ${res.is_open ? 'OPEN' : 'CLOSED'}.` : (res.message || 'Save failed.');
        this.isOpen = res.is_open ?? this.period.is_open;
        setTimeout(() => this.saveMsg = '', 4000);
      },
      error: () => { this.isSaving = false; this.saveMsgType = 'error'; this.saveMsg = '❌ Network error.'; }
    });
  }

  private toLocal(val: string): string {
    if (!val) return '';
    if (/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/.test(val)) return val.slice(0, 16);
    try { return new Date(val).toISOString().slice(0, 16); } catch { return ''; }
  }

  get statusLabel(): string {
    if (!this.period.is_open) return 'CLOSED';
    if (this.period.start && new Date(this.period.start) > new Date()) return 'SCHEDULED';
    if (this.period.end   && new Date(this.period.end)   < new Date()) return 'ENDED';
    return 'OPEN';
  }
  get statusColor(): string {
    return { 'OPEN': '#16a34a', 'SCHEDULED': '#d97706', 'CLOSED': '#dc2626', 'ENDED': '#dc2626' }[this.statusLabel] || '#6b7280';
  }
}