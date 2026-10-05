export type LoginHost = 'abaco' | 'sivar' | 'onvo' | 'contadores' | 'default';

export function loginHostFromHostname(host: string): LoginHost {
  const h = host.toLowerCase();
  if (h.includes('abaco')) return 'abaco';
  if (h.includes('sivar')) return 'sivar';
  if (h.includes('onvo')) return 'onvo';
  if (h.includes('contadores')) return 'contadores';
  return 'default';
}
