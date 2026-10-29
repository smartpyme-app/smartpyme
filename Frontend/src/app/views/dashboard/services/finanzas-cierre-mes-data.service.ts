import { Injectable } from '@angular/core';
import { Observable, of } from 'rxjs';
import { map, timeout } from 'rxjs/operators';
import { DashboardAnalyticsApiService } from './dashboard-analytics-api.service';
import {
  CierreChecklistEstado,
  CierreMesDetalle,
  CierreMesEstado,
  CierreMesVista,
  CierrePresupuestoEstado,
  CierreVarTono,
} from '../sections/finanzas/cierre-mes/cierre-mes.model';

export interface CierreMesQuery {
  anio: number;
  mes?: number;
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
    puedeCertificar?: boolean;
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
    impuestos?: { vencimiento?: string; items?: { concepto?: string; monto?: number }[] };
    gastosFueraRango?: {
      umbralPct?: number;
      filas?: { categoria?: string; mes?: number; promedio6m?: number; variacionPct?: number }[];
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
    puedeCertificar: raw.puedeCertificar === true,
    checklist: (raw.checklist ?? []).map((i) => ({
      estado: estadoChecklist(i.estado),
      texto: i.texto ?? '',
      accion: i.accion,
    })),
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
