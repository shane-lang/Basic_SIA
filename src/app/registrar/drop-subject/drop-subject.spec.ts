import { ComponentFixture, TestBed } from '@angular/core/testing';

import { DropSubject } from './drop-subject';

describe('DropSubject', () => {
  let component: DropSubject;
  let fixture: ComponentFixture<DropSubject>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [DropSubject]
    })
    .compileComponents();

    fixture = TestBed.createComponent(DropSubject);
    component = fixture.componentInstance;
    await fixture.whenStable();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
