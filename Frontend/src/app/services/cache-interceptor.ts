import { Injectable } from '@angular/core';
import { HttpInterceptor, HttpRequest, HttpHandler, HttpEvent, HttpResponse } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { tap } from 'rxjs/operators';
import { HttpCacheService } from './http-cache.service';

@Injectable()
export class CacheInterceptor implements HttpInterceptor {
  private cleanupInterval?: any;

  constructor(
    private cacheService: HttpCacheService
  ) {
    // Limpiar cache expirado cada 5 minutos
    // Nota: Los interceptors son singletons que viven durante toda la vida de la aplicación,
    // por lo que este intervalo es intencional y necesario para mantener el cache limpio.
    this.cleanupInterval = setInterval(() => {
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

    const saltarJWT = req.params.get('saltarJWT');
    if (saltarJWT) {
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
      this.cacheService.invalidatePattern('/venta');
      this.cacheService.invalidatePattern('/clientes');
      // Invalidar cache del item específico si se está editando
      const match = url.match(/\/venta\/(\d+)/);
      if (match) {
        this.cacheService.delete(`/venta/${match[1]}`);
      }
    }

    if (url.includes('/compra') || url.includes('/compras')) {
      this.cacheService.invalidatePattern('/compras');
      this.cacheService.invalidatePattern('/compra');
      this.cacheService.invalidatePattern('/proveedores');
      // Invalidar cache del item específico si se está editando
      const match = url.match(/\/compra\/(\d+)/);
      if (match) {
        this.cacheService.delete(`/compra/${match[1]}`);
      }
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

    if (url.includes('/gasto') || url.includes('/gastos')) {
      this.cacheService.invalidatePattern('/gastos');
      this.cacheService.invalidatePattern('/gasto');
      this.cacheService.invalidatePattern('/proveedores');
      // Invalidar cache del item específico si se está editando
      const match = url.match(/\/gasto\/(\d+)/);
      if (match) {
        this.cacheService.delete(`/gasto/${match[1]}`);
      }
    }

    if (url.includes('/bodega') || url.includes('/bodegas')) {
      this.cacheService.invalidatePattern('/bodegas');
    }

    if (url.includes('/planilla') || url.includes('/planillas')) {
      this.cacheService.invalidatePattern('/planillas');
      this.cacheService.invalidatePattern('/planilla');
      // Invalidar cache del item específico si se está editando
      const match = url.match(/\/planilla\/(\d+)/);
      if (match) {
        this.cacheService.delete(`/planilla/${match[1]}`);
      }
    }

    if (url.includes('/empleado') || url.includes('/empleados')) {
      this.cacheService.invalidatePattern('/empleados');
      this.cacheService.invalidatePattern('/empleado');
      this.cacheService.invalidatePattern('/planillas');
      // Invalidar cache del item específico si se está editando
      const match = url.match(/\/empleado\/(\d+)/);
      if (match) {
        this.cacheService.delete(`/empleado/${match[1]}`);
      }
    }

    if (url.includes('/producto') || url.includes('/productos')) {
      this.cacheService.invalidatePattern('/productos');
      this.cacheService.invalidatePattern('/producto');
      // Invalidar cache del item específico si se está editando
      const match = url.match(/\/producto\/(\d+)/);
      if (match) {
        this.cacheService.delete(`/producto/${match[1]}`);
      }
    }

    if (url.includes('/servicio') || url.includes('/servicios')) {
      this.cacheService.invalidatePattern('/servicios');
      this.cacheService.invalidatePattern('/servicio');
      // Invalidar cache del item específico si se está editando
      const match = url.match(/\/servicio\/(\d+)/);
      if (match) {
        this.cacheService.delete(`/servicio/${match[1]}`);
      }
    }

    if (url.includes('/partida') || url.includes('/partidas')) {
      this.cacheService.invalidatePattern('/partidas');
      this.cacheService.invalidatePattern('/partida');
      // Invalidar cache del item específico si se está editando
      const match = url.match(/\/partida\/(\d+)/);
      if (match) {
        this.cacheService.delete(`/partida/${match[1]}`);
      }
    }

    if (url.includes('/orden-de-compra') || url.includes('/ordenes-de-compras')) {
      this.cacheService.invalidatePattern('/ordenes-de-compras');
      this.cacheService.invalidatePattern('/orden-de-compra');
      this.cacheService.invalidatePattern('/proveedores');
      // Invalidar cache del item específico si se está editando
      const match = url.match(/\/orden-de-compra\/(\d+)/);
      if (match) {
        this.cacheService.delete(`/orden-de-compra/${match[1]}`);
      }
    }
  }
}
