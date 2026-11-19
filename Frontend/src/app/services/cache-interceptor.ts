import { Injectable, Injector } from '@angular/core';
import { HttpInterceptor, HttpRequest, HttpHandler, HttpEvent, HttpResponse } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { filter, finalize, map, shareReplay, tap } from 'rxjs/operators';
import { HttpCacheService } from './http-cache.service';
import { SharedDataService } from './shared-data.service';
import { AuthService } from './auth.service';

/**
 * Caché HTTP (solo GET) y unificación de peticiones repetidas.
 *
 * 1) Peticiones GET idénticas en vuelo al mismo tiempo (p. ej. header + sidebar pidiendo
 *    `notificaciones`, o dos componentes pidiendo `usuarios/list`) comparten UNA sola
 *    llamada al servidor. No hay riesgo de datos viejos: es la misma respuesta del momento.
 *
 * 2) Caché corto (60 s) SOLO para datos de configuración/catálogos que casi no cambian
 *    (sucursales, bodegas, usuarios, impuestos, formas de pago, ...). Nunca para datos
 *    transaccionales: ventas, documentos (correlativo), inventario, notificaciones, etc.
 *    `constants` y `modules` mantienen su caché largo de antes.
 *
 * - Cualquier POST/PUT/PATCH/DELETE vacía el caché (al empezar y al terminar).
 * - La clave incluye usuario y empresa: cambiar de empresa/usuario nunca reutiliza datos.
 * - Cada consumidor recibe una COPIA del cuerpo: si un componente modifica la lista
 *   (p. ej. `formaPagos.push(...)`), no afecta a los demás ni al caché.
 */
@Injectable()
export class CacheInterceptor implements HttpInterceptor {
  private cleanupInterval?: any;
  private readonly enVuelo = new Map<string, Observable<HttpEvent<any>>>();

  /** TTL del caché corto de catálogos/configuración. */
  private static readonly TTL_CATALOGOS_MS = 60 * 1000;

  /** Rutas (después de /api/) con caché corto. Coincidencia exacta del final de la URL. */
  private static readonly CATALOGOS_CORTOS: RegExp[] = [
    /\/api\/sucursales\/list$/,
    /\/api\/bodegas\/list$/,
    /\/api\/usuarios\/list$/,
    /\/api\/formas-de-pago\/list$/,
    /\/api\/canales\/list$/,
    /\/api\/impuestos$/,
    /\/api\/proyectos\/list$/,
    /\/api\/identificacion-tipos$/,
    /\/api\/banco\/cuentas\/list$/,
    /\/api\/bancos\/list$/,
    /\/api\/moneda\/config$/,
    /\/api\/boxful\/status$/,
    /\/api\/empresa\/get-alert$/,
    /\/api\/restaurante\/pantallas$/,
    /\/api\/ventas-recurrentes\/preferencias$/,
    /\/api\/verificar-accesos$/,
  ];

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
      return next.handle(req).pipe(finalize(() => this.invalidateAfterMutation()));
    }

    const saltarJWT = req.params.get('saltarJWT');
    if (saltarJWT || req.responseType !== 'json' || this.esDescarga(req.url)) {
      return next.handle(req);
    }

    const params = this.extractParams(req);
    const claveUrl = `${this.alcance()}|${req.url}`;

    const cachedResponse = this.cacheService.get(claveUrl, params);
    if (cachedResponse) {
      return of(this.copia(cachedResponse));
    }

    const claveVuelo = `${claveUrl}?${req.params.toString()}`;
    const existente = this.enVuelo.get(claveVuelo);
    if (existente) {
      return existente.pipe(map(ev => this.copiaEvento(ev)));
    }

    const ttl = this.ttlCache(req.url);
    const compartida = next.handle(req).pipe(
      tap(event => {
        if (ttl !== null && event instanceof HttpResponse && event.status === 200) {
          this.cacheService.set(claveUrl, event, ttl, params);
        }
      }),
      finalize(() => this.enVuelo.delete(claveVuelo)),
      // Solo se reenvía la respuesta final a quienes se suman tarde.
      shareReplay({ bufferSize: 1, refCount: true }),
    );
    this.enVuelo.set(claveVuelo, compartida.pipe(filter(ev => ev instanceof HttpResponse)));

    return compartida.pipe(map(ev => this.copiaEvento(ev)));
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

  /** Usuario + empresa activos: separa el caché entre sesiones / "Trabajando en…". */
  private alcance(): string {
    try {
      const u = this.injector.get(AuthService).auth_user();
      return `${u?.id ?? 0}:${u?.id_empresa ?? 0}`;
    } catch {
      return '0:0';
    }
  }

  private esDescarga(url: string): boolean {
    const lower = url.toLowerCase();
    return lower.includes('/exportar') || lower.includes('/download') || lower.includes('/export');
  }

  /**
   * null = no se guarda en caché (solo se unifica si está en vuelo).
   */
  private ttlCache(url: string): number | null {
    const lower = url.toLowerCase();
    if (lower.includes('/login') || lower.includes('/logout') || lower.includes('/auth')) {
      return null;
    }
    if (lower.includes('/constants') || lower.includes('/modules')) {
      return 30 * 60 * 1000;
    }
    const path = lower.split('?')[0];
    if (CacheInterceptor.CATALOGOS_CORTOS.some(rx => rx.test(path))) {
      return CacheInterceptor.TTL_CATALOGOS_MS;
    }
    return null;
  }

  private copiaEvento(ev: HttpEvent<any>): HttpEvent<any> {
    return ev instanceof HttpResponse ? this.copia(ev) : ev;
  }

  private copia(res: HttpResponse<any>): HttpResponse<any> {
    const body = res.body;
    if (body === null || typeof body !== 'object') {
      return res.clone();
    }
    try {
      return res.clone({ body: structuredClone(body) });
    } catch {
      return res.clone({ body: JSON.parse(JSON.stringify(body)) });
    }
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
