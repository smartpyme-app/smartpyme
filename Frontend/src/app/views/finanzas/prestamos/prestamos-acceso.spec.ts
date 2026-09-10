import { puedeVerMenuPrestamos, SLUG_PRESTAMOS_EMPRESA } from './prestamos-acceso';

describe('prestamos-acceso', () => {
  it('usa el slug prestamos-empresa', () => {
    expect(SLUG_PRESTAMOS_EMPRESA).toBe('prestamos-empresa');
  });

  it('oculta el menú sin contabilidad, funcionalidad o permiso', () => {
    expect(puedeVerMenuPrestamos(true, true, true)).toBe(true);
    expect(puedeVerMenuPrestamos(false, true, true)).toBe(false);
    expect(puedeVerMenuPrestamos(true, false, true)).toBe(false);
    expect(puedeVerMenuPrestamos(true, true, false)).toBe(false);
  });
});
