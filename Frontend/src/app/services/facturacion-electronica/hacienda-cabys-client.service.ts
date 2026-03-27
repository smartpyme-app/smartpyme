import { Injectable } from '@angular/core';
import { HttpBackend, HttpClient, HttpParams } from '@angular/common/http';
import { Observable, catchError } from 'rxjs';
import { environment } from 'src/environments/environment';
import { ApiService } from '@services/api.service';

/**
 * Consultas CABYS directas al dominio público de Hacienda desde el navegador.
 * Evita el bloqueo WAF que a menudo afecta a las IPs de servidores (hosting);
 * la API expone Access-Control-Allow-Origin: *.
 *
 * Si falla (red, CORS inesperado), se usa el proxy Laravel /api/fe-cr/cabys.
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
    const params = new HttpParams().set('q', q.trim()).set('top', String(top));
    return this.http.get<unknown>(`${this.baseUrl}/fe/cabys`, { params }).pipe(
      catchError(() => this.apiService.getAll('fe-cr/cabys', { q: q.trim(), top })),
    );
  }

  getCabysByCodigo(codigo: string): Observable<unknown> {
    const digits = codigo.replace(/\D/g, '');
    const params = new HttpParams().set('codigo', digits);
    return this.http.get<unknown>(`${this.baseUrl}/fe/cabys`, { params }).pipe(
      catchError(() => this.apiService.getAll('fe-cr/cabys', { codigo: digits })),
    );
  }
}
