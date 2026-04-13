import { Injectable } from '@angular/core';
import { HttpBackend, HttpClient, HttpParams } from '@angular/common/http';
import { Observable, catchError } from 'rxjs';
import { environment } from 'src/environments/environment';
import { ApiService } from '@services/api.service';

/**
 * CABYS: mismo JSON que Hacienda (`{ cabys, cantidad, total }`).
 * Primero el proxy Laravel (sesión, caché servidor, JSON estable); si falla, GET directo a api.hacienda.go.cr (HttpBackend, sin interceptors).
 */
@Injectable({ providedIn: 'root' })
export class HaciendaCabysClientService {
  private readonly http: HttpClient;

  private readonly baseUrl: string;

  constructor(
    httpBackend: HttpBackend,
    private readonly apiService: ApiService,
  ) {
    this.http = new HttpClient(httpBackend);
    const env = environment as { haciendaPublicApiUrl?: string };
    this.baseUrl = (env.haciendaPublicApiUrl ?? 'https://api.hacienda.go.cr').replace(/\/$/, '');
  }

  getCabysByQuery(q: string, top: number = 20): Observable<unknown> {
    const qTrim = q.trim();
    const topStr = String(top);
    const filtros = { q: qTrim, top: topStr };
    return this.apiService.getAll('fe-cr/cabys', filtros).pipe(
      catchError(() => {
        const params = new HttpParams().set('q', qTrim).set('top', topStr);
        return this.http.get<unknown>(`${this.baseUrl}/fe/cabys`, { params });
      }),
    );
  }

  getCabysByCodigo(codigo: string): Observable<unknown> {
    const digits = codigo.replace(/\D/g, '');
    const filtros = { codigo: digits };
    return this.apiService.getAll('fe-cr/cabys', filtros).pipe(
      catchError(() => {
        const params = new HttpParams().set('codigo', digits);
        return this.http.get<unknown>(`${this.baseUrl}/fe/cabys`, { params });
      }),
    );
  }
}
