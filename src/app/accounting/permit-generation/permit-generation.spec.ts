import { ComponentFixture, TestBed } from '@angular/core/testing';

import { PermitGeneration } from './permit-generation';

describe('PermitGeneration', () => {
  let component: PermitGeneration;
  let fixture: ComponentFixture<PermitGeneration>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [PermitGeneration]
    })
    .compileComponents();

    fixture = TestBed.createComponent(PermitGeneration);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
