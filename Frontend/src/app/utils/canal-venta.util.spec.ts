import { aplicarMeseroPrefillVenta, meseroPrefillDesdeNav, resolverCanalVentaDefault } from './canal-venta.util';

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

describe('meseroPrefillDesdeNav / aplicarMeseroPrefillVenta', () => {
  const canales = [
    { id: 10, predeterminado: 0 },
    { id: 20, predeterminado: 1 },
  ];

  it('lee mesero_id del state de navegación', () => {
    expect(meseroPrefillDesdeNav({ mesero_id: 7, mesero_id_canal: 10 })).toEqual({
      id: 7,
      id_canal: 10,
    });
  });

  it('no prellena si no hay mesero', () => {
    const venta = { id_usuario: 1, id_vendedor: 1, id_canal: 20 };
    expect(aplicarMeseroPrefillVenta(venta, null, canales)).toBe(false);
    expect(venta.id_usuario).toBe(1);
  });

  it('pone vendedor y canal del mesero y deja el usuario de caja', () => {
    const venta = { id_usuario: 1, id_vendedor: 1, id_canal: 20 };
    expect(aplicarMeseroPrefillVenta(venta, { id: 7, id_canal: 10 }, canales)).toBe(true);
    expect(venta.id_usuario).toBe(1);
    expect(venta.id_vendedor).toBe(7);
    expect(venta.id_canal).toBe(10);
  });
});
