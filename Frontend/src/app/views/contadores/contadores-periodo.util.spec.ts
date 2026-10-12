import {
  periodoCierrePorDefecto,
  textoVencimientoIva,
  vencimientoIvaSv,
} from './contadores-periodo.util';

describe('contadores-periodo.util', () => {
  it('periodo por defecto es el mes anterior', () => {
    const ref = new Date(2026, 9, 5); // octubre 2026
    expect(periodoCierrePorDefecto(ref)).toEqual({ mes: 9, anio: 2026 });
  });

  it('vencimiento IVA es día 14 del mes siguiente', () => {
    const v = vencimientoIvaSv(9, 2026);
    expect(v.getFullYear()).toBe(2026);
    expect(v.getMonth()).toBe(9);
    expect(v.getDate()).toBe(14);
  });

  it('texto de vencimiento menciona el mes del periodo', () => {
    const hoy = new Date(2026, 9, 1);
    const txt = textoVencimientoIva(9, 2026, hoy);
    expect(txt.toLowerCase()).toContain('septiembre');
  });
});
