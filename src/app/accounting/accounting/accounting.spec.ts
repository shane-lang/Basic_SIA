import { ComponentFixture, TestBed } from '@angular/core/testing';
import { Accounting } from './accounting';

describe('Accounting', () => {
  let component: Accounting;
  let fixture: ComponentFixture<Accounting>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [Accounting],
    }).compileComponents();

    fixture = TestBed.createComponent(Accounting);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});