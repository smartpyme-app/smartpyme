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

  it('en una venta de Shopify el descuento es precio por cantidad menos el total, sin el centavo del IVA', () => {
    const component = createComponent();
    component.venta.referencia_shopify = 'SHOPIFY-17148113387890';
    const detalle = {
      cantidad: 9,
      precio: 10.62,
      precio_con_iva: 12,
      descuento: 9.74,
      gravada: 85.84,
      exenta: 0,
      no_sujeta: 0,
      iva: 11.16,
      total: 85.84,
    };

    expect(component.precioDetalleConIva(detalle)).toBe(12);
    expect(component.descuentoDetalleConIva(detalle)).toBe(11);
    expect(component.totalDetalleConIva(detalle)).toBe(97);
  });

  it('consolidarShopify pide el pedido y sustituye la venta', () => {
    const component = createComponent();
    component.venta = { id: 5, referencia_shopify: 'SHOPIFY-1' };
    component.alertService = { success: () => undefined, warning: () => undefined, error: () => undefined };
    component.loadAll = () => undefined;
    let urlLlamada = '';
    component.apiService.store = (url: string) => {
      urlLlamada = url;
      return {
        subscribe: (ok: (resp: any) => void) => ok({
          status: 'ok',
          venta: { id: 5, total: 102, referencia_shopify: 'SHOPIFY-1' },
          mensaje: 'Venta consolidada con Shopify',
        }),
      };
    };

    component.consolidarShopify();

    expect(urlLlamada).toBe('venta/5/shopify/consolidar');
    expect(component.venta.total).toBe(102);
  });

  it('sin referencia de Shopify no llama al API', () => {
    const component = createComponent();
    component.venta = { id: 5 };
    let llamado = false;
    component.apiService.store = () => {
      llamado = true;
      return { subscribe: () => undefined };
    };

    component.consolidarShopify();

    expect(llamado).toBeFalse();
  });
});
