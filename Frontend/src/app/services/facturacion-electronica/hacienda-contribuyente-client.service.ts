import { Injectable } from '@angular/core';
import { HttpBackend, HttpClient, HttpErrorResponse, HttpParams } from '@angular/common/http';
import { Observable, throwError } from 'rxjs';
import { catchError, map } from 'rxjs/operators';
import { environment } from 'src/environments/environment';

const HACIENDA_HTML_ERROR =
  'El Ministerio de Hacienda no devolvió datos en formato esperado (respuesta bloqueada o página de error). Espere unos minutos, evite muchas búsquedas seguidas o pruebe desde otra red. Si persiste, consulte facturati@hacienda.go.cr o seguridaddigital@hacienda.go.cr.';

export const HACIENDA_CONTRIBUYENTE_NO_ENCONTRADO =
  'El registro no fue encontrado. Verifique la identificación e intente de nuevo.';

/**
 * Consulta pública de contribuyente CR (`GET /fe/ae`).
 * Solo desde el navegador: la API de Hacienda expone CORS y no requiere JWT.
 */
@Injectable({ providedIn: 'root' })
export class HaciendaContribuyenteClientService {
  private readonly http: HttpClient;

  private readonly baseUrl: string;

  constructor(httpBackend: HttpBackend) {
    this.http = new HttpClient(httpBackend);
    const env = environment as { haciendaPublicApiUrl?: string };
    this.baseUrl = (env.haciendaPublicApiUrl ?? 'https://api.hacienda.go.cr').replace(/\/$/, '');
  }

  getContribuyente(identificacion: string): Observable<unknown> {
    const id = identificacion.replace(/\D/g, '');
    if (id.length < 9 || id.length > 12) {
      return throwError(
        () =>
          new HttpErrorResponse({
            status: 422,
            error: { error: 'identificacion debe tener entre 9 y 12 dígitos (sin guiones).' },
          }),
      );
    }

    const params = new HttpParams().set('identificacion', id);

    return this.http
      .get(`${this.baseUrl}/fe/ae`, { params, responseType: 'text', observe: 'response' })
      .pipe(
        map((response) =>
          this.parseBody(response.status, response.body ?? '', response.headers.get('Content-Type')),
        ),
        catchError((err: HttpErrorResponse) => this.handleHttpError(err)),
      );
  }

  private parseBody(status: number, bodyRaw: string, contentType: string | null): unknown {
    if (this.responseLooksLikeHtml(bodyRaw, contentType ?? '')) {
      throw new HttpErrorResponse({
        status: 502,
        error: { error: HACIENDA_HTML_ERROR, code: 'hacienda_html_response' },
      });
    }

    if (status === 404 || status === 400) {
      throw this.contribuyenteNoEncontradoError();
    }

    if (bodyRaw === '') {
      return {};
    }

    try {
      const parsed = JSON.parse(bodyRaw) as unknown;
      if (parsed === null || typeof parsed !== 'object') {
        throw new Error('invalid json');
      }

      if (this.esRespuestaContribuyenteNoEncontrado(parsed)) {
        throw this.contribuyenteNoEncontradoError();
      }

      return parsed;
    } catch {
      throw new HttpErrorResponse({
        status: 502,
        error: { error: 'Respuesta de Hacienda no es JSON válido.', code: 'hacienda_invalid_json' },
      });
    }
  }

  private handleHttpError(err: HttpErrorResponse): Observable<unknown> {
    if (this.esRespuestaContribuyenteNoEncontrado(err.error)) {
      return throwError(() => this.contribuyenteNoEncontradoError());
    }

    if (err.status === 404 || err.status === 400) {
      return throwError(() => this.contribuyenteNoEncontradoError());
    }

    // Hacienda a veces responde 404 sin cabeceras CORS: el navegador expone status 0.
    if (err.status === 0 && (err.url ?? '').includes('/fe/ae')) {
      return throwError(() => this.contribuyenteNoEncontradoError());
    }

    const body = typeof err.error === 'string' ? err.error : '';
    if (this.responseLooksLikeHtml(body, err.headers?.get('Content-Type') ?? '')) {
      return throwError(
        () =>
          new HttpErrorResponse({
            status: 502,
            error: { error: HACIENDA_HTML_ERROR, code: 'hacienda_html_response' },
          }),
      );
    }

    return throwError(() => err);
  }

  private esRespuestaContribuyenteNoEncontrado(body: unknown): boolean {
    if (body === null || typeof body !== 'object') {
      return false;
    }
    const o = body as Record<string, unknown>;
    const code = o['code'];

    return code === 404 || code === '404';
  }

  private contribuyenteNoEncontradoError(): HttpErrorResponse {
    return new HttpErrorResponse({
      status: 404,
      error: { error: HACIENDA_CONTRIBUYENTE_NO_ENCONTRADO, code: 'hacienda_contribuyente_not_found' },
    });
  }

  private responseLooksLikeHtml(body: string, contentType: string): boolean {
    if (body === '') {
      return false;
    }
    if (contentType.toLowerCase().includes('text/html')) {
      return true;
    }
    const trim = body.trimStart();

    return trim.startsWith('<!DOCTYPE') || trim.toLowerCase().startsWith('<html');
  }
}
