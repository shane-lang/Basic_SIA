import { ComponentFixture, TestBed } from '@angular/core/testing';

import { StaffAccounts } from './staff-accounts';

describe('StaffAccounts', () => {
  let component: StaffAccounts;
  let fixture: ComponentFixture<StaffAccounts>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [StaffAccounts]
    })
    .compileComponents();

    fixture = TestBed.createComponent(StaffAccounts);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
