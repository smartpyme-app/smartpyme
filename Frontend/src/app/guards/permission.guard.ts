// src/app/guards/permission.guard.ts
import { Injectable } from '@angular/core';
import { ActivatedRouteSnapshot, RouterStateSnapshot, Router } from '@angular/router';
import { ApiService } from '../services/api.service';
import { AlertService } from '../services/alert.service';

@Injectable({
    providedIn: 'root'
})
export class PermissionGuard  {
    constructor(
        private apiService: ApiService,
        private router: Router,
        private alertService: AlertService
    ) {}

    canActivate(route: ActivatedRouteSnapshot, state: RouterStateSnapshot): boolean {
        // Obtener el permiso requerido de la ruta
        const requiredPermission = route.data['permission'];

        // Si no se requiere permiso específico, permitir acceso
        if (!requiredPermission) {
            return true;
        }

        // Verificar si el usuario tiene el permiso
        if (this.apiService.hasPermission(requiredPermission)) {
            return true;
        }

        // Si no tiene permiso, mostrar mensaje y redirigir
        this.alertService.error('No tienes permiso para acceder a esta sección');
        this.router.navigate(['/']);
        return false;
    }
}