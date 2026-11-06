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
        const requiredList = route.data['permissions'] as string[] | undefined;
        const requiredPermission = route.data['permission'] as string | undefined;

        if (requiredList?.length) {
            if (requiredList.some((p) => this.apiService.hasPermission(p))) {
                return true;
            }
        } else if (!requiredPermission) {
            return true;
        } else if (this.apiService.hasPermission(requiredPermission)) {
            return true;
        }

        // Si no tiene permiso, mostrar mensaje y redirigir
        this.alertService.error('No tienes permiso para acceder a esta sección');
        this.router.navigate(['/']);
        return false;
    }
}