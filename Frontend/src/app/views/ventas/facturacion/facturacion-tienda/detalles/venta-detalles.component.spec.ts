import { VentaDetallesComponent } from './venta-detalles.component';

describe('VentaDetallesComponent', () => {
  function createComponent(overrides: { lotesActivo?: boolean; metodologia?: string } = {}): any {
    const component: any = Object.create(VentaDetallesComponent.prototype);
    component.apiService = {
      auth_user: () => ({
        empresa: {
          iva: 13,
          pais: 'El Salvador',
          custom_empresa: {
            configuraciones: {
              lotes_metodologia: overrides.metodologia ?? 'Manual',
            },
          },
        },
      }),
      isLotesActivo: () => overrides.lotesActivo ?? true,
    };
    component.venta = { cobrar_impuestos: true, detalles: [] };
    component.update = { emit: jasmine.createSpy('update') };
    component.sumTotal = { emit: jasmine.createSpy('sumTotal') };
    component.skipLimpiarLotes = false;
    return component;
  }

  function detalleConLotes(): any {
    return {
      cantidad: 2,
      precio: 10,
      costo: 5,
      descuento: 0,
      descuento_porcentaje: 0,
      descuento_monto: 0,
      tipo_gravado: 'gravada',
      porcentaje_impuesto: 13,
      inventario_por_lotes: true,
      lotes_asignados: [{ lote_id: 7, numero_lote: 'A-1', cantidad: 2 }],
      lote_id: 7,
      lote: { id: 7, numero_lote: 'A-1' },
    };
  }

  it('al cambiar el precio no elimina la asignación de lotes', () => {
    const component = createComponent();
    const detalle = detalleConLotes();

    detalle.precio = 15;
    component.updateTotal(detalle);

    expect(detalle.lotes_asignados).toEqual([{ lote_id: 7, numero_lote: 'A-1', cantidad: 2 }]);
    expect(detalle.lote_id).toBe(7);
    expect(detalle.lote).toEqual({ id: 7, numero_lote: 'A-1' });
  });

  it('al cambiar la cantidad sí elimina la asignación de lotes', () => {
    const component = createComponent();
    const detalle = detalleConLotes();

    detalle.cantidad = 4;
    component.onCantidadChange(detalle);

    expect(detalle.lotes_asignados).toBeNull();
    expect(detalle.lote_id).toBeNull();
    expect(detalle.lote).toBeNull();
  });

  it('al confirmar lotes no elimina la asignación aunque cambie la cantidad', () => {
    const component = createComponent();
    const detalle = detalleConLotes();
    detalle.cantidad = 8;

    component.skipLimpiarLotes = true;
    component.onCantidadChange(detalle);
    component.skipLimpiarLotes = false;

    expect(detalle.lotes_asignados).toEqual([{ lote_id: 7, numero_lote: 'A-1', cantidad: 2 }]);
    expect(detalle.lote_id).toBe(7);
  });
});
