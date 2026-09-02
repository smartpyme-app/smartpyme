import { resolverCanalVentaDefault } from './canal-venta.util';

describe('resolverCanalVentaDefault', () => {
  const canales = [
    { id: 10, predeterminado: 0 },
    { id: 20, predeterminado: 1 },
    { id: 30, predeterminado: 0 },
  ];

  it('usa el canal del usuario si está en la lista', () => {
    expect(resolverCanalVentaDefault(canales, 10)).toBe(10);
  });

  it('usa el predeterminado si el usuario no tiene canal', () => {
    expect(resolverCanalVentaDefault(canales, null)).toBe(20);
  });

  it('usa el primero si no hay canal de usuario ni predeterminado', () => {
    const sinPredeterminado = [
      { id: 10, predeterminado: 0 },
      { id: 30, predeterminado: 0 },
    ];
    expect(resolverCanalVentaDefault(sinPredeterminado, null)).toBe(10);
  });

  it('cae al predeterminado si el canal del usuario ya no existe', () => {
    expect(resolverCanalVentaDefault(canales, 99)).toBe(20);
  });

  it('retorna null si no hay canales', () => {
    expect(resolverCanalVentaDefault([], 10)).toBeNull();
  });
});
