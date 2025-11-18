import { Injectable } from '@angular/core';
import { HttpService } from '@services/http.service';

export interface UserPermissions {
  role: string;
  rolePermissions: string[];
  directPermissions: string[];
  revokedPermissions: string[];
  effectivePermissions: string[];
}

@Injectable({
  providedIn: 'root'
})
export class PermissionService {
  private currentUserPermissions: UserPermissions = {
    rolePermissions: [],
    directPermissions: [],
    revokedPermissions: [],
    effectivePermissions: [],
    role: '',
  };

  constructor(
    private httpService: HttpService
  ) {}

  private getAuthUser(): any {
    const user = localStorage.getItem('SP_auth_user');
    return user ? JSON.parse(user) : null;
  }

  loadUserPermissions(userId: number): void {
    this.httpService.getAll(`roles-permissions/user/${userId}`).subscribe(
      (response: any) => {
        if (response.ok) {
          const permissions: UserPermissions = {
            rolePermissions: response.data.rolePermissions || [],
            directPermissions: response.data.directPermissions || [],
            revokedPermissions: response.data.revokedPermissions || [],
            effectivePermissions: response.data.effectivePermissions || [],
            role: response.data.role || '',
          };

          localStorage.setItem(
            'SP_user_permissions',
            JSON.stringify(permissions)
          );
          this.currentUserPermissions = permissions;
        }
      }
    );
  }

  clearPermissions(): void {
    this.currentUserPermissions = {
      rolePermissions: [],
      directPermissions: [],
      revokedPermissions: [],
      effectivePermissions: [],
      role: '',
    };
  }

  hasPermission(permission: string): boolean {
    if (this.currentUserPermissions.effectivePermissions.length === 0) {
      const storedPermissions = localStorage.getItem('SP_user_permissions');
      if (storedPermissions) {
        this.currentUserPermissions = JSON.parse(storedPermissions);
      }
    }

    const effectivePermissions = Array.isArray(
      this.currentUserPermissions.effectivePermissions
    )
      ? this.currentUserPermissions.effectivePermissions
      : Object.values(this.currentUserPermissions.effectivePermissions);

    const revokedPermissions = Array.isArray(
      this.currentUserPermissions.revokedPermissions
    )
      ? this.currentUserPermissions.revokedPermissions
      : Object.values(this.currentUserPermissions.revokedPermissions);

    if (revokedPermissions.includes(permission)) {
      return false;
    }

    return effectivePermissions.includes(permission);
  }

  hasAnyPermission(permissions: string[]): boolean {
    return permissions.some((permission) => this.hasPermission(permission));
  }

  canAccessModule(moduleName: string): boolean {
    return this.hasPermission(`${moduleName}.acceder`);
  }

  // Métodos de verificación de roles basados en tipo de usuario (legacy)
  isAdmin(): boolean {
    let usuario = this.getAuthUser();
    if (!usuario) return false;
    return usuario.tipo == 'Administrador' || 
           usuario.tipo == 'Contador' || 
           usuario.tipo == 'Supervisor' || 
           usuario.tipo == 'Supervisor Limitado';
  }

  isAdminCreate(): boolean {
    let usuario = this.getAuthUser();
    if (!usuario) return false;
    return usuario.tipo == 'Administrador';
  }

  canCreate(): boolean {
    let usuario = this.getAuthUser();
    if (!usuario) return false;
    return usuario.tipo == 'Administrador' ||
           usuario.tipo == 'Supervisor' ||
           usuario.tipo == 'Supervisor Limitado';
  }

  canEdit(): boolean {
    let usuario = this.getAuthUser();
    if (!usuario) return false;
    return usuario.tipo == 'Administrador' || usuario.tipo == 'Supervisor';
  }

  canDelete(): boolean {
    let usuario = this.getAuthUser();
    if (!usuario) return false;
    return usuario.tipo == 'Administrador' || 
           usuario.tipo == 'Supervisor' || 
           usuario.tipo == 'Supervisor Limitado';
  }

  canChange(): boolean {
    let usuario = this.getAuthUser();
    if (!usuario) return false;
    return usuario.tipo == 'Administrador' || usuario.tipo == 'Supervisor';
  }

  // Métodos basados en permisos (nuevo sistema)
  canCreateTest(permission: string): boolean {
    return this.hasPermission(permission);
  }

  canEditTest(permission: string): boolean {
    return this.hasPermission(permission);
  }

  canDeleteTest(permission: string): boolean {
    return this.hasPermission(permission);
  }

  // Métodos de verificación de roles basados en permisos
  verifyRoleAdmin(): boolean {
    let user = localStorage.getItem('SP_user_permissions');
    if (user) {
      let role = JSON.parse(user).role;
      return role === 'super_admin';
    }
    return false;
  }

  isNotSuperAdmin(): boolean {
    let user = localStorage.getItem('SP_user_permissions');
    if (user) {
      let role = JSON.parse(user).role;
      return role !== 'super_admin';
    }
    return true;
  }

  isAdminRole(): boolean {
    let user = localStorage.getItem('SP_user_permissions');
    if (user) {
      let role = JSON.parse(user).role;
      return (
        role === 'usuario_contador' ||
        role === 'admin' ||
        role === 'usuario_supervisor' ||
        role === 'usuario_citas'
      );
    }
    return false;
  }

  verifyVentasRole(): boolean {
    let user = localStorage.getItem('SP_user_permissions');
    if (user) {
      let role = JSON.parse(user).role;
      return role === 'usuario_ventas';
    }
    return false;
  }

  verifyCitasRole(): boolean {
    let user = localStorage.getItem('SP_user_permissions');
    if (user) {
      let role = JSON.parse(user).role;
      return role === 'usuario_citas';
    }
    return false;
  }

  validateRole(roleToCheck: string, equals: boolean = true): boolean {
    const userPermissions = localStorage.getItem('SP_user_permissions');
    if (!userPermissions) {
      return false;
    }

    try {
      const { role } = JSON.parse(userPermissions);
      return equals ? role === roleToCheck : role !== roleToCheck;
    } catch (error) {
      console.error('Error checking role:', error);
      return false;
    }
  }

  isSupervisorLimitado(): boolean {
    const userPermissions = localStorage.getItem('SP_user_permissions');
    if (userPermissions) {
      try {
        const { role } = JSON.parse(userPermissions);
        return role === 'usuario_supervisor_limitado' || role === 'supervisor_limitado';
      } catch (error) {
        console.error('Error checking supervisor limitado role:', error);
      }
    }

    // Fallback al campo tipo para compatibilidad
    let usuario = this.getAuthUser();
    if (usuario && usuario.tipo == 'Supervisor Limitado') return true;
    return false;
  }

  isVentasLimitado(): boolean {
    let usuario = this.getAuthUser();
    if (!usuario) return false;
    return usuario.tipo == 'Ventas Limitado';
  }

  isVentas(): boolean {
    let usuario = this.getAuthUser();
    if (!usuario) return false;
    return usuario.tipo == 'Ventas' || usuario.tipo == 'Ventas Limitado';
  }
}

