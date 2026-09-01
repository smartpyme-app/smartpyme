export function cuentaCoincideBusqueda(term: string, item: { codigo?: string; nombre?: string } | null | undefined): boolean {
  const t = (term || '').toLowerCase().trim();
  if (!t) return true;
  return `${item?.codigo ?? ''} ${item?.nombre ?? ''}`.toLowerCase().includes(t);
}

export function armarOpcionesTipoCuentaReporte(
  catalogo: Array<{ id?: string | number; codigo?: string; nombre?: string }> | null | undefined
): Array<{ value: string | number; label: string }> {
  const opciones: Array<{ value: string | number; label: string }> = [
    { value: 'all', label: 'Todas las cuentas' },
  ];
  if (!Array.isArray(catalogo)) {
    return opciones;
  }
  for (const c of catalogo) {
    const codigo = c?.codigo ?? '';
    const nombre = c?.nombre ?? '';
    opciones.push({
      value: c.id as string | number,
      label: codigo ? `${codigo} — ${nombre}` : nombre || String(c.id),
    });
  }
  return opciones;
}

export function armarOpcionesCuentaAuxiliar(
  catalogo: Array<{ id?: string | number; codigo?: string; nombre?: string }> | null | undefined
): Array<{ value: string | number; label: string }> {
  return armarOpcionesTipoCuentaReporte(catalogo).filter((o) => o.value !== 'all');
}
