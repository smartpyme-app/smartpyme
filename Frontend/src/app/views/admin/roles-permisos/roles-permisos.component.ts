import { Component, OnInit, TemplateRef } from '@angular/core';
import { ApiService } from '@services/api.service';
import { AlertService } from '@services/alert.service';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { ActivatedRoute, Router } from '@angular/router';

@Component({
  selector: 'app-roles-permisos',
  templateUrl: './roles-permisos.component.html'
})
export class RolesPermisosComponent implements OnInit {
  public roles: any = {};
  public modules: any[] = [];
  public loading: boolean = false;
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
    permissions: []
  };

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
    this.cargarModulos();
  }

  cargarModulos() {
    //this.apiService.getAll('modules').subscribe(
      this.apiService.getAll('permissions').subscribe(
      response => {
        console.log('Response modules:', response);
        // Verificamos que response.modules exista, si no, usamos un array vacío
        this.modules = (response?.modules || []).map((module: any) => ({
          ...module,
          expanded: true 
        }));
        console.log('Módulos cargados:', this.modules); // Para debug
      },
      error => {
        console.error('Error al cargar módulos:', error); // Para debug
        this.alertService.error(error);
        this.modules = []; // Aseguramos que modules sea al menos un array vacío
      }
    );
  }

  toggleModule(module: any) {
    module.expanded = !module.expanded;
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
    return this.role.permissions.includes(permission.name);
  }

  getSimplePermissionName(fullName: string) {
    return fullName.split('.').pop();
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
        this.roles = response;
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

  setPagination(event: { page: number }) {
    this.filtros.page = event.page;
    this.cargarDatos();
  }

  openModal(template: TemplateRef<any>, role: any) {
    this.alertService.modal = true;
    if (role.name) {
      // Modo edición
      this.selectedRole = role;
      this.role = {
        name: '',
        permissions: []
      };
    } else {
      // Modo creación
      this.selectedRole = null;
      this.role = {
        name: '',
        permissions: []
      };
    }
    this.modalRef = this.modalService.show(template, { class: 'modal-lg' });
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
    }
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

  savePermissions() {
    const selectedPermissions = this.selectedRole.permissions.map((p: any) => p.name);
    
    this.apiService.store('update-role-permissions', {
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
    this.selectedRole = null;
    this.role = {
      name: '',
      permissions: []
    };
  }

  
}