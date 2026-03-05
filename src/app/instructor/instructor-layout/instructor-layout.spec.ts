import { ComponentFixture, TestBed } from '@angular/core/testing';

import { InstructorLayout } from './instructor-layout';

describe('InstructorLayout', () => {
  let component: InstructorLayout;
  let fixture: ComponentFixture<InstructorLayout>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [InstructorLayout]
    })
    .compileComponents();

    fixture = TestBed.createComponent(InstructorLayout);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
