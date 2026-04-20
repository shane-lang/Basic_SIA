import { Component, OnInit, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../environment';

interface FeeRow {
  id: number;
  category: 'College' | 'SHS' | 'TVET';
  fee_key: string;
  fee_label: string;
  value: number;
  is_per_unit: number;
  applies_to: string;
  description: string;
  is_active: number;
  sort_order: number;
  // local UI state
  editing?: boolean;
  editValue?: number;
  editLabel?: string;
  editDesc?: string;
  editPerUnit?: boolean;
}

interface NewFeeForm {
  fee_key: string;
  fee_label: string;
  value: number | null;
  is_per_unit: boolean;
  applies_to: string;
  description: string;
}

@Component({
  selector: 'app-fee-config',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './fee-config.html',
  styleUrls:   ['./fee-config.css'],
})
export class FeeConfigComponent implements OnInit {
  private apiUrl = environment.accountingApi;

  categories: Array<'College' | 'SHS' | 'TVET'> = ['College', 'SHS', 'TVET'];
  activeCategory: 'College' | 'SHS' | 'TVET' = 'College';

  allConfig: { College: FeeRow[]; SHS: FeeRow[]; TVET: FeeRow[] } = {
    College: [], SHS: [], TVET: []
  };

  loading = false;
  saving  = false;
  alertMsg     = '';
  alertIsError = false;

  showAddForm = false;
  newFee: NewFeeForm = { fee_key: '', fee_label: '', value: null, is_per_unit: false, applies_to: 'All', description: '' };

  // Core fee keys that cannot be deleted (only edited)
  coreKeys = ['tuition_rate_per_unit','misc_fee','reg_fee','lab_fee_per_room','energy_rate_per_unit','installment_fee','transferee_flat_rate'];

  constructor(private http: HttpClient, private cdr: ChangeDetectorRef) {}

  ngOnInit(): void { this.loadConfig(); }
  loadConfig(): void {
    this.loading = true;
    this.http.get<any>(`${this.apiUrl}?action=get_fee_config`).subscribe({
      next: (res) => {
        this.loading = false;
        if (res.success) {
          this.allConfig = res.config;
        }
        this.cdr.detectChanges();
      },
      error: () => {
        this.loading = false;
        this.showAlert('Failed to load fee configuration. Check server connection.', true);
        this.cdr.detectChanges();
      }
    });
  }

  getRows(): FeeRow[] {
    return this.allConfig[this.activeCategory] || [];
  }

  isCoreFee(key: string): boolean {
    return this.coreKeys.includes(key);
  }

  /** Returns the correct type label for display — handles special cases */
  getTypeLabel(row: any): string {
    if (row.fee_key === 'lab_fee_per_room')    return 'Per Lab Room';
    if (row.fee_key === 'transferee_flat_rate') return 'Flat Rate';
    return Number(row.is_per_unit) === 1 ? 'Per Unit' : 'Fixed';
  }

  /** Returns true if the type field should be locked (not editable) */
  isTypeLocked(key: string): boolean {
    return ['lab_fee_per_room', 'transferee_flat_rate',
            'tuition_rate_per_unit', 'energy_rate_per_unit'].includes(key);
  }

  // Live preview: compute College total for N units
  calcCollegePreview(units: number): number {
    const rows = this.allConfig['College'] || [];
    let total = 0;
    const std = ['tuition_rate_per_unit','misc_fee','reg_fee','lab_fee_per_room','energy_rate_per_unit'];
    for (const r of rows) {
      if (r.fee_key === 'installment_fee') continue; // exclude from preview
      if (r.fee_key === 'lab_fee_per_room') { total += r.value * 2; continue; } // assume 2 lab rooms
      total += r.is_per_unit ? r.value * units : r.value;
    }
    return total;
  }

  startEdit(row: FeeRow): void {
    row.editing     = true;
    row.editValue   = row.value;
    row.editLabel   = row.fee_label;
    row.editDesc    = row.description;
    row.editPerUnit = Number(row.is_per_unit) === 1;
  }

  cancelEdit(row: FeeRow): void {
    row.editing = false;
  }

  saveRow(row: FeeRow): void {
    if (row.editValue === null || row.editValue === undefined) { this.showAlert('Amount is required.', true); return; }
    this.saving = true;
    const payload = { updates: [{ id: row.id, value: row.editValue, fee_label: row.editLabel, description: row.editDesc, is_per_unit: row.editPerUnit ? 1 : 0 }] };
    this.http.post<any>(`${this.apiUrl}?action=save_fee_config`, payload).subscribe({
      next: (res) => {
        this.saving = false;
        if (res.success) {
          row.value      = row.editValue!;
          row.fee_label  = row.editLabel!;
          row.description = row.editDesc!;
          row.is_per_unit = row.editPerUnit ? 1 : 0;
          row.editing    = false;
          this.showAlert(res.message || 'Fee updated successfully.');
        } else {
          this.showAlert(res.message || 'Save failed.', true);
        }
        this.cdr.detectChanges();
      },
      error: () => { this.saving = false; this.showAlert('Server error while saving.', true); }
    });
  }

  deleteFee(row: FeeRow): void {
    if (!confirm(`Remove "${row.fee_label}"?\nThis will stop charging this fee to new enrollees.`)) return;
    this.http.post<any>(`${this.apiUrl}?action=delete_fee_config`, { id: row.id }).subscribe({
      next: (res) => {
        if (res.success) {
          this.allConfig[this.activeCategory] = this.allConfig[this.activeCategory].filter(r => r.id !== row.id);
          this.showAlert('Fee removed.');
        } else { this.showAlert(res.message || 'Delete failed.', true); }
        this.cdr.detectChanges();
      },
      error: () => this.showAlert('Server error.', true)
    });
  }

  addFee(): void {
    if (!this.newFee.fee_label.trim()) { this.showAlert('Fee name is required.', true); return; }
    if (this.newFee.value === null || this.newFee.value === undefined) { this.showAlert('Amount is required.', true); return; }

    // Auto-generate key from label (Internal Key field is hidden from the UI)
    this.newFee.fee_key = this.newFee.fee_label.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '');

    this.saving = true;
    const payload = {
      category:    this.activeCategory,
      fee_key:     this.newFee.fee_key,
      fee_label:   this.newFee.fee_label,
      value:       this.newFee.value,
      is_per_unit: this.newFee.is_per_unit ? 1 : 0,
      applies_to:  this.newFee.applies_to,
      description: this.newFee.description,
    };
    this.http.post<any>(`${this.apiUrl}?action=add_fee_config`, payload).subscribe({
      next: (res) => {
        this.saving = false;
        if (res.success) {
          // Directly push the new fee into the local array so it shows immediately
          // without waiting for a full reload round-trip.
          const newRow: FeeRow = {
            id:          res.id,
            category:    this.activeCategory,
            fee_key:     this.newFee.fee_key,
            fee_label:   this.newFee.fee_label,
            value:       this.newFee.value ?? 0,
            is_per_unit: this.newFee.is_per_unit ? 1 : 0,
            applies_to:  this.newFee.applies_to,
            description: this.newFee.description,
            is_active:   1,
            sort_order:  9999,
          };
          // Replace the array reference so *ngFor detects the change
          this.allConfig[this.activeCategory] = [...this.allConfig[this.activeCategory], newRow];
          this.showAddForm = false;
          this.newFee = { fee_key: '', fee_label: '', value: null, is_per_unit: false, applies_to: 'All', description: '' };
          this.showAlert('Fee added successfully.');
        } else { this.showAlert(res.message || 'Add failed.', true); }
        this.cdr.detectChanges();
      },
      error: () => { this.saving = false; this.showAlert('Server error.', true); this.cdr.detectChanges(); }
    });
  }

  private showAlert(msg: string, isError = false): void {
    this.alertMsg     = msg;
    this.alertIsError = isError;
    if (!isError) setTimeout(() => { this.alertMsg = ''; this.cdr.detectChanges(); }, 4000);
  }
}