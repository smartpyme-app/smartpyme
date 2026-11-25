import { Injectable, inject } from '@angular/core';
import { NavigationEnd, Router } from '@angular/router';
import { Observable } from 'rxjs';
import { filter, share } from 'rxjs/operators';

/**
 * El layout (encabezado + menú) ahora vive toda la sesión: ya no se recrea al cambiar de módulo.
 * Para que notificaciones, empresa y alertas sigan actualizándose al navegar, este servicio
 * emite en cada navegación pero como máximo una vez por intervalo y por clave.
 */
@Injectable({ providedIn: 'root' })
export class RefrescoLayoutService {
  private readonly router = inject(Router);
  private readonly ultimaCarga = new Map<string, number>();
  private readonly navegaciones$ = this.router.events.pipe(
    filter((e): e is NavigationEnd => e instanceof NavigationEnd),
    share()
  );

  /** Registra que la clave se acaba de cargar (llamar junto a la carga inicial). */
  marcar(clave: string): void {
    this.ultimaCarga.set(clave, Date.now());
  }

  /** Emite al navegar, como máximo una vez cada `intervaloMs` para la clave dada. */
  alNavegar(clave: string, intervaloMs: number): Observable<NavigationEnd> {
    return this.navegaciones$.pipe(
      filter(() => {
        const ahora = Date.now();
        if (ahora - (this.ultimaCarga.get(clave) ?? 0) < intervaloMs) {
          return false;
        }
        this.ultimaCarga.set(clave, ahora);
        return true;
      })
    );
  }
}
