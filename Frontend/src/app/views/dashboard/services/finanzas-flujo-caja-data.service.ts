import { Injectable } from '@angular/core';
import { Observable, of } from 'rxjs';
import { map, timeout } from 'rxjs/operators';
import { DashboardAnalyticsApiService } from './dashboard-analytics-api.service';
import {
  FlujoCajaVista,
  FlujoPosicion,
  FlujoSegmentoBarra,
  FlujoSemanaDesglose,
  FlujoSemanaDetalleFila,
} from '../sections/finanzas/flujo-caja/flujo-caja.model';

export interface FlujoCajaQuery {
  sucursalId: string;
  cobroPct: number;
  metaPct: number;
}

interface FlujoCajaApiResponse {
  kpis?: {
    puntoBajo4Semanas?: number;
    semanaBajo4?: string;
    rangoBajo4?: string;
    vsColchon?: number;
    puntoBajo12Semanas?: number;
    semanaBajo12?: string;
    rangoBajo12?: string;
    cobroNecesarioPct?: number;
    metaNecesariaPct?: number;
    semanasBajoColchon?: number;
    totalSemanasProyeccion?: number;
  };
  efectivoInicial?: number;
  efectivoInicialFecha?: string;
  colchon?: number;
  cicloEfectivoDias?: number | null;
  cobroCxCHistoricoPct?: number;
  metaRitmoPct?: number;
  pctContado?: number;
  semanas?: {
    labels?: string[];
    neto?: number[];
    saldo?: number[];
    saldoBajo?: number[];
    saldoAlto?: number[];
  };
  posicion?: FlujoPosicion;
  detalle?: { filas?: FlujoSemanaDetalleFila[]; notaPie?: string };
  desgloses?: FlujoSemanaDesglose[];
}

@Injectable({
  providedIn: 'root',
})
export class FinanzasFlujoCajaDataService {
  constructor(private analytics: DashboardAnalyticsApiService) {}

  obtener(q: FlujoCajaQuery): Observable<FlujoCajaVista | null> {
    const empresa = this.analytics.idEmpresa;
    if (!empresa) {
      return of(null);
    }
    let p = `empresa=${empresa}&cobroPct=${q.cobroPct}&metaPct=${q.metaPct}`;
    if (q.sucursalId && q.sucursalId !== 'consolidado') {
      p += `&sucursal=${encodeURIComponent(q.sucursalId)}`;
    }
    const url = `${this.analytics.baseUrl}/api/finanzas/flujo-caja?${p}`;
    return this.analytics.get<FlujoCajaApiResponse>(url).pipe(
      timeout(90_000),
      map((raw) => (raw ? mapFlujoCaja(raw) : null)),
    );
  }
}

function mapFlujoCaja(raw: FlujoCajaApiResponse): FlujoCajaVista {
  const k = raw.kpis ?? {};
  const pos = raw.posicion ?? posicionVacia();
  return {
    kpis: {
      puntoBajo4Semanas: k.puntoBajo4Semanas ?? 0,
      semanaBajo4: k.semanaBajo4 ?? '',
      puntoBajo4SemanasSub: kpiSub(k.semanaBajo4, k.rangoBajo4),
      vsColchon: k.vsColchon ?? 0,
      puntoBajo12Semanas: k.puntoBajo12Semanas ?? 0,
      semanaBajo12: k.semanaBajo12 ?? '',
      puntoBajo12SemanasSub: kpiSub(k.semanaBajo12, k.rangoBajo12),
      cobroNecesarioPct: k.cobroNecesarioPct ?? 0,
      metaNecesariaPct: k.metaNecesariaPct ?? 0,
      semanasBajoColchon: k.semanasBajoColchon ?? 0,
      totalSemanasProyeccion: k.totalSemanasProyeccion ?? 12,
    },
    efectivoInicial: raw.efectivoInicial ?? 0,
    efectivoInicialFecha: raw.efectivoInicialFecha ?? '',
    colchon: raw.colchon ?? 0,
    cicloEfectivoDias: raw.cicloEfectivoDias ?? null,
    cobroCxCHistoricoPct: raw.cobroCxCHistoricoPct ?? 0,
    metaRitmoPct: raw.metaRitmoPct ?? 0,
    pctContado: raw.pctContado ?? 0,
    semanas: {
      labels: raw.semanas?.labels ?? [],
      neto: raw.semanas?.neto ?? [],
      saldo: raw.semanas?.saldo ?? [],
      saldoBajo: raw.semanas?.saldoBajo ?? [],
      saldoAlto: raw.semanas?.saldoAlto ?? [],
    },
    posicion: {
      cuentasCobrar: {
        total: pos.cuentasCobrar?.total ?? 0,
        segmentos: mapSegmentos(pos.cuentasCobrar?.segmentos),
        llamarPrimero: pos.cuentasCobrar?.llamarPrimero ?? [],
      },
      cuentasPagar: {
        total: pos.cuentasPagar?.total ?? 0,
        segmentos: mapSegmentos(pos.cuentasPagar?.segmentos),
        pagosGrandes: pos.cuentasPagar?.pagosGrandes ?? [],
      },
      salidasFijas: { items: pos.salidasFijas?.items ?? [] },
    },
    detalle: {
      filas: raw.detalle?.filas ?? [],
      notaPie: raw.detalle?.notaPie ?? '',
    },
    desgloses: raw.desgloses ?? [],
  };
}

function kpiSub(semana?: string, rango?: string): string {
  if (!semana?.trim()) {
    return '';
  }
  return rango?.trim() ? `${semana} - ${rango}` : semana;
}

function mapSegmentos(segs: FlujoSegmentoBarra[] | undefined): FlujoSegmentoBarra[] {
  return (segs ?? []).map((s) => ({
    etiqueta: s.etiqueta,
    monto: s.monto,
    tono: tonoSegmento(s.tono),
  }));
}

function tonoSegmento(t: string): FlujoSegmentoBarra['tono'] {
  if (t === 'green' || t === 'orange' || t === 'yellow' || t === 'red' || t === 'blue') {
    return t;
  }
  return 'blue';
}

function posicionVacia(): FlujoPosicion {
  return {
    cuentasCobrar: { total: 0, segmentos: [], llamarPrimero: [] },
    cuentasPagar: { total: 0, segmentos: [], pagosGrandes: [] },
    salidasFijas: { items: [] },
  };
}

export const FLUJO_CAJA_VACIA: FlujoCajaVista = mapFlujoCaja({});
