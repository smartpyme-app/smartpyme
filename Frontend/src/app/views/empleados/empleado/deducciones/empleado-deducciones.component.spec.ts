import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { EmpleadoDeduccionesComponent } from './empleado-deducciones.component';

describe('EmpleadoDeduccionesComponent', () => {
  let component: EmpleadoDeduccionesComponent;
  let fixture: ComponentFixture<EmpleadoDeduccionesComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ EmpleadoDeduccionesComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(EmpleadoDeduccionesComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
