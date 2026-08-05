import {
  asignarUnidades,
  lineaCompleta,
  matrizValida,
  buildAsignaciones
} from './pos-division';

describe('pos-division', () => {
  it('asigna y parte línea entre personas', () => {
    let m: Record<number, Record<number, number>> = {};
    m = asignarUnidades(m, 10, 1, 0.5, 1);
    m = asignarUnidades(m, 10, 2, 0.5, 1);
    expect(lineaCompleta(m, 10, 1, 2)).toBe(true);
    expect(matrizValida([{ id: 10, cantidad: 1 }], m, 2)).toBe(true);
    expect(buildAsignaciones([{ id: 10, cantidad: 1 }], m, 2)).toEqual([
      { orden_detalle_id: 10, pagador_index: 1, cantidad: 0.5 },
      { orden_detalle_id: 10, pagador_index: 2, cantidad: 0.5 }
    ]);
  });

  it('no permite sumar más que la línea', () => {
    let m = asignarUnidades({}, 10, 1, 1, 1);
    m = asignarUnidades(m, 10, 2, 1, 1);
    expect(lineaCompleta(m, 10, 1, 2)).toBe(true);
    expect(m[10][2]).toBe(0);
  });
});
