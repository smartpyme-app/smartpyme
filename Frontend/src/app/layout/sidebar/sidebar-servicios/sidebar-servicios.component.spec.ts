import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { SidebarServiciosComponent } from './sidebar-admin-servicios.component';

describe('SidebarServiciosComponent', () => {
  let component: SidebarServiciosComponent;
  let fixture: ComponentFixture<SidebarServiciosComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ SidebarServiciosComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(SidebarServiciosComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
