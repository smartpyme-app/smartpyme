import { resumenTarifasLibroIvaCr } from './libro-iva-cr-resumen-tarifas.util';

describe('resumenTarifasLibroIvaCr', () => {
  it('arma base e IVA por tarifa y trata el exento como IVA 0%', () => {
    const rows = resumenTarifasLibroIvaCr({
      subtotal_13: 100,
      iva_13: 13,
      subtotal_exento: 40,
      subtotal_exonerado: 10,
    });

    expect(rows.map((r) => r.etiqueta)).toEqual([
      'IVA 13%',
      'IVA 8%',
      'IVA 4%',
      'IVA 2%',
      'IVA 1%',
      'Exento (IVA 0%)',
      'Exonerado',
    ]);
    expect(rows[0]).toEqual({ etiqueta: 'IVA 13%', base: 100, iva: 13 });
    expect(rows[1]).toEqual({ etiqueta: 'IVA 8%', base: 0, iva: 0 });
    expect(rows.find((r) => r.etiqueta === 'Exento (IVA 0%)')).toEqual({
      etiqueta: 'Exento (IVA 0%)',
      base: 40,
      iva: 0,
    });
    expect(rows.find((r) => r.etiqueta === 'Exonerado')?.base).toBe(10);
  });
});
