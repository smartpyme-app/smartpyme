export function resumenTotalesLibroIva(fiscalResumen: unknown): {
  ventas: number;
  compras: number;
  compras_sin_devoluciones: number;
  gastos: number;
} {
  const t = (fiscalResumen as { totales?: Record<string, unknown> })?.totales;
  const compras = Number(t?.['compras'] ?? 0);
  const comprasSinDevoluciones =
    t?.['compras_sin_devoluciones'] != null ? Number(t['compras_sin_devoluciones']) : compras;
  return {
    ventas: Number(t?.['ventas'] ?? 0),
    compras,
    compras_sin_devoluciones: comprasSinDevoluciones,
    gastos: Number(t?.['gastos'] ?? 0),
  };
}

export function ventasPorImpuestoResumenLibroIva(
  fiscalResumen: unknown
): { tarifa: string; etiqueta: string; base: number; iva: number }[] {
  const rows = (fiscalResumen as { ventas_por_impuesto?: unknown[] })?.ventas_por_impuesto;
  return Array.isArray(rows) ? (rows as { tarifa: string; etiqueta: string; base: number; iva: number }[]) : [];
}

export function sumaBaseDesgloseLibroIva(rows: { base?: number; iva?: number }[]): number {
  return rows.reduce((s, r) => s + Number(r.base ?? 0), 0);
}

export function sumaImpuestoDesgloseLibroIva(rows: { base?: number; iva?: number }[]): number {
  return rows.reduce((s, r) => s + Number(r.iva ?? 0), 0);
}

export function totalFilaDesgloseLibroIva(row: { base?: number; iva?: number }): number {
  return Number(row.base ?? 0) + Number(row.iva ?? 0);
}

export function sumaVentasDesgloseLibroIva(
  ventasPorImpuesto: { base?: number; iva?: number }[]
): number {
  return ventasPorImpuesto.reduce((s, r) => s + Number(r.base ?? 0) + Number(r.iva ?? 0), 0);
}

export function comprasPorImpuestoResumenLibroIva(
  fiscalResumen: unknown
): { tarifa: string; etiqueta: string; base: number; iva: number }[] {
  const rows = (fiscalResumen as { compras_por_impuesto?: unknown[] })?.compras_por_impuesto;
  return Array.isArray(rows) ? (rows as { tarifa: string; etiqueta: string; base: number; iva: number }[]) : [];
}

export function sumaComprasDesgloseLibroIva(
  comprasPorImpuesto: { base?: number; iva?: number }[]
): number {
  return comprasPorImpuesto.reduce((s, r) => s + Number(r.base ?? 0) + Number(r.iva ?? 0), 0);
}

export function resumenIvaLibroIva(fiscalResumen: unknown): {
  iva_a_favor: number;
  iva_en_contra: number;
  diferencia_estimada_pago_iva: number;
  credito_fiscal_compras: number | null;
  credito_fiscal_gastos: number | null;
  credito_fiscal_devoluciones_compras: number | null;
} {
  const i = (fiscalResumen as { iva?: Record<string, unknown> })?.iva;
  return {
    iva_a_favor: Number(i?.['iva_a_favor'] ?? 0),
    iva_en_contra: Number(i?.['iva_en_contra'] ?? 0),
    diferencia_estimada_pago_iva: Number(i?.['diferencia_estimada_pago_iva'] ?? 0),
    credito_fiscal_compras: i?.['credito_fiscal_compras'] != null ? Number(i['credito_fiscal_compras']) : null,
    credito_fiscal_gastos: i?.['credito_fiscal_gastos'] != null ? Number(i['credito_fiscal_gastos']) : null,
    credito_fiscal_devoluciones_compras:
      i?.['credito_fiscal_devoluciones_compras'] != null ? Number(i['credito_fiscal_devoluciones_compras']) : null,
  };
}

export function pagoCuentaIvaResumenLibroIva(fiscalResumen: unknown): {
  aplica: boolean;
  monto: number;
  descripcion: string;
} {
  const p = (fiscalResumen as { pago_a_cuenta_iva?: Record<string, unknown> })?.pago_a_cuenta_iva;
  return {
    aplica: Boolean(p?.['aplica']),
    monto: Number(p?.['monto'] ?? 0),
    descripcion: String(p?.['descripcion'] ?? ''),
  };
}

export function resumenPeriodoSinMovimientosLibroIva(fiscalResumen: unknown): boolean {
  if (!fiscalResumen) {
    return false;
  }
  const t = resumenTotalesLibroIva(fiscalResumen);
  return t.ventas === 0 && t.compras === 0 && t.gastos === 0;
}
