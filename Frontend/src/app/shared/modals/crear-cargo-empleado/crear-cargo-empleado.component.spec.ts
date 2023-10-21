import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { CrearCargoEmpleadoComponent } from './crear-cargo-empleado.component';

describe('CrearCargoEmpleadoComponent', () => {
  let component: CrearCargoEmpleadoComponent;
  let fixture: ComponentFixture<CrearCargoEmpleadoComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ CrearCargoEmpleadoComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(CrearCargoEmpleadoComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
