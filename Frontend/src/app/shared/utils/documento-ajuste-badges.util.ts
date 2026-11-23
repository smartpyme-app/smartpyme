import {
  esNombreNotaCredito,
  esNombreNotaDebito,
} from '@views/ventas/documentos/documento-nombre-options';

export type BadgeDocumentoAjuste = 'NC' | 'ND' | 'DEV';

export interface ItemDocumentoAjuste {
  nombre_documento?: string | null;
  tipo_documento?: string | null;
  total?: number | string | null;
}

function nombreItem(item: ItemDocumentoAjuste): string {
  return String(item.nombre_documento ?? item.tipo_documento ?? '').trim();
}

/**
 * Badges visuales para listados: NC / ND / DEV.
 * No cambia el estado de cobro; solo indica documentos de ajuste asociados.
 * DEV = hay monto de devoluciones no cubierto solo por NC/ND listadas (p. ej. devolución física).
 */
export function badgesDocumentoAjuste(
  items: ItemDocumentoAjuste[] | null | undefined,
  devolucionesSumTotal?: number | string | null
): BadgeDocumentoAjuste[] {
  const list = Array.isArray(items) ? items : [];
  let tieneNc = false;
  let tieneNd = false;
  let totalNcNd = 0;

  for (const item of list) {
    const nombre = nombreItem(item);
    const total = Number(item.total || 0);
    if (esNombreNotaCredito(nombre)) {
      tieneNc = true;
      totalNcNd += total;
    } else if (esNombreNotaDebito(nombre)) {
      tieneNd = true;
      totalNcNd += total;
    }
  }

  const sum = Number(devolucionesSumTotal || 0);
  const tieneDev = sum > totalNcNd + 0.009;

  const badges: BadgeDocumentoAjuste[] = [];
  if (tieneNc) badges.push('NC');
  if (tieneNd) badges.push('ND');
  if (tieneDev) badges.push('DEV');
  return badges;
}
