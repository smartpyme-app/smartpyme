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

  /** IDs o listas CSV para query (ventas y otros dashboards). */
  private csvQueryParam(value: any): string | null {
    if (value == null || value === '') return null;
    if (Array.isArray(value)) {
      const parts = value.map((s: any) => String(s).trim()).filter(Boolean);
      return parts.length ? parts.join(',') : null;
    }
    const s = String(value).trim();
    return s || null;
  }

  /**
   * Valor CSV en query: codifica cada segmento pero deja las comas literales.
   * Así el servidor recibe `id_producto=1,2,3` y no `1%2C2%2C3` (algunos parsers no lo tratan como lista).
   */
  private csvQueryParamEncoded(csv: string): string {
    return csv
      .split(',')
      .map((s) => encodeURIComponent(s.trim()))
      .filter(Boolean)
      .join(',');
  }

  private appendFiltrosOpcionalesVentas(filtros: any, p: string): string {
    let out = p;
    const estado = this.csvQueryParam(filtros?.estado);
    if (estado) out += `&estado=${estado}`;
    const canal = this.csvQueryParam(filtros?.canal);
    if (canal) out += `&canal=${canal}`;
    const cliente = this.csvQueryParam(filtros?.cliente);
    if (cliente) out += `&cliente=${cliente}`;
    const vendedor = this.csvQueryParam(filtros?.vendedor);
    if (vendedor) out += `&vendedor=${vendedor}`;
    const categoria = this.csvQueryParam(filtros?.categoria);
    if (categoria) out += `&categoria=${categoria}`;
    const idProducto = this.csvQueryParam(filtros?.idProducto);
    if (idProducto) out += `&id_producto=${this.csvQueryParamEncoded(idProducto)}`;
    return out;
  }

  params(filtros: any, extras: string[] = []): string {
    const empresa = this.idEmpresa;
    const anio = filtros?.anio ?? new Date().getFullYear();
    let p = `empresa=${empresa}&anio=${anio}`;
    if (filtros?.mes) p += `&mes=${filtros.mes}`;
    const sv = this.sucursalQueryParam(filtros?.sucursal);
    if (sv) p += `&sucursal=${sv}`;
    p = this.appendFiltrosOpcionalesVentas(filtros, p);
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
    p = this.appendFiltrosOpcionalesVentas(filtros, p);
    extras.forEach((e) => {
      if (e) p += `&${e}`;
    });
    return p;
  }
}
