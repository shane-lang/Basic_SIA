import { Pipe, PipeTransform } from '@angular/core';


@Pipe({
  name: 'coeCount',
  standalone: true,
  pure: true,
})
export class CoeCountPipe implements PipeTransform {
  transform(records: any[], status: string): number {
    if (!Array.isArray(records)) return 0;
    return records.filter(r => r.status === status).length;
  }
}