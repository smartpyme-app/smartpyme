/** Lista de tarifas para el pedido (misma idea que facturación v2). */

export interface PrecioPedidoOpcion {
  precio: number;
  precio_con_iva: number;
  clasificacion?: string | null;
}

export function armarListaPreciosPedido(producto: any, ivaPct: number): PrecioPedidoOpcion[] {
  const factor = ivaPct > 0 ? 1 + ivaPct / 100 : 1;
  const base = parseFloat(String(producto?.precio_sin_iva ?? producto?.precio ?? 0)) || 0;
  const extras = Array.isArray(producto?.precios) ? producto.precios : [];
  const seen = new Set<string>();
  const out: PrecioPedidoOpcion[] = [];

  const push = (sin: number, clasificacion?: string | null) => {
    const key = sin.toFixed(4);
    if (seen.has(key)) {
      return;
    }
    seen.add(key);
    out.push({
      precio: sin,
      precio_con_iva: sin * factor,
      clasificacion: clasificacion ?? null,
    });
  };

  push(base, producto?.clasificacion ?? null);
  for (const p of extras) {
    push(parseFloat(String(p?.precio ?? 0)) || 0, p?.clasificacion ?? null);
  }

  return out;
}

export function quitarPrecioDeLista<T extends { id?: number | string }>(
  precios: T[] | null | undefined,
  id: number | string
): T[] {
  const lista = Array.isArray(precios) ? precios : [];
  const n = Number(id);
  return lista.filter((p) => Number(p.id) !== n);
}
