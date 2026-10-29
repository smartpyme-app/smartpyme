import { Injectable } from '@angular/core';
import { Observable, of } from 'rxjs';
import { map, timeout } from 'rxjs/operators';
import { DashboardAnalyticsApiService } from './dashboard-analytics-api.service';
import {
  RentabilidadHorizonteId,
  RentabilidadMock,
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
          filasSucursal: raw.filasSucursal ?? [],
          filasProducto: raw.filasProducto ?? [],
          filasCliente: raw.filasCliente ?? [],
          filasProveedor: raw.filasProveedor ?? [],
          filasProyecto: raw.filasProyecto ?? [],
        };
      }),
    );
  }
}
