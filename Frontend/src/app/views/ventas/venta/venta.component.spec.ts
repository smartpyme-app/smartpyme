import { VentaComponent } from './venta.component';

describe('VentaComponent', () => {
  function createComponent(): any {
    const component: any = Object.create(VentaComponent.prototype);
    component.apiService = {
      auth_user: () => ({
        empresa: { iva: 13, pais: 'El Salvador' },
      }),
    };
    component.venta = {
      iva: 2.19,
      cobrar_impuestos: true,
    };
    return component;
  }

  it('muestra el total de línea con IVA a partir del precio con IVA, no del neto redondeado', () => {
    const component = createComponent();
    const detalle = {
      cantidad: 1,
      precio: 4.424778761,
      descuento: 0,
      total: 4.42,
      tipo_gravado: 'gravada',
    };

    expect(component.precioDetalleConIva(detalle)).toBe(5);
    expect(component.totalDetalleConIva(detalle)).toBe(5);
  });

  it('calcula el total con IVA como precio con IVA por cantidad menos descuento con IVA', () => {
    const component = createComponent();
    const detalle = {
      cantidad: 2,
      precio: 4.424778761,
      descuento: 0.884955752,
      total: 7.96,
      tipo_gravado: 'gravada',
    };

    expect(component.precioDetalleConIva(detalle)).toBe(5);
    expect(component.descuentoDetalleConIva(detalle)).toBe(1);
    expect(component.totalDetalleConIva(detalle)).toBe(9);
  });
});
