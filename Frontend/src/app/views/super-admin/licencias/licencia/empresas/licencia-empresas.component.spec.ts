import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { LicenciaEmpresasComponent } from './licencia-empresas.component';

describe('LicenciaEmpresasComponent', () => {
  let component: LicenciaEmpresasComponent;
  let fixture: ComponentFixture<LicenciaEmpresasComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ LicenciaEmpresasComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(LicenciaEmpresasComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
