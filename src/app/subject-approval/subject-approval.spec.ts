import { ComponentFixture, TestBed } from '@angular/core/testing';

import { SubjectApproval } from './subject-approval';

describe('SubjectApproval', () => {
  let component: SubjectApproval;
  let fixture: ComponentFixture<SubjectApproval>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [SubjectApproval]
    })
    .compileComponents();

    fixture = TestBed.createComponent(SubjectApproval);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
