import { ComponentFixture, TestBed } from '@angular/core/testing';

import { AnnouncementsAdmin } from './announcements';

describe('Announcements', () => {
  let component: AnnouncementsAdmin;
  let fixture: ComponentFixture<AnnouncementsAdmin>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [AnnouncementsAdmin]
    })
    .compileComponents();

    fixture = TestBed.createComponent(AnnouncementsAdmin);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
