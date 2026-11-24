import { Injectable } from '@angular/core';
import { HttpEvent, HttpHandler, HttpInterceptor, HttpRequest } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../environments/environment';

/**
 * Envía las peticiones a la API por el mismo dominio del frontend (`/api/...`).
 *
 * Llamar a `https://apiunificado.smartpyme.site/api/...` desde `app.smartpyme.site` es una petición
 * entre dominios: el navegador manda antes un OPTIONS (preflight CORS) y espera su respuesta.
 * En los dominios listados en `apiMismoOrigen.hosts`, nginx reenvía `/api/` al mismo backend, así que
 * aquí solo se cambia el prefijo de la URL. Imágenes, plantillas (/img, /docs) y descargas que no pasan
 * por HttpClient siguen usando API_URL tal cual.
 *
 * Va registrado al final de los interceptores para que los demás (caché, JWT, permisos) vean la URL original.
 */
@Injectable()
export class ApiMismoOrigenInterceptor implements HttpInterceptor {
  private readonly prefijo = `${environment.API_URL}/api/`;
  private readonly activo = ApiMismoOrigenInterceptor.hostHabilitado();

  private static hostHabilitado(): boolean {
    const config = (environment as { apiMismoOrigen?: { hosts?: string[] } }).apiMismoOrigen;
    if (!config?.hosts?.length || typeof window === 'undefined') {
      return false;
    }
    return config.hosts.includes(window.location.hostname);
  }

  intercept(req: HttpRequest<unknown>, next: HttpHandler): Observable<HttpEvent<unknown>> {
    if (this.activo && req.url.startsWith(this.prefijo)) {
      return next.handle(req.clone({ url: '/api/' + req.url.slice(this.prefijo.length) }));
    }
    return next.handle(req);
  }
}
