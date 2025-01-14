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
        this.loading = false;
      },
      (error) => {
        this.alertService.error(error);
        this.loading = false;
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
    this.apiService.getAll(`roles-permissions/user/${id}`).subscribe(
      (response: any) => {
        if (response.data) {
          // Almacenar los módulos con la propiedad expanded
          this.modules = response.data.modules.map((module: any) => ({
            ...module,
            expanded: false, // Inicialmente colapsados
          }));

          // Guardar los permisos actuales
          this.rolePermissions = response.data.rolePermissions || [];
          this.directPermissions = response.data.directPermissions || [];
        }
        this.permissionsLoading = false;
      },
      (error) => {
        this.alertService.error(error);
        this.permissionsLoading = false;
      }
    );
  }

  toggleModule(module: any) {
    module.expanded = !module.expanded;
  }

  getSimplePermissionName(fullName: string): string {
    const parts = fullName.split('.');
    return parts[parts.length - 1];
  }

  isPermissionSelected(permission: any): boolean {
    return (
      this.rolePermissions.includes(permission.name) ||
      this.directPermissions.includes(permission.name)
    );
  }

  onPermissionChange(permission: Permission) {
    if (permission.fromRole) return; // No permitir cambios en permisos del rol

    // Si está seleccionado, asegurarse que no esté en directPermissions
    if (!permission.selected) {
      this.directPermissions = this.directPermissions.filter(
        (p) => p !== permission.name
      );
    } else {
      if (!this.directPermissions.includes(permission.name)) {
        this.directPermissions.push(permission.name);
      }
    }
  }

  savePermissions() {
    this.permissionsLoading = true;

    // Solo enviar permisos directos que no vienen del rol
    const permissionsToSave = this.directPermissions.filter(
      (p) => !this.rolePermissions.includes(p)
    );
    console.log('permissionsToSave', permissionsToSave);

    this.apiService
      .store(`roles-permissions/user/${this.usuario.id}`, {
        permissions: permissionsToSave,
      })
      .subscribe(
        (response) => {
          if (response.ok) {
            this.alertService.success(
              'Permisos actualizados correctamente',
              'Los permisos se han actualizado correctamente.'
            );
            this.loadPermissions(this.usuario.id); // Recargar permisos
          }
          this.permissionsLoading = false;
        },
        (error) => {
          this.alertService.error(error);
          this.permissionsLoading = false;
        }
      );
  }

  get moduleKeys(): string[] {
    return Object.keys(this.modulePermissions);
  }

  
}
