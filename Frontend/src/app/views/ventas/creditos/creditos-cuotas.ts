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

export function sumaMontosCuotas(cuotas: { monto: number }[]): number {
  return round2(cuotas.reduce((s, c) => s + (Number(c.monto) || 0), 0));
}

export function planCuadra(total: number, cuotas: { monto: number }[]): boolean {
  if (cuotas.length < 2) {
    return false;
  }
  if (cuotas.some((c) => !(Number(c.monto) > 0))) {
    return false;
  }
  return sumaMontosCuotas(cuotas) === round2(total);
}

export type SnapshotMontosVenta = {
  total: number;
  sub_total: number;
  iva: number;
  fecha_pago: string;
  detalles: any[];
};

export type PlanCuotasFactura = {
  tipo: string;
  n_cuotas: number;
  fecha_inicio: string;
  concepto?: string;
  cuotas?: PreviewCuota[];
};

export function snapshotVentaMontos(venta: any): SnapshotMontosVenta {
  return {
    total: Number(venta.total) || 0,
    sub_total: Number(venta.sub_total) || 0,
    iva: Number(venta.iva) || 0,
    fecha_pago: venta.fecha_pago || '',
    detalles: JSON.parse(JSON.stringify(venta.detalles || [])),
  };
}

export function restoreSnapshotVenta(venta: any, snap: SnapshotMontosVenta): void {
  venta.total = snap.total;
  venta.sub_total = snap.sub_total;
  venta.iva = snap.iva;
  venta.fecha_pago = snap.fecha_pago;
  venta.detalles = JSON.parse(JSON.stringify(snap.detalles));
  delete venta.credito_contrato;
}

export function aplicarMontoADetalles(venta: any, nuevoTotal: number): void {
  const actual = Number(venta.total) || 0;
  if (actual <= 0 || nuevoTotal <= 0) {
    return;
  }
  const factor = nuevoTotal / actual;
  const scale = (v: unknown) => round2((parseFloat(String(v ?? 0)) || 0) * factor);
  const detalles = (venta.detalles || []).map((d: any) => {
    const scaled: any = {
      ...d,
      precio: scale(d.precio),
      precio_sin_iva: d.precio_sin_iva != null && d.precio_sin_iva !== '' ? scale(d.precio_sin_iva) : d.precio_sin_iva,
      precio_iva: d.precio_iva != null && d.precio_iva !== '' ? scale(d.precio_iva) : d.precio_iva,
      precio_con_iva: d.precio_con_iva != null && d.precio_con_iva !== '' ? scale(d.precio_con_iva) : d.precio_con_iva,
      descuento: scale(d.descuento),
      descuento_con_iva: d.descuento_con_iva != null && d.descuento_con_iva !== ''
        ? scale(d.descuento_con_iva)
        : d.descuento_con_iva,
      sub_total: scale(d.sub_total),
      total: scale(d.total),
      total_iva: d.total_iva != null && d.total_iva !== '' ? scale(d.total_iva) : d.total_iva,
      iva: scale(d.iva),
      gravada: d.gravada != null ? scale(d.gravada) : d.gravada,
    };
    return scaled;
  });
  const gross = (d: any) => {
    const qty = Number(d.cantidad) || 0;
    const pIva = parseFloat(String(d.precio_iva ?? ''));
    if (Number.isFinite(pIva) && String(d.precio_iva ?? '') !== '') {
      const desc = parseFloat(String(d.descuento_con_iva ?? 0)) || 0;
      return round2(qty * pIva - desc);
    }
    const tIva = parseFloat(String(d.total_iva ?? ''));
    if (Number.isFinite(tIva) && String(d.total_iva ?? '') !== '') {
      return round2(tIva);
    }
    return round2(Number(d.total) || 0);
  };
  const suma = round2(detalles.reduce((s: number, d: any) => s + gross(d), 0));
  const diff = round2(nuevoTotal - suma);
  if (detalles.length && diff !== 0) {
    const last = detalles[detalles.length - 1];
    if (last.precio_iva != null && last.precio_iva !== '') {
      last.precio_iva = round2((parseFloat(String(last.precio_iva)) || 0) + diff);
    }
    last.total_iva = last.total_iva != null && last.total_iva !== ''
      ? round2((parseFloat(String(last.total_iva)) || 0) + diff)
      : last.total_iva;
    last.total = round2((Number(last.total) || 0) + diff);
    last.sub_total = round2((Number(last.sub_total) || 0) + diff);
  }
  venta.detalles = detalles;
  venta.sub_total = scale(venta.sub_total);
  venta.iva = scale(venta.iva);
  venta.total = nuevoTotal;
}

export function aplicarPlanAVenta(venta: any, form: PlanCuotasFactura, snap: SnapshotMontosVenta): void {
  restoreSnapshotVenta(venta, snap);
  const preview = form.cuotas?.length
    ? form.cuotas
    : generarPreviewCuotas(snap.total, Number(form.n_cuotas) || 0, form.fecha_inicio);
  if (!preview.length || !planCuadra(snap.total, preview)) {
    return;
  }
  aplicarMontoADetalles(venta, preview[0].monto);
  venta.fecha_pago = preview[0].fechaVencimiento;
  venta.credito = true;
  venta.condicion = 'Crédito';
  venta.estado = 'Pagada';
  venta.credito_contrato = {
    tipo: form.tipo,
    monto: snap.total,
    n_cuotas: preview.length,
    fecha_inicio: form.fecha_inicio,
    concepto: form.concepto || null,
    tasa_interes: 0,
    tasa_mora: 0,
    cuotas: preview.map((c) => ({
      numero: c.numero,
      monto: Number(c.monto),
      fecha_vencimiento: c.fechaVencimiento,
    })),
  };
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

export function etiquetaTipoCredito(tipo: string | null | undefined): string {
  if (tipo === 'bien') return 'Bien';
  if (tipo === 'servicio') return 'Servicio';
  if (tipo === 'prestamo') return 'Préstamo';
  return tipo || '-';
}

export function etiquetaEstadoContrato(estado: string | null | undefined): string {
  if (estado === 'activo') return 'Activo';
  if (estado === 'cerrado') return 'Cerrado';
  return estado || '-';
}

export function avanceCuotas(credito: { n_cuotas?: number; cuotas_hechas?: number } | null | undefined): {
  hechas: number;
  total: number;
  porcentaje: number;
  tipo: 'success' | 'warning';
} {
  const total = Math.max(0, Number(credito?.n_cuotas) || 0);
  const hechas = Math.min(total, Math.max(0, Number(credito?.cuotas_hechas) || 0));
  const completo = total > 0 && hechas === total;
  return {
    hechas,
    total,
    porcentaje: total > 0 ? Math.round((hechas / total) * 100) : 0,
    tipo: completo ? 'success' : 'warning',
  };
}
