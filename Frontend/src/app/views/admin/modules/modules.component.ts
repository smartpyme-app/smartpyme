// modules.component.ts
import { Component, OnInit, TemplateRef, ViewChild, DestroyRef, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { ApiService } from '@services/api.service';
import { AlertService } from '@services/alert.service';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { subscriptionHelper } from '@shared/utils/subscription.helper';

@Component({
    selector: 'app-modules',
    templateUrl: './modules.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule],
    
})
export class ModulesComponent implements OnInit {
  public modules: any = {};
  public loading: boolean = false;
  public filtros = {
    buscador: '',
    paginate: 10,
    orden: '',
    direccion: 'asc',
    page: 1
  };
  newPermissionAction: string = '';

 
  public module: any = {
    name: '',
    display_name: '',
    description: '',
    submodules: [],
    custom_permissions: []
};
@ViewChild('moduleModal') moduleModal!: TemplateRef<any>;  // Agregar esta línea


  modalRef!: BsModalRef;

  private destroyRef = inject(DestroyRef);
  private untilDestroyed = subscriptionHelper(this.destroyRef);

  constructor(
    public apiService: ApiService,
    private alertService: AlertService,
    private modalService: BsModalService
  ) { }

  ngOnInit() {
    this.loadModules();
  }

  loadModules() {
    this.loading = true;
    this.apiService.getAll('modules', this.filtros)
      .pipe(this.untilDestroyed())
      .subscribe(
      modules => {
        this.modules = modules;
        this.loading = false;
      },
      error => {
        this.alertService.error(error);
        this.loading = false;
      }
    );
  }

  setOrden(columna: string) {
    if (this.filtros.orden === columna) {
      this.filtros.direccion = this.filtros.direccion === 'asc' ? 'desc' : 'asc';
    } else {
      this.filtros.orden = columna;
      this.filtros.direccion = 'asc';
    }
    this.filtrarModulos();
  }

  filtrarModulos() {
    this.loadModules();
  }

  setPagination(event: { page: number }) {
    this.filtros.page = event.page;
    this.loadModules();
  }

  deleteModule(id: number) {
    if (confirm('¿Está seguro que desea eliminar este módulo?')) {
      this.loading = true;
      this.apiService.delete('modules/', id)
        .pipe(this.untilDestroyed())
        .subscribe(
        () => {
          this.loadModules();
          this.alertService.success('Módulo eliminado', 'El módulo fue eliminado exitosamente.');
        },
        error => {
          this.alertService.error(error);
          this.loading = false;
        }
      );
    }
  }

  openModal(template: TemplateRef<any>, module: any = null) {
    if (module) {
      this.module = {...module};
    } else {
      this.module = {
        name: '',
        display_name: '',
        description: '',
        status: true
      };
    }
    this.modalRef = this.modalService.show(template, { class: 'modal-lg' });
    this.alertService.modal = true;
  }

  closeModal() {
    this.modalRef.hide();
    this.alertService.modal = false;
  }

  saveModule() {
    this.loading = true;
    
    // Preparar los datos incluyendo permisos
    const moduleData = {
        ...this.module,
        default_permissions: {
            module: this.getDefaultModulePermissions(),
            submodules: this.module.submodules.map((sub: any) => ({
                name: sub.name,
                permissions: this.getDefaultSubmodulePermissions(sub.name)
            }))
        },
        custom_permissions: this.module.custom_permissions
    };

    const operation = this.module.id ? 
        this.apiService.update('modules/', this.module.id, moduleData) :
        this.apiService.store('modules', moduleData);

    operation.pipe(this.untilDestroyed()).subscribe(
      response => {
        this.loadModules();
        this.alertService.success('Módulo guardado', 'El módulo fue guardado exitosamente.');
        this.closeModal();
      },
      error => {
        this.alertService.error(error);
        this.loading = false;
      }
    );
}

  addSubmodule() {
    if (!this.module.submodules) {
        this.module.submodules = [];
    }
    this.module.submodules.push({
        name: '',
        display_name: '',
        status: true
    });
}

removeSubmodule(index: number) {
    this.module.submodules.splice(index, 1);
}


getDefaultModulePermissions(): string[] {
    if (!this.module.name) return [];
    return [
        `${this.module.name}.ver`,
        `${this.module.name}.crear`,
        `${this.module.name}.editar`,
        `${this.module.name}.eliminar`
    ];
}

getDefaultSubmodulePermissions(submoduleName: string): string[] {
    if (!this.module.name || !submoduleName) return [];
    return [
        `${this.module.name}.${submoduleName}.ver`,
        `${this.module.name}.${submoduleName}.crear`,
        `${this.module.name}.${submoduleName}.editar`,
        `${this.module.name}.${submoduleName}.eliminar`
    ];
}

getGeneratedPermissions(): string[] {
  const permissions: string[] = [];
  
  // Verificar que module y custom_permissions existan
  if (!this.module || !this.module.custom_permissions) {
    return permissions;
  }

  this.module.custom_permissions.forEach((perm: any) => {
    // Verificar que el módulo tenga nombre y acción
    if (!this.module.name || !perm.action) {
      return;
    }

    // Si aplica al módulo principal
    if (perm.applyToModule) {
      permissions.push(`${this.module.name}.${perm.action}`);
    }
    
    // Verificar que targets exista antes de iterarlo
    if (perm.targets) {
      // Para cada submódulo seleccionado
      Object.entries(perm.targets).forEach(([submoduleName, isSelected]) => {
        if (isSelected && submoduleName) {
          permissions.push(`${this.module.name}.${submoduleName}.${perm.action}`);
        }
      });
    }
  });

  return permissions;
}



addCustomPermission() {
  if (!this.isValidNewPermission()) return;

  if (!this.module.custom_permissions) {
      this.module.custom_permissions = [];
  }

  // Crear objeto de targets con todos los submódulos
  const targets: {[key: string]: boolean} = {};
  if (this.module.submodules?.length) {
      this.module.submodules.forEach((sub: any) => {
          targets[sub.name] = false;
      });
  }

  // Si no hay submódulos, automáticamente aplicar al módulo principal
  const applyToModule = !this.module.submodules?.length;

  this.module.custom_permissions.push({
      action: this.newPermissionAction.toLowerCase(),
      applyToModule,
      targets
  });

  this.newPermissionAction = '';
}

removeCustomPermission(index: number) {
    this.module.custom_permissions.splice(index, 1);
}

generateTechnicalName(displayName: string): string {
  return displayName
      .toLowerCase()
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '') // Eliminar acentos
      .replace(/[^a-z0-9\s]/g, '')     // Solo letras, números y espacios
      .trim()
      .replace(/\s+/g, '_');           // Espacios a guiones bajos
}

onDisplayNameChange(event: any) {

  const displayName = event
  this.module.name = this.generateTechnicalName(displayName);
}

onSubmoduleDisplayNameChange(submodule: any, displayName: string) {
  submodule.name = this.generateTechnicalName(displayName);
}

onCustomPermissionChange(permission: any) {
  if (permission.action && permission.target) {
      permission.name = `${this.module.name}.${permission.target !== this.module.name ? permission.target + '.' : ''}${permission.action}`;
  }
}

getCustomPermissionPreview(permission: any): string {
  if (!permission.action || !permission.target) return 'Pendiente...';
  return `${this.module.name}.${permission.target !== this.module.name ? permission.target + '.' : ''}${permission.action}`;
}

isValidNewPermission(): boolean {
  if (!this.newPermissionAction) return false;
  
  // Verificar que no exista ya esta acción
  return !this.module.custom_permissions?.some(
      (p: any) => p.action.toLowerCase() === this.newPermissionAction.toLowerCase()
  );
}


}