import { parseMontoInput } from './parse-monto.util';

describe('parseMontoInput', () => {
  it('interpreta 25.00 como 25 dólares, no como 0.25 centavos', () => {
    expect(parseMontoInput('25.00')).toBe(25);
    expect(parseMontoInput('25')).toBe(25);
  });

  it('acepta coma decimal (25,50 → 25.50)', () => {
    expect(parseMontoInput('25,50')).toBe(25.5);
  });

  it('no convierte montos típicos a centavos', () => {
    expect(parseMontoInput('1')).toBe(1);
    expect(parseMontoInput('10.00')).toBe(10);
    expect(parseMontoInput('100')).toBe(100);
  });

  it('permite valor incompleto mientras se escribe (25. → 25)', () => {
    expect(parseMontoInput('25.')).toBe(25);
    expect(parseMontoInput('25,')).toBe(25);
  });

  it('devuelve null si el campo está vacío o no es número', () => {
    expect(parseMontoInput('')).toBeNull();
    expect(parseMontoInput('   ')).toBeNull();
    expect(parseMontoInput(null)).toBeNull();
    expect(parseMontoInput(undefined)).toBeNull();
    expect(parseMontoInput('.')).toBeNull();
    expect(parseMontoInput(',')).toBeNull();
    expect(parseMontoInput('abc')).toBeNull();
  });
});
