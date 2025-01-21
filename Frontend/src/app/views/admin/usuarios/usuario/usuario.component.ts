import { Component, OnInit } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { AlertService } from '../../../../services/alert.service';
import { ApiService } from '../../../../services/api.service';

interface Permission {
  id: number;
  name: string;
  fromRole: boolean;
  selected: boolean;
}
@Component({
  selector: 'app-usuario',
  templateUrl: './usuario.component.html',
})
export class UsuarioComponent implements OnInit {
  public usuario: any = {
    password_show: false,
    password_confirmation_show: false,
  };
  public sucursales: any = [];
  public empleados: any = [];
  public roles: any = [];
  public loading = false;
  public mostrarCambioContrasena = false;

  // Img Upload
  public file?: File;
  public preview = false;
  public url_img_preview: string = '';

  public editandoEmail: boolean = false;
  public editandoPassword: boolean = false;
  public nuevoEmail: string = '';
  public newPassword: string = '';
  public confirmPassword: string = '';
  public showPassword: boolean = false;
  public showConfirmPassword: boolean = false;
  public rolePermissions: string[] = [];
  public directPermissions: string[] = [];
  public allPermissions: Permission[] = [];
  public permissionsLoading: boolean = false;
  public modulePermissions: { [key: string]: Permission[] } = {};
  public Object = Object;
  public modules: any[] = [];
  public searchText: string = '';
  public selectedPermissions: string[] = [];
  public addedPermissions: string[] = []; // Permisos que se añadirán
  public removedPermissions: string[] = []; // Permisos que se quitarán
  public revokedPermissions: string[] = []; // Permisos que se revocan
  public effectivePermissions: string[] = []; // Permisos efectivos

  constructor(
    public apiService: ApiService,
    private alertService: AlertService,
    private route: ActivatedRoute,
    private router: Router
  ) {}

  ngOnInit() {
    const id = +this.route.snapshot.paramMap.get('id')!;

    if (isNaN(id)) {
      this.usuario = {};
      this.usuario.tipo = 'Vendedor';
      this.usuario.sucursal_id = this.apiService.auth_user().sucursal_id;
      this.usuario.caja_id = 1;
      this.usuario.activo = true;
    } else {
      this.loadAll(id);
    }

    this.apiService.getAll('sucursales/list').subscribe(
      (sucursales) => {
        this.sucursales = sucursales;
        this.loading = false;
      },
      (error) => {
        this.alertService.error(error);
      }
    );

    this.loadPermissions(id);
  }

  public loadAll(id: number) {
    this.loading = true;
    this.apiService.read('usuario/', id).subscribe(
      (usuario) => {
        this.usuario = usuario;
        this.usuario.rol_id = usuario.roles[0].id;
        this.loading = false;
      },
      (error) => {
        this.alertService.error(error);
        this.loading = false;
      }
    );

    this.apiService.getAll('roles').subscribe(
      (roles) => {
        this.roles = roles.map((role: any) => {
          return {
            ...role,
            name: role.name.split('_')
                         .map((word: string) => word.charAt(0).toUpperCase() + word.slice(1))
                         .join(' ')
          };
        });
      },
      (error) => {
        this.alertService.error(error);
      }
    );
  }

  public onSubmit() {
    this.loading = true;
    if (this.usuario.tipo == 1) {
      this.usuario.caja_id == null;
    }

    let formData: FormData = new FormData();
    for (var key in this.usuario) {
      if (key == 'activo' || key == 'empleado') {
        this.usuario[key] = this.usuario[key] ? 1 : 0;
      }
      formData.append(key, this.usuario[key] == null ? '' : this.usuario[key]);
    }

    // Save the user
    this.apiService.store('usuario', formData).subscribe(
      (usuario) => {
        if (!this.usuario.id) {
          this.router.navigate(['/usuarios']);
        }
        this.usuario = usuario;
        this.loading = false;
        this.preview = false;
        this.alertService.success(
          'Usuario guardado',
          'El usuario fue guardado exitosamente.'
        );
      },
      (error) => {
        this.alertService.error(error);
        this.loading = false;
      }
    );
  }

  setFile(event: any) {
    this.file = event.target.files[0];
    this.usuario.file = this.file;
    var reader = new FileReader();
    reader.onload = () => {
      var url: any;
      url = reader.result;
      this.url_img_preview = url;
      this.preview = true;
    };
    reader.readAsDataURL(this.file!);
  }

  mostrarPassword(campo: string): boolean {
    return this.usuario[`${campo}_show`] || false;
  }

  togglePassword(campo: string): void {
    this.usuario[`${campo}_show`] = !this.usuario[`${campo}_show`];
  }

  confirmarCambioEmail() {
    // Add logic to request confirmation from the user
    // before allowing to edit the email
    if (confirm('¿Está seguro que desea cambiar el correo electrónico?')) {
      this.usuario.email_editable = true;
    }
  }

  confirmarCambioContrasena() {
    // Add logic to request confirmation from the user
    // before allowing to edit the password
    if (confirm('¿Está seguro que desea cambiar la contraseña?')) {
      this.mostrarCambioContrasena = true;
    }
  }

  guardarCambiosContrasena() {
    // Validate that the passwords match
    if (this.usuario.password === this.usuario.password_confirmation) {
      // Save the password changes
      this.apiService
        .update('usuario', this.usuario.id, {
          password: this.usuario.password,
        })
        .subscribe(
          () => {
            this.alertService.success(
              'Contraseña actualizada',
              'La contraseña se ha actualizado correctamente.'
            );
            this.mostrarCambioContrasena = false;
          },
          (error) => {
            this.alertService.error(error);
          }
        );
    } else {
      this.alertService.error('Las contraseñas no coinciden');
    }
  }

