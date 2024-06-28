import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { AdminFacturacionesComponent } from './admin-facturaciones.component';

describe('AdminUsuariosComponent', () => {
  let component: AdminFacturacionesComponent;
  let fixture: ComponentFixture<AdminFacturacionesComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ AdminFacturacionesComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(AdminFacturacionesComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
