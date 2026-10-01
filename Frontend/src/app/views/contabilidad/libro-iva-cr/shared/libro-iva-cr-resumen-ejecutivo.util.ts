type FilaImpuesto = { tarifa?: string; etiqueta?: string; iva?: number };

export type ResumenEjecutivoTipoIva = {
  tipo: string;
  ventas: number;
  compras: number;
  neto: number;
};

function redondear(n: number): number {
  return Math.round(n * 100) / 100;
}

function claveTipo(row: FilaImpuesto): { key: string; tipo: string; orden: number } {
  const tarifa = String(row.tarifa ?? '').trim();
  if (tarifa.endsWith('%')) {
    const n = Number(tarifa.slice(0, -1));
    const orden = Number.isFinite(n) ? n : 0;
    return { key: `pct:${orden}`, tipo: `IVA ${tarifa}`, orden };
  }
  const tipo = String(row.etiqueta || tarifa || 'Exento').trim();
  return { key: `otro:${tipo.toLowerCase()}`, tipo, orden: Number.NEGATIVE_INFINITY };
}

/** Impuesto por tipo de IVA: ventas, compras y neto (ventas − compras). */
export function resumenEjecutivoPorTipoIva(fiscalResumen: unknown): ResumenEjecutivoTipoIva[] {
  const data = fiscalResumen as { ventas_por_impuesto?: FilaImpuesto[]; compras_por_impuesto?: FilaImpuesto[] } | null;
  const ventas = Array.isArray(data?.ventas_por_impuesto) ? data.ventas_por_impuesto : [];
  const compras = Array.isArray(data?.compras_por_impuesto) ? data.compras_por_impuesto : [];
  const map = new Map<string, { tipo: string; orden: number; ventas: number; compras: number }>();

  const acumular = (rows: FilaImpuesto[], lado: 'ventas' | 'compras') => {
    for (const row of rows) {
      const c = claveTipo(row);
      const cur = map.get(c.key) ?? { tipo: c.tipo, orden: c.orden, ventas: 0, compras: 0 };
      cur[lado] += Number(row.iva ?? 0);
      map.set(c.key, cur);
    }
  };

  acumular(ventas, 'ventas');
  acumular(compras, 'compras');

  return [...map.values()]
    .sort((a, b) => b.orden - a.orden || a.tipo.localeCompare(b.tipo))
    .map((row) => ({
      tipo: row.tipo,
      ventas: redondear(row.ventas),
      compras: redondear(row.compras),
      neto: redondear(row.ventas - row.compras),
    }));
}
