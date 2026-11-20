import type { RentabilidadKpi, RentabilidadResumen, RentabilidadSucursalFila } from './rentabilidad.model';

/** Si el API no manda resumen (sucursal asignada o servidor viejo), los cards salen de las filas ya filtradas. */
export function resumenDesdeFilas(
  filas: RentabilidadSucursalFila[],
  mesEtiqueta: string,
  comparacionEtiqueta: string,
): RentabilidadResumen | null {
  if (!filas.length) {
    return null;
  }
  let ventas = 0;
  let costo = 0;
  let utilidad = 0;
  let perdida = 0;
  let peor = '';
  let peorUtil = 0;
  for (const fila of filas) {
    ventas += fila.ventas;
    utilidad += fila.utilidad;
    const paso = fila.detalle?.puente?.find((p) => p.etiqueta === 'Costo');
    costo += paso?.monto ?? 0;
    if (fila.utilidad < 0) {
      perdida++;
      if (!peor || fila.utilidad < peorUtil) {
        peor = fila.nombre;
        peorUtil = fila.utilidad;
      }
    }
  }
  const margen = (num: number, den: number): number | null => (den ? (num / den) * 100 : null);
  return {
    mesEtiqueta,
    comparacionEtiqueta,
    utilidadOperativa: utilidad,
    utilidadOperativaPrev: 0,
    utilidadOperativaVsPct: null,
    margenOperativo: margen(utilidad, ventas),
    margenOperativoPrev: null,
    margenBruto: margen(ventas - costo, ventas),
    margenBrutoPrev: null,
    sucursalesPerdida: perdida,
    sucursalesTotal: filas.length,
    peorSucursal: peor,
    peorSucursalUtilidad: peorUtil,
  };
}

export function kpisDesdeResumen(
  resumen: RentabilidadResumen,
  formatMonto: (value: number) => string,
  formatPct: (value: number, digits?: number) => string,
): RentabilidadKpi[] {
  const mes = resumen.mesEtiqueta || 'mes';
  const vs = resumen.comparacionEtiqueta;
  const utilidad: RentabilidadKpi = {
    titulo: `Utilidad operativa · ${mes}`,
    valor: formatMonto(resumen.utilidadOperativa),
    tono: resumen.utilidadOperativa < 0 ? 'rojo' : 'azul',
  };
  if (resumen.utilidadOperativaVsPct !== null) {
    utilidad.sublinea = `${flecha(resumen.utilidadOperativaVsPct)} ${signoPct(resumen.utilidadOperativaVsPct)} vs. ${vs} · ${formatMonto(resumen.utilidadOperativaPrev)}`;
    utilidad.sublineaTono = tonoDelta(resumen.utilidadOperativaVsPct);
  }

  const margenOp = cardMargen(
    'Margen operativo',
    resumen.margenOperativo,
    resumen.margenOperativoPrev,
    vs,
    formatPct,
  );
  if (resumen.margenOperativo !== null && resumen.margenOperativoPrev !== null) {
    margenOp.sublinea = `${formatPct(resumen.margenOperativo)} vs. ${vs} ${formatPct(resumen.margenOperativoPrev)}`;
  }
  const margenBruto = cardMargen(
    'Margen bruto',
    resumen.margenBruto,
    resumen.margenBrutoPrev,
    vs,
    formatPct,
  );

  const perdida = resumen.sucursalesPerdida;
  const sucursales: RentabilidadKpi = {
    titulo: 'Sucursales con pérdida',
    valor: `${perdida} de ${resumen.sucursalesTotal}`,
    tono: perdida > 0 ? 'rojo' : 'azul',
    alerta: perdida > 0,
  };
  if (perdida > 0 && resumen.peorSucursal) {
    sucursales.sublinea = `${resumen.peorSucursal} · ${formatMonto(resumen.peorSucursalUtilidad)}`;
  }

  return [utilidad, margenOp, margenBruto, sucursales];
}

function cardMargen(
  titulo: string,
  actual: number | null,
  prev: number | null,
  vs: string,
  formatPct: (value: number, digits?: number) => string,
): RentabilidadKpi {
  const card: RentabilidadKpi = {
    titulo,
    valor: actual === null ? '—' : formatPct(actual),
    tono: 'azul',
  };
  if (actual !== null && prev !== null) {
    const delta = actual - prev;
    card.sublinea = `${flecha(delta)} vs. ${vs} · ${formatPct(prev)}`;
    card.sublineaTono = tonoDelta(delta);
  }
  return card;
}

function flecha(delta: number): string {
  if (delta < 0) {
    return '↓';
  }
  if (delta > 0) {
    return '↑';
  }
  return '→';
}

function tonoDelta(delta: number): 'neg' | 'pos' | undefined {
  if (delta < 0) {
    return 'neg';
  }
  if (delta > 0) {
    return 'pos';
  }
  return undefined;
}

function signoPct(value: number): string {
  const n = value.toFixed(1);
  return value > 0 ? `+${n}%` : `${n}%`;
}
