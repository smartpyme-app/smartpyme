/** Id de catálogo en una línea de venta/cotización (con o sin relación producto cargada). */
export function idProductoEditable(detalle: unknown): number | null {
  const d = detalle as { producto?: { id?: unknown }; id_producto?: unknown } | null | undefined;
  const raw = d?.producto?.id ?? d?.id_producto;
  if (raw == null || raw === '') {
    return null;
  }
  const n = Number(raw);
  return Number.isFinite(n) && n > 0 ? n : null;
}

/** Ruta de edición en inventario (producto o servicio). */
export function rutaEditarProductoDetalle(detalle: unknown): string[] | null {
  const id = idProductoEditable(detalle);
  if (id == null) {
    return null;
  }
  const d = detalle as { producto?: { tipo?: unknown }; tipo?: unknown };
  const tipo = String(d?.producto?.tipo ?? d?.tipo ?? '');
  const base = tipo === 'Servicio' ? '/servicio/editar' : '/producto/editar';
  return [base, String(id)];
}
