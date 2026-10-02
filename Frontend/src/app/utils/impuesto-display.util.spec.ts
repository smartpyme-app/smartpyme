import { displayNombreImpuesto, etiquetaImpuestoPais } from './impuesto-display.util';

describe('impuesto-display.util', () => {
  const hn = { pais: 'Honduras' };
  const sv = { pais: 'El Salvador' };

  it('usa ISV en Honduras', () => {
    expect(etiquetaImpuestoPais(hn)).toBe('ISV');
    expect(displayNombreImpuesto('IVA', hn)).toBe('ISV');
    expect(displayNombreImpuesto('IVA 15%', hn)).toBe('ISV 15%');
  });

  it('conserva IVA fuera de Honduras', () => {
    expect(etiquetaImpuestoPais(sv)).toBe('IVA');
    expect(displayNombreImpuesto('IVA 13%', sv)).toBe('IVA 13%');
  });
});
