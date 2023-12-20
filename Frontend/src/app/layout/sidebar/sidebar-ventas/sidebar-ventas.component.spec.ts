import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { SidebarVentasComponent } from './sidebar-admin-ventas.component';

describe('SidebarVentasComponent', () => {
  let component: SidebarVentasComponent;
  let fixture: ComponentFixture<SidebarVentasComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ SidebarVentasComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(SidebarVentasComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
