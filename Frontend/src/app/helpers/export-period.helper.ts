export const MAX_DIAS_EXPORT_DETALLES = 31;

export const MAX_DIAS_EXPORT_VENTAS = 90;

export const MAX_DIAS_EXPORT_GENERAL = 90;

export const MENSAJE_REPORTES_AUTOMATICOS =
  'Para períodos más amplios, configure la descarga en Reportes automáticos (/reportes-automaticos).';

export type TipoPeriodoExport = 'rango' | 'mes' | 'anio_mes';

export type ExportLimiteTipo = 'detalles' | 'ventas' | 'general';

export interface ExportPeriodoState {
  tipo: TipoPeriodoExport;
  rangoInicio: string;
  rangoFin: string;
  anio: number;
  mes: number;
}

export const MESES_EXPORT_PERIODO = [
  { v: 1, n: 'Enero' },
  { v: 2, n: 'Febrero' },
  { v: 3, n: 'Marzo' },
  { v: 4, n: 'Abril' },
  { v: 5, n: 'Mayo' },
  { v: 6, n: 'Junio' },
  { v: 7, n: 'Julio' },
  { v: 8, n: 'Agosto' },
  { v: 9, n: 'Septiembre' },
  { v: 10, n: 'Octubre' },
  { v: 11, n: 'Noviembre' },
  { v: 12, n: 'Diciembre' },
];

export function maxDiasExportPorTipo(tipo: ExportLimiteTipo): number {
  switch (tipo) {
    case 'detalles':
      return MAX_DIAS_EXPORT_DETALLES;
    case 'ventas':
      return MAX_DIAS_EXPORT_VENTAS;
    default:
      return MAX_DIAS_EXPORT_GENERAL;
  }
}

export function crearEstadoExportPeriodoDefault(): ExportPeriodoState {
  const d = new Date();
  return {
    tipo: 'anio_mes',
    rangoInicio: '',
    rangoFin: '',
    anio: d.getFullYear(),
    mes: d.getMonth() + 1,
  };
}

function pad2ExportPeriodo(n: number): string {
  return String(n).padStart(2, '0');
}

function ultimoDiaDelMesExportPeriodo(anio: number, mes: number): number {
  return new Date(anio, mes, 0).getDate();
}

export function resolveFechasExportPeriodo(
  state: ExportPeriodoState
): { inicio: string; fin: string } | null {
  switch (state.tipo) {
    case 'rango': {
      const ini = state.rangoInicio?.trim();
      const fin = state.rangoFin?.trim();
      if (!ini || !fin || ini > fin) return null;
      return { inicio: ini, fin };
    }
    case 'mes': {
      const y = new Date().getFullYear();
      const m = +state.mes;
      if (m < 1 || m > 12) return null;
      const ud = ultimoDiaDelMesExportPeriodo(y, m);
      return {
        inicio: `${y}-${pad2ExportPeriodo(m)}-01`,
        fin: `${y}-${pad2ExportPeriodo(m)}-${pad2ExportPeriodo(ud)}`,
      };
    }
    case 'anio_mes': {
      const y = +state.anio;
      const m = +state.mes;
      if (!y || y < 2000 || y > 2100 || m < 1 || m > 12) return null;
      const ud = ultimoDiaDelMesExportPeriodo(y, m);
      return {
        inicio: `${y}-${pad2ExportPeriodo(m)}-01`,
        fin: `${y}-${pad2ExportPeriodo(m)}-${pad2ExportPeriodo(ud)}`,
      };
    }
    default:
      return null;
  }
}

export function prefillExportPeriodoDesdeFiltros(
  filtros: { inicio?: string; fin?: string },
  state: ExportPeriodoState
): void {
  const i = String(filtros?.inicio ?? '').trim();
  const f = String(filtros?.fin ?? '').trim();
  if (!i || !f) return;
  state.tipo = 'rango';
  state.rangoInicio = i;
  state.rangoFin = f;
}

export function aniosDisponiblesExportDesde(minAnio = 2023): number[] {
  const end = new Date().getFullYear();
  const anios: number[] = [];
  for (let year = end; year >= minAnio; year--) {
    anios.push(year);
  }
  return anios;
}

export function diasEntreFechasIso(inicioIso: string, finIso: string): number {
  const s = new Date(inicioIso + 'T12:00:00');
  const e = new Date(finIso + 'T12:00:00');
  return Math.floor((e.getTime() - s.getTime()) / 86400000);
}

export function validarPeriodoExport(
  inicio: string | undefined | null,
  fin: string | undefined | null,
  maxDias: number
): { valid: boolean; error?: string } {
  const ini = String(inicio ?? '').trim();
  const end = String(fin ?? '').trim();

  if (!ini || !end) {
    return {
      valid: false,
      error: `Debe indicar fecha de inicio y fin (máximo ${maxDias} días). ${MENSAJE_REPORTES_AUTOMATICOS}`,
    };
  }

  if (ini > end) {
    return {
      valid: false,
      error: 'La fecha de inicio no puede ser posterior a la fecha de fin.',
    };
  }

  if (diasEntreFechasIso(ini, end) > maxDias) {
    return {
      valid: false,
      error: `El rango no puede superar ${maxDias} días. ${MENSAJE_REPORTES_AUTOMATICOS}`,
    };
  }

  return { valid: true };
}

export function buildFechasExportValidadas(
  state: ExportPeriodoState,
  limiteTipo: ExportLimiteTipo
): { inicio: string; fin: string } | null {
  const raw = resolveFechasExportPeriodo(state);
  if (!raw) return null;
  const check = validarPeriodoExport(raw.inicio, raw.fin, maxDiasExportPorTipo(limiteTipo));
  return check.valid ? raw : null;
}

export function esErrorTimeoutExport(error: unknown): boolean {
  if (!error || typeof error !== 'object') {
    return false;
  }
  const err = error as { name?: string; message?: string; status?: number };
  return (
    err.name === 'TimeoutError' ||
    (typeof err.message === 'string' && /timeout/i.test(err.message)) ||
    err.status === 504
  );
}

export function mensajeErrorTimeoutExport(maxDias: number): string {
  return `La descarga superó el tiempo permitido. Reduzca el rango (máximo ${maxDias} días) o use Reportes automáticos.`;
}
