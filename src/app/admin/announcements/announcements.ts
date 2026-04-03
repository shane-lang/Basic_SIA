import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../environment';

type AType = 'enrollment'|'payment'|'school'|'department'|'system';
type APri  = 'high'|'normal'|'low';
type EType = 'enrollment'|'payment'|'exam'|'activity'|'holiday';

interface Announcement { id:number; title:string; message:string; date:string; type:AType; priority:APri; icon:string; }
interface SchoolEvent   { id:number; title:string; event_date:string; type:EType; description:string; }

@Component({
  selector: 'app-announcements',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './announcements.html',
  styleUrl: './announcements.css',
})
export class AnnouncementsAdmin implements OnInit {
  private api = environment.dashboardApi;

  // ── Tab ──────────────────────────────────────────────────────
  tab: 'announcements'|'events' = 'announcements';

  // ── Announcements ────────────────────────────────────────────
  announcements: Announcement[] = [];
  aLoading = false;
  aForm: Partial<Announcement> = {};
  aEditing: number|null = null;  // id of announcement being edited
  aShowForm = false;
  aSaving = false;
  aMsg = ''; aMsgType = '';

  aTypeOptions: AType[] = ['school','enrollment','payment','department','system'];
  aPriOptions:  APri[]  = ['high','normal','low'];
  aIconOptions  = ['📢','📋','💳','🏫','⚙️','📅','🎓','📌','⚠️','✅','🔔','📝'];

  // Auto-icon map: selecting a type sets the default icon automatically
  private typeIconMap: Record<AType, string> = {
    school:     '🏫',
    enrollment: '📋',
    payment:    '💳',
    department: '🎓',
    system:     '⚙️',
  };

  onTypeChange(): void {
    const t = this.aForm.type as AType;
    if (t && this.typeIconMap[t]) {
      this.aForm.icon = this.typeIconMap[t];
    }
  }

  // ── Events ───────────────────────────────────────────────────
  events: SchoolEvent[] = [];
  eLoading = false;
  eForm: Partial<SchoolEvent> = {};
  eEditing: number|null = null;
  eShowForm = false;
  eSaving = false;
  eMsg = ''; eMsgType = '';

  eTypeOptions: EType[] = ['enrollment','payment','exam','activity','holiday'];

  constructor(private http: HttpClient) {}
  ngOnInit(): void { this.loadAnnouncements(); this.loadEvents(); }

  // ── Announcements CRUD ───────────────────────────────────────
  loadAnnouncements(): void {
    this.aLoading = true;
    this.http.get<any>(`${this.api}?action=get_announcements`).subscribe({
      next: r => { this.announcements = r.announcements ?? []; this.aLoading = false; },
      error: () => { this.aLoading = false; }
    });
  }

  openNewA(): void {
    this.aEditing = null;
    this.aForm = { title:'', message:'', date: new Date().toISOString().slice(0,10), type:'school', priority:'normal', icon:'📢' };
    this.aShowForm = true;
  }

  editA(a: Announcement): void {
    this.aEditing = a.id;
    this.aForm = { ...a };
    this.aShowForm = true;
  }

  cancelA(): void { this.aShowForm = false; this.aEditing = null; this.aMsg = ''; }

  saveA(): void {
    if (!this.aForm.title || !this.aForm.message) return;
    this.aSaving = true;
    const action = this.aEditing ? 'update_announcement' : 'add_announcement';
    const body = this.aEditing ? { ...this.aForm, id: this.aEditing } : this.aForm;
    this.http.post<any>(`${this.api}?action=${action}`, body).subscribe({
      next: r => {
        this.aSaving = false;
        if (r.success) {
          this.aMsgType = 'success'; this.aMsg = this.aEditing ? '✅ Updated!' : '✅ Posted!';
          this.aShowForm = false; this.aEditing = null;
          this.loadAnnouncements();
          setTimeout(() => this.aMsg = '', 3000);
        } else { this.aMsgType = 'error'; this.aMsg = r.message || 'Save failed.'; }
      },
      error: () => { this.aSaving = false; this.aMsgType = 'error'; this.aMsg = '❌ Network error.'; }
    });
  }

  deleteA(id: number): void {
    if (!confirm('Delete this announcement?')) return;
    this.http.post<any>(`${this.api}?action=delete_announcement`, { id }).subscribe({
      next: r => { if (r.success) this.loadAnnouncements(); }
    });
  }

  // ── Events CRUD ──────────────────────────────────────────────
  loadEvents(): void {
    this.eLoading = true;
    this.http.get<any>(`${this.api}?action=get_events`).subscribe({
      next: r => { this.events = r.events ?? []; this.eLoading = false; },
      error: () => { this.eLoading = false; }
    });
  }

  openNewE(): void {
    this.eEditing = null;
    this.eForm = { title:'', event_date: new Date().toISOString().slice(0,10), type:'activity', description:'' };
    this.eShowForm = true;
  }

  editE(e: SchoolEvent): void { this.eEditing = e.id; this.eForm = { ...e }; this.eShowForm = true; }
  cancelE(): void { this.eShowForm = false; this.eEditing = null; this.eMsg = ''; }

  saveE(): void {
    if (!this.eForm.title || !this.eForm.event_date) return;
    this.eSaving = true;
    const action = this.eEditing ? 'update_event' : 'add_event';
    const body = this.eEditing ? { ...this.eForm, id: this.eEditing } : this.eForm;
    this.http.post<any>(`${this.api}?action=${action}`, body).subscribe({
      next: r => {
        this.eSaving = false;
        if (r.success) {
          this.eMsgType = 'success'; this.eMsg = this.eEditing ? '✅ Updated!' : '✅ Event Added!';
          this.eShowForm = false; this.eEditing = null;
          this.loadEvents();
          setTimeout(() => this.eMsg = '', 3000);
        } else { this.eMsgType = 'error'; this.eMsg = r.message || 'Save failed.'; }
      },
      error: () => { this.eSaving = false; this.eMsgType = 'error'; this.eMsg = '❌ Network error.'; }
    });
  }

  deleteE(id: number): void {
    if (!confirm('Delete this event?')) return;
    this.http.post<any>(`${this.api}?action=delete_event`, { id }).subscribe({
      next: r => { if (r.success) this.loadEvents(); }
    });
  }

  // ── Helpers ──────────────────────────────────────────────────
  typeColor(t: string): string {
    return { enrollment:'#6366f1', payment:'#f59e0b', school:'#3b82f6', department:'#8b5cf6', system:'#6b7280',
             exam:'#ef4444', activity:'#10b981', holiday:'#f97316' }[t] || '#6b7280';
  }
  priColor(p: string): string {
    return { high:'#dc2626', normal:'#2563eb', low:'#6b7280' }[p] || '#6b7280';
  }
  fmtDate(d: string): string {
    if (!d) return '';
    try { return new Date(d + 'T00:00').toLocaleDateString('en-PH', { month:'short', day:'numeric', year:'numeric' }); } catch { return d; }
  }
}