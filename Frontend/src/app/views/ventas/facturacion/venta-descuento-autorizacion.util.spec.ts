import {
  debePedirPinDescuento,
  ventaTieneDescuentoLinea,
} from './venta-descuento-autorizacion.util';

describe('venta-descuento-autorizacion.util', () => {
  it('detecta descuento de línea en monto o porcentaje', () => {
    expect(ventaTieneDescuentoLinea({ detalles: [{ descuento: 1 }] })).toBeTrue();
    expect(ventaTieneDescuentoLinea({ detalles: [{ descuento_porcentaje: 10 }] })).toBeTrue();
    expect(ventaTieneDescuentoLinea({ detalles: [{ descuento_monto: 0.01 }] })).toBeTrue();
  });

  it('ignora puntos y ceros', () => {
    expect(ventaTieneDescuentoLinea({ detalles: [{ descuento: 0, descuento_puntos: 5 }] })).toBeFalse();
    expect(ventaTieneDescuentoLinea({ detalles: [] })).toBeFalse();
    expect(ventaTieneDescuentoLinea({})).toBeFalse();
  });

  it('no pide PIN si tiene aplicar, es cotización o no hay descuento', () => {
    const conDesc = { detalles: [{ descuento: 5 }] };
    expect(debePedirPinDescuento(true, conDesc)).toBeFalse();
    expect(debePedirPinDescuento(false, { ...conDesc, cotizacion: 1 })).toBeFalse();
    expect(debePedirPinDescuento(false, { detalles: [{ descuento: 0 }] })).toBeFalse();
  });

  it('pide PIN si no tiene aplicar y hay descuento de línea', () => {
    expect(debePedirPinDescuento(false, { detalles: [{ descuento: 5 }] })).toBeTrue();
  });
});
