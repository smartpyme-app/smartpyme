import { comparativoVentasGastosLibroIva } from './libro-iva-resumen.util';

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

  it('sin ventas no inventa porcentaje', () => {
    const r = comparativoVentasGastosLibroIva({
      totales: { ventas: 0, gastos: 150 },
    });
    expect(r.diferencia).toBe(-150);
    expect(r.porcentajeGastos).toBeNull();
  });
});
