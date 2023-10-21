import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { DevolucionCompraDetallesComponent } from './devolucion-compra-detalles.component';

describe('DevolucionCompraDetallesComponent', () => {
  let component: DevolucionCompraDetallesComponent;
  let fixture: ComponentFixture<DevolucionCompraDetallesComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ DevolucionCompraDetallesComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(DevolucionCompraDetallesComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
