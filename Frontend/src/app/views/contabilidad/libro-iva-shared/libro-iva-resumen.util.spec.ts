import {
  comparativoVentasGastosLibroIva,
  detalleImpuestoCrResumenLibroIva,
  etiquetaFilaDesgloseImpuesto,
  sumaDocumentosDesgloseLibroIva,
} from './libro-iva-resumen.util';

describe('comparativoVentasGastosLibroIva', () => {
  it('resta gastos de ventas y calcula el porcentaje', () => {
    const r = comparativoVentasGastosLibroIva({
      totales: { ventas: 1000, gastos: 400 },
    });
    expect(r.ventas).toBe(1000);
    expect(r.gastos).toBe(400);
    expect(r.diferencia).toBe(600);
    expect(r.porcentajeGastos).toBe(40);
  });

  it('detecta formato detalle CR', () => {
    expect(detalleImpuestoCrResumenLibroIva({ detalle_impuesto_cr: true })).toBe(true);
    expect(detalleImpuestoCrResumenLibroIva({})).toBe(false);
  });

  it('usa impuesto CR y suma documentos', () => {
    const filas = [
      { tarifa: '13%', etiqueta: 'IVA general', impuesto: 'IVA 13', base: 100, iva: 13, documentos: 2 },
      { tarifa: '8%', etiqueta: 'IVA reducido', impuesto: 'IVA 8', base: 50, iva: 4, documentos: 1 },
    ];
    expect(etiquetaFilaDesgloseImpuesto(filas[0])).toBe('IVA 13');
    expect(sumaDocumentosDesgloseLibroIva(filas)).toBe(3);
  });

  it('sin ventas no inventa porcentaje', () => {
    const r = comparativoVentasGastosLibroIva({
      totales: { ventas: 0, gastos: 150 },
    });
    expect(r.diferencia).toBe(-150);
    expect(r.porcentajeGastos).toBeNull();
  });
});
