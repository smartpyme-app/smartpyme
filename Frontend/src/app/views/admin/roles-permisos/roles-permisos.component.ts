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
  public roles: any[] = [];
  public permisos: any[] = [];
  public loading: boolean = false;
  public filtros: any = {};
  permissions: any[] = [];
  selectedRole: any = null;
  searchText: string = ''
  permisosSeleccionados: any[] = [];

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

  cargarDatos() {
    this.loading = true;
    this.apiService.getAll('roles-permissions', this.filtros).subscribe(
      (response) => {
          this.roles = response.data;
          this.loading = false;
        
      },
      (error) => {
        this.alertService.error(error);
        this.loading = false;
      }
    );
    this.apiService.getAll('permissions', this.filtros).subscribe(
      (response) => {
          this.permissions = response;
          this.loading = false;
      },
      (error) => {
        this.alertService.error(error);
        this.loading = false;
      }
    );
  }

  openModal(template: TemplateRef<any>, role: any) {
    this.selectedRole = role;
    this.preparePermissionsForRole();
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
    // Lógica para cuando cambia un permiso
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
}
