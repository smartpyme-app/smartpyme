import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { TiendaVentaProductoComponent } from './tienda-venta-producto.component';

describe('TiendaVentaProductoComponent', () => {
  let component: TiendaVentaProductoComponent;
  let fixture: ComponentFixture<TiendaVentaProductoComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ TiendaVentaProductoComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(TiendaVentaProductoComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});
