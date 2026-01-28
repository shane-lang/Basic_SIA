import { ComponentFixture, TestBed } from '@angular/core/testing';

import { ClassSections } from './class-sections';

describe('ClassSections', () => {
  let component: ClassSections;
  let fixture: ComponentFixture<ClassSections>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [ClassSections],
    }).compileComponents();

    fixture = TestBed.createComponent(ClassSections);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
