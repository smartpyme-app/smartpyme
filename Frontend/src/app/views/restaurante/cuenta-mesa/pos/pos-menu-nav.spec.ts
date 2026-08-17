import { resolveCategoriaTap, trackFichaPos, nombreLineaOrden } from './pos-menu-nav';

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

  it('distingue fichas del mismo producto', () => {
    expect(trackFichaPos({ id: 1, id_presentacion: null })).toBe('1:0');
    expect(trackFichaPos({ id: 1, id_presentacion: 9 })).toBe('1:9');
  });

  it('nombra la línea con la presentación', () => {
    expect(nombreLineaOrden({ producto: { nombre: 'Cerveza' } })).toBe('Cerveza');
    expect(nombreLineaOrden({
      producto: { nombre: 'Cerveza' },
      presentacion: { nombre_comercial: '330ml' }
    })).toBe('330ml (Cerveza)');
  });
});
