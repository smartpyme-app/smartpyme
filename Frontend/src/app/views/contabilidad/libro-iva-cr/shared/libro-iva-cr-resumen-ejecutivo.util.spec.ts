import { resumenEjecutivoPorTipoIva } from './libro-iva-cr-resumen-ejecutivo.util';

describe('resumenEjecutivoPorTipoIva', () => {
  it('junta la misma tarifa de ventas y compras y calcula el neto', () => {
    const rows = resumenEjecutivoPorTipoIva({
      ventas_por_impuesto: [
        { tarifa: '13%', etiqueta: 'IVA', iva: 130 },
        { tarifa: '1%', etiqueta: 'IVA reducido', iva: 10 },
      ],
      compras_por_impuesto: [
        { tarifa: '13%', etiqueta: 'IVA compras', iva: 40 },
        { tarifa: 'LIBRO_EX', etiqueta: 'Exentas (libro compras)', iva: 0 },
      ],
    });

    expect(rows.map((r) => r.tipo)).toEqual(['IVA 13%', 'IVA 1%', 'Exentas (libro compras)']);
    expect(rows[0]).toEqual({ tipo: 'IVA 13%', ventas: 130, compras: 40, neto: 90 });
    expect(rows[1]).toEqual({ tipo: 'IVA 1%', ventas: 10, compras: 0, neto: 10 });
    expect(rows[2].neto).toBe(0);
  });
});
