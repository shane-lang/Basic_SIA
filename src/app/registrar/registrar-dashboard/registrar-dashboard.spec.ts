import { ComponentFixture, TestBed } from '@angular/core/testing';

import { RegistrarDashboard } from './registrar-dashboard';

describe('RegistrarDashboard', () => {
  let component: RegistrarDashboard;
  let fixture: ComponentFixture<RegistrarDashboard>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [RegistrarDashboard]
    })
    .compileComponents();

    fixture = TestBed.createComponent(RegistrarDashboard);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
