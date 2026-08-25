import { estadoCola, generarPreviewCuotas } from './creditos-cuotas';

describe('generarPreviewCuotas', () => {
  it('reparte centavos en la última cuota', () => {
    const cuotas = generarPreviewCuotas(100, 3, '2026-01-15');
    expect(cuotas.map((c) => c.monto)).toEqual([33.33, 33.33, 33.34]);
    expect(cuotas.reduce((s, c) => s + c.monto, 0)).toBeCloseTo(100, 2);
  });

  it('avanza un mes por cuota', () => {
    const cuotas = generarPreviewCuotas(90, 3, '2026-01-15');
    expect(cuotas.map((c) => c.fechaVencimiento)).toEqual([
      '2026-01-15',
      '2026-02-15',
      '2026-03-15',
    ]);
  });
});

describe('estadoCola', () => {
  it('marca vencida si vence hoy o ya pasó y no tiene venta', () => {
    expect(estadoCola(null, '2026-08-24', '2026-08-24')).toBe('vencida');
    expect(estadoCola(null, '2026-08-20', '2026-08-24')).toBe('vencida');
  });

  it('marca por facturar si vence dentro de 7 días', () => {
    expect(estadoCola(null, '2026-08-31', '2026-08-24')).toBe('por_facturar');
  });

  it('excluye cuotas a más de 7 días y las que ya tienen venta', () => {
    expect(estadoCola(null, '2026-09-01', '2026-08-24')).toBeNull();
    expect(estadoCola(99, '2026-08-20', '2026-08-24')).toBeNull();
  });
});
