import { Injectable } from '@angular/core';
import { CanActivate, ActivatedRouteSnapshot, RouterStateSnapshot, Router } from '@angular/router';
import { ApiService } from '@services/api.service';

@Injectable({
  providedIn: 'root'
})
export class SupervisorLimitadoGuard implements CanActivate {

  constructor(
    private apiService: ApiService,
    private router: Router
  ) {}

  canActivate(
    route: ActivatedRouteSnapshot,
    state: RouterStateSnapshot): boolean {

    if (!this.apiService.isSupervisorLimitado()) {
      return true;
    }

    const bloqueoModuloCompleto = route.data['bloquearSupervisorLimitadoModuloCompleto'] === true;

    if (bloqueoModuloCompleto) {
      this.router.navigate(['/']);
      return false;
    }

    return true;
  }
}
