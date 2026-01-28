import { ComponentFixture, TestBed } from '@angular/core/testing';

import { ASR } from './asr';

describe('ASR', () => {
  let component: ASR;
  let fixture: ComponentFixture<ASR>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [ASR]
    })
    .compileComponents();

    fixture = TestBed.createComponent(ASR);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
