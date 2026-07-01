import { Injectable } from '@angular/core';
import { Router, CanActivate, ActivatedRouteSnapshot } from '@angular/router';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import { FuncionalidadesService } from '@services/functionalities.service';
import { ApiService } from '@services/api.service';

export const SLUG_DESCARGA_AUTOMATIZADA_DTES = 'descarga-automatizada-dtes';

@Injectable()
export class FuncionalidadGuard implements CanActivate {

  constructor(
    private router: Router,
    private funcionalidadesService: FuncionalidadesService,
    private apiService: ApiService
  ) {}

  canActivate(route: ActivatedRouteSnapshot): Observable<boolean> {
    const slug = route.data['funcionalidadSlug'] as string;
    if (!slug) {
      return this.funcionalidadesService.verificarAcceso(SLUG_DESCARGA_AUTOMATIZADA_DTES).pipe(
        map((acceso) => this.resolveAccess(acceso))
      );
    }

    return this.funcionalidadesService.verificarAcceso(slug).pipe(
      map((acceso) => {
        if (slug === 'fidelizacion-clientes' && acceso) {
          return this.resolveAccess(this.apiService.isFidelizacionActiva());
        }
        return this.resolveAccess(acceso);
      })
    );
  }

  private resolveAccess(acceso: boolean): boolean {
    if (acceso) {
      return true;
    }
    this.router.navigate(['/']);
    return false;
  }
}
