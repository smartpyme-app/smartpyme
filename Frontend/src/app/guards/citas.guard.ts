import { Injectable } from '@angular/core';
import { Router, ActivatedRouteSnapshot, RouterStateSnapshot } from '@angular/router';
import { Observable } from 'rxjs';
import { ApiService } from '@services/api.service';

@Injectable()
export class CitasGuard  {

    constructor(private router: Router, private apiService: ApiService){}

    canActivate(
    next: ActivatedRouteSnapshot,
    state: RouterStateSnapshot): Observable<boolean> | Promise<boolean> | boolean {

        // Verificar que el usuario esté autenticado
        if (!this.apiService.autenticated()) {
            this.router.navigate(['/login']);
            return false;
        }

        const usuario = this.apiService.auth_user();

        // Si es super admin, tiene acceso a todo
        if (this.apiService.verifyRoleAdmin()) {
            return true;
        }

        // Verificar si tiene permisos específicos para citas
        if (this.apiService.hasPermission('citas.acceder') ||
            this.apiService.hasPermission('citas.ver') ||
            this.apiService.hasPermission('citas.listar')) {
            return true;
        }

        // Verificar roles que pueden acceder a citas (excepto empresa 2)
        if (usuario.id_empresa !== 2) {
            // Usar el sistema de roles del backend
            const userPermissions = localStorage.getItem('SP_user_permissions');
            if (userPermissions) {
                const { role } = JSON.parse(userPermissions);
                const rolesCitas = [
                    'usuario_citas',        // ROL_USUARIO_CITAS
                    'usuario_ventas',       // ROL_USUARIO_VENTAS
                    'usuario_supervisor',   // ROL_USUARIO_SUPERVISOR
                    'gerente_operaciones',  // ROL_GERENTE_OPERACIONES
                    'admin',                // ROL_ADMIN
                    'usuario_contador'      // ROL_CONTADOR_SUPERIOR
                ];

                if (rolesCitas.includes(role)) {
                    return true;
                }
            }
        }

        this.router.navigate(['/']);
        return false;
  }
}
