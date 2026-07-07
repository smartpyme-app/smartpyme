import {
  esNombreNotaCredito,
  esNombreNotaDebito,
  NOMBRE_DOCUMENTO_CR,
} from './documento-nombre-options';

describe('documento-nombre-options CR', () => {
  it('reconoce nota de crédito SV y CR', () => {
    expect(esNombreNotaCredito('Nota de crédito')).toBe(true);
    expect(esNombreNotaCredito(NOMBRE_DOCUMENTO_CR.notaCredito)).toBe(true);
    expect(esNombreNotaCredito('Factura')).toBe(false);
  });

  it('reconoce nota de débito SV y CR', () => {
    expect(esNombreNotaDebito('Nota de débito')).toBe(true);
    expect(esNombreNotaDebito(NOMBRE_DOCUMENTO_CR.notaDebito)).toBe(true);
  });
});
