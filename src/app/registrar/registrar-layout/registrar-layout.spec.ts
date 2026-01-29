import { ComponentFixture, TestBed } from '@angular/core/testing';

import { RegistrarLayout } from './registrar-layout';

describe('RegistrarLayout', () => {
  let component: RegistrarLayout;
  let fixture: ComponentFixture<RegistrarLayout>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [RegistrarLayout]
    })
    .compileComponents();

    fixture = TestBed.createComponent(RegistrarLayout);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
