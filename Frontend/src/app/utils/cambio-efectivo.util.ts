/**
 * Vuelto: efectivo recibido menos lo que se debe cobrar en efectivo.
 * En pago simple el monto a cobrar incluye la propina.
 * En pago mixto se usa la parte asignada a efectivo.
 */
export function calcularCambioEfectivo(params: {
  montoPago: unknown;
  total: unknown;
  propina?: unknown;
  formaPago?: string;
  efectivo?: unknown;
}): string {
  const raw = params.montoPago;
  if (raw === null || raw === undefined || raw === '') {
    return '';
  }
  const recibido = parseFloat(String(raw)) || 0;
  const totalVenta = parseFloat(String(params.total ?? 0)) || 0;
  const propina = parseFloat(String(params.propina ?? 0)) || 0;
  const totalACobrar = totalVenta + propina;
  const enMultiple = params.formaPago === 'Multiple';
  const parteEfectivo = parseFloat(String(params.efectivo ?? 0)) || 0;
  const aCobrarEfectivo = enMultiple && parteEfectivo > 0 ? parteEfectivo : totalACobrar;
  return (recibido - aCobrarEfectivo).toFixed(2);
}

export type FormaPagoMonto = { nombre?: string; total?: unknown };

export type ResumenPagoMultiple = {
  recibido: number;
  pendiente: number;
  vuelto: number;
  efectivoRecibido: number;
  efectivoAplicado: number;
  puedeAplicar: boolean;
};

export function esFormaPagoEfectivo(nombre: unknown): boolean {
  return String(nombre ?? '').trim().toLowerCase() === 'efectivo';
}

function toMonto(v: unknown): number {
  if (v === null || v === undefined || v === '') {
    return 0;
  }
  const n = parseFloat(String(v));
  return Number.isFinite(n) ? n : 0;
}

function toCents(v: unknown): number {
  return Math.round(toMonto(v) * 100);
}

/** Recibido, pendiente, vuelto y si se puede aplicar un pago mixto. El exceso solo vale en efectivo. */
export function resumenPagoMultiple(params: {
  total: unknown;
  formaPagos: FormaPagoMonto[] | null | undefined;
}): ResumenPagoMultiple {
  const aCobrarCents = toCents(params.total);
  let efectivoCents = 0;
  let noEfectivoCents = 0;
  for (const fp of params.formaPagos ?? []) {
    const t = toCents(fp.total);
    if (t <= 0) {
      continue;
    }
    if (esFormaPagoEfectivo(fp.nombre)) {
      efectivoCents += t;
    } else {
      noEfectivoCents += t;
    }
  }
  const recibidoCents = efectivoCents + noEfectivoCents;
  const pendienteCents = Math.max(0, aCobrarCents - recibidoCents);
  const excesoNoEfectivo = noEfectivoCents > aCobrarCents;
  const efectivoAplicadoCents = excesoNoEfectivo
    ? 0
    : Math.min(efectivoCents, Math.max(0, aCobrarCents - noEfectivoCents));
  const vueltoCents =
    excesoNoEfectivo || pendienteCents > 0
      ? 0
      : Math.max(0, efectivoCents - efectivoAplicadoCents);
  return {
    recibido: recibidoCents / 100,
    pendiente: pendienteCents / 100,
    vuelto: vueltoCents / 100,
    efectivoRecibido: efectivoCents / 100,
    efectivoAplicado: efectivoAplicadoCents / 100,
    puedeAplicar: aCobrarCents > 0 && !excesoNoEfectivo && pendienteCents === 0,
  };
}

/** Deja el efectivo aplicado (sin vuelto) y el monto recibido para calcular el cambio. */
export function aplicarResumenPagoMultiple(
  venta: { monto_pago?: unknown },
  formaPagos: FormaPagoMonto[],
  resumen: ResumenPagoMultiple,
): void {
  venta.monto_pago = resumen.efectivoRecibido;
  const ef = formaPagos.find((fp) => esFormaPagoEfectivo(fp.nombre));
  if (ef) {
    ef.total = resumen.efectivoAplicado;
  }
}
