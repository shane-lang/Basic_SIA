import { ComponentFixture, TestBed } from '@angular/core/testing';

import { Faculty } from './faculty';

describe('Faculty', () => {
  let component: Faculty;
  let fixture: ComponentFixture<Faculty>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [Faculty],
    }).compileComponents();

    fixture = TestBed.createComponent(Faculty);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
