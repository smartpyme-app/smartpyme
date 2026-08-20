import {
  copiarImpuestosProductoAlDetalle,
  normalizarPorcentajeImpuestoDetalle,
  redondearMoneda,
  resolverPorcentajeImpuestoVenta,
} from '@utils/impuestos-venta.util';

export interface ProductoDetalleV2MapperCtx {
  ivaEmpresa: number;
  valorInventarioPromedio: boolean;
  lotesActivo: boolean;
  idBodega: number | null | undefined;
  sumStock: (items: any[], field: string) => number;
  getNombreCompleto: (producto: any) => string;
}

export function getPrecioConIvaProducto(producto: any, ivaEmpresa: number): number {
  if (!producto) {
    return 0;
  }
  const precio = parseFloat(producto.precio) || 0;
  const pct = resolverPorcentajeImpuestoVenta(producto.porcentaje_impuesto, ivaEmpresa);
  return precio * (1 + pct / 100);
}

export function armarPreciosDetalleV2(producto: any, ivaEmpresa: number): {
  pctImpuesto: number;
  porcentajeImpuesto: number | null;
  precioSinIva: number;
  precioConIva: number;
} {
  const pctImpuesto = resolverPorcentajeImpuestoVenta(producto.porcentaje_impuesto, ivaEmpresa);
  const porcentajeImpuesto = normalizarPorcentajeImpuestoDetalle(producto.porcentaje_impuesto, ivaEmpresa);
  const precioSinIva = parseFloat(producto.precio) || 0;
  const precioConIva = pctImpuesto > 0
    ? precioSinIva * (1 + pctImpuesto / 100)
    : precioSinIva;
  return { pctImpuesto, porcentajeImpuesto, precioSinIva, precioConIva };
}

export function armarListaPreciosDetalleV2(producto: any, precioSinIva: number, pctImpuesto: number): any[] {
  const lista = producto.precios
    ? producto.precios.map((p: any) => {
        const sinIvaLista = parseFloat(p.precio);
        const conIva = pctImpuesto > 0
          ? sinIvaLista * (1 + pctImpuesto / 100)
          : sinIvaLista;
        return {
          ...p,
          precio: sinIvaLista.toFixed(4),
          precio_sin_iva: sinIvaLista,
          precio_con_iva: conIva.toFixed(4),
        };
      })
    : [];
  const conIvaBase = pctImpuesto > 0
    ? precioSinIva * (1 + pctImpuesto / 100)
    : precioSinIva;
  lista.unshift({
    precio: precioSinIva.toFixed(4),
    precio_sin_iva: precioSinIva,
    precio_con_iva: conIvaBase.toFixed(4),
  });
  return lista;
}

/** Arma payload de detalle v2 listo para productoSelect / addDetalle. */
export function armarDetalleDesdeProductoV2(producto: any, ctx: ProductoDetalleV2MapperCtx): any {
  const detalle = Object.assign({}, producto);
  detalle.descripcion = ctx.getNombreCompleto(producto);
  detalle.img = producto.img;

  const esPlanoBuscador = producto.nombre_mostrar != null;
  const { pctImpuesto, porcentajeImpuesto, precioSinIva, precioConIva } =
    armarPreciosDetalleV2(producto, ctx.ivaEmpresa);

  detalle.porcentaje_impuesto = porcentajeImpuesto;
  copiarImpuestosProductoAlDetalle(detalle, producto, ctx.ivaEmpresa);
  detalle.precio_iva = redondearMoneda(precioConIva).toFixed(2);
  detalle.precio = precioSinIva.toFixed(4);
  detalle.precio_base = precioSinIva;
  detalle.precios = armarListaPreciosDetalleV2(producto, precioSinIva, pctImpuesto);

  if (ctx.valorInventarioPromedio && producto.costo_promedio > 0) {
    detalle.costo = parseFloat(producto.costo_promedio);
  } else {
    detalle.costo = parseFloat(producto.costo ?? 0);
  }

  if (esPlanoBuscador) {
    detalle.id_producto = producto.id_producto;
    detalle.id_presentacion = producto.id_presentacion ?? null;
    detalle.factor_conversion = producto.factor_conversion ?? 1;
    detalle.descripcion = producto.nombre_mostrar;
    detalle.tipo = producto.tipo;
    detalle.stock = producto.tipo === 'Servicio'
      ? null
      : (producto.stock_base_actual ?? null);
  } else {
    detalle.id_producto = producto.id;
    detalle.id_presentacion = producto.id_presentacion ?? null;
    detalle.factor_conversion = producto.factor_conversion ?? 1;

    if (producto.tipo === 'Compuesto' && producto.composiciones) {
      producto.composiciones.forEach((composicion: any) => {
        composicion.compuesto.inventarios = composicion.compuesto.inventarios.filter(
          (item: any) => item.id_bodega == ctx.idBodega
        );
        const stock = ctx.sumStock(composicion.compuesto.inventarios, 'stock');
        if (stock < composicion.cantidad) {
          producto.inventarios = [];
        }
      });
    }

    producto.inventarios = producto.inventarios?.filter((item: any) => item.id_bodega == ctx.idBodega) || [];

    if (
      producto.inventario_por_lotes &&
      producto.lotes &&
      producto.lotes.length > 0 &&
      ctx.lotesActivo
    ) {
      const lotesBodega = ctx.idBodega
        ? producto.lotes.filter((l: any) => l.id_bodega == ctx.idBodega)
        : producto.lotes;
      const stockLotes = lotesBodega.reduce(
        (sum: number, lote: any) => sum + (parseFloat(lote.stock) || 0),
        0
      );
      detalle.stock = stockLotes;
      detalle.inventario_por_lotes = true;
      detalle.lote_id = null;
    } else if (producto.tipo !== 'Servicio' && producto.inventarios.length > 0) {
      detalle.stock = ctx.sumStock(producto.inventarios, 'stock');
      detalle.inventario_por_lotes = false;
      detalle.lote_id = null;
    } else if (!esPlanoBuscador) {
      detalle.stock = null;
      detalle.inventario_por_lotes = false;
      detalle.lote_id = null;
    }
  }

  detalle.cantidad = 1;
  detalle.descuento = 0;
  detalle.descuento_porcentaje = 0;

  return detalle;
}
