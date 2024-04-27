import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { EmpresasUsuariosComponent } from './empresas-usuarios.component';

describe('EmpresasUsuariosComponent', () => {
  let component: EmpresasUsuariosComponent;
  let fixture: ComponentFixture<EmpresasUsuariosComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ EmpresasUsuariosComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(EmpresasUsuariosComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
