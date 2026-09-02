import {
  armarDetalleDesdeProductoV2,
  armarPreciosDetalleV2,
  getPrecioConIvaProducto,
} from './producto-detalle-v2.mapper';

describe('producto-detalle-v2.mapper', () => {
  const ctx = {
    ivaEmpresa: 13,
    valorInventarioPromedio: false,
    lotesActivo: false,
    idBodega: 1,
    sumStock: (items: any[]) => items.reduce((s, i) => s + Number(i.stock || 0), 0),
    getNombreCompleto: (p: any) => p.nombre_mostrar || p.nombre,
  };

  it('calcula precio_iva desde porcentaje del producto', () => {
    const det = armarDetalleDesdeProductoV2(
      { id: 1, nombre: 'Cafe', precio: 100, porcentaje_impuesto: 13, tipo: 'Producto', inventarios: [] },
      ctx
    );
    expect(det.precio_iva).toBe('113.00');
    expect(det.precio).toBe('100.0000');
  });

  it('usa fila plana del buscador con presentacion', () => {
    const det = armarDetalleDesdeProductoV2(
      {
        id_producto: 5,
        id_presentacion: 9,
        nombre_mostrar: 'Caja x12',
        precio: 50,
        factor_conversion: 12,
        tipo: 'Producto',
        stock_base_actual: 24,
      },
      ctx
    );
    expect(det.id_producto).toBe(5);
    expect(det.id_presentacion).toBe(9);
    expect(det.factor_conversion).toBe(12);
    expect(det.stock).toBe(24);
  });

  it('getPrecioConIvaProducto aplica iva empresa si producto sin pct', () => {
    expect(getPrecioConIvaProducto({ precio: 100 }, 15)).toBe(115);
  });

  it('armarPreciosDetalleV2 respeta exento explicito', () => {
    const r = armarPreciosDetalleV2({ precio: 100, porcentaje_impuesto: 0 }, 15);
    expect(r.precioConIva).toBe(100);
    expect(r.porcentajeImpuesto).toBe(0);
  });
});
