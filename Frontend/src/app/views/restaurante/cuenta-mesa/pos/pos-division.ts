const EPS = 0.0001;

function round4(n: number): number {
  return Math.round(n * 10000) / 10000;
}

export function sumaPersonaLinea(
  matriz: Record<number, Record<number, number>>,
  ordenDetalleId: number,
  numPersonas: number
): number {
  const row = matriz[ordenDetalleId] || {};
  let s = 0;
  for (let p = 1; p <= numPersonas; p++) {
    s += Number(row[p] || 0);
  }
  return round4(s);
}

export function asignarUnidades(
  matriz: Record<number, Record<number, number>>,
  ordenDetalleId: number,
  persona: number,
  cantidad: number,
  maxLinea: number
): Record<number, Record<number, number>> {
  const next: Record<number, Record<number, number>> = { ...matriz, [ordenDetalleId]: { ...(matriz[ordenDetalleId] || {}) } };
  const row = next[ordenDetalleId];
  const otros = sumaPersonaLinea(next, ordenDetalleId, 20) - Number(row[persona] || 0);
  const maxParaPersona = Math.max(0, round4(maxLinea - otros));
  row[persona] = round4(Math.min(Math.max(0, cantidad), maxParaPersona));
  return next;
}

/**
 * Deja la línea entera en manos de una sola persona. Limpia la fila antes de
 * asignar: si no, el tope `maxLinea` la recorta contra lo que ya tenía otra
 * persona y la línea queda en cero (había que tocar dos veces).
 */
export function asignarExclusivo(
  matriz: Record<number, Record<number, number>>,
  ordenDetalleId: number,
  persona: number,
  cantidad: number,
  maxLinea: number
): Record<number, Record<number, number>> {
  return asignarUnidades({ ...matriz, [ordenDetalleId]: {} }, ordenDetalleId, persona, cantidad, maxLinea);
}

export function lineaCompleta(
  matriz: Record<number, Record<number, number>>,
  ordenDetalleId: number,
  cantidadLinea: number,
  numPersonas: number
): boolean {
  return Math.abs(sumaPersonaLinea(matriz, ordenDetalleId, numPersonas) - Number(cantidadLinea)) < EPS;
}

export function matrizValida(
  items: { id: number; cantidad: number }[],
  matriz: Record<number, Record<number, number>>,
  numPersonas: number
): boolean {
  return items.every((it) => lineaCompleta(matriz, it.id, it.cantidad, numPersonas));
}

export function buildAsignaciones(
  items: { id: number; cantidad: number }[],
  matriz: Record<number, Record<number, number>>,
  numPersonas: number
): { orden_detalle_id: number; pagador_index: number; cantidad: number }[] {
  const out: { orden_detalle_id: number; pagador_index: number; cantidad: number }[] = [];
  for (const it of items) {
    const row = matriz[it.id] || {};
    for (let p = 1; p <= numPersonas; p++) {
      const q = round4(Number(row[p] || 0));
      if (q > 0) {
        out.push({ orden_detalle_id: it.id, pagador_index: p, cantidad: q });
      }
    }
  }
  return out;
}
