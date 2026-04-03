import { ComponentFixture, TestBed } from '@angular/core/testing';

import { Cashier } from './cashier';

describe('Cashier', () => {
  let component: Cashier;
  let fixture: ComponentFixture<Cashier>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [Cashier]
    })
    .compileComponents();

    fixture = TestBed.createComponent(Cashier);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
