export type PreviewCuota = {
  numero: number;
  monto: number;
  fechaVencimiento: string;
};

function round2(n: number): number {
  return Math.round(n * 100) / 100;
}

function addMonthsNoOverflow(isoDate: string, months: number): string {
  const [year, month, day] = isoDate.split('-').map(Number);
  const targetMonthIndex = month - 1 + months;
  const lastDay = new Date(Date.UTC(year, targetMonthIndex + 1, 0)).getUTCDate();
  const date = new Date(Date.UTC(year, targetMonthIndex, Math.min(day, lastDay)));
  return date.toISOString().slice(0, 10);
}

export function generarPreviewCuotas(
  monto: number,
  nCuotas: number,
  fechaInicio: string,
): PreviewCuota[] {
  if (nCuotas < 2 || monto <= 0 || !fechaInicio) {
    return [];
  }

  const base = round2(monto / nCuotas);
  const cuotas: PreviewCuota[] = [];
  let acumulado = 0;

  for (let i = 1; i <= nCuotas; i++) {
    const montoCuota = i === nCuotas ? round2(monto - acumulado) : base;
    acumulado = round2(acumulado + montoCuota);
    cuotas.push({
      numero: i,
      monto: montoCuota,
      fechaVencimiento: addMonthsNoOverflow(fechaInicio, i - 1),
    });
  }

    return cuotas;
}

export function addDaysIso(isoDate: string, days: number): string {
  const [year, month, day] = isoDate.split('-').map(Number);
  return new Date(Date.UTC(year, month - 1, day + days)).toISOString().slice(0, 10);
}

export function estadoCola(
  idVenta: number | string | null | undefined,
  fechaVencimiento: string,
  hoy: string,
): 'vencida' | 'por_facturar' | null {
  if (idVenta) {
    return null;
  }
  if (!fechaVencimiento || !hoy) {
    return null;
  }
  if (fechaVencimiento <= hoy) {
    return 'vencida';
  }
  if (fechaVencimiento <= addDaysIso(hoy, 7)) {
    return 'por_facturar';
  }
  return null;
}

export function etiquetaEstadoCola(estado: string | null | undefined): string {
  if (estado === 'vencida') {
    return 'Vencida';
  }
  if (estado === 'por_facturar') {
    return 'Por facturar';
  }
  if (estado === 'facturada') {
    return 'Facturada';
  }
  return 'Programada';
}
