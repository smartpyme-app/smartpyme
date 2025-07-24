import { Component, OnInit, Input, TemplateRef, ViewChild } from '@angular/core';

import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { CountryISO, PhoneNumberFormat, SearchCountryField } from 'ngx-intl-tel-input';
import { EncryptService } from '@services/encryption/encrypt.service';


@Component({
  selector: 'app-usuarios',
  templateUrl: './usuarios.component.html',
})
export class UsuariosComponent implements OnInit {

  @ViewChild('mrol', { static: false }) roleModalTemplate!: TemplateRef<any>;

  public sucursales: any = [];
  public bodegas: any = [];
  public usuarios: any = [];
  public roles:any = [];
  public usuario: any = {};
  public paginacion = [];
  public loading: boolean = false;
  public saving: boolean = false;
  public filtrado: boolean = false;
  public usuarios_activos: any = 0;
  public filtros: any = {};
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
  empresas_supervisor_limitado = [13, 396, 397, 398, 427, 428, 429, 432, 438, 488];
  public modules: any[] = [];
  public permissionsLoading: boolean = false;
  public role: any = {
    name: '',
    permissions: [],
    is_global: false
  };

  modalRef?: BsModalRef;

    constructor( public apiService:ApiService, public alertService:AlertService,
        private modalService: BsModalService,
        public encryptService: EncryptService ){}

  ngOnInit() {
    this.filtros.id_sucursal = '';
    this.filtros.estado = '';
    this.filtros.buscador = '';
    this.filtros.orden = 'name';
    this.filtros.direccion = 'desc';
    this.filtros.paginate = 30;

    this.loadAll();

    this.apiService.getAll('sucursales/list').subscribe(
      (sucursales) => {
        this.sucursales = sucursales;
      },
      (error) => {
        this.alertService.error(error);
      }
    );

    this.apiService.getAll('bodegas/list').subscribe(
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
    console.log('Cargando módulos...');
    this.apiService.getAll('permissions').subscribe(
      response => {
        console.log('Response modules:', response);
        this.modules = (response?.modules || []).map((module: any) => ({
          ...module,
          expanded: false
        }));
        console.log('Módulos cargados:', this.modules);
      },
      error => {
        console.error('Error al cargar módulos:', error);
        this.alertService.error(error);
        this.modules = [];
      }
    );
  }

  public loadAll(){
    this.loading = true;
    if(!this.filtros.id_sucursal){
      this.filtros.id_sucursal = '';
    }
    this.apiService.getAll('usuarios', this.filtros).subscribe(usuarios => {
      this.usuarios = usuarios;
      this.usuarios.data.forEach((usuario:any) => {
        if (usuario.roles && usuario.roles.length > 0) {
          usuario.rol_id = usuario.roles[0].id;
          usuario.rol_name = usuario.roles[0].name;
        } else {
          usuario.rol_id = null;
          usuario.rol_name = 'Sin rol asignado';
        }
        usuario.encrypted_id = this.encryptService.encrypt(usuario.id);
      });
      this.contarActivos();
      this.loading = false;
    }, error => {this.alertService.error(error); this.loading = false;});

    this.apiService.getAll('roles').subscribe(roles => {
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



    openModal(template: TemplateRef<any>, usuario:any) {
        this.alertService.modal = true;
        this.usuario = usuario;
        if (!this.usuario.id) {
            this.usuario.rol_id = 2;
            this.usuario.id_sucursal = this.apiService.auth_user().id_sucursal;
            this.usuario.id_empresa = this.apiService.auth_user().id_empresa;
        }
        this.modalRef = this.modalService.show(template, { class: 'modal-lg', backdrop: 'static' });
    }

    openRoleModal() {
        this.alertService.modal = true;
        this.role = {
            name: '',
            permissions: [],
            is_global: false
        };

        // Cargar módulos cada vez que se abre el modal
        this.cargarModulos();

        this.modalRef = this.modalService.show(this.roleModalTemplate, {
            class: 'modal-lg',
            backdrop: 'static'
        });
    }

    public mostrarPassword(){
        this.showpassword = !this.showpassword;
    }

    public mostrarPassword2(){
        this.showpassword2 = !this.showpassword2;
    }

  public onSubmit() {
    this.saving = true;
    this.usuario.telefono = this.usuario.telefono?.e164Number || '';
    this.apiService.store('usuario', this.usuario).subscribe(
      (usuario) => {
        this.loadAll();
        this.saving = false;
        this.alertService.success(
          'Usuario guardado',
          'El usuario fue guardado exitosamente.'
        );
        this.modalRef?.hide();
        this.alertService.modal = false;
      },
      (error) => {
        this.alertService.error(error);
        this.saving = false;
      }
    );
  }

  public setEstado(usuario: any) {
    this.apiService.store('usuario', usuario).subscribe(
      (usuario) => {
        if (usuario.enable == '1') {
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
      },
      (error) => {
        this.alertService.error(error);
        this.loading = false;
      }
    );
  }

  public delete(id: number) {
    if (confirm('¿Desea eliminar el Registro?')) {
      this.apiService.delete('usuario/', id).subscribe(
        (data) => {
          for (let i = 0; i < this.usuarios.data.length; i++) {
            if (this.usuarios.data[i].id == data.id)
              this.usuarios.data.splice(i, 1);
          }
        },
        (error) => {
          this.alertService.error(error);
          this.loading = false;
        }
      );
    }
  }

  selectSucursal() {
    this.usuario.id_bodega = this.usuario.id_sucursal;
  }

  onFiltrar() {
    this.loading = true;
    this.apiService.store('usuarios/filtrar', this.filtros).subscribe(
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
  console.log('Evento completo:', event);
  this.usuario.telefono = event.e164Number;
  console.log('Teléfono a enviar:', this.usuario.telefono);
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

    this.apiService.store('roles-permissions', roleData).subscribe(
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

  closeModal() {
    this.modalRef?.hide();
    this.alertService.modal = false;
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

}
