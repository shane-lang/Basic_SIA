import { ComponentFixture, TestBed } from '@angular/core/testing';

import { FeeConfigComponent } from './fee-config';

describe('FeeConfig', () => {
  let component: FeeConfigComponent;
  let fixture: ComponentFixture<FeeConfigComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [FeeConfigComponent]
    })
    .compileComponents();

    fixture = TestBed.createComponent(FeeConfigComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
