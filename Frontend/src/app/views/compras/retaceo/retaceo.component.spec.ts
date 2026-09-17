import { productoDesdeDetalleCompra } from './retaceo-producto.util';

describe('productoDesdeDetalleCompra', () => {
  it('usa nombre_producto cuando la compra no trae producto anidado', () => {
    const label = productoDesdeDetalleCompra({
      id_producto: 1,
      nombre_producto: 'Café molido 1 lb',
    });
    expect(label.nombre).toBe('Café molido 1 lb');
  });

  it('prefiere producto.nombre si ya viene la relación', () => {
    const label = productoDesdeDetalleCompra({
      producto: { nombre: 'Azúcar' },
      nombre_producto: 'Otro',
      descripcion: 'Ignorar',
    });
    expect(label.nombre).toBe('Azúcar');
  });

  it('cae a descripcion si no hay nombre', () => {
    const label = productoDesdeDetalleCompra({ descripcion: 'Línea libre' });
    expect(label.nombre).toBe('Línea libre');
  });
});
