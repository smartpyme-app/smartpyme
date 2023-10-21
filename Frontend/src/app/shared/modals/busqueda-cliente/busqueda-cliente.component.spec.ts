import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { BusquedaClienteComponent } from './busqueda-cliente.component';

describe('BusquedaClienteComponent', () => {
  let component: BusquedaClienteComponent;
  let fixture: ComponentFixture<BusquedaClienteComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ BusquedaClienteComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(BusquedaClienteComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
