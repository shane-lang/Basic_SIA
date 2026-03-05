import { ComponentFixture, TestBed } from '@angular/core/testing';

import { Masterlist } from './masterlist';

describe('Masterlist', () => {
  let component: Masterlist;
  let fixture: ComponentFixture<Masterlist>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [Masterlist]
    })
    .compileComponents();

    fixture = TestBed.createComponent(Masterlist);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
