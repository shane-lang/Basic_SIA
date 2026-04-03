import { ComponentFixture, TestBed } from '@angular/core/testing';

import { CoeGenerator } from './coe-generator';

describe('CoeGenerator', () => {
  let component: CoeGenerator;
  let fixture: ComponentFixture<CoeGenerator>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [CoeGenerator]
    })
    .compileComponents();

    fixture = TestBed.createComponent(CoeGenerator);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
