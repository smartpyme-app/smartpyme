import { VentaDetallesV2Component } from './venta-detalles-v2.component';

describe('VentaDetallesV2Component', () => {
  function createComponent(): any {
    const component: any = Object.create(VentaDetallesV2Component.prototype);
    component.apiService = {
      auth_user: () => ({
        empresa: { iva: 13, pais: 'El Salvador' },
      }),
      isLotesActivo: () => false,
    };
    component.venta = { cobrar_impuestos: true, detalles: [] };
    component.update = { emit: jasmine.createSpy('update') };
    component.sumTotal = { emit: jasmine.createSpy('sumTotal') };
    component.skipLimpiarLotes = true;
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
});
