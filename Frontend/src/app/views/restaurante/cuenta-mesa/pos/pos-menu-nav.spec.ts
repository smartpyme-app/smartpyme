import { resolveCategoriaTap } from './pos-menu-nav';

describe('pos-menu-nav', () => {
  it('respeta el modo que manda la API', () => {
    expect(resolveCategoriaTap('productos', 3)).toBe('productos');
    expect(resolveCategoriaTap('subcategorias', 0)).toBe('subcategorias');
  });

  it('cae al conteo cuando la API no manda modo', () => {
    expect(resolveCategoriaTap(null, 0)).toBe('productos');
    expect(resolveCategoriaTap(undefined, 3)).toBe('subcategorias');
    expect(resolveCategoriaTap('otro', 3)).toBe('subcategorias');
  });
});
