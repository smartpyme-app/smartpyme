import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { DevolucionVentaDetallesComponent } from './devolucion-venta-detalles.component';

describe('DevolucionVentaDetallesComponent', () => {
  let component: DevolucionVentaDetallesComponent;
  let fixture: ComponentFixture<DevolucionVentaDetallesComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ DevolucionVentaDetallesComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(DevolucionVentaDetallesComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
