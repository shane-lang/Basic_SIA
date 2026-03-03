import { ComponentFixture, TestBed } from '@angular/core/testing';

import { AddDrop } from './add-drop';

describe('AddDrop', () => {
  let component: AddDrop;
  let fixture: ComponentFixture<AddDrop>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [AddDrop]
    })
    .compileComponents();

    fixture = TestBed.createComponent(AddDrop);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
