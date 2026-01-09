import { Component, OnInit, TemplateRef, inject, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { ApiService } from '@services/api.service';
import { AlertService } from '@services/alert.service';
import { ModalManagerService } from '@services/modal-manager.service';
import { BaseModalComponent } from '@shared/base/base-modal.component';
import { ActivatedRoute, Router } from '@angular/router';
import { subscriptionHelper } from '@shared/utils/subscription.helper';

@Component({
    selector: 'app-roles-permisos',
    templateUrl: './roles-permisos.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule],
    changeDetection: ChangeDetectionStrategy.OnPush,
    
})
export class RolesPermisosComponent extends BaseModalComponent implements OnInit {
  public roles: any = {};
  public modules: any[] = [];
  public override loading: boolean = false;
  public filtros = {
    buscador: '',
    paginate: 10,
    orden: '',
    direccion: 'asc',
    page: 1
  };
  selectedRole: any = null;
  searchText: string = '';
  permisosSeleccionados: any[] = [];
  role: any = {
    name: '',
    permissions: [],
    is_global: false
  };
  constructor(
    public apiService: ApiService,
    protected override alertService: AlertService,
    protected override modalManager: ModalManagerService,
    private route: ActivatedRoute,
    private router: Router,
    private cdr: ChangeDetectorRef
  ) {
    super(modalManager, alertService);
  }

  ngOnInit() {
    this.cargarDatos();
    this.cargarModulos();
  }

  cargarModulos() {
    this.apiService.getAll('permissions')
      .pipe(this.untilDestroyed())
      .subscribe(
      response => {
        console.log('Response modules:', response);
        this.modules = (response?.modules || []).map((module: any) => ({
          ...module,
          expanded: false 
        }));
        console.log('Módulos cargados:', this.modules);
        this.cdr.markForCheck();
      },
      error => {
        console.error('Error al cargar módulos:', error);
        this.alertService.error(error);
        this.modules = [];
        this.cdr.markForCheck();
      }
    );
  }

  toggleModule(module: any) {
    module.expanded = !module.expanded;
    this.cdr.markForCheck();
  }

  filterPermissionsBySearch(permissions: any[], searchText: string) {
    if (!searchText) return permissions;
    return permissions.filter(p => 
      p.permission.name.toLowerCase().includes(searchText.toLowerCase())
    );
  }

  isPermissionSelected(permission: any) {
    if (this.selectedRole) {
      return this.selectedRole.permissions.some((p: any) => p.name === permission.name);
    }
    // Para el modal de crear rol, verificamos si está en el array de permissions
    if (typeof permission === 'string') {
      return this.role.permissions.includes(permission);
    }
    return this.role.permissions.includes(permission.name);
  }

  getSimplePermissionName(fullName: string) {
    return fullName.split('.').pop();
  }

  saveRole() {
    this.loading = true;
    this.cdr.markForCheck();
    
    if (!this.role.name) {
      this.alertService.error('El nombre del rol es requerido');
      this.loading = false;
      this.cdr.markForCheck();
      return;
    }

    const roleData = {
      name: this.role.name,
      permissions: this.role.permissions,
      is_global: this.role.is_global && this.canCreateGlobalRoles()
    };

    this.apiService.store('roles-permissions', roleData)
      .pipe(this.untilDestroyed())
      .subscribe(
      response => {
        this.alertService.success('Rol creado correctamente', 'El rol ha sido creado exitosamente.');
        this.closeModal();
        this.cargarDatos();
        this.loading = false;
        this.cdr.markForCheck();
      },
      error => {
        this.alertService.error(error);
        this.loading = false;
        this.cdr.markForCheck();
      }
    );
  }

  cargarDatos() {
    this.loading = true;
    this.cdr.markForCheck();
    this.apiService.getAll('roles-permissions', this.filtros)
      .pipe(this.untilDestroyed())
      .subscribe(
      (response) => {
        this.roles = response;
        
        // Si el backend no envía las propiedades, las calculamos aquí
        if (this.roles.data) {
          this.roles.data.forEach((role: any) => {
            // Determinar si es global basado en id_empresa
            role.is_global = !role.id_empresa;
            
            // Calcular permisos de edición/eliminación
            if (this.apiService.verifyRoleAdmin()) {
              // Super admin puede editar/eliminar cualquier cosa
              role.can_edit = true;
              role.can_delete = !role.is_global; // No eliminar roles globales
            } else if (this.apiService.isAdminRole()) {
              // Admin solo puede editar/eliminar roles de su empresa
              role.can_edit = !role.is_global;
              role.can_delete = !role.is_global;
            } else {
              role.can_edit = false;
              role.can_delete = false;
            }
            
            // Nombre para mostrar
            role.display_name = this.formatRoleName(role.name);
            
            // Contador de permisos
            role.permissions_count = role.permissions?.length || 0;
          });
        }
        
        this.loading = false;
        if (this.modalRef) {
          this.closeModal();
        }
        this.cdr.markForCheck();
      },
      (error) => {
        this.alertService.error(error);
        this.loading = false;
        this.cdr.markForCheck();
      }
    );
  }

  formatRoleName(name: string): string {
    return name.split('_')
              .map((word: string) => word.charAt(0).toUpperCase() + word.slice(1))
              .join(' ');
  }

  public setOrden(columna: string) {
    if (this.filtros.orden === columna) {
      this.filtros.direccion = this.filtros.direccion === 'asc' ? 'desc' : 'asc';
    } else {
      this.filtros.orden = columna;
      this.filtros.direccion = 'asc';
    }
    this.cdr.markForCheck();
    this.filtrarRoles();
  }

  public filtrarRoles() {
    this.cargarDatos();
  }

  setPagination(event: { page: number }) {
    this.filtros.page = event.page;
    this.cdr.markForCheck();
    this.cargarDatos();
  }

  override openModal(template: TemplateRef<any>, role: any) {
    if (role.name) {
      // Modo edición - verificar si puede editar
      if (!role.can_edit) {
        this.alertService.error('No tienes permisos para editar este rol');
        return;
      }
      this.selectedRole = role;
      this.role = {
        name: '',
        permissions: [],
        is_global: false
      };
    } else {
      // Modo creación
      this.selectedRole = null;
      this.role = {
        name: '',
        permissions: [],
        is_global: false
      };
    }
    super.openModal(template, { 
      class: 'modal-lg',
      backdrop: 'static' 
    });
  }

  onPermissionChange(permission: any) {
    if (this.selectedRole) {
      const isChecked = this.selectedRole.permissions.some((p: any) => p.name === permission.name);
      if (isChecked) {
        this.selectedRole.permissions = this.selectedRole.permissions.filter(
          (p: any) => p.name !== permission.name
        );
      } else {
        this.selectedRole.permissions.push(permission);
      }
      this.cdr.markForCheck();
    }
  }

  onPermissionSelect(event: any) {
    const permission = event.target.value;
    const isChecked = event.target.checked;
    this.updatePermissionSelection(permission, isChecked);
  }

  updatePermissionSelection(permissionName: string, isSelected: boolean) {
    if (isSelected) {
      if (!this.role.permissions.includes(permissionName)) {
        this.role.permissions.push(permissionName);
      }
    } else {
      const index = this.role.permissions.indexOf(permissionName);
      if (index > -1) {
        this.role.permissions.splice(index, 1);
      }
    }
    this.cdr.markForCheck();
  }

  // Métodos para seleccionar todos los permisos
  selectAllModuleOnlyPermissions(module: any, event: any) {
    const isChecked = event.target.checked;
    
    // Solo seleccionar permisos del módulo principal (no submódulos)
    module.permissions?.forEach((perm: any) => {
      this.updatePermissionSelection(perm.permission.name, isChecked);
    });
  }

  isModulePermissionsSelected(module: any): boolean {
    const permissions = (module.permissions || []).map((p: any) => p.permission.name);
    return permissions.length > 0 && permissions.every((perm: any) => this.role.permissions.includes(perm));
  }

  selectAllSubmodulePermissions(submodule: any, event: any) {
    const isChecked = event.target.checked;
    
    submodule.permissions?.forEach((perm: any) => {
      this.updatePermissionSelection(perm.permission.name, isChecked);
    });
  }

  isSubmoduleFullySelected(submodule: any): boolean {
    const permissions = (submodule.permissions || []).map((p: any) => p.permission.name);
    return permissions.length > 0 && permissions.every((perm: any) => this.role.permissions.includes(perm));
  }

  savePermissions() {
    const selectedPermissions = this.selectedRole.permissions.map((p: any) => p.name);
    
    this.apiService.store('update-role-permissions', {
      role: this.selectedRole.name,
      permissions: selectedPermissions
    })
      .pipe(this.untilDestroyed())
      .subscribe(
      response => {
        this.alertService.success('Permisos actualizados correctamente', 'Los permisos del rol han sido actualizados.');
        this.closeModal();
        this.cargarDatos();
        this.cdr.markForCheck();
      },
      error => {
        this.alertService.error(error);
        this.cdr.markForCheck();
      }
    );
  }

  canCreateGlobalRoles(): boolean {
    return this.apiService.verifyRoleAdmin(); // Super admin
  }

  canCreateRoles(): boolean {
    return this.apiService.verifyRoleAdmin() || this.apiService.isAdminRole();
  }

  override closeModal() {
    super.closeModal();
    this.selectedRole = null;
    this.role = {
      name: '',
      permissions: [],
      is_global: false
    };
    this.cdr.markForCheck();
  }

  getRoleTypeLabel(role: any): string {
    return role.is_global ? 'Global' : 'Personalizado';
  }

  deleteRole(role: any) {
    if (!role.can_delete) {
      this.alertService.error('No puedes eliminar este rol');
      return;
    }

    if (confirm(`¿Está seguro que desea eliminar el rol "${role.display_name || role.name}"?`)) {
      this.apiService.delete('roles-permissions/', role.id)
        .pipe(this.untilDestroyed())
        .subscribe(
        response => {
          this.alertService.success('Rol eliminado', 'El rol ha sido eliminado correctamente.');
          this.cargarDatos();
          this.cdr.markForCheck();
        },
        error => {
          this.alertService.error(error);
          this.cdr.markForCheck();
        }
      );
    }
  }

  canEditRole(role: any): boolean {
    return role.can_edit || false;
  }

  canDeleteRole(role: any): boolean {
    return role.can_delete || false;
  }
}