import { ComponentFixture, TestBed } from '@angular/core/testing';

import { CoeStudent } from './coe-student';

describe('CoeStudent', () => {
  let component: CoeStudent;
  let fixture: ComponentFixture<CoeStudent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [CoeStudent]
    })
    .compileComponents();

    fixture = TestBed.createComponent(CoeStudent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
