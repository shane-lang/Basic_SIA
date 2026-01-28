import { ComponentFixture, TestBed } from '@angular/core/testing';

import { Gcash } from './gcash';

describe('Gcash', () => {
  let component: Gcash;
  let fixture: ComponentFixture<Gcash>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [Gcash]
    })
    .compileComponents();

    fixture = TestBed.createComponent(Gcash);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
