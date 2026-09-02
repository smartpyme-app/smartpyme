import { CompraDetallesComponent } from './compra-detalles.component';

describe('CompraDetallesComponent lotes SPT-443', () => {
  function createComponent(overrides: { lotesActivo?: boolean; metodologia?: string } = {}): any {
    const component: any = Object.create(CompraDetallesComponent.prototype);
    component.apiService = {
      isLotesActivo: () => overrides.lotesActivo ?? true,
      getLotesMetodologia: () => overrides.metodologia ?? 'FIFO',
      auth_user: () => ({ empresa: { iva: 13, pais: 'El Salvador' } }),
    };
    component.compra = { id_bodega: 1, detalles: [] };
    component.update = { emit: jasmine.createSpy('update') };
    component.sumTotal = { emit: jasmine.createSpy('sumTotal') };
    component.skipLimpiarLotes = false;
    component.lotesModal = { abrir: jasmine.createSpy('abrir') };
    return component;
  }

  it('pide distribución de lotes en FIFO, no solo en Manual', () => {
    const fifo = createComponent({ metodologia: 'FIFO' });
    const fefo = createComponent({ metodologia: 'FEFO' });
    const manual = createComponent({ metodologia: 'Manual' });
    const detalle = { inventario_por_lotes: true };

    expect(fifo.requiereDistribucionLotes(detalle)).toBe(true);
    expect(fefo.requiereDistribucionLotes(detalle)).toBe(true);
    expect(manual.requiereDistribucionLotes(detalle)).toBe(true);
  });

  it('no pide lotes si el módulo está apagado', () => {
    const component = createComponent({ lotesActivo: false });
    expect(component.requiereDistribucionLotes({ inventario_por_lotes: true })).toBe(false);
  });

  it('al confirmar lotes deja una sola línea con varios lotes y la suma como cantidad', () => {
    const component = createComponent();
    const detalle = {
      id_producto: 9,
      costo: 5,
      descuento: 0,
      cantidad: 1,
      inventario_por_lotes: true,
      lotes_asignados: [
        { lote_id: 1, numero_lote: 'A', cantidad: 10 },
        { lote_id: 2, numero_lote: 'B', cantidad: 20 },
      ],
      lote_id: null,
    };
    component.compra.detalles = [detalle];

    component.onLotesConfirmados(detalle);

    expect(component.compra.detalles.length).toBe(1);
    expect(detalle.cantidad).toBe(30);
    expect(detalle.lotes_asignados.length).toBe(2);
    expect(component.update.emit).toHaveBeenCalled();
  });

  it('al agregar el mismo producto fusiona en una línea y reabre el modal de lotes', () => {
    const component = createComponent();
    const existente = {
      id_producto: 9,
      cantidad: 5,
      costo: 2,
      descuento: 0,
      inventario_por_lotes: true,
      lote_id: 1,
      lotes_asignados: [{ lote_id: 1, numero_lote: 'A', cantidad: 5 }],
    };
    component.compra.detalles = [existente];
    spyOn(window, 'setTimeout').and.callFake(((fn: any) => {
      if (typeof fn === 'function') {
        fn();
      }
      return 0;
    }) as any);

    component.productoSelect({
      id_producto: 9,
      cantidad: 3,
      costo: 2,
      descuento: 0,
      inventario_por_lotes: true,
    });

    expect(component.compra.detalles.length).toBe(1);
    expect(component.compra.detalles[0].cantidad).toBe(8);
    expect(component.lotesModal.abrir).toHaveBeenCalled();
  });
});
