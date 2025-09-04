import { Injectable } from '@angular/core';
import { Router, CanActivate, ActivatedRouteSnapshot, RouterStateSnapshot } from '@angular/router';
import { Observable } from 'rxjs';
import { ApiService } from '@services/api.service';

@Injectable()
export class AdminGuard implements CanActivate {

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

    const currentUrl = state.url;

    // Rutas que solo permiten acceso a administradores
    const adminOnlyRoutes = [
      '/suscripcion',
      '/usuarios',
      '/usuario',
      '/sucursales',
      '/mi-cuenta',
      '/roles-permisos',
      '/notificaciones',
      '/whatsapp'
    ];

    // Si la ruta actual es una de las restringidas, verificar permisos de administrador
    if (adminOnlyRoutes.some(route => currentUrl.includes(route))) {
      // Verificar si tiene permisos específicos para la funcionalidad
      if (currentUrl.includes('/usuarios') || currentUrl.includes('/usuario')) {
        if (this.apiService.hasPermission('usuarios.acceder') ||
            this.apiService.hasPermission('usuarios.ver') ||
            this.apiService.hasPermission('usuarios.listar')) {
          return true;
        }
      }
      
      if (currentUrl.includes('/sucursales')) {
        if (this.apiService.hasPermission('sucursales.acceder') ||
            this.apiService.hasPermission('sucursales.ver')) {
          return true;
        }
      }

      if (currentUrl.includes('/roles-permisos')) {
        if (this.apiService.hasPermission('roles.acceder') ||
            this.apiService.hasPermission('permisos.acceder')) {
          return true;
        }
      }

      // Verificar si tiene rol de administrador
      if (this.apiService.isAdminRole() ||
          this.apiService.hasPermission('admin.acceder')) {
        return true;
      } else {
        this.router.navigate(['/']);
        return false;
      }
    }

    // Para otras rutas del admin, permitir acceso a roles administrativos
    if (this.apiService.isAdminRole() || 
        this.apiService.hasPermission('admin.acceder')) {
      return true;
    }

    // Si no tiene permisos, redirigir al inicio
    this.router.navigate(['/']);
    return false;
  }
}
