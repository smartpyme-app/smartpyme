import { Injectable } from '@angular/core';
import {
  CanActivate,
  ActivatedRouteSnapshot,
  RouterStateSnapshot,
  Router,
} from '@angular/router';
import { Observable } from 'rxjs';
import { map } from 'rxjs/operators';
import { ApiService } from '@services/api.service';
import { FuncionalidadesService } from '@services/functionalities.service';

const SLUG_TRANSFORMACION_PRODUCTOS = 'transformacion-productos';

@Injectable({
  providedIn: 'root',
})
export class TransformacionProductosGuard implements CanActivate {
  constructor(
    private router: Router,
    private apiService: ApiService,
    private funcionalidadesService: FuncionalidadesService
  ) {}

  canActivate(
    _route: ActivatedRouteSnapshot,
    _state: RouterStateSnapshot
  ): Observable<boolean> {
    return this.funcionalidadesService.verificarAcceso(SLUG_TRANSFORMACION_PRODUCTOS).pipe(
      map((tieneFuncionalidad) => {
        const activo = tieneFuncionalidad && this.apiService.isTransformacionProductosConfigActivo();
        if (!activo) {
          void this.router.navigate(['/productos']);
        }
        return activo;
      })
    );
  }
}
