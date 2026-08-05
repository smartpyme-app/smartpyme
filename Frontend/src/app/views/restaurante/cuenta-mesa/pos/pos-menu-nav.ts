export function resolveCategoriaTap(subcategoriasCount: number): 'subcategorias' | 'productos' {
  return subcategoriasCount > 0 ? 'subcategorias' : 'productos';
}
