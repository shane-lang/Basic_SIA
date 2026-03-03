import { Pipe, PipeTransform } from '@angular/core';

@Pipe({ name: 'methodFilter', standalone: true })
export class MethodFilterPipe implements PipeTransform {
  transform(methods: { method: string; count: number; total: number }[] | null, methodName: string): number {
    if (!methods) return 0;
    return methods.find(m => m.method === methodName)?.total ?? 0;
  }
}