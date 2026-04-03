import { Pipe, PipeTransform } from '@angular/core';

@Pipe({ name: 'maskEmail', standalone: true })
export class MaskEmailPipe implements PipeTransform {
  transform(email: string | null | undefined): string {
    if (!email) return '—';
    const [user, domain] = email.split('@');
    if (!domain) return email;
    const visible = user.slice(0, 2);
    return `${visible}***@${domain}`;
  }
}
