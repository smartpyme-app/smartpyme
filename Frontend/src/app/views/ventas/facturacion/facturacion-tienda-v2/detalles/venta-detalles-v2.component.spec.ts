import { VentaDetallesV2Component } from './venta-detalles-v2.component';

describe('VentaDetallesV2Component', () => {
  function createComponent(overrides: { lotesActivo?: boolean; metodologia?: string } = {}): any {
    const component: any = Object.create(VentaDetallesV2Component.prototype);
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

  function detalleBase(precioIva: string | number): any {
    return {
      cantidad: 1,
      precio_iva: precioIva,
      precio: '0',
      costo: 0,
      descuento: 0,
      descuento_porcentaje: 0,
      descuento_monto: 0,
      tipo_gravado: 'gravada',
      porcentaje_impuesto: 13,
    };
  }

  function detalleConLotes(precioIva: string | number = '11.30'): any {
    return {
      ...detalleBase(precioIva),
      inventario_por_lotes: true,
      lotes_asignados: [{ lote_id: 10, numero_lote: 'L-1', cantidad: 1 }],
      lote_id: 10,
      lote: { id: 10, numero_lote: 'L-1' },
    };
  }

  it('no reformatea precio_iva mientras se escribe (keyup en vivo)', () => {
    const component = createComponent();
    const detalle = detalleBase('12');

    component.updateTotal(detalle);

    expect(detalle.precio_iva).toBe('12');
  });

  it('no fuerza 0.00 si el campo queda vacío a mitad de edición', () => {
    const component = createComponent();
    const detalle = detalleBase('');

    component.updateTotal(detalle);

    expect(detalle.precio_iva).toBe('');
  });

  it('formatea precio_iva a 2 decimales al confirmar el cambio', () => {
    const component = createComponent();
    const detalle = detalleBase('12.5');

    component.updateTotal(detalle, true);

    expect(detalle.precio_iva).toBe('12.50');
  });

  it('al cambiar el precio no elimina la asignación de lotes', () => {
    const component = createComponent();
    const detalle = detalleConLotes('11.30');

    detalle.precio_iva = '22.60';
    component.updateTotal(detalle, true);

    expect(detalle.lotes_asignados).toEqual([{ lote_id: 10, numero_lote: 'L-1', cantidad: 1 }]);
    expect(detalle.lote_id).toBe(10);
    expect(detalle.lote).toEqual({ id: 10, numero_lote: 'L-1' });
  });

  it('al cambiar la cantidad sí elimina la asignación de lotes', () => {
    const component = createComponent();
    const detalle = detalleConLotes('11.30');

    detalle.cantidad = 3;
    component.onCantidadChange(detalle);

    expect(detalle.lotes_asignados).toBeNull();
    expect(detalle.lote_id).toBeNull();
    expect(detalle.lote).toBeNull();
  });

  it('al confirmar lotes no elimina la asignación aunque cambie la cantidad', () => {
    const component = createComponent();
    const detalle = detalleConLotes('11.30');
    detalle.cantidad = 5;

    component.skipLimpiarLotes = true;
    component.onCantidadChange(detalle);
    component.skipLimpiarLotes = false;

    expect(detalle.lotes_asignados).toEqual([{ lote_id: 10, numero_lote: 'L-1', cantidad: 1 }]);
    expect(detalle.lote_id).toBe(10);
  });

  it('onPrecioSelectChange conserva el valor de catálogo en el select', () => {
    const component = createComponent();
    const detalle = detalleConLotes('11.30');
    detalle.precios = [
      { precio: 10, precio_sin_iva: 10, precio_con_iva: 11.3 },
      { precio: 20, precio_sin_iva: 20, precio_con_iva: 22.6 },
    ];
    detalle.precio = 20;

    component.onPrecioSelectChange(detalle);

    expect(detalle.precio).toBe(20);
    expect(detalle.lotes_asignados).toEqual([{ lote_id: 10, numero_lote: 'L-1', cantidad: 1 }]);
  });
});
