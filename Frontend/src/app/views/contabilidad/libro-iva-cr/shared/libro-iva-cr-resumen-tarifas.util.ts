export type ResumenTarifaIvaCr = { etiqueta: string; base: number; iva: number };

const TARIFAS = [13, 8, 4, 2, 1] as const;

function monto(totales: Record<string, number> | null | undefined, key: string): number {
  const v = Number(totales?.[key]);
  return Number.isFinite(v) ? v : 0;
}

/** Resumen fijo por tarifa del libro CR. El exento es la tarifa 0%. */
export function resumenTarifasLibroIvaCr(
  totales: Record<string, number> | null | undefined
): ResumenTarifaIvaCr[] {
  const rows: ResumenTarifaIvaCr[] = TARIFAS.map((t) => ({
    etiqueta: `IVA ${t}%`,
    base: monto(totales, `subtotal_${t}`),
    iva: monto(totales, `iva_${t}`),
  }));
  rows.push({ etiqueta: 'Exento (IVA 0%)', base: monto(totales, 'subtotal_exento'), iva: 0 });
  rows.push({ etiqueta: 'Exonerado', base: monto(totales, 'subtotal_exonerado'), iva: 0 });
  return rows;
}
