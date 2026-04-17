import { Injectable, Injector } from '@angular/core';
import { HttpInterceptor, HttpRequest, HttpHandler, HttpEvent, HttpResponse } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { tap } from 'rxjs/operators';
import { HttpCacheService } from './http-cache.service';
import { SharedDataService } from './shared-data.service';

/**
 * Caché HTTP (solo GET): por defecto NO se cachea nada.
 * Sistema transaccional (ventas, compras, inventario, planilla, etc.): los datos deben verse al instante tras crear/editar.
 *
 * Solo se permiten en caché respuestas explícitamente seguras (p. ej. constants / modules).
 * Cualquier POST, PUT, PATCH o DELETE vacía el HttpCacheService e invalida listas operativas en SharedDataService.
 */
@Injectable()
export class CacheInterceptor implements HttpInterceptor {
  private cleanupInterval?: any;

  constructor(
    private cacheService: HttpCacheService,
    private injector: Injector
  ) {
    this.cleanupInterval = setInterval(() => {
      this.cacheService.cleanExpired();
    }, 5 * 60 * 1000);
  }

  intercept(req: HttpRequest<any>, next: HttpHandler): Observable<HttpEvent<any>> {
    if (req.method !== 'GET') {
      this.invalidateAfterMutation();
      return next.handle(req);
    }

    const saltarJWT = req.params.get('saltarJWT');
    if (saltarJWT) {
      return next.handle(req);
    }

    const params = this.extractParams(req);
    const cachedResponse = this.cacheService.get(req.url, params);

    if (cachedResponse) {
      return of(cachedResponse);
    }

    return next.handle(req).pipe(
      tap(event => {
        if (event instanceof HttpResponse && event.status === 200 && this.shouldCache(req.url)) {
          this.cacheService.set(req.url, event, undefined, params);
        }
      })
    );
  }

  private extractParams(req: HttpRequest<any>): any {
    const params: any = {};
    if (req.params && req.params.keys().length > 0) {
      req.params.keys().forEach(key => {
        params[key] = req.params.get(key);
      });
    }
    return params;
  }

  /**
   * Opt-in: solo URLs claramente estáticas o de configuración de app.
   * Todo lo demás (ventas, compras, listas, inventario, citas, etc.) va siempre a red.
   */
  private shouldCache(url: string): boolean {
    const lower = url.toLowerCase();

    if (lower.includes('/exportar') || lower.includes('/download') || lower.includes('/export')) {
      return false;
    }
    if (lower.includes('/login') || lower.includes('/logout') || lower.includes('/auth')) {
      return false;
    }
    if (lower.includes('/notificaciones') || lower.includes('/chat') || lower.includes('/ping')) {
      return false;
    }
    if (lower.includes('fe-cr/cabys') || lower.includes('/fe/cabys')) {
      return false;
    }
    if (lower.includes('fe-cr/contribuyente')) {
      return false;
    }

    if (lower.includes('/constants') || lower.includes('/modules')) {
      return true;
    }

    return false;
  }

  /**
   * Tras cualquier mutación: sin caché HTTP y listas compartidas marcadas para recargar.
   */
  private invalidateAfterMutation(): void {
    this.cacheService.clear();
    try {
      const shared = this.injector.get(SharedDataService);
      shared.invalidateOperationalListCaches();
    } catch {
      // arranque muy temprano
    }
  }
}
