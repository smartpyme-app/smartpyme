import { Injectable } from '@angular/core';
import { CanActivate, Router } from '@angular/router';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import { ApiService } from '@services/api.service';

/**
 * Se aplica solo a rutas de gastos. Refresca la empresa en sesión para leer la preferencia al instante
 * (el guard del lazy load de compras no se vuelve a ejecutar al navegar de p. ej. /compras a /gastos).
 */
@Injectable({
  providedIn: 'root'
})
export class GastosSupervisorRestriccionGuard implements CanActivate {

  constructor(
    private apiService: ApiService,
    private router: Router
  ) {}

  canActivate(): boolean | Observable<boolean> {
    if (!this.apiService.isSupervisorLimitado()) {
      return true;
    }

    return this.apiService.refreshEmpresaEnSesion().pipe(
      map(() => {
        if (!this.apiService.empresaRestringeGastosSupervisorLimitado()) {
          return true;
        }
        this.router.navigate(['/']);
        return false;
      })
    );
  }
}
