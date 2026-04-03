import { ComponentFixture, TestBed } from '@angular/core/testing';

import { PendingScholarships } from './pending-scholarships';

describe('PendingScholarships', () => {
  let component: PendingScholarships;
  let fixture: ComponentFixture<PendingScholarships>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [PendingScholarships]
    })
    .compileComponents();

    fixture = TestBed.createComponent(PendingScholarships);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
