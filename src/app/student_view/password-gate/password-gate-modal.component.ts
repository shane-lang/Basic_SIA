// =============================================================================
// password-gate-modal.component.ts
//
// Self-contained overlay modal that prompts for the student's account password
// before revealing a sensitive document. It is mounted dynamically by
// PasswordGateService so it works across any route without needing to be
// declared in that route's component.
// =============================================================================

import {
  Component,
  Input,
  ChangeDetectionStrategy,
  ChangeDetectorRef,
} from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { PasswordGateService } from './password-gate.service';

@Component({
  selector: 'app-password-gate-modal',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './password-gate-modal.component.html',
  styleUrl: './password-gate-modal.component.css',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class PasswordGateModalComponent {

  /** Label shown in the title, e.g. "COE", "Grades", "SOA", "Receipts" */
  @Input() documentLabel = 'Document';

  /** Callbacks injected by PasswordGateService before mounting */
  onVerified: () => void = () => {};
  onDismiss:  () => void = () => {};

  password     = '';
  errorMessage = '';
  isLoading    = false;
  showPassword = false;

  // Map label → icon class (Lucide / any icon font you already use, or emoji fallback)
  readonly iconMap: Record<string, string> = {
    'COE':      'shield-check',
    'Grades':   'graduation-cap',
    'SOA':      'file-text',
    'Receipts': 'receipt',
  };

  constructor(
    private gate: PasswordGateService,
    private cdr:  ChangeDetectorRef,
  ) {}

  get icon(): string {
    return this.iconMap[this.documentLabel] ?? 'lock';
  }

  async submit(): Promise<void> {
    if (!this.password.trim()) {
      this.errorMessage = 'Please enter your password.';
      this.cdr.markForCheck();
      return;
    }

    this.isLoading    = true;
    this.errorMessage = '';
    this.cdr.markForCheck();

    const error = await this.gate.verifyPassword(this.password);

    this.isLoading = false;

    if (error) {
      this.errorMessage = error;
      this.password     = '';           // clear on failure
      this.cdr.markForCheck();
      return;
    }

    this.onVerified();
  }

  dismiss(): void {
    this.onDismiss();
  }

  onOverlayClick(event: MouseEvent): void {
    if ((event.target as HTMLElement).classList.contains('pg-overlay')) {
      this.dismiss();
    }
  }

  onKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape') this.dismiss();
    if (event.key === 'Enter')  this.submit();
  }
}