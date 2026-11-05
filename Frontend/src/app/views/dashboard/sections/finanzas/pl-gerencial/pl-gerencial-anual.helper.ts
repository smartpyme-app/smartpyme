import { PlEstadoResultadosFila } from './pl-gerencial.model';

export type PlFilaVistaAnualEstilo = 'normal' | 'subtotal' | 'margen' | 'resultado';

export interface PlFilaVistaAnual {
  key: string;
  concepto: string;
  indent?: boolean;
  estilo: PlFilaVistaAnualEstilo;
  /** Fila de montos del API. */
  fuente?: PlEstadoResultadosFila;
  /** Porcentajes mes a mes (12) para filas de margen. */
  margenMeses?: (number | null)[];
  margenTotal?: number | null;
}

const ORDEN_ANUAL: { id: string; margen?: 'bruto' | 'operativo' }[] = [
  { id: 'ventas_netas' },
  { id: 'costo_ventas' },
  { id: 'utilidad_bruta' },
  { id: '__margen_bruto__', margen: 'bruto' },
  { id: 'opex_personal' },
  { id: 'opex_local' },
  { id: 'opex_ventas_marketing' },
  { id: 'opex_admin' },
  { id: 'opex_financieros' },
  { id: 'utilidad_operativa' },
  { id: '__margen_operativo__', margen: 'operativo' },
  { id: 'resultado_neto' },
];

function byLineId(filas: PlEstadoResultadosFila[]): Map<string, PlEstadoResultadosFila> {
  const m = new Map<string, PlEstadoResultadosFila>();
  for (const f of filas) {
    if (f.lineId) {
      m.set(f.lineId, f);
    }
  }
  return m;
}

function pctMargen(util: number, netas: number): number | null {
  if (Math.abs(netas) < 0.01) {
    return null;
  }
  return Math.round((util / netas) * 1000) / 10;
}

function margenDesdeFilas(
  util?: PlEstadoResultadosFila,
  netas?: PlEstadoResultadosFila,
): { meses: (number | null)[]; total: number | null } {
  const meses: (number | null)[] = [];
  for (let i = 0; i < 12; i++) {
    const u = util?.meses?.[i] ?? 0;
    const n = netas?.meses?.[i] ?? 0;
    meses.push(pctMargen(u, n));
  }
  const uT = util?.totalAnual ?? util?.mes ?? 0;
  const nT = netas?.totalAnual ?? netas?.mes ?? 0;
  return { meses, total: pctMargen(uT, nT) };
}

function estiloFilaAnual(lineId: string): PlFilaVistaAnualEstilo {
  if (lineId === 'resultado_neto') {
    return 'resultado';
  }
  if (lineId === 'ventas_netas' || lineId === 'utilidad_bruta' || lineId === 'utilidad_operativa') {
    return 'subtotal';
  }
  return 'normal';
}

/** Filas visibles en la grilla anual (mock v4). */
export function buildFilasVistaAnual(filas: PlEstadoResultadosFila[]): PlFilaVistaAnual[] {
  const map = byLineId(filas);
  const netas = map.get('ventas_netas');
  const bruta = map.get('utilidad_bruta');
  const oper = map.get('utilidad_operativa');
  const out: PlFilaVistaAnual[] = [];

  for (const spec of ORDEN_ANUAL) {
    if (spec.margen === 'bruto') {
      const { meses, total } = margenDesdeFilas(bruta, netas);
      out.push({
        key: spec.id,
        concepto: 'Margen bruto %',
        estilo: 'margen',
        margenMeses: meses,
        margenTotal: total,
      });
      continue;
    }
    if (spec.margen === 'operativo') {
      const { meses, total } = margenDesdeFilas(oper, netas);
      out.push({
        key: spec.id,
        concepto: 'Margen operativo %',
        estilo: 'margen',
        margenMeses: meses,
        margenTotal: total,
      });
      continue;
    }
    const fuente = map.get(spec.id);
    if (!fuente) {
      continue;
    }
    out.push({
      key: spec.id,
      concepto: fuente.concepto,
      indent: fuente.indent,
      estilo: estiloFilaAnual(spec.id),
      fuente,
    });
  }
  return out;
}
