import { calcularCambioEfectivo } from './cambio-efectivo.util';

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
