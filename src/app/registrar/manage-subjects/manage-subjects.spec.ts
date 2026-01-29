import { ComponentFixture, TestBed } from '@angular/core/testing';

import { ManageSubjects } from './manage-subjects';

describe('ManageSubjects', () => {
  let component: ManageSubjects;
  let fixture: ComponentFixture<ManageSubjects>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [ManageSubjects]
    })
    .compileComponents();

    fixture = TestBed.createComponent(ManageSubjects);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
