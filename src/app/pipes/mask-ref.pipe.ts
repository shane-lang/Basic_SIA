import { Pipe, PipeTransform } from '@angular/core';

@Pipe({ name: 'maskRef', standalone: true })
export class MaskRefPipe implements PipeTransform {
  transform(ref: string | null | undefined): string {
    if (!ref) return '—';
    if (ref.length <= 4) return '••••';
    return '••••' + ref.slice(-4);
  }
}
