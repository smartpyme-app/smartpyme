import { Injectable } from '@angular/core';
import { Observable, of } from 'rxjs';
import { map, timeout } from 'rxjs/operators';
import { DashboardAnalyticsApiService } from './dashboard-analytics-api.service';
import {
  RentabilidadHorizonteId,
  RentabilidadMock,
  RentabilidadResumen,
} from '../sections/finanzas/rentabilidad/rentabilidad.model';

export interface RentabilidadQuery {
  anio: number;
  mes: number;
  horizonte: RentabilidadHorizonteId;
  sucursalId: string;
}

@Injectable({
  providedIn: 'root',
})
export class FinanzasRentabilidadDataService {
  constructor(private analytics: DashboardAnalyticsApiService) {}

  obtener(q: RentabilidadQuery): Observable<RentabilidadMock | null> {
    const empresa = this.analytics.idEmpresa;
    if (!empresa) {
      return of(null);
    }
    let p = `empresa=${empresa}&anio=${q.anio}&mes=${q.mes}&horizonte=${q.horizonte}`;
    if (q.sucursalId && q.sucursalId !== 'consolidado') {
      p += `&sucursal=${encodeURIComponent(q.sucursalId)}`;
    }
    const url = `${this.analytics.baseUrl}/api/finanzas/rentabilidad?${p}`;
    return this.analytics.get<RentabilidadMock>(url).pipe(
      timeout(90_000),
      map((raw) => {
        if (!raw) {
          return null;
        }
        return {
          kpis: [],
          resumen: this.mapResumen(raw.resumen),
          filasSucursal: raw.filasSucursal ?? [],
          filasProducto: raw.filasProducto ?? [],
          filasCliente: raw.filasCliente ?? [],
          filasProveedor: raw.filasProveedor ?? [],
          filasProyecto: raw.filasProyecto ?? [],
        };
      }),
    );
  }

  private mapResumen(raw: RentabilidadResumen | null | undefined): RentabilidadResumen | null {
    if (!raw) {
      return null;
    }
    const num = (v: unknown) => {
      const n = Number(v);
      return Number.isFinite(n) ? n : 0;
    };
    const opt = (v: unknown) => {
      if (v === null || v === undefined) {
        return null;
      }
      const n = Number(v);
      return Number.isFinite(n) ? n : null;
    };
    return {
      mesEtiqueta: String(raw.mesEtiqueta ?? ''),
      comparacionEtiqueta: String(raw.comparacionEtiqueta ?? ''),
      utilidadOperativa: num(raw.utilidadOperativa),
      utilidadOperativaPrev: num(raw.utilidadOperativaPrev),
      utilidadOperativaVsPct: opt(raw.utilidadOperativaVsPct),
      margenOperativo: opt(raw.margenOperativo),
      margenOperativoPrev: opt(raw.margenOperativoPrev),
      margenBruto: opt(raw.margenBruto),
      margenBrutoPrev: opt(raw.margenBrutoPrev),
      sucursalesPerdida: num(raw.sucursalesPerdida),
      sucursalesTotal: num(raw.sucursalesTotal),
      peorSucursal: String(raw.peorSucursal ?? ''),
      peorSucursalUtilidad: num(raw.peorSucursalUtilidad),
    };
  }
}
