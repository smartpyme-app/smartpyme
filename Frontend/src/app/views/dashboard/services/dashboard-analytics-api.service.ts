import { Injectable } from '@angular/core';
import { HttpClient, HttpHeaders } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { catchError } from 'rxjs/operators';
import { ApiService } from '@services/api.service';
import { environment } from '../../../../environments/environment';

/**
 * Cliente HTTP y utilidades de query compartidas por los dashboards de analytics (Go API).
 */
@Injectable({
  providedIn: 'root',
})
export class DashboardAnalyticsApiService {
  constructor(
    private apiService: ApiService,
    private http: HttpClient,
  ) {}

  get idEmpresa(): number {
    return (
      this.apiService.auth_user()?.id_empresa ??
      this.apiService.auth_user()?.empresa?.id ??
      0
    );
  }

  get baseUrl(): string {
    return environment.goApiUrl;
  }

  private get headers(): HttpHeaders {
    return new HttpHeaders({
      Authorization: `Bearer ${environment.goApiSecret}`,
    });
  }

  /**
   * GET con log de error y emisión de `null` (no propaga error).
   */
  get<T = any>(url: string): Observable<T | null> {
    return this.http
      .get<T>(url, {
        headers: this.headers,
        params: { saltarJWT: 'true' },
      })
      .pipe(
        catchError((err) => {
          console.error('Analytics API error:', url, err);
          return of(null);
        }),
      );
  }

  /**
   * Para entradas de `forkJoin`: asegura que no se rompa el grupo si algo falla después del primer catch.
   */
  getSafe(url: string): Observable<any> {
    return this.get(url).pipe(catchError(() => of(null)));
  }

  private sucursalQueryParam(sucursal: any): string | null {
    if (sucursal == null || sucursal === '' || sucursal === 'todas') {
      return null;
    }
    if (Array.isArray(sucursal)) {
      const parts = sucursal.map((s: any) => String(s).trim()).filter(Boolean);
      return parts.length ? parts.join(',') : null;
    }
    const s = String(sucursal).trim();
    return s || null;
  }

  params(filtros: any, extras: string[] = []): string {
    const empresa = this.idEmpresa;
    const anio = filtros?.anio ?? new Date().getFullYear();
    let p = `empresa=${empresa}&anio=${anio}`;
    if (filtros?.mes) p += `&mes=${filtros.mes}`;
    const sv = this.sucursalQueryParam(filtros?.sucursal);
    if (sv) p += `&sucursal=${sv}`;
    extras.forEach((e) => {
      if (e) p += `&${e}`;
    });
    return p;
  }

  paramsAnual(filtros: any, extras: string[] = []): string {
    const empresa = this.idEmpresa;
    const anio = filtros?.anio ?? new Date().getFullYear();
    let p = `empresa=${empresa}&anio=${anio}`;
    const sv = this.sucursalQueryParam(filtros?.sucursal);
    if (sv) p += `&sucursal=${sv}`;
    extras.forEach((e) => {
      if (e) p += `&${e}`;
    });
    return p;
  }
}
