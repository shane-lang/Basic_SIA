import { ComponentFixture, TestBed } from '@angular/core/testing';

import { StudentInfo } from './student-info';

describe('StudentInfo', () => {
  let component: StudentInfo;
  let fixture: ComponentFixture<StudentInfo>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [StudentInfo]
    })
    .compileComponents();

    fixture = TestBed.createComponent(StudentInfo);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
