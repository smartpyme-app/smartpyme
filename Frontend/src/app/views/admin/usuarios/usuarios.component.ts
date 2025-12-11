import { Component, OnInit, TemplateRef, ViewChild } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { ModalManagerService } from '@services/modal-manager.service';
import { BaseCrudComponent } from '@shared/base/base-crud.component';
import { CountryISO, PhoneNumberFormat, SearchCountryField } from 'ngx-intl-tel-input';
import { EncryptService } from '@services/encryption/encrypt.service';


@Component({
    selector: 'app-usuarios',
    templateUrl: './usuarios.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule],

})
export class UsuariosComponent extends BaseCrudComponent<any> implements OnInit {

  @ViewChild('mrol', { static: false }) roleModalTemplate!: TemplateRef<any>;

  public sucursales: any = [];
  public bodegas: any = [];
  public usuarios: any = [];
  public roles:any = [];
  public usuario: any = {};
  public paginacion = [];
  public filtrado: boolean = false;
  public usuarios_activos: any = 0;
  public override filtros: any = {};
  public showpassword: boolean = false;
  public showpassword2: boolean = false;
  public authUser: any = {};
  separateDialCode = false;
  SearchCountryField = SearchCountryField;
  CountryISO = CountryISO;
  PhoneNumberFormat = PhoneNumberFormat;
  preferredCountries: CountryISO[] = [
    CountryISO.ElSalvador,
    CountryISO.Guatemala,
    CountryISO.Honduras,
    CountryISO.Nicaragua,
    CountryISO.CostaRica,
    CountryISO.Panama
  ];
  empresas_supervisor_limitado = [13, 396, 397, 398, 427, 428, 429, 432, 438, 488, 569, 657, 538];
  public modules: any[] = [];
  public permissionsLoading: boolean = false;
  public role: any = {
    name: '',
    permissions: [],
    is_global: false
  };

    constructor(
        protected override apiService:ApiService,
        protected override alertService:AlertService,
        protected override modalManager: ModalManagerService,
        public encryptService: EncryptService
    ) {
        super(apiService, alertService, modalManager, {
            endpoint: 'usuario',
            itemsProperty: 'usuarios',
            itemProperty: 'usuario',
            reloadAfterSave: false,
            reloadAfterDelete: false,
            messages: {
                created: 'El usuario fue añadido exitosamente.',
                updated: 'El usuario fue guardado exitosamente.',
                deleted: 'Usuario eliminado exitosamente.',
                createTitle: 'Usuario guardado',
                updateTitle: 'Usuario guardado',
                deleteTitle: 'Usuario eliminado',
                deleteConfirm: '¿Desea eliminar el Registro?'
            },
            beforeSave: (item) => {
                // Transformar teléfono antes de guardar
                if (item.telefono?.e164Number) {
                    item.telefono = item.telefono.e164Number;
                }
                return item;
            },
            afterSave: () => {
                this.loadAll();
            },
            afterDelete: () => {
                this.contarActivos();
            },
            initNewItem: (item) => {
                item.rol_id = 2;
                item.id_sucursal = this.apiService.auth_user().id_sucursal;
                item.id_empresa = this.apiService.auth_user().id_empresa;
                return item;
            }
        });
    }

  ngOnInit() {
    this.filtros.id_sucursal = '';
    this.filtros.estado = '';
    this.filtros.buscador = '';
    this.filtros.orden = 'name';
    this.filtros.direccion = 'desc';
    this.filtros.paginate = 30;

    this.loadAll();

    this.apiService.getAll('sucursales/list')
      .pipe(this.untilDestroyed())
      .subscribe(
      (sucursales) => {
        this.sucursales = sucursales;
      },
      (error) => {
        this.alertService.error(error);
      }
    );

    this.apiService.getAll('bodegas/list')
      .pipe(this.untilDestroyed())
      .subscribe(
      (bodegas) => {
        this.bodegas = bodegas;
      },
      (error) => {
        this.alertService.error(error);
      }
    );
    this.usuarioLogueado();
  }

  cargarModulos() {
    this.apiService.getAll('permissions')
      .pipe(this.untilDestroyed())
      .subscribe(
      response => {
        this.modules = (response?.modules || []).map((module: any) => ({
          ...module,
          expanded: false
        }));
      },
      error => {
        this.alertService.error(error);
        this.modules = [];
      }
    );
  }

