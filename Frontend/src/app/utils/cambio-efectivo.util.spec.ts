import { aplicarResumenPagoMultiple, calcularCambioEfectivo, resumenPagoMultiple } from './cambio-efectivo.util';

describe('calcularCambioEfectivo', () => {
  it('calcula el cambio sobre el total más la propina en pago de efectivo', () => {
    expect(calcularCambioEfectivo({
      montoPago: 120,
      total: 100,
      propina: 10,
      formaPago: 'Efectivo',
    })).toBe('10.00');
  });

  it('usa solo el total cuando no hay propina', () => {
    expect(calcularCambioEfectivo({
      montoPago: 120,
      total: 100,
      propina: 0,
      formaPago: 'Efectivo',
    })).toBe('20.00');
  });

  it('en pago mixto usa la parte en efectivo, no el total con propina', () => {
    expect(calcularCambioEfectivo({
      montoPago: 60,
      total: 100,
      propina: 10,
      formaPago: 'Multiple',
      efectivo: 50,
    })).toBe('10.00');
  });

  it('deja el cambio vacío si aún no hay efectivo recibido', () => {
    expect(calcularCambioEfectivo({
      montoPago: '',
      total: 100,
      propina: 10,
      formaPago: 'Efectivo',
    })).toBe('');
  });
});

describe('resumenPagoMultiple', () => {
  it('bloquea si el recibido no cubre el total', () => {
    const r = resumenPagoMultiple({
      total: 357.30,
      formaPagos: [
        { nombre: 'Efectivo', total: 220 },
        { nombre: 'POS DAVIVIENDA', total: 137.20 },
      ],
    });
    expect(r.puedeAplicar).toBe(false);
    expect(r.pendiente).toBeCloseTo(0.10, 2);
    expect(r.vuelto).toBe(0);
  });

  it('permite efectivo de más y calcula el vuelto sobre la parte en efectivo', () => {
    const r = resumenPagoMultiple({
      total: 357.30,
      formaPagos: [
        { nombre: 'Efectivo', total: 220 },
        { nombre: 'POS DAVIVIENDA', total: 150 },
      ],
    });
    expect(r.puedeAplicar).toBe(true);
    expect(r.pendiente).toBe(0);
    expect(r.vuelto).toBeCloseTo(12.70, 2);
    expect(r.efectivoAplicado).toBeCloseTo(207.30, 2);
    expect(r.efectivoRecibido).toBe(220);
  });

  it('no da vuelto ni aplica si el exceso es solo de tarjeta', () => {
    const r = resumenPagoMultiple({
      total: 357.30,
      formaPagos: [{ nombre: 'POS DAVIVIENDA', total: 400 }],
    });
    expect(r.puedeAplicar).toBe(false);
    expect(r.vuelto).toBe(0);
  });

  it('permite el exacto sin vuelto', () => {
    const r = resumenPagoMultiple({
      total: 357.30,
      formaPagos: [
        { nombre: 'Efectivo', total: 207.30 },
        { nombre: 'POS DAVIVIENDA', total: 150 },
      ],
    });
    expect(r.puedeAplicar).toBe(true);
    expect(r.vuelto).toBe(0);
    expect(r.efectivoAplicado).toBeCloseTo(207.30, 2);
  });

  it('al aplicar deja el efectivo neto y el monto recibido', () => {
    const formaPagos = [
      { nombre: 'Efectivo', total: 220 },
      { nombre: 'POS DAVIVIENDA', total: 150 },
    ];
    const venta = { monto_pago: '' };
    const r = resumenPagoMultiple({ total: 357.30, formaPagos });
    aplicarResumenPagoMultiple(venta, formaPagos, r);
    expect(venta.monto_pago).toBe(220);
    expect(formaPagos[0].total).toBeCloseTo(207.30, 2);
  });
});
