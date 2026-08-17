export type ModoCatalogo = 'subcategorias' | 'productos';

/**
 * El `modo` que devuelve la API manda: es quien sabe si la categoría tiene
 * subcategorías activas. `subcategorias_count` solo es el respaldo cuando la
 * respuesta viene sin `modo`.
 */
export function resolveCategoriaTap(modoApi?: string | null, subcategoriasCount = 0): ModoCatalogo {
  if (modoApi === 'subcategorias' || modoApi === 'productos') {
    return modoApi;
  }
  return Number(subcategoriasCount) > 0 ? 'subcategorias' : 'productos';
}

export function trackFichaPos(p: { id: number; id_presentacion?: number | null }): string {
  return `${p.id}:${p.id_presentacion || 0}`;
}

export function nombreLineaOrden(item: {
  producto?: { nombre?: string } | null;
  presentacion?: { nombre_comercial?: string } | null;
}): string {
  const prod = String(item?.producto?.nombre || '').trim();
  const com = String(item?.presentacion?.nombre_comercial || '').trim();
  if (!com) {
    return prod;
  }
  return prod ? `${com} (${prod})` : com;
}
