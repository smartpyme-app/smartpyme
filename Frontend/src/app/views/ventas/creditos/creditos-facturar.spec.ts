import { aplicarPrefillCredito, puedeFacturarVentaCuota, prepararVentaParaFacturarCuota, queryFacturarCuota, queryFacturarVenta, sinCorrelativo } from './creditos-facturar';

describe('queryFacturarCuota', () => {
  it('abre venta/crear con la cuota', () => {
    expect(queryFacturarCuota(15)).toEqual({ credito_cuota: 15 });
  });
});

describe('queryFacturarVenta', () => {
  it('abre venta/crear con la venta existente', () => {
    expect(queryFacturarVenta(88)).toEqual({ id_venta: 88, facturar: '1' });
  });
});

describe('puedeFacturarVentaCuota', () => {
  it('no muestra Facturar si la empresa no tiene créditos', () => {
    expect(puedeFacturarVentaCuota({ estado: 'Pendiente', correlativo: null })).toBe(false);
    expect(puedeFacturarVentaCuota({ estado: 'Pendiente', correlativo: null }, false)).toBe(false);
  });

  it('solo si hay créditos, está pendiente y sin correlativo', () => {
    expect(puedeFacturarVentaCuota({ estado: 'Pendiente', correlativo: null }, true)).toBe(true);
    expect(puedeFacturarVentaCuota({ estado: 'Pendiente', correlativo: '' }, true)).toBe(true);
    expect(puedeFacturarVentaCuota({ estado: 'Pendiente', correlativo: 0 }, true)).toBe(true);
    expect(puedeFacturarVentaCuota({ estado: 'Pendiente', correlativo: 19 }, true)).toBe(false);
    expect(puedeFacturarVentaCuota({ estado: 'Pagada', correlativo: null }, true)).toBe(false);
  });
});

describe('sinCorrelativo', () => {
  it('trata null, vacío y 0 como sin número', () => {
    expect(sinCorrelativo(null)).toBe(true);
    expect(sinCorrelativo('')).toBe(true);
    expect(sinCorrelativo(0)).toBe(true);
    expect(sinCorrelativo(12)).toBe(false);
  });
});

describe('prepararVentaParaFacturarCuota', () => {
  it('quita crédito para que no quede pendiente', () => {
    const venta: any = {
      estado: 'Pendiente',
      condicion: 'Crédito',
      credito: true,
      credito_contrato: { n_cuotas: 3 },
    };
    prepararVentaParaFacturarCuota(venta);
    expect(venta.credito).toBe(false);
    expect(venta.estado).toBe('Pagada');
    expect(venta.condicion).toBe('Contado');
    expect(venta.credito_contrato).toBeUndefined();
  });
});

describe('aplicarPrefillCredito', () => {
  it('prellena cliente, monto y fecha de la cuota', () => {
    const venta: any = { detalles: [] };
    const out = aplicarPrefillCredito(venta, {
      id_cuota: 3,
      id_cliente: 8,
      cliente: { id: 8, nombre: 'Ana' },
      monto: 50.5,
      fecha: '2026-08-20',
      descripcion: 'Cuota 1',
      id_documento: null,
      documento_bloqueado: false,
    });
    expect(out.id_cliente).toBe(8);
    expect(out.fecha).toBe('2026-08-20');
    expect(out.estado).toBe('Pendiente');
    expect(out.id_credito_cuota).toBe(3);
    expect(out.detalles[0].total).toBe(50.5);
    expect(out.detalles[0].descripcion).toBe('Cuota 1');
  });

  it('bloquea el documento si el crédito ya tiene tipo fijo', () => {
    const out = aplicarPrefillCredito({ detalles: [] }, {
      id_cuota: 4,
      id_cliente: 8,
      cliente: { id: 8 },
      monto: 10,
      fecha: '2026-09-20',
      descripcion: 'Cuota 2',
      id_documento: 22,
      documento_bloqueado: true,
    });
    expect(out.id_documento).toBe(22);
    expect(out.documento_bloqueado).toBe(true);
  });
});
