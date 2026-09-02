export interface CanalVentaOption {
  id: number;
  predeterminado?: string | number | boolean | null;
  enable?: string | number | boolean | null;
}

/**
 * Resuelve el canal para una venta nueva:
 * 1) canal del usuario (si sigue en la lista activa),
 * 2) canal predeterminado de la empresa,
 * 3) primer canal de la lista (comportamiento anterior).
 */
export function resolverCanalVentaDefault(
  canales: CanalVentaOption[] | null | undefined,
  idCanalUsuario?: number | string | null
): number | null {
  if (!canales?.length) {
    return null;
  }

  if (idCanalUsuario != null && idCanalUsuario !== '') {
    const delUsuario = canales.find((c) => Number(c.id) === Number(idCanalUsuario));
    if (delUsuario) {
      return Number(delUsuario.id);
    }
  }

  const predeterminado = canales.find(
    (c) => c.predeterminado == 1 || c.predeterminado === true || c.predeterminado === '1'
  );
  if (predeterminado) {
    return Number(predeterminado.id);
  }

  return Number(canales[0].id);
}
