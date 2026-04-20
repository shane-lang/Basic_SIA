import { ComponentFixture, TestBed } from '@angular/core/testing';

import { BlockCapacity } from './block-capacity';

describe('BlockCapacity', () => {
  let component: BlockCapacity;
  let fixture: ComponentFixture<BlockCapacity>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [BlockCapacity]
    })
    .compileComponents();

    fixture = TestBed.createComponent(BlockCapacity);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
