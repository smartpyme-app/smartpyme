import { Injectable } from '@angular/core';
import {
  CanActivate,
  ActivatedRouteSnapshot,
  RouterStateSnapshot,
  Router,
} from '@angular/router';
import { ApiService } from '@services/api.service';

/** Rutas reservadas al rol Administrador (p. ej. operaciones de inventario). */
@Injectable({
  providedIn: 'root',
})
export class SoloAdministradorGuard implements CanActivate {
  constructor(
    private router: Router,
    private apiService: ApiService
  ) {}

  canActivate(
    _route: ActivatedRouteSnapshot,
    _state: RouterStateSnapshot
  ): boolean {
    if (this.apiService.esSoloAdministrador()) {
      return true;
    }
    void this.router.navigate(['/404']);
    return false;
  }
}
