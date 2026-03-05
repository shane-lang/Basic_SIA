import { ComponentFixture, TestBed } from '@angular/core/testing';

import { StudentMasterlist } from './student-masterlist';

describe('StudentMasterlist', () => {
  let component: StudentMasterlist;
  let fixture: ComponentFixture<StudentMasterlist>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [StudentMasterlist]
    })
    .compileComponents();

    fixture = TestBed.createComponent(StudentMasterlist);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
