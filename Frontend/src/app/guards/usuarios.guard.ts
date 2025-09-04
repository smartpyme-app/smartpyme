import { Injectable } from '@angular/core';
import { Router, CanActivate, ActivatedRouteSnapshot, RouterStateSnapshot } from '@angular/router';
import { Observable } from 'rxjs';
import { ApiService } from '@services/api.service';

@Injectable({
  providedIn: 'root'
})
export class UsuariosGuard implements CanActivate {

  constructor(
    private router: Router,
    private apiService: ApiService
  ) {}

  canActivate(
    route: ActivatedRouteSnapshot,
    state: RouterStateSnapshot
  ): Observable<boolean> | Promise<boolean> | boolean {
    
    // Verificar que el usuario esté autenticado
    if (!this.apiService.autenticated()) {
      this.router.navigate(['/login']);
      return false;
    }

    // Si es super admin, tiene acceso a todo
    if (this.apiService.verifyRoleAdmin()) {
      return true;
    }

    // Verificar si tiene permisos específicos para usuarios
    if (this.apiService.hasPermission('usuarios.acceder') ||
        this.apiService.hasPermission('usuarios.ver') ||
        this.apiService.hasPermission('usuarios.listar')) {
      return true;
    }

    // Verificar si tiene rol de administrador
    if (this.apiService.isAdminRole()) {
      return true;
    }

    // Si no tiene permisos, redirigir al inicio
    this.router.navigate(['/']);
    return false;
  }
}
