import { aplicarPlanAVenta, estadoCola, generarPreviewCuotas, snapshotVentaMontos } from './creditos-cuotas';

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

describe('aplicarPlanAVenta', () => {
  it('deja esta factura en la cuota 1 y guarda el monto del contrato', () => {
    const venta: any = {
      total: 90,
      sub_total: 90,
      iva: 0,
      detalles: [{ cantidad: 1, precio: 90, total: 90, sub_total: 90, iva: 0, descuento: 0 }],
    };
    const snap = snapshotVentaMontos(venta);
    aplicarPlanAVenta(venta, {
      tipo: 'bien',
      n_cuotas: 3,
      fecha_inicio: '2026-01-15',
      concepto: 'Moto',
    }, snap);
    expect(venta.total).toBe(30);
    expect(venta.detalles[0].cantidad).toBe(1);
    expect(venta.credito_contrato.monto).toBe(90);
    expect(venta.credito_contrato.n_cuotas).toBe(3);
    expect(venta.fecha_pago).toBe('2026-01-15');
    expect(venta.estado).toBe('Pagada');
  });

  it('escala precio_iva para que líneas e IVA coincidan con la cuota 1', () => {
    const venta: any = {
      total: 113,
      sub_total: 100,
      iva: 13,
      detalles: [{
        cantidad: 1,
        precio: 100,
        precio_iva: 113,
        precio_con_iva: 113,
        total: 100,
        total_iva: 113,
        sub_total: 100,
        iva: 13,
        descuento: 0,
      }],
    };
    const snap = snapshotVentaMontos(venta);
    aplicarPlanAVenta(venta, {
      tipo: 'bien',
      n_cuotas: 3,
      fecha_inicio: '2026-01-15',
    }, snap);
    expect(venta.detalles[0].cantidad).toBe(1);
    expect(Number(venta.detalles[0].precio_iva)).toBe(37.67);
    expect(Number(venta.detalles[0].precio)).toBe(33.34);
    expect(venta.total).toBe(37.67);
    expect(venta.credito_contrato.monto).toBe(113);
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
