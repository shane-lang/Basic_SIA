import { ComponentFixture, TestBed } from '@angular/core/testing';

import { PendingRegistrations } from './pending-registrations';

describe('PendingRegistrations', () => {
  let component: PendingRegistrations;
  let fixture: ComponentFixture<PendingRegistrations>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [PendingRegistrations]
    })
    .compileComponents();

    fixture = TestBed.createComponent(PendingRegistrations);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
