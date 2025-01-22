import { Injectable } from '@angular/core';
import { Router, CanActivate, ActivatedRouteSnapshot, RouterStateSnapshot } from '@angular/router';
import { Observable } from 'rxjs';
import { ApiService } from '@services/api.service';
import { AlertService } from '@services/alert.service';

// Tipos para mejorar la mantenibilidad
type GuardType = 'admin' | 'citas' | 'superAdmin';

interface UserPermissions {
    role: string;
    rolePermissions: string[];
    directPermissions: string[];
    revokedPermissions: string[];
    effectivePermissions: string[];
}

@Injectable({
    providedIn: 'root'
})
export class RoleGuard implements CanActivate {
    constructor(
        private router: Router,
        private apiService: ApiService,
        private alertService: AlertService
    ) {}

    private getUserPermissions(): UserPermissions | null {
        try {
            const permissions = localStorage.getItem('SP_user_permissions');
            if (!permissions) return null;
            return JSON.parse(permissions);
        } catch (error) {
            console.error('Error parsing user permissions:', error);
            return null;
        }
    }

    private checkAdminAccess(userRole: string): boolean {
        // Roles administrativos según constants.php
        return [
            'super_admin',      // ROL_SUPER_ADMIN
            'usuario_contador',   // ROL_CONTADOR_SUPERIOR
            'admin',            // ROL_ADMIN
            'auxiliar_contable' // ROL_CONTADOR_AUXILIAR
        ].includes(userRole);
    }

    private checkCitasAccess(userRole: string, empresaId: number): boolean {
        // No permitir acceso si es empresa 2
        if (empresaId === 2) return false;

        // Roles que pueden acceder a citas
        return [
            'usuario_citas',        // ROL_USUARIO_CITAS
            'usuario_ventas',       // ROL_USUARIO_VENTAS
            'usuario_supervisor',       // ROL_usuario_supervisor
            'gerente_operaciones',  // ROL_GERENTE_OPERACIONES
            'super_admin',          // ROL_SUPER_ADMIN
            'admin',                // ROL_ADMIN
            'usuario_contador'        // ROL_CONTADOR_SUPERIOR
        ].includes(userRole);
    }

    private checkSuperAdminAccess(userRole: string, empresaId: number): boolean {
        // Solo super_admin de la empresa 2 tiene acceso
        return userRole === 'super_admin';
    }

    private handleAccessDenied(): boolean {
        this.alertService.error('No tienes acceso a esta sección');
        this.router.navigate(['/']);
        return false;
    }

    canActivate(
        route: ActivatedRouteSnapshot,
        state: RouterStateSnapshot
    ): Observable<boolean> | Promise<boolean> | boolean {
        // Verificar autenticación
        if (!this.apiService.autenticated()) {
            this.router.navigate(['login']);
            return false;
        }

        const user = this.apiService.auth_user();
        const userPermissions = this.getUserPermissions();
        
        // Si no hay guardType, solo verificar autenticación
        const guardType = route.data['guardType'] as GuardType;
        if (!guardType) {
            return true;
        }

        // Verificar que existan los datos necesarios
        if (!userPermissions?.role || !user) {
            return this.handleAccessDenied();
        }

        // Verificar acceso según el tipo de guard
        let hasAccess = false;
        switch (guardType) {
            case 'admin':
                hasAccess = this.checkAdminAccess(userPermissions.role);
                break;

            case 'citas':
                hasAccess = this.checkCitasAccess(userPermissions.role, user.id_empresa);
                break;

            case 'superAdmin':
                hasAccess = this.checkSuperAdminAccess(userPermissions.role, user.id_empresa);
                break;

            default:
                console.warn(`Guard type "${guardType}" not implemented`);
                hasAccess = false;
        }

        return hasAccess ? true : this.handleAccessDenied();
    }
}