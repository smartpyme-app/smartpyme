// roles-permisos.component.ts
import { Component, OnInit, TemplateRef } from '@angular/core';
import { ApiService } from '@services/api.service';
import { AlertService } from '@services/alert.service';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { ActivatedRoute, Router } from '@angular/router';

@Component({
  selector: 'app-roles-permisos',
  templateUrl: './roles-permisos.component.html',
})
export class RolesPermisosComponent implements OnInit {
  public roles: any = {};
  public permisos: any[] = [];
  public loading: boolean = false;
  public filtros = {
    buscador: '',
    paginate: 10,
    orden: '',
    direccion: 'asc',
    page: 1
};
  permissions: any[] = [];
  selectedRole: any = null;
  searchText: string = ''
  permisosSeleccionados: any[] = [];
  role: any = {};

  modalRef!: BsModalRef;

  constructor(
    public apiService: ApiService,
    public alertService: AlertService,
    private route: ActivatedRoute,
    private router: Router,
    private modalService: BsModalService
  ) {}

  ngOnInit() {
    this.cargarDatos();
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
      permissions: this.role.permissions
    };

    this.apiService.store('roles-permissions', roleData).subscribe(
      response => {
        this.alertService.success('Rol creado correctamente', 'success');
        this.closeModal();
        this.cargarDatos();
        this.loading = false;
      },
      error => {
        this.alertService.error(error);
        this.loading = false;
      }
    );
  }

  cargarDatos() {
    this.loading = true;
    this.apiService.getAll('roles-permissions', this.filtros).subscribe(
      (response) => {
        this.roles = response; // Ahora recibe un objeto con data, total, etc.
        console.log('Roles:', this.roles.total);
        this.loading = false;
        if (this.modalRef) {
          this.modalRef.hide();
        }
      },
      (error) => {
        this.alertService.error(error);
        this.loading = false;
      }
    );
  }

  public setOrden(columna: string) {
    if (this.filtros.orden === columna) {
      this.filtros.direccion = this.filtros.direccion === 'asc' ? 'desc' : 'asc';
    } else {
      this.filtros.orden = columna;
      this.filtros.direccion = 'asc';
    }
    this.filtrarRoles();
  }

  public filtrarRoles() {
    this.cargarDatos();
  }

  public setPagination(event: any): void {
    this.loading = true;
    this.apiService.paginate(this.roles.path + '?page=' + event.page, this.filtros)
      .subscribe(
        roles => {
          this.roles = roles;
          this.loading = false;
        },
        error => {
          this.alertService.error(error);
          this.loading = false;
        }
      );
  }

  cargarPermisos() {
    this.apiService.getAll('permissions').subscribe(
      (response) => {
        this.permissions = response;
        if (this.selectedRole) {
          this.preparePermissionsForRole();
        }
      },
      (error) => {
        this.alertService.error(error);
      }
    );
  }

  openModal(template: TemplateRef<any>, role: any) {
    if (role.name) {
      // Si es edición
      this.selectedRole = role;
      if (!this.permissions.length) {
        this.cargarPermisos();
      } else {
        this.preparePermissionsForRole();
      }
    } else {
      // Si es creación
      this.role = {
        name: '',
        permissions: []
      };
      if (!this.permissions.length) {
        this.cargarPermisos();
      }
    }
    this.alertService.modal = true;
    this.modalRef = this.modalService.show(template, { class: 'modal-lg' });
  }

  preparePermissionsForRole() {
    // Asegurarnos que permissions existe y tiene datos
    if (this.permissions && this.selectedRole) {
      this.permissions = this.permissions.map(permission => ({
        ...permission,
        checked: this.selectedRole.permissions.some((p: any) => p.name === permission.name)
      }));
    }
  }

  get filteredPermissions() {
    return this.permissions.filter(permission =>
      permission.name.toLowerCase().includes(this.searchText.toLowerCase())
    );
  }

  onPermissionChange(permission: any) {
    console.log('Permiso cambiado:', permission);
  }

  savePermissions() {
    const selectedPermissions = this.permissions
      .filter(p => p.checked)
      .map(p => p.name);

    this.apiService.store('roles-permissions/assign-permission-to-role', {
      role: this.selectedRole.name,
      permissions: selectedPermissions
    }).subscribe(
      response => {
        this.alertService.success('Permisos actualizados correctamente', 'success');
        this.closeModal();
        this.cargarDatos();
      },
      error => {
        this.alertService.error(error);
      }
    );
  }

  closeModal() {
    this.modalRef.hide();
    this.alertService.modal = false;
  }

  onPermissionSelect(event: any) {
    const permission = event.target.value;
    const isChecked = event.target.checked;

    if (isChecked) {
      this.role.permissions.push(permission);
    } else {
      const index = this.role.permissions.indexOf(permission);
      if (index > -1) {
        this.role.permissions.splice(index, 1);
      }
    }
  }

}
