import { Injectable } from '@angular/core';
import { HttpInterceptor, HttpRequest, HttpHandler, HttpEvent, HttpResponse } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { tap } from 'rxjs/operators';
import { HttpCacheService } from './http-cache.service';

@Injectable()
export class CacheInterceptor implements HttpInterceptor {

  constructor(
    private cacheService: HttpCacheService
  ) {
    // Limpiar cache expirado cada 5 minutos
    setInterval(() => {
      this.cacheService.cleanExpired();
    }, 5 * 60 * 1000);
  }

  intercept(req: HttpRequest<any>, next: HttpHandler): Observable<HttpEvent<any>> {
    // Solo cachear peticiones GET
    if (req.method !== 'GET') {
      // Invalidar cache relacionado cuando se hacen cambios
      this.invalidateRelatedCache(req);
      return next.handle(req);
    }

    // Extraer parámetros de la petición
    const params = this.extractParams(req);

    // Verificar si hay una respuesta en cache
    const cachedResponse = this.cacheService.get(req.url, params);

    if (cachedResponse) {
      // Retornar respuesta desde cache
      return of(cachedResponse);
    }

    // Si no hay cache, hacer la petición y guardar la respuesta
    return next.handle(req).pipe(
      tap(event => {
        if (event instanceof HttpResponse) {
          // Solo cachear respuestas exitosas
          if (event.status === 200) {
            // Determinar si este endpoint debe ser cacheado
            if (this.shouldCache(req.url)) {
              this.cacheService.set(req.url, event, undefined, params);
            }
          }
        }
      })
    );
  }

  /**
   * Extrae los parámetros de la petición HTTP
   */
  private extractParams(req: HttpRequest<any>): any {
    const params: any = {};
    
    // Si la petición tiene HttpParams, extraerlos
    if (req.params && req.params.keys().length > 0) {
      req.params.keys().forEach(key => {
        params[key] = req.params.get(key);
      });
    }
    
    return params;
  }

  /**
   * Determina si una URL debe ser cacheada
   */
  private shouldCache(url: string): boolean {
    // No cachear endpoints de exportación o descarga
    if (url.includes('/exportar') || url.includes('/download') || url.includes('/export')) {
      return false;
    }

    // No cachear endpoints de autenticación
    if (url.includes('/login') || url.includes('/logout') || url.includes('/auth')) {
      return false;
    }

    // No cachear endpoints de notificaciones en tiempo real
    if (url.includes('/notificaciones') || url.includes('/chat') || url.includes('/ping')) {
      return false;
    }

    // Cachear endpoints de listas y datos de referencia
    if (url.includes('/list') || url.includes('/constants') || url.includes('/modules')) {
      return true;
    }

    // Cachear otros endpoints GET por defecto
    return true;
  }

  /**
   * Invalida el cache relacionado cuando se hacen cambios (POST, PUT, DELETE)
   */
  private invalidateRelatedCache(req: HttpRequest<any>): void {
    const url = req.url;

    // Invalidar cache de listas relacionadas cuando se modifican datos
    if (url.includes('/venta') || url.includes('/ventas')) {
      this.cacheService.invalidatePattern('/ventas');
      this.cacheService.invalidatePattern('/clientes');
    }

    if (url.includes('/compra') || url.includes('/compras')) {
      this.cacheService.invalidatePattern('/compras');
      this.cacheService.invalidatePattern('/proveedores');
    }

    if (url.includes('/sucursal') || url.includes('/sucursales')) {
      this.cacheService.invalidatePattern('/sucursales');
    }

    if (url.includes('/usuario') || url.includes('/usuarios')) {
      this.cacheService.invalidatePattern('/usuarios');
    }

    if (url.includes('/documento') || url.includes('/documentos')) {
      this.cacheService.invalidatePattern('/documentos');
    }

    if (url.includes('/forma-de-pago') || url.includes('/formas-de-pago')) {
      this.cacheService.invalidatePattern('/formas-de-pago');
    }

    if (url.includes('/proyecto') || url.includes('/proyectos')) {
      this.cacheService.invalidatePattern('/proyectos');
    }

    if (url.includes('/categoria') || url.includes('/categorias')) {
      this.cacheService.invalidatePattern('/categorias');
    }

    if (url.includes('/marca') || url.includes('/marcas')) {
      this.cacheService.invalidatePattern('/marcas');
    }

    if (url.includes('/canal') || url.includes('/canales')) {
      this.cacheService.invalidatePattern('/canales');
    }
  }
}
