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

  it('en crédito fiscal de El Salvador guarda el descuento y no cambia la base', () => {
    const linea = montosLineaGastoDesdeDte(
      { cantidad: 2, precioUni: 100, ventaGravada: 180, montoDescu: 20 },
      { ivaRate: 0.13, precioSinImpuesto: false, tipoDte: '03' }
    );

    expect(linea.descuento).toBe(20);
    expect(linea.sub_total).toBe(180);
    expect(linea.iva).toBe(23.4);
    expect(linea.total).toBe(203.4);
  });

  it('en crédito fiscal no resta un descuento si el precio ya es el neto', () => {
    const linea = montosLineaGastoDesdeDte(
      { cantidad: 1, precioUni: 200, ventaGravada: 200, montoDescu: 20 },
      { ivaRate: 0.13, precioSinImpuesto: false, tipoDte: '03' }
    );

    expect(linea.descuento).toBe(0);
    expect(linea.sub_total).toBe(200);
    expect(linea.total).toBe(226);
  });

  it('en factura de El Salvador ignora el descuento si el precio ya incluye IVA', () => {
    const linea = montosLineaGastoDesdeDte(
      { cantidad: 1, precioUni: 113, ventaGravada: 113, montoDescu: 10 },
      { ivaRate: 0.13, precioSinImpuesto: false, tipoDte: '01' }
    );

    expect(linea.descuento).toBe(0);
    expect(linea.sub_total).toBe(100);
    expect(linea.iva).toBe(13);
    expect(linea.total).toBe(113);
  });

  it('en factura de El Salvador muestra el descuento si el precio viene sin IVA y cuadra', () => {
    const linea = montosLineaGastoDesdeDte(
      { cantidad: 2, precioUni: 100, ventaGravada: 203.4, montoDescu: 20 },
      { ivaRate: 0.13, precioSinImpuesto: false, tipoDte: '01' }
    );

    expect(linea.descuento).toBe(20);
    expect(linea.sub_total).toBe(180);
    expect(linea.total).toBe(203.4);
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
