import { Component, OnInit, ViewChild } from '@angular/core';
import { NgForm } from '@angular/forms';
import { Router, ActivatedRoute } from '@angular/router';
import { ApiService } from '@services/api.service';
import { AlertService } from '@services/alert.service';

@Component({
  selector: 'app-module-form',
  templateUrl: './module-form.component.html'
})
export class ModuleFormComponent implements OnInit {
  @ViewChild('moduleForm') moduleForm!: NgForm;

  public loading: boolean = false;
  public isEdit: boolean = false;
  public newPermissionAction: string = '';

  public module: any = {
    name: '',
    display_name: '',
    description: '',
    status: true,
    submodules: [],
    custom_permissions: []
  };

  constructor(
    public apiService: ApiService,
    private alertService: AlertService,
    private router: Router,
    private route: ActivatedRoute
  ) { }

  ngOnInit() {
    const id = this.route.snapshot.params['id'];
    if (id) {
      this.isEdit = true;
      this.loadModule(id);
    }
  }

  loadModule(id: number) {
    this.loading = true;
    this.apiService.read('modules/', id).subscribe(
      module => {
        this.module = module;
        this.loading = false;
      },
      error => {
        this.alertService.error(error);
        this.loading = false;
      }
    );
  }

  saveModule() {
    if (this.moduleForm.invalid) return;

    this.loading = true;
    
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
      () => {
        this.alertService.success('Módulo guardado', 'El módulo fue guardado exitosamente.');
        this.router.navigate(['/modulos']);
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
    
    if (!this.module || !this.module.custom_permissions) {
      return permissions;
    }

    this.module.custom_permissions.forEach((perm: any) => {
      if (!this.module.name || !perm.action) {
        return;
      }

      if (perm.applyToModule) {
        permissions.push(`${this.module.name}.${perm.action}`);
      }
      
      if (perm.targets) {
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

    const targets: {[key: string]: boolean} = {};
    if (this.module.submodules?.length) {
      this.module.submodules.forEach((sub: any) => {
        targets[sub.name] = false;
      });
    }

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
      .replace(/[\u0300-\u036f]/g, '')
      .replace(/[^a-z0-9\s]/g, '')
      .trim()
      .replace(/\s+/g, '_');
  }

  onDisplayNameChange(event: any) {
    const displayName = event;
    this.module.name = this.generateTechnicalName(displayName);
  }

  onSubmoduleDisplayNameChange(submodule: any, displayName: string) {
    submodule.name = this.generateTechnicalName(displayName);
  }

  isValidNewPermission(): boolean {
    if (!this.newPermissionAction) return false;
    
    return !this.module.custom_permissions?.some(
      (p: any) => p.action.toLowerCase() === this.newPermissionAction.toLowerCase()
    );
  }
}