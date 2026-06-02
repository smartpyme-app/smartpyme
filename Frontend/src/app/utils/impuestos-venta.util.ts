/**
 * Resuelve el % de impuesto a aplicar en una línea de venta.
 * Si el producto/detalle no tiene impuesto configurado (null/undefined/''), usa el IVA de la empresa.
 */
export function resolverPorcentajeImpuestoVenta(
  porcentajeImpuesto: unknown,
  ivaEmpresa: unknown,
  cobrarImpuestos = true
): number {
  if (!cobrarImpuestos) {
    return 0;
  }
  if (porcentajeImpuesto != null && porcentajeImpuesto !== '') {
    return Number(porcentajeImpuesto) || 0;
  }
  return Number(ivaEmpresa ?? 0) || 0;
}

/** Valor a guardar en detalle.porcentaje_impuesto (snapshot al facturar). */
export function normalizarPorcentajeImpuestoDetalle(
  porcentajeImpuesto: unknown,
  ivaEmpresa: unknown
): number | null {
  if (porcentajeImpuesto != null && porcentajeImpuesto !== '') {
    return Number(porcentajeImpuesto);
  }
  const iva = Number(ivaEmpresa ?? 0);
  return iva > 0 ? iva : null;
}
