import { Injectable } from '@angular/core';
import { Observable, of, throwError } from 'rxjs';
import { catchError, map, timeout } from 'rxjs/operators';
import { DashboardAnalyticsApiService } from './dashboard-analytics-api.service';
import {
  PlEstadoResultadosFila,
  PlGastoPctVentaFila,
  PlGastosPctVentasMock,
  PlKpi,
} from '../sections/finanzas/pl-gerencial/pl-gerencial.model';
export interface PlGerencialQuery {
  periodo: 'mes' | 'anio_completo';
  base: 'gerencial' | 'caja';
  sucursalId: string;
  comparacion: 'mes_anterior' | 'mes_anterior_2' | 'presupuesto';
  anio: number;
  mes: number;
}

export interface PlGerencialResponse {
  meta: {
    periodoTitulo: string;
    comparacionTitulo: string;
    anioAcumulado: number;
  };
  kpis: PlKpi[];
  estadoResultados: PlEstadoResultadosFila[];
  gastosPctVentas: PlGastosPctVentasMock;
}

interface PlGerencialApiKpi {
  titulo: string;
  valor: number;
  esPct?: boolean;
  tono: PlKpi['tono'];
  sublinea?: string;
}

@Injectable({
  providedIn: 'root',
})
export class FinanzasPlDataService {
  constructor(private analytics: DashboardAnalyticsApiService) {}

  obtenerPlGerencial(q: PlGerencialQuery): Observable<PlGerencialResponse | null> {
    const empresa = this.analytics.idEmpresa;
    if (!empresa) {
      return of(null);
    }
    let p = `empresa=${empresa}&anio=${q.anio}&mes=${q.mes}`;
    p += `&periodo=${q.periodo}&base=${q.base}&comparacion=${q.comparacion}`;
    if (q.sucursalId && q.sucursalId !== 'consolidado') {
      p += `&sucursal=${encodeURIComponent(q.sucursalId)}`;
    }
    const url = `${this.analytics.baseUrl}/api/finanzas/pl-gerencial?${p}`;
    return this.analytics.get<Record<string, unknown>>(url).pipe(
      timeout(90_000),
      map((raw) => {
        if (!raw) {
          return null;
        }
        try {
          return this.mapear(raw);
        } catch (e) {
          console.error('Error mapping /api/finanzas/pl-gerencial:', e, raw);
          return null;
        }
      }),
      catchError((err) => {
        console.error('Error loading /api/finanzas/pl-gerencial:', url, err);
        return throwError(() => err);
      }),
    );
  }

  private mapear(raw: Record<string, unknown>): PlGerencialResponse {
    const meta = (raw['meta'] ?? {}) as Record<string, unknown>;
    const kpisRaw = (raw['kpis'] ?? []) as PlGerencialApiKpi[];
    const filas = (raw['estadoResultados'] ?? []) as PlEstadoResultadosFila[];
    const gpv = (raw['gastosPctVentas'] ?? {}) as { filas?: PlGastoPctVentaFila[] };

    return {
      meta: {
        periodoTitulo: String(meta['periodoTitulo'] ?? ''),
        comparacionTitulo: String(meta['comparacionTitulo'] ?? ''),
        anioAcumulado: Number(meta['anioAcumulado'] ?? new Date().getFullYear()),
      },
      kpis: kpisRaw.map((k) => ({
        titulo: k.titulo,
        valor: k.esPct ? `${k.valor}%` : this.formatMonto(k.valor),
        tono: k.tono,
        sublinea: k.sublinea,
      })),
      estadoResultados: filas,
      gastosPctVentas: { filas: gpv.filas ?? [] },
    };
  }

  private formatMonto(n: number): string {
    const abs = Math.abs(n);
    const formatted = new Intl.NumberFormat('en-US', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    }).format(abs);
    if (n < 0) {
      return `-$${formatted}`;
    }
    return `$${formatted}`;
  }
}