  editarEmail() {
    this.editandoEmail = true;
    this.nuevoEmail = this.usuario.email;
  }

  cancelarEmail() {
    this.editandoEmail = false;
    this.nuevoEmail = '';
  }

  guardarEmail() {
    if (!this.nuevoEmail) {
      this.alertService.error('El correo es requerido');
      return;
    }

    this.apiService
      .update('usuario/email', this.usuario.id, {
        email: this.nuevoEmail,
      })
      .subscribe(
        () => {
          this.usuario.email = this.nuevoEmail;
          this.editandoEmail = false;
          this.alertService.success(
            'Correo actualizado correctamente',
            'El correo electrónico se ha actualizado correctamente.'
          );
        },
        (error) => {
          this.alertService.error(error);
        }
      );
  }

  // Password
  editarPassword() {
    this.editandoPassword = true;
    this.newPassword = '';
    this.confirmPassword = '';
  }

  cancelarPassword() {
    this.editandoPassword = false;
    this.newPassword = '';
    this.confirmPassword = '';
    this.showPassword = false;
    this.showConfirmPassword = false;
  }

  guardarPassword() {
    if (!this.newPassword || !this.confirmPassword) {
      this.alertService.error('Todos los campos son requeridos');
      return;
    }

    if (this.newPassword !== this.confirmPassword) {
      this.alertService.error('Las contraseñas no coinciden');
      return;
    }

    this.apiService
      .update('usuario/password', this.usuario.id, {
        password: this.newPassword,
      })
      .subscribe(
        () => {
          this.editandoPassword = false;
          this.newPassword = '';
          this.confirmPassword = '';
          this.alertService.success(
            'Contraseña actualizada correctamente',
            'La contraseña se ha actualizado correctamente.'
          );
        },
        (error) => {
          this.alertService.error(error);
        }
      );
  }

  loadPermissions(id: number) {
    this.permissionsLoading = true;

    this.apiService.getAll(`roles-permissions/user/${id}`).subscribe({
        next: (response: any) => {
            if (response.data) {
                this.modules = response.data.modules.map((module: any) => ({
                    ...module,
                    expanded: false
                }));
                this.rolePermissions = response.data.rolePermissions || [];
                this.directPermissions = response.data.directPermissions || [];
                this.revokedPermissions = response.data.revokedPermissions || [];
                this.effectivePermissions = response.data.effectivePermissions || [];
            }
            this.permissionsLoading = false;
        },
        error: (error) => {
            console.error('Error cargando permisos:', error);
            this.alertService.error(error);
            this.permissionsLoading = false;
        }
    });
}
  toggleModule(module: any) {
    module.expanded = !module.expanded;
  }

  getSimplePermissionName(fullName: string): string {
    const parts = fullName.split('.');
    return parts[parts.length - 1];
  }

  isPermissionSelected(permission: any): boolean {
    const permissionName = permission.name;
    
    // Si está en los permisos revocados, retornamos false
    if (this.revokedPermissions?.includes(permissionName)) {
        return false;
    }

    // Si está en los permisos directos, retornamos true
    if (this.directPermissions?.includes(permissionName)) {
        return true;
    }

    // Si está en los permisos del rol y no está revocado, retornamos true
    if (this.rolePermissions?.includes(permissionName)) {
        return true;
    }

    return false;
}

  onPermissionChange(permission: any) {
    const permissionName = permission.name;
    const isCurrentlySelected = this.isPermissionSelected(permission);

    if (isCurrentlySelected) {
      // Si está seleccionado y lo deseleccionamos
      this.addedPermissions = this.addedPermissions.filter(
        (p) => p !== permissionName
      );
      if (!this.removedPermissions.includes(permissionName)) {
        this.removedPermissions.push(permissionName);
      }
    } else {
      // Si no está seleccionado y lo seleccionamos
      this.removedPermissions = this.removedPermissions.filter(
        (p) => p !== permissionName
      );
      if (!this.addedPermissions.includes(permissionName)) {
        this.addedPermissions.push(permissionName);
      }
    }

    console.log('Estado de permisos:', {
      permission: permissionName,
      added: this.addedPermissions,
      removed: this.removedPermissions,
      currentState: isCurrentlySelected,
    });
  }

  savePermissions() {
    this.permissionsLoading = true;

    const data = {
      added_permissions: this.addedPermissions,
      removed_permissions: this.removedPermissions,
    };

    console.log('Guardando cambios:', data);

    this.apiService
      .store(`roles-permissions/user/${this.usuario.id}`, data)
      .subscribe({
        next: (response: any) => {
          if (response.ok) {
            this.alertService.success(
              'Permisos actualizados',
              'Los permisos se han actualizado correctamente'
            );

            // Resetear los arrays de cambios
            this.addedPermissions = [];
            this.removedPermissions = [];

            // Recargar los permisos
            this.loadPermissions(this.usuario.id);
          }
          this.permissionsLoading = false;
        },
        error: (error) => {
          console.error('Error al guardar permisos:', error);
          this.alertService.error(error);
          this.permissionsLoading = false;
        },
      });
  }

  getTotalPermissions(module: any): number {
    let total = module.permissions?.length || 0;
    module.submodules?.forEach((submodule: any) => {
      total += submodule.permissions?.length || 0;
    });
    return total;
  }

  get moduleKeys(): string[] {
    return Object.keys(this.modulePermissions);
  }
}
