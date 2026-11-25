import { Injectable } from '@angular/core';
import { Subject } from 'rxjs';
import { HttpService } from '@services/http.service';
import { esRolGerente } from '@shared/utils/gerente-roles.util';

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

  /** Gerente compras/ventas/operaciones: menú principal y AdminGuard (no es super_admin). */
  isGerenteRole(): boolean {
    const stored = localStorage.getItem('SP_user_permissions');
    let slug: string | null = null;
    if (stored) {
      try {
        const role = this.parseCacheado(stored).role;
        if (typeof role === 'string' && role) {
          slug = role;
        }
      } catch {
        /* ignore */
      }
    }
    const usuario = this.getAuthUser();
    if (esRolGerente(slug, usuario?.tipo)) {
      return true;
    }
    const roles = usuario?.roles;
    if (Array.isArray(roles)) {
      return roles.some((r: { name?: string } | string) => {
        const name = typeof r === 'string' ? r : r?.name;
        return esRolGerente(name, null);
      });
    }
    return false;
  }

  getDisplayRoleName(): string {
    const storedPermissions = localStorage.getItem('SP_user_permissions');
    if (storedPermissions) {
      try {
        const role = this.parseCacheado(storedPermissions).role;
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

  /**
   * El menú lateral llama hasPermission()/isAdmin() desde la plantilla en cada ciclo de
   * detección de cambios: miles de JSON.parse de SP_auth_user (~25 kB) y SP_user_permissions
   * por segundo mientras cargan las peticiones. Se reutiliza el objeto parseado mientras el
   * texto guardado no cambie (si cambia, se vuelve a parsear). Los objetos se usan sólo para leer.
   */
  private readonly cacheParse = new Map<string, any>();

  private parseCacheado(raw: string): any {
    if (this.cacheParse.has(raw)) {
      return this.cacheParse.get(raw);
    }
    const valor = JSON.parse(raw);
    if (this.cacheParse.size >= 4) {
      this.cacheParse.clear();
    }
    this.cacheParse.set(raw, valor);
    return valor;
  }

  private getAuthUser(): any {
    const user = localStorage.getItem('SP_auth_user');
    return user ? this.parseCacheado(user) : null;
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

  applyPermissionsFromApiData(data: unknown): void {
    if (!data || typeof data !== 'object') {
      return;
    }
    const record = data as Record<string, unknown>;
    const permissions: UserPermissions = {
      rolePermissions: this.toPermissionNameArray(record['rolePermissions']),
      directPermissions: this.toPermissionNameArray(record['directPermissions']),
      revokedPermissions: this.toPermissionNameArray(record['revokedPermissions']),
      effectivePermissions: this.toPermissionNameArray(record['effectivePermissions']),
      role: typeof record['role'] === 'string' ? record['role'] : '',
    };
    localStorage.setItem('SP_user_permissions', JSON.stringify(permissions));
    this.currentUserPermissions = permissions;
    this.permissionsUpdated.next();
  }

  loadUserPermissions(userId: number): void {
    this.httpService.getAll(`roles-permissions/user/${userId}`).subscribe({
      next: (response: any) => {
        if (response?.ok === false) {
          return;
        }
        const data = response?.data ?? response;
        this.applyPermissionsFromApiData(data);
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

  private hydrateFromStorageIfNeeded(): void {
    if (this.currentUserPermissions.effectivePermissions.length > 0) {
      return;
    }
    const storedPermissions = localStorage.getItem('SP_user_permissions');
    if (!storedPermissions) {
      return;
    }
    try {
      this.currentUserPermissions = this.parseCacheado(storedPermissions);
    } catch {
      /* ignore */
    }
  }

  /** Roles personalizados (p. ej. almacen): menú operativo según permisos Spatie, no solo slugs fijos. */
  hasEffectivePermissions(): boolean {
    this.hydrateFromStorageIfNeeded();
    return (
      this.toPermissionNameArray(this.currentUserPermissions.effectivePermissions)
        .length > 0
    );
  }

  hasPermission(permission: string): boolean {
    if (this.verifyRoleAdmin()) {
      return true;
    }

    this.hydrateFromStorageIfNeeded();

    const effectivePermissions = this.toPermissionNameArray(
      this.currentUserPermissions.effectivePermissions
    );
    const revokedPermissions = this.toPermissionNameArray(
      this.currentUserPermissions.revokedPermissions
    );

    if (revokedPermissions.includes(permission)) {
      return false;
    }

    // ponytail: finanzas.cuentas y finanzas.bancos son el mismo submódulo en UI (cuentas bancarias)
    const bancosCuentasAlias = permission.startsWith('finanzas.cuentas.')
      ? permission.replace('finanzas.cuentas.', 'finanzas.bancos.')
      : permission.startsWith('finanzas.bancos.')
        ? permission.replace('finanzas.bancos.', 'finanzas.cuentas.')
        : null;
    if (
      bancosCuentasAlias &&
      !revokedPermissions.includes(bancosCuentasAlias) &&
      effectivePermissions.includes(bancosCuentasAlias)
    ) {
      return true;
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
    const usuario = this.getAuthUser();
    if (!usuario) return false;
    if (this.verifyRoleAdmin()) {
      return true;
    }
    if (
      usuario.tipo == 'Administrador' ||
      usuario.tipo == 'Super Administrador' ||
      usuario.tipo == 'Contador' ||
      usuario.tipo == 'Supervisor' ||
      usuario.tipo == 'Supervisor Limitado'
    ) {
      return true;
    }

    if (this.isGerenteRole()) {
      return true;
    }

    const stored = localStorage.getItem('SP_user_permissions');
    if (!stored) {
      return false;
    }
    try {
      const role = this.parseCacheado(stored).role;
      if (typeof role !== 'string' || !role) {
        return false;
      }
      return (
        role === 'admin' ||
        role === 'usuario_contador' ||
        role === 'usuario_supervisor' ||
        role === 'usuario_supervisor_limitado' ||
        role === 'supervisor_limitado'
      );
    } catch {
      return false;
    }
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
    const anular = permission.replace(/\.eliminar$/, '.anular');
    if (anular !== permission && this.hasPermission(anular)) {
      return true;
    }

    return this.hasPermission(permission);
  }

  // Métodos de verificación de roles basados en permisos
  verifyRoleAdmin(): boolean {
    const stored = localStorage.getItem('SP_user_permissions');
    if (stored) {
      try {
        if (this.parseCacheado(stored).role === 'super_admin') {
          return true;
        }
      } catch {
        /* ignore */
      }
    }

    const usuario = this.getAuthUser();
    if (usuario?.tipo === 'Super Administrador') {
      return true;
    }

    const roles = usuario?.roles;
    if (Array.isArray(roles)) {
      return roles.some((r: { name?: string } | string) => {
        const name = typeof r === 'string' ? r : r?.name;
        return name === 'super_admin';
      });
    }

    return false;
  }

  isNotSuperAdmin(): boolean {
    return !this.verifyRoleAdmin();
  }

  isAdminRole(): boolean {
    if (this.isGerenteRole()) {
      return true;
    }
    let user = localStorage.getItem('SP_user_permissions');
    if (user) {
      let role = this.parseCacheado(user).role;
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
      let role = this.parseCacheado(user).role;
      return role === 'usuario_ventas';
    }
    return false;
  }

  verifyCitasRole(): boolean {
    let user = localStorage.getItem('SP_user_permissions');
    if (user) {
      let role = this.parseCacheado(user).role;
      return role === 'usuario_citas';
    }
    return false;
  }

  /** Menú Inteligencia de negocios (/reportes): roles permitidos + permiso ver. */
  puedeVerInteligenciaNegociosMenu(): boolean {
    if (!this.hasPermission('inteligencia_negocios.ver')) {
      return false;
    }
    return (
      this.validateRole('admin', true) ||
      this.validateRole('super_admin', true) ||
      this.validateRole('usuario_contador', true) ||
      this.validateRole('contador_superior', true) ||
      this.isGerenteRole()
    );
  }

  validateRole(roleToCheck: string, equals: boolean = true): boolean {
    const userPermissions = localStorage.getItem('SP_user_permissions');
    if (!userPermissions) {
      return false;
    }

    try {
      const { role } = this.parseCacheado(userPermissions);
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
        const { role } = this.parseCacheado(userPermissions);
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
    const userPermissions = localStorage.getItem('SP_user_permissions');
    if (userPermissions) {
      try {
        const { role } = this.parseCacheado(userPermissions);
        if (role === 'ventas_limitado') return true;
      } catch {
        /* fallback al tipo */
      }
    }

    const usuario = this.getAuthUser();
    if (!usuario) return false;
    return usuario.tipo == 'Ventas Limitado';
  }

  isVentas(): boolean {
    if (this.isVentasLimitado()) return true;
    const usuario = this.getAuthUser();
    if (!usuario) return false;
    return usuario.tipo == 'Ventas';
  }
}

