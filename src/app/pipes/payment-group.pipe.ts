import { Pipe, PipeTransform } from '@angular/core';

// Returns true if any row in the array matches the given payment method
@Pipe({ name: 'hasMethod', standalone: true })
export class HasMethodPipe implements PipeTransform {
  transform(rows: any[], method: string): boolean {
    return rows.some(r => (r.paymentMethod || '').toLowerCase() === method.toLowerCase());
  }
}

// Returns the sum of gcashAmount across all rows
@Pipe({ name: 'sumAmount', standalone: true })
export class SumAmountPipe implements PipeTransform {
  transform(rows: any[]): number {
    return rows.reduce((sum, r) => sum + (r.gcashAmount || 0), 0);
  }
}

// Returns true if ALL rows have status === 'Verified'
@Pipe({ name: 'allVerified', standalone: true })
export class AllVerifiedPipe implements PipeTransform {
  transform(rows: any[]): boolean {
    return rows.length > 0 && rows.every(r => r.status === 'Verified');
  }
}

// Counts rows where status === 'Verified'
@Pipe({ name: 'countVerified', standalone: true })
export class CountVerifiedPipe implements PipeTransform {
  transform(rows: any[]): number {
    return rows.filter(r => r.status === 'Verified').length;
  }
}

// Counts rows where status === 'Rejected'
@Pipe({ name: 'countRejected', standalone: true })
export class CountRejectedPipe implements PipeTransform {
  transform(rows: any[]): number {
    return rows.filter(r => r.status === 'Rejected').length;
  }
}