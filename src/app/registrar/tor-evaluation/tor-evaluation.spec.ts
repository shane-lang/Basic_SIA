import { ComponentFixture, TestBed } from '@angular/core/testing';

import { TorEvaluation } from './tor-evaluation';

describe('TorEvaluation', () => {
  let component: TorEvaluation;
  let fixture: ComponentFixture<TorEvaluation>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [TorEvaluation]
    })
    .compileComponents();

    fixture = TestBed.createComponent(TorEvaluation);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
