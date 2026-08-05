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
