import { Injectable } from '@angular/core';
import { Subject } from 'rxjs';
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
  private readonly permissionsUpdated = new Subject<void>();

  constructor(
    private httpService: HttpService
  ) {}

  onPermissionsUpdated() {
    return this.permissionsUpdated.asObservable();
  }

  formatRoleName(name: string): string {
    return name.replace(/_/g, ' ').replace(/\b\w/g, (char: string) => char.toUpperCase());
  }

  getDisplayRoleName(): string {
    const storedPermissions = localStorage.getItem('SP_user_permissions');
    if (storedPermissions) {
      try {
        const role = JSON.parse(storedPermissions).role;
        if (typeof role === 'string' && role && role !== 'Sin rol asignado') {
          return this.formatRoleName(role);
        }
      } catch {
        /* ignore */
      }
    }

    const usuario = this.getAuthUser();
    const roles = usuario?.roles;
    if (Array.isArray(roles) && roles.length > 0 && roles[0]?.name) {
      return this.formatRoleName(roles[0].name);
    }

    if (usuario?.tipo) {
      return usuario.tipo;
    }

    return 'Usuario';
  }

  private getAuthUser(): any {
    const user = localStorage.getItem('SP_auth_user');
    return user ? JSON.parse(user) : null;
  }

  private toPermissionNameArray(value: unknown): string[] {
    if (Array.isArray(value)) {
      return value.filter((x): x is string => typeof x === 'string');
    }
    if (value && typeof value === 'object') {
      return Object.values(value as Record<string, unknown>).filter(
        (x): x is string => typeof x === 'string'
      );
    }
    return [];
  }

  loadUserPermissions(userId: number): void {
    this.httpService.getAll(`roles-permissions/user/${userId}`).subscribe({
      next: (response: any) => {
        if (response?.ok === false) {
          return;
        }
        const data = response?.data ?? response;
        if (!data || typeof data !== 'object') {
          return;
        }

        const permissions: UserPermissions = {
          rolePermissions: this.toPermissionNameArray(data.rolePermissions),
          directPermissions: this.toPermissionNameArray(data.directPermissions),
          revokedPermissions: this.toPermissionNameArray(data.revokedPermissions),
          effectivePermissions: this.toPermissionNameArray(data.effectivePermissions),
          role: typeof data.role === 'string' ? data.role : '',
        };

        localStorage.setItem('SP_user_permissions', JSON.stringify(permissions));
        this.currentUserPermissions = permissions;
        this.permissionsUpdated.next();
      },
      error: () => {
        /* Mantiene SP_user_permissions previo si el request falla */
      },
    });
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
        try {
          this.currentUserPermissions = JSON.parse(storedPermissions);
        } catch {
          /* ignore */
        }
      }
    }

    const effectivePermissions = this.toPermissionNameArray(
      this.currentUserPermissions.effectivePermissions
    );
    const revokedPermissions = this.toPermissionNameArray(
      this.currentUserPermissions.revokedPermissions
    );

    if (revokedPermissions.includes(permission)) {
      return false;
    }

    const role = this.currentUserPermissions.role;
    if (role === 'admin' || role === 'super_admin') {
      return true;
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
    return this.isAdmin();
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

