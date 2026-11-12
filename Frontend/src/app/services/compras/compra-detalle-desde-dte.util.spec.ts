import { lineaGastoTieneDesglose, montosLineaGastoDesdeDte } from './compra-detalle-desde-dte.util';

describe('montosLineaGastoDesdeDte', () => {
  it('en Costa Rica usa la base después del descuento y suma el 13%', () => {
    const linea = montosLineaGastoDesdeDte(
      {
        cantidad: 21,
        precioUni: 2632.75,
        ventaGravada: 27643.88,
        montoDescu: 27643.87,
      },
      { ivaRate: 0.13, precioSinImpuesto: true, tipoDte: '01' }
    );

    expect(linea.precio_unitario).toBe(2632.75);
    expect(linea.descuento).toBe(27643.87);
    expect(linea.sub_total).toBe(27643.88);
    expect(linea.iva).toBe(3593.7);
    expect(linea.total).toBe(31237.58);
    expect(linea.tipo_gravado).toBe('gravada');
  });

  it('un solo ítem con cantidad o descuento sigue siendo desglose', () => {
    expect(lineaGastoTieneDesglose({ cantidad: 21, precio_unitario: 2632.75, sub_total: 27643.87 })).toBe(true);
    expect(lineaGastoTieneDesglose({ cantidad: 1, precio_unitario: 100, sub_total: 100 })).toBe(false);
  });

  it('en El Salvador factura sigue tratando el monto como precio con IVA', () => {
    const linea = montosLineaGastoDesdeDte(
      { cantidad: 1, precioUni: 113, ventaGravada: 113 },
      { ivaRate: 0.13, precioSinImpuesto: false, tipoDte: '01' }
    );

    expect(linea.descuento).toBe(0);
    expect(linea.sub_total).toBe(100);
    expect(linea.iva).toBe(13);
    expect(linea.total).toBe(113);
  });
});
