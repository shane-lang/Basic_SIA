import { Component, OnInit, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';

interface Room {
  id: number;
  room_name: string;
  building: string;
  capacity: number;
  room_type: 'Classroom' | 'Laboratory' | 'Lecture Hall' | 'Conference Room' | 'Gymnasium';
  status: 'Available' | 'Occupied' | 'Under Maintenance';
  created_at?: string;
}

@Component({
  selector: 'app-rooms',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './rooms.html',
  styleUrl: './rooms.css',
})
export class Rooms implements OnInit {
  private api = 'http://localhost/sia-api/admin.php';

  roomList:     Room[] = [];
  filteredList: Room[] = [];
  isLoading   = false;
  searchQuery = '';
  filterType  = 'All';
  filterStatus = 'All';

  showModal  = false;
  isEditing  = false;
  isSaving   = false;

  showDeleteModal = false;
  deleteTarget: Room | null = null;
  isDeleting = false;

  form: Partial<Room> = this.emptyForm();

  toast: { show: boolean; type: 'success' | 'error'; message: string } = { show: false, type: 'success', message: '' };

  readonly ROOM_TYPES = ['Classroom', 'Laboratory', 'Lecture Hall', 'Conference Room', 'Gymnasium'];
  readonly BUILDINGS  = [
    'IT Building', 'Science Building', 'Liberal Arts Building',
    'Main Building', 'Admin Building', 'Engineering Building', 'Gymnasium'
  ];

  
  /** Returns HTTP headers with the auth token. Call this in every API request. */
  private getHeaders() {
    const token = sessionStorage.getItem('token') ?? '';
    return { headers: { Authorization: `Bearer ${token}` } };
  }

  constructor(private http: HttpClient, private cdr: ChangeDetectorRef) {}

  ngOnInit(): void { this.loadRooms(); }

  emptyForm(): Partial<Room> {
    return { room_name: '', building: '', capacity: 40, room_type: 'Classroom', status: 'Available' };
  }

  loadRooms(): void {
    this.isLoading = true;
    this.http.get<any>(`${this.api}?action=get_rooms`, this.getHeaders()).subscribe({
      next: (res) => {
        this.isLoading = false;
        if (res.success) { this.roomList = res.rooms; this.applyFilter(); }
        this.cdr.detectChanges();
      },
      error: () => { this.isLoading = false; this.showToast('error', 'Cannot load rooms. Check XAMPP.'); this.cdr.detectChanges(); }
    });
  }

  applyFilter(): void {
    let list = [...this.roomList];
    if (this.searchQuery.trim()) {
      const q = this.searchQuery.toLowerCase();
      list = list.filter(r =>
        r.room_name.toLowerCase().includes(q) ||
        r.building?.toLowerCase().includes(q)
      );
    }
    if (this.filterType   !== 'All') list = list.filter(r => r.room_type === this.filterType);
    if (this.filterStatus !== 'All') list = list.filter(r => r.status    === this.filterStatus);
    this.filteredList = list;
  }

  get uniqueBuildings(): string[] {
    return [...new Set(this.roomList.map(r => r.building).filter(Boolean))].sort() as string[];
  }

  openAdd(): void {
    this.form = this.emptyForm();
    this.isEditing = false;
    this.showModal = true;
    this.cdr.detectChanges();
  }

  openEdit(r: Room): void {
    this.form = { ...r };
    this.isEditing = true;
    this.showModal = true;
    this.cdr.detectChanges();
  }

  closeModal(): void { this.showModal = false; this.cdr.detectChanges(); }

  save(): void {
    if (!this.form.room_name) { this.showToast('error', 'Room name is required.'); return; }
    this.isSaving = true;
    const action = this.isEditing ? 'update_room' : 'create_room';
    this.http.post<any>(`${this.api}?action=${action}`, this.form, this.getHeaders()).subscribe({
      next: (res) => {
        this.isSaving = false;
        if (res.success) {
          this.showToast('success', this.isEditing ? 'Room updated!' : 'Room created!');
          this.closeModal(); this.loadRooms();
        } else {
          this.showToast('error', res.message || 'Save failed.');
        }
        this.cdr.detectChanges();
      },
      error: () => { this.isSaving = false; this.showToast('error', 'Server error.'); this.cdr.detectChanges(); }
    });
  }

  confirmDelete(r: Room): void {
    this.deleteTarget = r;
    this.showDeleteModal = true;
    this.cdr.detectChanges();
  }

  closeDeleteModal(): void { this.showDeleteModal = false; this.deleteTarget = null; this.cdr.detectChanges(); }

  doDelete(): void {
    if (!this.deleteTarget) return;
    this.isDeleting = true;
    this.http.post<any>(`${this.api}?action=delete_room`, { id: this.deleteTarget.id }, this.getHeaders()).subscribe({
      next: (res) => {
        this.isDeleting = false;
        if (res.success) { this.showToast('success', 'Room deleted.'); this.loadRooms(); }
        else { this.showToast('error', res.message || 'Delete failed.'); }
        this.closeDeleteModal(); this.cdr.detectChanges();
      },
      error: () => { this.isDeleting = false; this.showToast('error', 'Server error.'); this.cdr.detectChanges(); }
    });
  }

  showToast(type: 'success' | 'error', message: string): void {
    this.toast = { show: true, type, message };
    setTimeout(() => { this.toast.show = false; this.cdr.detectChanges(); }, 4000);
  }

  getRoomTypeIcon(type: string): string {
    const icons: Record<string,string> = {
      'Classroom': '🏫', 'Laboratory': '🔬', 'Lecture Hall': '🎓',
      'Conference Room': '📋', 'Gymnasium': '🏋️'
    };
    return icons[type] || '🚪';
  }

  getStatusClass(s: string): string {
    return s === 'Available' ? 'status-avail' : s === 'Occupied' ? 'status-occ' : 'status-maint';
  }

  get availableCount():    number { return this.roomList.filter(r => r.status === 'Available').length; }
  get occupiedCount():     number { return this.roomList.filter(r => r.status === 'Occupied').length; }
  get maintenanceCount():  number { return this.roomList.filter(r => r.status === 'Under Maintenance').length; }
  get laboratoryCount():   number { return this.roomList.filter(r => r.room_type === 'Laboratory').length; }
  get totalCapacity():     number { return this.roomList.reduce((s, r) => s + (r.capacity || 0), 0); }
}