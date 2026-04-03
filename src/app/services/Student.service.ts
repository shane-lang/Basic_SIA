import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { BehaviorSubject, tap } from 'rxjs';
import { environment } from '../environment';

@Injectable({ providedIn: 'root' })
export class StudentService {
  private apiUrl = environment.enrollApi;

  // Shared state — any component can subscribe to these
  private _studentDbId = new BehaviorSubject<number>(0);
  private _userId      = new BehaviorSubject<number>(0);

  studentDbId$ = this._studentDbId.asObservable();
  userId$      = this._userId.asObservable();

  get studentDbId(): number { return this._studentDbId.getValue(); }
  get userId(): number      { return this._userId.getValue(); }

  constructor(private http: HttpClient) {
    // On service init, read from localStorage immediately
    this.readFromStorage();
  }

  readFromStorage(): void {
    const stored = sessionStorage.getItem('currentUser');
    if (stored) {
      const u = JSON.parse(stored);
      this._userId.next(u.id ?? 0);
    }
  }

  setStudentDbId(id: number): void {
    this._studentDbId.next(id);
  }

  // Fetch profile and update studentDbId — call this from any component
  fetchProfile() {
    this.readFromStorage();
    const uid = this._userId.getValue();
    if (!uid) return;
    // BUG FIX: was missing .subscribe() — HTTP call was never actually fired!
    // .pipe(tap(...)) alone does NOT execute the HTTP request in Angular.
    this.http.get<any>(`${this.apiUrl}?action=get_profile&user_id=${uid}`).pipe(
      tap(res => {
        if (res.success) {
          this._studentDbId.next(res.student.dbId);
          sessionStorage.setItem('studentDbId', String(res.student.dbId));
        }
      })
    ).subscribe();
  }
}