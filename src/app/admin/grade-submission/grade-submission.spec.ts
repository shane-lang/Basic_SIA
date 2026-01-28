import { ComponentFixture, TestBed } from '@angular/core/testing';

import { GradeSubmission } from './grade-submission';

describe('GradeSubmission', () => {
  let component: GradeSubmission;
  let fixture: ComponentFixture<GradeSubmission>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [GradeSubmission],
    }).compileComponents();

    fixture = TestBed.createComponent(GradeSubmission);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
