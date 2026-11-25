import { Injectable } from '@angular/core';
import { ActivatedRouteSnapshot, DetachedRouteHandle, RouteReuseStrategy } from '@angular/router';
import { LayoutComponent } from '../layout/layout.component';

type ReglaReutilizacion = (future: ActivatedRouteSnapshot, curr: ActivatedRouteSnapshot) => boolean;

/**
 * Estrategia de reutilización de rutas de la app.
 *
 * Varias pantallas (facturación, cotización, devoluciones, entradas/salidas, etc.) hacen
 * `router.routeReuseStrategy.shouldReuseRoute = () => false` para que al volver a la misma ruta
 * la pantalla se cree de nuevo. Esa asignación es global y permanente: desde ese momento TODA
 * navegación recreaba también el layout (encabezado, menú, permisos y sus peticiones).
 *
 * Esta estrategia conserva exactamente ese comportamiento para las pantallas (la asignación pasa
 * por el setter de abajo), pero el `LayoutComponent` se reutiliza siempre que la ruta sea la misma.
 */
@Injectable()
export class EstrategiaReutilizacionRutas implements RouteReuseStrategy {
  private reglaPantallas: ReglaReutilizacion | null = null;

  get shouldReuseRoute(): ReglaReutilizacion {
    return (future, curr) => {
      const mismaRuta = future.routeConfig === curr.routeConfig;
      // La raíz (sin routeConfig) y el layout: si no se reutilizan, Angular recrea todo lo que
      // cuelga de ellos, incluido el encabezado y el menú.
      const esRaiz = future.routeConfig === null;
      if (mismaRuta && (esRaiz || future.routeConfig?.component === LayoutComponent)) {
        return true;
      }
      return this.reglaPantallas ? this.reglaPantallas(future, curr) : mismaRuta;
    };
  }

  set shouldReuseRoute(regla: ReglaReutilizacion) {
    this.reglaPantallas = regla;
  }

  shouldDetach(_route: ActivatedRouteSnapshot): boolean {
    return false;
  }

  store(_route: ActivatedRouteSnapshot, _handle: DetachedRouteHandle | null): void {}

  shouldAttach(_route: ActivatedRouteSnapshot): boolean {
    return false;
  }

  retrieve(_route: ActivatedRouteSnapshot): DetachedRouteHandle | null {
    return null;
  }
}
