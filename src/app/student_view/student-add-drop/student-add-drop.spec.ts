import { ComponentFixture, TestBed } from '@angular/core/testing';

import { StudentAddDrop } from './student-add-drop';

describe('StudentAddDrop', () => {
  let component: StudentAddDrop;
  let fixture: ComponentFixture<StudentAddDrop>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [StudentAddDrop]
    })
    .compileComponents();

    fixture = TestBed.createComponent(StudentAddDrop);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
