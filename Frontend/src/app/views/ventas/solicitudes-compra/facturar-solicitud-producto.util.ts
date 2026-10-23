/** URL para resolver producto al facturar solicitud (empresa actual u origen de la OC). */
export function urlProductoBuscarSolicitud(codigo: string, idEmpresaOrden: number): string {
  const code = encodeURIComponent(codigo ?? '');
  const q = idEmpresaOrden ? `?id_empresa_orden=${idEmpresaOrden}` : '';
  return `producto/buscar-by-code-solicitud/${code}${q}`;
}

/** Respuesta API de producto (misma forma que `producto/buscar-by-code`). */
export type ProductoApiSolicitud = any;

export type RespuestaProductoSolicitud =
  | { modo: 'local'; producto: ProductoApiSolicitud }
  | { modo: 'origen'; producto_origen: ProductoApiSolicitud };

export type ProductoSolicitudResuelto =
  | { kind: 'local'; producto: ProductoApiSolicitud }
  | { kind: 'origen'; productoOrigen: ProductoApiSolicitud }
  | { kind: 'legacy'; producto: ProductoApiSolicitud }
  | { kind: 'none' };

/** Normaliza respuesta del API (nuevo `{ modo }` o producto plano legacy). */
/** Texto corto para alertas al facturar solicitud (código + nombre del producto en la OC). */
export function etiquetaProductoSolicitud(
  detalleCompra: { codigo?: string | null; nombre_producto?: string | null },
  productoOrigen?: { nombre?: string | null; codigo?: string | null; nombre_variante?: string | null }
): string {
  const codigo = (detalleCompra.codigo ?? productoOrigen?.codigo ?? '—').toString();
  let nombre = (productoOrigen?.nombre ?? detalleCompra.nombre_producto ?? '').toString().trim();
  const variante = (productoOrigen?.nombre_variante ?? '').toString().trim();
  if (variante && nombre && !nombre.includes(variante)) {
    nombre = `${nombre} (${variante})`;
  }
  if (nombre && nombre !== codigo) {
    return `${codigo} — ${nombre}`;
  }
  return codigo;
}

export function resolverProductoSolicitud(resp: unknown): ProductoSolicitudResuelto {
  if (!resp || typeof resp !== 'object') {
    return { kind: 'none' };
  }
  const r = resp as Record<string, unknown>;
  if (r['modo'] === 'local' && r['producto'] && typeof r['producto'] === 'object') {
    return { kind: 'local', producto: r['producto'] as ProductoApiSolicitud };
  }
  if (r['modo'] === 'origen' && r['producto_origen'] && typeof r['producto_origen'] === 'object') {
    return { kind: 'origen', productoOrigen: r['producto_origen'] as ProductoApiSolicitud };
  }
  if (r['id'] != null || r['codigo'] != null) {
    return { kind: 'legacy', producto: resp as ProductoApiSolicitud };
  }
  return { kind: 'none' };
}
