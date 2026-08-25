import { puedeCrearCredito, puedeVerMenuCreditos, SLUG_CREDITOS_CLIENTES } from './creditos-acceso';

describe('puedeVerMenuCreditos', () => {
  it('usa el slug creditos-clientes', () => {
    expect(SLUG_CREDITOS_CLIENTES).toBe('creditos-clientes');
  });

  it('muestra el menú si hay funcionalidad y permiso de ver ventas', () => {
    expect(puedeVerMenuCreditos(true, true)).toBe(true);
  });

  it('oculta el menú sin funcionalidad', () => {
    expect(puedeVerMenuCreditos(false, true)).toBe(false);
  });

  it('oculta el menú sin permiso de ventas', () => {
    expect(puedeVerMenuCreditos(true, false)).toBe(false);
  });
});

describe('puedeCrearCredito', () => {
  it('permite crear si no es Ventas Limitado', () => {
    expect(puedeCrearCredito(false)).toBe(true);
  });

  it('bloquea crear si es Ventas Limitado', () => {
    expect(puedeCrearCredito(true)).toBe(false);
  });
});
