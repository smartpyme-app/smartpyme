import { aplicarPrefillCredito, queryFacturarCuota } from './creditos-facturar';

describe('queryFacturarCuota', () => {
  it('abre venta/crear con la cuota', () => {
    expect(queryFacturarCuota(15)).toEqual({ credito_cuota: 15 });
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
