import { puedeCrearCredito, puedeVerMenuCreditos, RUTA_CREDITOS_CLIENTES, SLUG_CREDITOS_CLIENTES } from './creditos-acceso';

describe('puedeVerMenuCreditos', () => {
  it('usa el slug creditos-clientes', () => {
    expect(SLUG_CREDITOS_CLIENTES).toBe('creditos-clientes');
  });

  it('vive en clientes, no en el menú de ventas', () => {
    expect(RUTA_CREDITOS_CLIENTES).toBe('/clientes/creditos');
  });

  it('oculta el menú: la cola son las ventas de cuotas', () => {
    expect(puedeVerMenuCreditos(true, true)).toBe(false);
    expect(puedeVerMenuCreditos(false, true)).toBe(false);
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