  protected aplicarFiltros(): void {
    this.loading = true;
    if(!this.filtros.id_sucursal){
      this.filtros.id_sucursal = '';
    }
    this.apiService.getAll('usuarios', this.filtros)
      .pipe(this.untilDestroyed())
      .subscribe(usuarios => {
      this.usuarios = usuarios;
      this.usuarios.data.forEach((usuario:any) => {
        if (usuario.roles && usuario.roles.length > 0) {
          usuario.rol_id = usuario.roles[0].id;
          usuario.rol_name = usuario.roles[0].name;
        } else {
          usuario.rol_name = 'Sin rol asignado';
        }
        usuario.encrypted_id = this.encryptService.encrypt(usuario.id);
      });
      this.contarActivos();
      this.loading = false;
    }, error => {this.alertService.error(error); this.loading = false;});

    this.apiService.getAll('roles')
      .pipe(this.untilDestroyed())
      .subscribe(roles => {
      this.roles = roles;

      this.roles.forEach((rol:any) => {
        rol.name = rol.name.split('_')
          .map((word: string) => word.charAt(0).toUpperCase() + word.slice(1))
          .join(' ');
      });
    }, error => {this.alertService.error(error); });
  }

  public contarActivos() {
    this.usuarios_activos = this.usuarios.data.filter(
      (item: any) => item.enable == '1'
    ).length;
  }



    override openModal(template: TemplateRef<any>, usuario?: any, modalConfig?: any) {
        super.openModal(template, usuario, { class: 'modal-lg', backdrop: 'static', ...modalConfig });
    }

    openRoleModal() {
        this.role = {
            name: '',
            permissions: [],
            is_global: false
        };

        // Cargar módulos cada vez que se abre el modal
        this.cargarModulos();

        super.openModal(this.roleModalTemplate, { class: 'modal-lg', backdrop: 'static' });
    }

    public mostrarPassword(){
        this.showpassword = !this.showpassword;
    }

    public mostrarPassword2(){
        this.showpassword2 = !this.showpassword2;
    }


  public async setEstado(usuario: any) {
    try {
      const usuarioActualizado = await this.apiService.store('usuario', usuario)
        .pipe(this.untilDestroyed())
        .toPromise();

      if (usuarioActualizado.enable == '1') {
        this.alertService.success(
          'Usuario activado',
          'El usuario fue activado exitosamente.'
        );
      } else {
        this.alertService.success(
          'Usuario desactivado',
          'El usuario fue desactivado exitosamente.'
        );
      }
      this.contarActivos();
    } catch (error: any) {
      this.alertService.error(error);
    }
  }


  selectSucursal() {
    this.usuario.id_bodega = this.usuario.id_sucursal;
  }

  onFiltrar() {
    this.loading = true;
    this.apiService.store('usuarios/filtrar', this.filtros)
      .pipe(this.untilDestroyed())
      .subscribe(
      (usuarios) => {
        this.usuarios = usuarios;
        this.loading = false;
        this.modalRef?.hide();
      },
      (error) => {
        this.alertService.error(error);
        this.loading = false;
      }
    );
  }

  //usuarioLogueado

  public usuarioLogueado() {
    this.authUser = this.apiService.auth_user();
  }

public changePhoneNumber(event: any) {
  this.usuario.telefono = event.e164Number;
}

// Métodos para el modal de roles
  toggleModule(module: any) {
    module.expanded = !module.expanded;
  }

  getSimplePermissionName(fullName: string): string {
    return fullName.split('.').pop() || fullName;
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
  }

  isPermissionSelected(permissionName: string): boolean {
    return this.role.permissions.includes(permissionName);
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

  saveRole() {
    this.loading = true;

    if (!this.role.name) {
      this.alertService.error('El nombre del rol es requerido');
      this.loading = false;
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
        this.loadAll(); // Recargar para actualizar la lista de roles
        this.loading = false;
      },
      error => {
        this.alertService.error(error);
        this.loading = false;
      }
    );
  }

  canCreateGlobalRoles(): boolean {
    return this.apiService.verifyRoleAdmin();
  }

  override closeModal() {
    super.closeModal();
    this.role = {
      name: '',
      permissions: [],
      is_global: false
    };
  }

  editarUsuario(usuario: any) {
    const encryptedId = this.encryptService.encrypt(usuario.id);
    // Navegar usando Router si lo tienes importado, o usar window.location
    window.location.href = `/usuario/${encryptedId}`;
  }

  crearUsuario() {
    // Para nuevo usuario, usamos un ID especial (0) que será detectado en el componente usuario
    const encryptedId = this.encryptService.encrypt(0);
    window.location.href = `/usuario/${encryptedId}`;
  }

  getRolName(rolId: number): string {
    const rol = this.roles.find((r: any) => r.id === rolId);
    return rol ? rol.name : 'Sin rol asignado';
  }

}
