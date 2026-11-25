import { Injectable } from '@angular/core';
import { Observable, of } from 'rxjs';
import { map, timeout } from 'rxjs/operators';
import { DashboardAnalyticsApiService } from './dashboard-analytics-api.service';
import {
  CierreChecklistEstado,
  CierreChecklistGrupo,
  CierreChecklistGrupoItem,
  CierreChecklistGrupos,
  CierreMesDetalle,
  CierreMesEstado,
  CierreMesResumen,
  CierreMesVista,
  CierrePresupuestoEstado,
  CierreTopVentas,
  CierreVarTono,
} from '../sections/finanzas/cierre-mes/cierre-mes.model';

export interface CierreMesQuery {
  anio: number;
  mes?: number;
}

interface CierreChecklistGrupoItemApi {
  titulo?: string;
  detalle?: string;
  estado?: string;
  monto?: number | null;
  accion?: string;
  tipoGasto?: string;
  concepto?: string;
  montoSugerido?: number | null;
}

interface CierreChecklistGrupoApi {
  registrados?: number;
  total?: number;
  montoTotal?: number;
  items?: CierreChecklistGrupoItemApi[];
}

interface CierreMesApiResponse {
  anio?: number;
  mesActivoId?: number | null;
  mesesCerrados?: number;
  totalMeses?: number;
  meses?: { id?: number; etiqueta?: string; estado?: string }[];
  detalle?: {
    mesTitulo?: string;
    mesCertificar?: string;
    mesEtiquetaCorto?: string;
    checklist?: { estado?: string; texto?: string; accion?: string }[];
    checklistGrupos?: {
      fijos?: CierreChecklistGrupoApi;
      variables?: CierreChecklistGrupoApi;
      otros?: CierreChecklistGrupoItemApi[];
    } | null;
    bloqueado?: boolean;
    puedeCertificar?: boolean;
    resumen?: {
      ventas?: {
        monto?: number;
        vsMesAnt?: { label?: string; pct?: number | null };
        vsAnioAnt?: { label?: string; pct?: number | null };
        vsPresupuesto?: { label?: string; pct?: number | null };
      };
      gastos?: {
        monto?: number;
        vsMesAnt?: { label?: string; pct?: number | null };
        vsAnioAnt?: { label?: string; pct?: number | null };
        vsPresupuesto?: { label?: string; pct?: number | null };
      };
      rentabilidad?: {
        margenPct?: number;
        utilidad?: number;
        vsMesAnt?: { label?: string; pts?: number | null };
        vsAnioAnt?: { label?: string; pts?: number | null };
        vsPresupuesto?: { label?: string; pts?: number | null };
      };
      flujo?: {
        neto?: number;
        saldo?: number | null;
        saldoFecha?: string;
        entro?: number;
        salio?: number;
        flujoAntLabel?: string;
        flujoAnt?: number;
      };
    } | null;
    presupuesto?: {
      categoria?: string;
      presupuesto?: number;
      real?: number;
      varMesPct?: number | null;
      varMesTono?: string;
      varAcumMonto?: number | null;
      varAcumPct?: number | null;
      estado?: string;
    }[];
    impuestos?: { total?: number; vencimiento?: string; items?: { concepto?: string; monto?: number }[] };
    gastosFueraRango?: {
      umbralPct?: number;
      filas?: { categoria?: string; mes?: number; promedio6m?: number; variacionPct?: number }[];
    };
    topVentas?: {
      mesLabel?: string;
      mesAnteriorLabel?: string;
      vendedores?: {
        nombre?: string;
        sucursal?: string;
        monto?: number;
        deltaPct?: number | null;
        pctDelMes?: number;
        pctBarra?: number;
      }[];
      productos?: {
        nombre?: string;
        categoria?: string;
        monto?: number;
        margenPct?: number;
        pctDelMes?: number;
        pctBarra?: number;
      }[];
    };
  } | null;
}

@Injectable({
  providedIn: 'root',
})
export class FinanzasCierreMesDataService {
  constructor(private analytics: DashboardAnalyticsApiService) {}

  obtener(q: CierreMesQuery): Observable<CierreMesVista | null> {
    const empresa = this.analytics.idEmpresa;
    if (!empresa) {
      return of(null);
    }
    let p = `empresa=${empresa}&anio=${q.anio}`;
    if (q.mes) {
      p += `&mes=${q.mes}`;
    }
    const url = `${this.analytics.baseUrl}/api/finanzas/cierre-mes?${p}`;
    return this.analytics.get<CierreMesApiResponse>(url).pipe(
      timeout(90_000),
      map((raw) => (raw ? mapCierre(raw, q.anio) : null)),
    );
  }

