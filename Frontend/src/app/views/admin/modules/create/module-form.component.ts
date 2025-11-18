import { Component, OnInit, ViewChild, DestroyRef, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { NgForm } from '@angular/forms';
import { Router, ActivatedRoute } from '@angular/router';
import { ApiService } from '@services/api.service';
import { AlertService } from '@services/alert.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';

@Component({
    selector: 'app-module-form',
    templateUrl: './module-form.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule],
    
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
    custom_permissions: [],
  };

  private destroyRef = inject(DestroyRef);
  private untilDestroyed = subscriptionHelper(this.destroyRef);

  constructor(
    public apiService: ApiService,
    private alertService: AlertService,
    private router: Router,
    private route: ActivatedRoute
  ) {}

  ngOnInit() {
    const id = this.route.snapshot.params['id'];
    if (id) {
      this.isEdit = true;
      this.loadModule(id);
    }
  }

  loadModule(id: number) {
    this.loading = true;
    this.apiService.read('modules/', id)
      .pipe(this.untilDestroyed())
      .subscribe(
      (response) => {
        // Encontrar permisos personalizados únicos
        const customPermissions = new Map<string, any>();

        // Procesar permisos del módulo principal
        response.permissions
          .filter((p: any) => p.permission_type === 'custom')
          .forEach((p: any) => {
            const action = p.permission.name.split('.').pop(); // Obtener la última parte (ej: 'exportar' de 'atencion_al_cliente.exportar')
            if (!customPermissions.has(action)) {
              customPermissions.set(action, {
                action,
                applyToModule: true,
                targets: {},
              });
            } else {
              customPermissions.get(action).applyToModule = true;
            }

            // Inicializar targets con todos los submódulos en false
            response.submodules.forEach((sub: any) => {
              if (!customPermissions.get(action).targets[sub.name]) {
                customPermissions.get(action).targets[sub.name] = false;
              }
            });
          });

        // Procesar permisos de submódulos
        response.submodules.forEach((sub: any) => {
          sub.permissions
            .filter((p: any) => p.permission_type === 'custom')
            .forEach((p: any) => {
              const action = p.permission.name.split('.').pop(); // Obtener la última parte (ej: 'exportar')

              if (!customPermissions.has(action)) {
                customPermissions.set(action, {
                  action,
                  applyToModule: false,
                  targets: {},
                });
              }

              // Marcar este submódulo como true para este permiso
              customPermissions.get(action).targets[sub.name] = true;
            });
        });

        // Construir el módulo con los permisos personalizados normalizados
        this.module = {
          ...response,
          custom_permissions: Array.from(customPermissions.values()),
        };

        this.loading = false;
      },
      (error) => {
        this.alertService.error(error);
        this.loading = false;
      }
    );
  }

  //   saveModule() {
  //     if (this.moduleForm.invalid) return;

  //     this.loading = true;

  //     const moduleData = {
  //       ...this.module,
  //       default_permissions: {
  //         module: this.getDefaultModulePermissions(),
  //         submodules: this.module.submodules.map((sub: any) => ({
  //           name: sub.name,
  //           permissions: this.getDefaultSubmodulePermissions(sub.name)
  //         }))
  //       },
  //       custom_permissions: this.module.custom_permissions
  //     };

  //     const operation = this.module.id ?
  //       this.apiService.update('modules/', this.module.id, moduleData) :
  //       this.apiService.store('modules', moduleData);

  //     operation.subscribe(
  //       () => {
  //         this.alertService.success('Módulo guardado', 'El módulo fue guardado exitosamente.');
  //         this.router.navigate(['/modulos']);
  //       },
  //       error => {
  //         this.alertService.error(error);
  //         this.loading = false;
  //       }
  //     );
  //   }
  saveModule() {
    if (this.moduleForm.invalid) return;
    this.loading = true;

    // Si es edición, usar la estructura existente
    if (this.isEdit) {
      this.updateModule();
    } else {
      // Estructura original para crear nuevo
      const moduleData = {
        ...this.module,
        default_permissions: {
          module: this.getDefaultModulePermissions(),
          submodules: this.module.submodules.map((sub: any) => ({
            name: sub.name,
            permissions: this.getDefaultSubmodulePermissions(sub.name),
          })),
        },
        custom_permissions: this.module.custom_permissions,
      };

      this.apiService.store('modules', moduleData)
        .pipe(this.untilDestroyed())
        .subscribe(
        () => {
          this.alertService.success(
            'Módulo creado',
            'El módulo fue creado exitosamente.'
          );
          this.router.navigate(['/modulos']);
        },
        (error) => {
          this.alertService.error(error);
          this.loading = false;
        }
      );
    }
  }

  private updateModule() {
    // Preparar permisos personalizados para actualizar
    const customPermissions = this.module.custom_permissions.flatMap(
      (perm: any) => {
        const permissions = [];

        // Si aplica al módulo principal
        if (perm.applyToModule) {
          permissions.push({
            id: perm.id, // Si existe
            module_id: this.module.id,
            submodule_id: null,
            permission_id: null, // Se generará en el backend
            permission_type: 'custom',
            permission: {
              name: `${this.module.name}.${perm.action}`,
              guard_name: 'web',
            },
          });
        }

        // Para submódulos seleccionados
        if (perm.targets) {
          Object.entries(perm.targets).forEach(([subName, isSelected]) => {
            if (isSelected) {
              const submodule = this.module.submodules.find(
                (s: any) => s.name === subName
              );
              if (submodule) {
                permissions.push({
                  module_id: null,
                  submodule_id: submodule.id,
                  permission_type: 'custom',
                  permission: {
                    name: `${this.module.name}.${subName}.${perm.action}`,
                    guard_name: 'web',
                  },
                });
              }
            }
          });
        }

        return permissions;
      }
    );

    // Mantener los permisos base y agregar los personalizados
    const moduleData = {
      ...this.module,
      permissions: [
        ...this.module.permissions.filter(
          (p: any) => p.permission_type === 'base'
        ),
        ...customPermissions,
      ],
    };

    this.apiService.update('modules', this.module.id, moduleData)
      .pipe(this.untilDestroyed())
      .subscribe(
      () => {
        this.alertService.success(
          'Módulo actualizado',
          'El módulo fue actualizado exitosamente.'
        );
        this.router.navigate(['/modulos']);
      },
      (error) => {
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
      status: true,
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
      `${this.module.name}.eliminar`,
    ];
  }

  getDefaultSubmodulePermissions(submoduleName: string): string[] {
    if (!this.module.name || !submoduleName) return [];
    return [
      `${this.module.name}.${submoduleName}.ver`,
      `${this.module.name}.${submoduleName}.crear`,
      `${this.module.name}.${submoduleName}.editar`,
      `${this.module.name}.${submoduleName}.eliminar`,
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
            permissions.push(
              `${this.module.name}.${submoduleName}.${perm.action}`
            );
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

    const targets: { [key: string]: boolean } = {};
    if (this.module.submodules?.length) {
      this.module.submodules.forEach((sub: any) => {
        targets[sub.name] = false;
      });
    }

    const applyToModule = !this.module.submodules?.length;

    this.module.custom_permissions.push({
      action: this.newPermissionAction.toLowerCase(),
      applyToModule,
      targets,
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
      (p: any) =>
        p.action.toLowerCase() === this.newPermissionAction.toLowerCase()
    );
  }
}
