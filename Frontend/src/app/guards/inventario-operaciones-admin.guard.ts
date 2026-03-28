import { Injectable } from '@angular/core';
import {
  CanActivate,
  ActivatedRouteSnapshot,
  RouterStateSnapshot,
  Router,
} from '@angular/router';
import { ApiService } from '@services/api.service';

/** Traslados, ajustes, consignas y entradas/salidas de inventario: solo administrador. */
@Injectable({
  providedIn: 'root',
})
export class InventarioOperacionesAdminGuard implements CanActivate {
  constructor(
    private router: Router,
    private apiService: ApiService
  ) {}

  canActivate(
    _route: ActivatedRouteSnapshot,
    _state: RouterStateSnapshot
  ): boolean {
    if (this.apiService.canAccederOperacionesInventario()) {
      return true;
    }
    void this.router.navigate(['/404']);
    return false;
  }
}
