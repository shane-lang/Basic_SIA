import { Component, OnInit, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../environment';

interface Block {
  id: number;
  blockCode: string;
  program: string;
  yearLevel: string;
  semester: string;
  schoolYear: string;
  maxCapacity: number;
  enrolledCount: number;
  availableSeats: number;
  isFull: boolean;
  isActive: boolean;
}

interface BlockStudent {
  id: number;
  studentNumber: string;
  firstName: string;
  lastName: string;
  fullName: string;
  yearLevel: string;
  semester: string;
  enrollmentStatus: string;
  approvalStatus: string;
  paymentStatus: string;
  email: string;
}

interface BlockDetail {
  id: number;
  blockCode: string;
  program: string;
  yearLevel: string;
  semester: string;
  schoolYear: string;
  maxCapacity: number;
  enrolledCount: number;
  availableSeats: number;
  isFull: boolean;
  isActive: boolean;
}

@Component({
  selector: 'app-block-capacity',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './block-capacity.html',
  styleUrl: './block-capacity.css',
})
export class BlockCapacityComponent implements OnInit {
  private api = environment.registrarApi;

  // ── Data ──────────────────────────────────────────────────────────────────
  allBlocks: Block[]    = [];
  filteredBlocks: Block[] = [];
  isLoading             = false;
  errorMsg              = '';

  // ── Filters ───────────────────────────────────────────────────────────────
  programs:   string[] = [];
  yearLevels: string[] = [];
  semesters:  string[] = [];

  filterProgram   = '';
  filterYearLevel = '';
  filterSemester  = '';
  searchQuery     = '';

  // ── Detail panel ──────────────────────────────────────────────────────────
  selectedBlock:   BlockDetail | null = null;
  selectedStudents: BlockStudent[]    = [];
  detailLoading    = false;
  detailError      = '';
  detailCache      = new Map<number, { block: BlockDetail; students: BlockStudent[]; cachedAt: number }>();

  constructor(private http: HttpClient, private cdr: ChangeDetectorRef) {}

  ngOnInit(): void {
    this.loadBlocks();
  }

  loadBlocks(): void {
    this.isLoading = true;
    this.errorMsg  = '';
    // FIX BUG-3: Clear stale cache and close any open detail panel so the
    // Refresh button always fetches fresh student data from the server.
    this.detailCache.clear();
    this.closeDetail();
    this.http.get<any>(`${this.api}?action=get_blocks`).subscribe({
      next: (res) => {
        this.isLoading = false;
        if (res.success) {
          this.allBlocks = res.blocks ?? [];
          this.buildFilterOptions();
          this.applyFilters();
        } else {
          this.errorMsg = res.message || 'Failed to load blocks.';
        }
        this.cdr.detectChanges();
      },
      error: () => {
        this.isLoading = false;
        this.errorMsg  = 'Server error loading blocks.';
        this.cdr.detectChanges();
      }
    });
  }

  buildFilterOptions(): void {
    this.programs   = [...new Set(this.allBlocks.map(b => b.program))].sort();
    this.yearLevels = [...new Set(this.allBlocks.map(b => b.yearLevel))].sort();
    this.semesters  = [...new Set(this.allBlocks.map(b => b.semester))].sort();
  }

  applyFilters(): void {
    const q = this.searchQuery.trim().toLowerCase();
    this.filteredBlocks = this.allBlocks.filter(b =>
      (!this.filterProgram   || b.program   === this.filterProgram) &&
      (!this.filterYearLevel || b.yearLevel === this.filterYearLevel) &&
      (!this.filterSemester  || b.semester  === this.filterSemester) &&
      (!q || b.blockCode.toLowerCase().includes(q))
    );
    // Close detail if selected block no longer in filter
    if (this.selectedBlock && !this.filteredBlocks.find(b => b.id === this.selectedBlock!.id)) {
      this.closeDetail();
    }
    this.cdr.detectChanges();
  }

  onFilterChange(): void { this.applyFilters(); }

  selectBlock(block: Block): void {
    // Toggle off if same block clicked
    if (this.selectedBlock?.id === block.id) {
      this.closeDetail();
      return;
    }

    this.detailError   = '';
    this.selectedStudents = [];

    // Use cache if available and fresh (within 2 minutes)
    const cached = this.detailCache.get(block.id);
    const CACHE_TTL_MS = 2 * 60 * 1000;
    if (cached && (Date.now() - cached.cachedAt) < CACHE_TTL_MS) {
      this.selectedBlock    = cached.block;
      this.selectedStudents = cached.students;
      this.cdr.detectChanges();
      return;
    }

    // Show skeleton while loading
    this.selectedBlock = { ...block, enrolledCount: block.enrolledCount, availableSeats: block.availableSeats };
    this.detailLoading = true;
    this.cdr.detectChanges();

    this.http.get<any>(`${this.api}?action=get_block_detail&block_id=${block.id}`).subscribe({
      next: (res) => {
        this.detailLoading = false;
        if (res.success) {
          this.selectedBlock    = res.block;
          this.selectedStudents = res.students ?? [];
          this.detailCache.set(block.id, { block: res.block, students: res.students ?? [], cachedAt: Date.now() });
        } else {
          this.detailError = res.message || 'Failed to load block detail.';
        }
        this.cdr.detectChanges();
      },
      error: () => {
        this.detailLoading = false;
        this.detailError   = 'Server error loading students.';
        this.cdr.detectChanges();
      }
    });
  }

  closeDetail(): void {
    this.selectedBlock    = null;
    this.selectedStudents = [];
    this.detailError      = '';
    this.cdr.detectChanges();
  }

  isSelected(block: Block): boolean {
    return this.selectedBlock?.id === block.id;
  }

  // ── Computed helpers ──────────────────────────────────────────────────────
  fillPct(b: { enrolledCount: number; maxCapacity: number }): number {
    if (!b.maxCapacity) return 0;
    return Math.min(100, Math.round((b.enrolledCount / b.maxCapacity) * 100));
  }

  fillColor(pct: number): string {
    if (pct >= 100) return '#ef4444';
    if (pct >= 75)  return '#f59e0b';
    return '#22c55e';
  }

  badgeClass(b: Block): string {
    if (b.isFull)            return 'badge-full';
    if (this.fillPct(b) >= 75) return 'badge-warn';
    return 'badge-ok';
  }

  badgeLabel(b: Block): string {
    if (b.isFull)              return 'Full';
    if (this.fillPct(b) >= 75) return 'Almost full';
    return 'Available';
  }

  statusPillClass(status: string): string {
    const m: Record<string, string> = {
      Enrolled:  'pill-enrolled',
      Pending:   'pill-pending',
      Dropped:   'pill-dropped',
    };
    return m[status] ?? 'pill-other';
  }

  initials(s: BlockStudent): string {
    return ((s.firstName?.[0] ?? '?') + (s.lastName?.[0] ?? '?')).toUpperCase();
  }

  // ── Summary stats for header cards ───────────────────────────────────────
  get totalBlocks():    number { return this.filteredBlocks.length; }
  get totalEnrolled():  number { return this.filteredBlocks.reduce((a, b) => a + b.enrolledCount, 0); }
  get totalCapacity():  number { return this.filteredBlocks.reduce((a, b) => a + b.maxCapacity, 0); }
  get totalAvailable(): number { return this.filteredBlocks.reduce((a, b) => a + b.availableSeats, 0); }
  get fullBlocks():     number { return this.filteredBlocks.filter(b => b.isFull).length; }
}