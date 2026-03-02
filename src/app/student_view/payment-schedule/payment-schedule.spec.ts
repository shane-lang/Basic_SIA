import { ComponentFixture, TestBed } from '@angular/core/testing';

import { PaymentsSchedule } from './payment-schedule';

describe('PaymentsSchedule', () => {
  let component: PaymentsSchedule;
  let fixture: ComponentFixture<PaymentsSchedule>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [PaymentsSchedule]
    })
    .compileComponents();

    fixture = TestBed.createComponent(PaymentsSchedule);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