  certificar(q: CierreMesQuery): Observable<boolean> {
    const empresa = this.analytics.idEmpresa;
    if (!empresa || !q.mes) {
      return of(false);
    }
    const url = `${this.analytics.baseUrl}/api/finanzas/cierre-mes/certificar?empresa=${empresa}&anio=${q.anio}&mes=${q.mes}`;
    return this.analytics.post<{ ok?: boolean }>(url).pipe(
      timeout(90_000),
      map((raw) => raw?.ok === true),
    );
  }

  descartarFueraRango(q: CierreMesQuery & { categoria: string }): Observable<boolean> {
    const empresa = this.analytics.idEmpresa;
    if (!empresa || !q.mes || !q.categoria) {
      return of(false);
    }
    const url = `${this.analytics.baseUrl}/api/finanzas/cierre-mes/descartar?empresa=${empresa}&anio=${q.anio}&mes=${q.mes}`;
    return this.analytics.post<{ ok?: boolean }>(url, { categoria: q.categoria }).pipe(
      timeout(90_000),
      map((raw) => raw?.ok === true),
    );
  }
}

function mapCierre(raw: CierreMesApiResponse, anio: number): CierreMesVista {
  return {
    anio: raw.anio ?? anio,
    mesActivoId: raw.mesActivoId ?? null,
    mesesCerrados: raw.mesesCerrados ?? 0,
    totalMeses: raw.totalMeses ?? 12,
    meses: (raw.meses ?? []).map((m) => ({
      id: m.id ?? 0,
      etiqueta: m.etiqueta ?? '',
      estado: estadoMes(m.estado),
    })),
    detalle: raw.detalle ? mapDetalle(raw.detalle) : null,
  };
}

function mapDetalle(raw: NonNullable<CierreMesApiResponse['detalle']>): CierreMesDetalle {
  return {
    mesTitulo: raw.mesTitulo ?? '',
    mesCertificar: raw.mesCertificar ?? '',
    mesEtiquetaCorto: raw.mesEtiquetaCorto ?? '',
    bloqueado: raw.bloqueado === true,
    puedeCertificar: raw.puedeCertificar === true,
    resumen: raw.resumen ? mapResumen(raw.resumen) : null,
    checklist: (raw.checklist ?? []).map((i) => ({
      estado: estadoChecklist(i.estado),
      texto: i.texto ?? '',
      accion: i.accion,
    })),
    checklistGrupos: raw.checklistGrupos ? mapChecklistGrupos(raw.checklistGrupos) : null,
    presupuesto: (raw.presupuesto ?? []).map((f) => ({
      categoria: f.categoria ?? '',
      presupuesto: f.presupuesto ?? 0,
      real: f.real ?? 0,
      varMesPct: f.varMesPct ?? null,
      varMesTono: tono(f.varMesTono),
      varAcumMonto: f.varAcumMonto ?? null,
      varAcumPct: f.varAcumPct ?? null,
      estado: estadoPresupuesto(f.estado),
    })),
    impuestos: {
      total: raw.impuestos?.total ?? (raw.impuestos?.items ?? []).reduce((s, i) => s + (i.monto ?? 0), 0),
      vencimiento: raw.impuestos?.vencimiento ?? '',
      items: (raw.impuestos?.items ?? []).map((i) => ({
        concepto: i.concepto ?? '',
        monto: i.monto ?? 0,
      })),
    },
    gastosFueraRango: {
      umbralPct: raw.gastosFueraRango?.umbralPct ?? 20,
      filas: (raw.gastosFueraRango?.filas ?? []).map((f) => ({
        categoria: f.categoria ?? '',
        mes: f.mes ?? 0,
        promedio6m: f.promedio6m ?? 0,
        variacionPct: f.variacionPct ?? 0,
      })),
    },
    topVentas: mapTopVentas(raw.topVentas, raw.mesEtiquetaCorto ?? ''),
  };
}

function mapTopVentas(
  raw: NonNullable<CierreMesApiResponse['detalle']>['topVentas'],
  mesFallback: string,
): CierreTopVentas {
  const clampBar = (v?: number) => Math.max(0, Math.min(100, v ?? 0));
  return {
    mesLabel: raw?.mesLabel ?? mesFallback,
    mesAnteriorLabel: raw?.mesAnteriorLabel ?? '',
    vendedores: (raw?.vendedores ?? []).map((v) => ({
      nombre: v.nombre ?? '',
      sucursal: v.sucursal ?? '',
      monto: v.monto ?? 0,
      deltaPct: v.deltaPct ?? null,
      pctDelMes: v.pctDelMes ?? 0,
      pctBarra: clampBar(v.pctBarra),
    })),
    productos: (raw?.productos ?? []).map((p) => ({
      nombre: p.nombre ?? '',
      categoria: p.categoria ?? '',
      monto: p.monto ?? 0,
      margenPct: p.margenPct ?? 0,
      pctDelMes: p.pctDelMes ?? 0,
      pctBarra: clampBar(p.pctBarra),
    })),
  };
}

