import { ComponentFixture, TestBed } from '@angular/core/testing';

import { EnrollmentHistory } from './enrollment-history';

describe('EnrollmentHistory', () => {
  let component: EnrollmentHistory;
  let fixture: ComponentFixture<EnrollmentHistory>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [EnrollmentHistory]
    })
    .compileComponents();

    fixture = TestBed.createComponent(EnrollmentHistory);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
