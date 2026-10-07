export type LoginHost = 'abaco' | 'sivar' | 'onvo' | 'contadores' | 'default';

/** Marcador local tras login válido en portal contadores (cartera asignada). */
export const CONTADOR_PORTAL_STORAGE_KEY = 'SP_contador_portal';

export function loginHostFromHostname(host: string): LoginHost {
  const h = host.toLowerCase();
  if (h.includes('abaco')) return 'abaco';
  if (h.includes('sivar')) return 'sivar';
  if (h.includes('onvo')) return 'onvo';
  if (h.includes('contadores')) return 'contadores';
  return 'default';
}
