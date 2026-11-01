export type ValorInventarioModo = 'promedio' | 'ultimo' | 'anterior';

/** Normaliza valor_inventario de empresa (legacy "Promedio", opciones ultimo/anterior/promedio). */
export function valorInventarioModo(raw: string | null | undefined): ValorInventarioModo {
  const v = String(raw ?? 'promedio').trim().toLowerCase();
  if (v === 'promedio' || v.includes('promedio')) {
    return 'promedio';
  }
  if (v === 'anterior' || v.includes('anterior')) {
    return 'anterior';
  }
  if (v === 'ultimo' || v.includes('ultim')) {
    return 'ultimo';
  }
  // Legacy BD: default histórico "Promedio"
  return 'promedio';
}

export function campoCostoInventario(modo: ValorInventarioModo): 'costo' | 'costo_promedio' | 'costo_anterior' {
  switch (modo) {
    case 'promedio':
      return 'costo_promedio';
    case 'anterior':
      return 'costo_anterior';
    default:
      return 'costo';
  }
}

export function etiquetaCostoInventario(modo: ValorInventarioModo): string {
  switch (modo) {
    case 'promedio':
      return 'Promedio ponderado';
    case 'anterior':
      return 'Costo anterior';
    default:
      return 'Última compra';
  }
}

function parseCostoProducto(v: unknown): number | null {
  if (v === null || v === undefined || v === '') {
    return null;
  }
  const n = Number(v);
  return Number.isFinite(n) ? n : null;
}

export function costoProductoParaMargen(
  producto: { costo?: number; costo_promedio?: number; costo_anterior?: number } | null | undefined,
  modo: ValorInventarioModo,
): number {
  const p = producto ?? {};
  const c = parseCostoProducto(p.costo);
  const cp = parseCostoProducto(p.costo_promedio);
  const ca = parseCostoProducto(p.costo_anterior);
  switch (modo) {
    case 'promedio':
      return cp ?? c ?? ca ?? 0;
    case 'anterior':
      return ca ?? c ?? cp ?? 0;
    default:
      return c ?? cp ?? ca ?? 0;
  }
}

/** Si el campo activo está vacío pero hay costo en otro campo, copia para edición/visualización. */
export function rellenarCampoCostoActivoSiVacio(
  producto: { costo?: unknown; costo_promedio?: unknown; costo_anterior?: unknown } | null | undefined,
  modo: ValorInventarioModo,
): void {
  if (!producto) {
    return;
  }
  const campo = campoCostoInventario(modo);
  if (parseCostoProducto((producto as Record<string, unknown>)[campo]) !== null) {
    return;
  }
  const tieneCosto =
    parseCostoProducto(producto.costo) !== null ||
    parseCostoProducto(producto.costo_promedio) !== null ||
    parseCostoProducto(producto.costo_anterior) !== null;
  if (!tieneCosto) {
    return;
  }
  (producto as Record<string, unknown>)[campo] = costoProductoParaMargen(producto, modo);
}
