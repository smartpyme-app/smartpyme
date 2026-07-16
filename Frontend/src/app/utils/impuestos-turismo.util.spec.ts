import {
  empresaTieneImpuestoTurismo,
  filtrarImpuestosTurismo,
} from './impuestos-turismo.util';

describe('impuestos-turismo.util', () => {
  it('filtra solo impuestos 5% que no son IVA', () => {
    const impuestos = [
      { id: 1, porcentaje: 13, codigo_mh: '20' },
      { id: 2, porcentaje: 5, codigo_mh: '59' },
      { id: 3, porcentaje: 5, codigo_mh: '20' },
      { id: 4, porcentaje: 5, codigo_mh: null },
      { id: 5, porcentaje: 2, codigo_mh: 'C8' },
    ];

    expect(filtrarImpuestosTurismo(impuestos).map((i) => i.id)).toEqual([2, 4]);
    expect(empresaTieneImpuestoTurismo(impuestos)).toBe(true);
  });

  it('retorna false si la empresa no tiene impuesto turismo', () => {
    expect(
      empresaTieneImpuestoTurismo([
        { id: 1, porcentaje: 13, codigo_mh: '20' },
        { id: 3, porcentaje: 5, codigo_mh: '20' },
      ])
    ).toBe(false);
    expect(empresaTieneImpuestoTurismo([])).toBe(false);
    expect(empresaTieneImpuestoTurismo(null)).toBe(false);
  });
});
