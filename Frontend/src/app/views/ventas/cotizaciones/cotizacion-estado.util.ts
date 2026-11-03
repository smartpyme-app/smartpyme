/** Estados cotización: canonical PascalCase + clases badge como listado ventas. */

export type EstadoCotizacionCanonico = 'Pendiente' | 'Facturada' | 'Anulada';

export function canonicoEstadoCotizacion(estado: string | null | undefined): EstadoCotizacionCanonico | string {
  const raw = (estado ?? '').trim();
  if (!raw) {
    return raw;
  }
  if (raw === 'Pendiente' || raw === 'Facturada' || raw === 'Anulada') {
    return raw;
  }
  const e = raw.toLowerCase();
  if (e === 'pendiente' || e === 'enviada' || e === 'borrador' || e === 'aceptada') {
    return 'Pendiente';
  }
  if (e === 'facturada') {
    return 'Facturada';
  }
  if (e === 'anulada' || e === 'vencida' || e === 'cancelada') {
    return 'Anulada';
  }
  return raw.charAt(0).toUpperCase() + raw.slice(1).toLowerCase();
}

export function estadoCotizacionBadgeClass(estado: string | null | undefined): Record<string, boolean> {
  const c = canonicoEstadoCotizacion(estado);
  return {
    'bg-warning': c === 'Pendiente',
    'bg-danger': c === 'Anulada',
    'bg-success': c === 'Facturada',
  };
}
