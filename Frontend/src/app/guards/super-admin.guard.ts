import { Injectable } from '@angular/core';
import { Router, ActivatedRouteSnapshot, RouterStateSnapshot } from '@angular/router';
import { Observable } from 'rxjs';
import { ApiService } from '@services/api.service';

@Injectable()
export class SuperAdminGuard  {

    constructor(private router: Router, private apiService: ApiService){}

    canActivate(
    next: ActivatedRouteSnapshot,
    state: RouterStateSnapshot): Observable<boolean> | Promise<boolean> | boolean {
        
        // Verificar que el usuario esté autenticado
        if (!this.apiService.autenticated()) {
            this.router.navigate(['/login']);
            return false;
        }

        // Si es super admin, tiene acceso a todo
        if (this.apiService.verifyRoleAdmin()) {
            return true;
        }

        // Verificar si es administrador de empresa 2 o 13 (empresas especiales)
        const user = this.apiService.auth_user();
        if (user && user.tipo === 'Administrador' && (user.id_empresa === 2 || user.id_empresa === 13)) {
            return true;
        }
        
        this.router.navigate(['/']);
        return false;
  }
}
