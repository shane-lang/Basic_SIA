import { ComponentFixture, TestBed } from '@angular/core/testing';

import { SubjectSelection } from './subject-selection';

describe('SubjectSelection', () => {
  let component: SubjectSelection;
  let fixture: ComponentFixture<SubjectSelection>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [SubjectSelection]
    })
    .compileComponents();

    fixture = TestBed.createComponent(SubjectSelection);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
