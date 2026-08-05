import { resolveCategoriaTap } from './pos-menu-nav';

describe('pos-menu-nav', () => {
  it('sin subcategorías va a productos', () => {
    expect(resolveCategoriaTap(0)).toBe('productos');
  });
  it('con subcategorías va a subcategorías', () => {
    expect(resolveCategoriaTap(3)).toBe('subcategorias');
  });
});
