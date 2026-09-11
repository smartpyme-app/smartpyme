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

export interface MeseroPrefill {
  id: number;
  id_canal: number | null;
}

/** Lee mesero_id / mesero_id_canal del state al facturar una pre-cuenta de mesa. */
export function meseroPrefillDesdeNav(state: unknown): MeseroPrefill | null {
  const s = state as { mesero_id?: unknown; mesero_id_canal?: unknown; preCuentaData?: { mesero_id?: unknown; mesero_id_canal?: unknown } } | null;
  const rawId = s?.mesero_id ?? s?.preCuentaData?.mesero_id;
  if (rawId == null || rawId === '') {
    return null;
  }
  const rawCanal = s?.mesero_id_canal ?? s?.preCuentaData?.mesero_id_canal;
  return {
    id: Number(rawId),
    id_canal: rawCanal == null || rawCanal === '' ? null : Number(rawCanal),
  };
}

/** Prefill vendedor y canal con el mesero de la mesa. El usuario de caja no se toca. */
export function aplicarMeseroPrefillVenta(
  venta: { id_vendedor?: unknown; id_canal?: unknown },
  mesero: MeseroPrefill | null | undefined,
  canales?: CanalVentaOption[] | null,
): boolean {
  if (!mesero?.id) {
    return false;
  }
  venta.id_vendedor = mesero.id;
  if (canales?.length) {
    venta.id_canal = resolverCanalVentaDefault(canales, mesero.id_canal);
  }
  return true;
}
