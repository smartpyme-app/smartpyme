import { async, ComponentFixture, TestBed } from '@angular/core/testing';

import { FacturacionCompraComponent } from './facturacion-compra.component';

describe('FacturacionCompraComponent', () => {
  let component: FacturacionCompraComponent;
  let fixture: ComponentFixture<FacturacionCompraComponent>;

  beforeEach(async(() => {
    TestBed.configureTestingModule({
      declarations: [ FacturacionCompraComponent ]
    })
    .compileComponents();
  }));

  beforeEach(() => {
    fixture = TestBed.createComponent(FacturacionCompraComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should be created', () => {
    expect(component).toBeTruthy();
  });
});

describe('FacturacionCompraComponent lotes SPT-443', () => {
  function createComponent(): any {
    const component: any = Object.create(FacturacionCompraComponent.prototype);
    component.apiService = { isLotesActivo: () => true };
    component.alertService = { error: jasmine.createSpy('error') };
    component.compra = { cotizacion: 0, detalles: [] };
    component.saving = false;
    return component;
  }

  it('permite guardar una línea con varios lotes aunque lote_id sea null', () => {
    const component = createComponent();
    component.compra.detalles = [{
      nombre_producto: 'Producto A',
      inventario_por_lotes: true,
      lote_id: null,
      lotes_asignados: [
        { lote_id: 1, cantidad: 10 },
        { lote_id: 2, cantidad: 20 },
      ],
    }];

    const falta = component.lineaCompraSinLotes(component.compra.detalles[0]);

    expect(falta).toBe(false);
  });

  it('bloquea guardar si el producto usa lotes y no hay asignación', () => {
    const component = createComponent();
    const detalle = {
      nombre_producto: 'Producto A',
      inventario_por_lotes: true,
      lote_id: null,
      lotes_asignados: null,
    };

    expect(component.lineaCompraSinLotes(detalle)).toBe(true);
  });
});