function mapChecklistGrupoItem(i: CierreChecklistGrupoItemApi): CierreChecklistGrupoItem {
  return {
    titulo: i.titulo ?? '',
    detalle: i.detalle,
    estado: estadoChecklist(i.estado),
    monto: i.monto ?? null,
    accion: i.accion,
    tipoGasto: i.tipoGasto,
    concepto: i.concepto,
    montoSugerido: i.montoSugerido ?? null,
  };
}

function mapChecklistGrupo(g?: CierreChecklistGrupoApi): CierreChecklistGrupo {
  return {
    registrados: g?.registrados ?? 0,
    total: g?.total ?? 0,
    montoTotal: g?.montoTotal ?? 0,
    items: (g?.items ?? []).map(mapChecklistGrupoItem),
  };
}

function mapChecklistGrupos(raw: NonNullable<NonNullable<CierreMesApiResponse['detalle']>['checklistGrupos']>): CierreChecklistGrupos {
  return {
    fijos: mapChecklistGrupo(raw.fijos),
    variables: mapChecklistGrupo(raw.variables),
    otros: (raw.otros ?? []).map(mapChecklistGrupoItem),
  };
}

function mapResumen(raw: NonNullable<NonNullable<CierreMesApiResponse['detalle']>['resumen']>): CierreMesResumen {
  const varPct = (v?: { label?: string; pct?: number | null }) => ({
    label: v?.label ?? '',
    valor: v?.pct ?? null,
  });
  const varPts = (v?: { label?: string; pts?: number | null }) => ({
    label: v?.label ?? '',
    valor: v?.pts ?? null,
  });
  return {
    ventas: {
      monto: raw.ventas?.monto ?? 0,
      vsMesAnt: varPct(raw.ventas?.vsMesAnt),
      vsAnioAnt: varPct(raw.ventas?.vsAnioAnt),
      vsPresupuesto: varPct(raw.ventas?.vsPresupuesto),
    },
    gastos: {
      monto: raw.gastos?.monto ?? 0,
      vsMesAnt: varPct(raw.gastos?.vsMesAnt),
      vsAnioAnt: varPct(raw.gastos?.vsAnioAnt),
      vsPresupuesto: varPct(raw.gastos?.vsPresupuesto),
    },
    rentabilidad: {
      margenPct: raw.rentabilidad?.margenPct ?? 0,
      utilidad: raw.rentabilidad?.utilidad ?? 0,
      vsMesAnt: varPts(raw.rentabilidad?.vsMesAnt),
      vsAnioAnt: varPts(raw.rentabilidad?.vsAnioAnt),
      vsPresupuesto: varPts(raw.rentabilidad?.vsPresupuesto),
    },
    flujo: {
      neto: raw.flujo?.neto ?? 0,
      saldo: raw.flujo?.saldo ?? null,
      saldoFecha: raw.flujo?.saldoFecha ?? '',
      entro: raw.flujo?.entro ?? 0,
      salio: raw.flujo?.salio ?? 0,
      flujoAntLabel: raw.flujo?.flujoAntLabel ?? '',
      flujoAnt: raw.flujo?.flujoAnt ?? 0,
    },
  };
}

function estadoMes(v: string | undefined): CierreMesEstado {
  if (v === 'cerrado' || v === 'activo' || v === 'pendiente') {
    return v;
  }
  return 'pendiente';
}

function estadoChecklist(v: string | undefined): CierreChecklistEstado {
  if (v === 'ok' || v === 'error' || v === 'alerta') {
    return v;
  }
  return 'alerta';
}

function tono(v: string | undefined): CierreVarTono {
  if (v === 'neutral' || v === 'pos' || v === 'neg' || v === 'warn') {
    return v;
  }
  return 'neutral';
}

function estadoPresupuesto(v: string | undefined): CierrePresupuestoEstado {
  if (v === 'ok' || v === 'na' || v === 'warn') {
    return v;
  }
  return 'na';
}

export const CIERRE_MES_VACIA: CierreMesVista = {
  anio: new Date().getFullYear(),
  meses: [],
  mesActivoId: null,
  mesesCerrados: 0,
  totalMeses: 12,
  detalle: null,
};
