import { badgesDocumentoAjuste, etiquetaBadgeAjuste, textoAjusteListado } from './documento-ajuste-badges.util';

describe('badgesDocumentoAjuste', () => {
  it('sin items ni suma → vacío', () => {
    expect(badgesDocumentoAjuste([], 0)).toEqual([]);
    expect(badgesDocumentoAjuste(null, null)).toEqual([]);
  });

  it('marca NC y ND por nombre de documento', () => {
    expect(
      badgesDocumentoAjuste(
        [
          { nombre_documento: 'Nota de crédito', total: 10 },
          { tipo_documento: 'Nota de débito', total: 5 },
        ],
        15
      )
    ).toEqual(['NC', 'ND']);
  });

  it('reconoce notas electrónicas CR', () => {
    expect(
      badgesDocumentoAjuste(
        [{ nombre_documento: 'Nota de Crédito Electrónica', total: 3 }],
        3
      )
    ).toEqual(['NC']);
  });

  it('marca DEV cuando el total de devoluciones supera NC/ND', () => {
    expect(
      badgesDocumentoAjuste([{ nombre_documento: 'Nota de crédito', total: 10 }], 25)
    ).toEqual(['NC', 'DEV']);
  });

  it('marca solo DEV si hay devoluciones sin NC/ND', () => {
    expect(badgesDocumentoAjuste([], 8)).toEqual(['DEV']);
  });

  it('etiqueta legible corta', () => {
    expect(etiquetaBadgeAjuste('NC')).toBe('Nota crédito');
    expect(etiquetaBadgeAjuste('ND')).toBe('Nota débito');
    expect(etiquetaBadgeAjuste('DEV')).toBe('Devolución');
  });

  it('une etiquetas para el listado', () => {
    expect(textoAjusteListado(['NC', 'DEV'])).toBe('Nota crédito · Devolución');
  });
});
