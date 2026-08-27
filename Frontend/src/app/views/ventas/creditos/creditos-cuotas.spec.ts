import { aplicarPlanAVenta, avanceCuotas, estadoCola, etiquetaEstadoContrato, etiquetaTipoCredito, generarPreviewCuotas, planCuadra, snapshotVentaMontos, sumaMontosCuotas } from './creditos-cuotas';

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

  it('usa montos editados si la suma cuadra', () => {
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
      cuotas: [
        { numero: 1, monto: 50, fechaVencimiento: '2026-01-15' },
        { numero: 2, monto: 20, fechaVencimiento: '2026-02-15' },
        { numero: 3, monto: 20, fechaVencimiento: '2026-03-15' },
      ],
    }, snap);
    expect(venta.total).toBe(50);
    expect(venta.credito_contrato.cuotas.map((c: any) => c.monto)).toEqual([50, 20, 20]);
  });

  it('no aplica si la suma no cuadra', () => {
    const venta: any = {
      total: 90,
      sub_total: 90,
      iva: 0,
      detalles: [{ cantidad: 1, precio: 90, total: 90, sub_total: 90, iva: 0, descuento: 0 }],
    };
    const snap = snapshotVentaMontos(venta);
    aplicarPlanAVenta(venta, {
      tipo: 'bien',
      n_cuotas: 2,
      fecha_inicio: '2026-01-15',
      cuotas: [
        { numero: 1, monto: 50, fechaVencimiento: '2026-01-15' },
        { numero: 2, monto: 20, fechaVencimiento: '2026-02-15' },
      ],
    }, snap);
    expect(venta.total).toBe(90);
    expect(venta.credito_contrato).toBeUndefined();
  });
});

describe('planCuadra', () => {
  it('exige suma exacta y montos > 0', () => {
    expect(sumaMontosCuotas([{ monto: 50 }, { monto: 40 }])).toBe(90);
    expect(planCuadra(90, [{ monto: 50 }, { monto: 40 }])).toBe(true);
    expect(planCuadra(90, [{ monto: 50 }, { monto: 39 }])).toBe(false);
    expect(planCuadra(90, [{ monto: 90 }, { monto: 0 }])).toBe(false);
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

describe('etiquetas de contrato', () => {
  it('nombra tipo y estado', () => {
    expect(etiquetaTipoCredito('bien')).toBe('Bien');
    expect(etiquetaTipoCredito('prestamo')).toBe('Préstamo');
    expect(etiquetaEstadoContrato('activo')).toBe('Activo');
    expect(etiquetaEstadoContrato('cerrado')).toBe('Cerrado');
  });
});

describe('avanceCuotas', () => {
  it('arma 2/4 y el porcentaje', () => {
    expect(avanceCuotas({ n_cuotas: 4, cuotas_hechas: 2 })).toEqual({
      hechas: 2,
      total: 4,
      porcentaje: 50,
      tipo: 'warning',
    });
  });

  it('va en verde si ya están todas', () => {
    expect(avanceCuotas({ n_cuotas: 4, cuotas_hechas: 4 }).tipo).toBe('success');
  });

  it('no se pasa del total', () => {
    expect(avanceCuotas({ n_cuotas: 3, cuotas_hechas: 9 }).hechas).toBe(3);
  });
});
