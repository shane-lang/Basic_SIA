import { ComponentFixture, TestBed } from '@angular/core/testing';

import { SubjectFeeLog } from './subject-fee-log';

describe('SubjectFeeLog', () => {
  let component: SubjectFeeLog;
  let fixture: ComponentFixture<SubjectFeeLog>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [SubjectFeeLog]
    })
    .compileComponents();

    fixture = TestBed.createComponent(SubjectFeeLog);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
