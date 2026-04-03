import { ComponentFixture, TestBed } from '@angular/core/testing';

import { PortalLoginComponent } from './portal-login';

describe('PortalLogin', () => {
  let component: PortalLoginComponent;
  let fixture: ComponentFixture<PortalLoginComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [PortalLoginComponent]
    })
    .compileComponents();

    fixture = TestBed.createComponent(PortalLoginComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
