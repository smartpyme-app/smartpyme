// modules.component.ts
import { Component, OnInit, TemplateRef } from '@angular/core';
import { ApiService } from '@services/api.service';
import { AlertService } from '@services/alert.service';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';

@Component({
  selector: 'app-modules',
  templateUrl: './modules.component.html'
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

 
  public module: any = {
    name: '',
    display_name: '',
    description: '',
    submodules: [],
    custom_permissions: []
};

  modalRef!: BsModalRef;

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
    this.apiService.getAll('modules', this.filtros).subscribe(
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
      this.apiService.delete('modules/', id).subscribe(
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

    operation.subscribe(
        // ... resto del código
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

addCustomPermission() {
    if (!this.module.custom_permissions) {
        this.module.custom_permissions = [];
    }
    this.module.custom_permissions.push({
        name: '',
        type: 'module'
    });
}

removeCustomPermission(index: number) {
    this.module.custom_permissions.splice(index, 1);
}
}