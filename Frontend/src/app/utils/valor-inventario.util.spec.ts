import {
  campoCostoInventario,
  costoProductoParaMargen,
  rellenarCampoCostoActivoSiVacio,
  valorInventarioModo,
} from './valor-inventario.util';

describe('valorInventarioModo', () => {
  it('normaliza Promedio legacy', () => {
    expect(valorInventarioModo('Promedio')).toBe('promedio');
  });

  it('usa ultimo por defecto para ultimo', () => {
    expect(valorInventarioModo('ultimo')).toBe('ultimo');
  });

  it('mapea campo costo segun modo', () => {
    expect(campoCostoInventario('promedio')).toBe('costo_promedio');
    expect(campoCostoInventario('ultimo')).toBe('costo');
  });
});

describe('costoProductoParaMargen', () => {
  it('lee costo_promedio en modo promedio', () => {
    expect(costoProductoParaMargen({ costo: 1, costo_promedio: 5 }, 'promedio')).toBe(5);
  });

  it('usa costo como respaldo en modo promedio', () => {
    expect(costoProductoParaMargen({ costo: 3, costo_promedio: null }, 'promedio')).toBe(3);
  });
});

describe('rellenarCampoCostoActivoSiVacio', () => {
  it('copia costo a costo_promedio cuando la empresa usa promedio', () => {
    const p: Record<string, unknown> = { costo: 10, costo_promedio: null };
    rellenarCampoCostoActivoSiVacio(p, 'promedio');
    expect(p.costo_promedio).toBe(10);
  });
});
