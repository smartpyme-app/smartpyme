import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { OrganizacionEmpresasComponent } from './organizacion-empresas.component';

describe('OrganizacionEmpresasComponent', () => {
  let component: OrganizacionEmpresasComponent;
  let fixture: ComponentFixture<OrganizacionEmpresasComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ OrganizacionEmpresasComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(OrganizacionEmpresasComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
