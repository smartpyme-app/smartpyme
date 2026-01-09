import { Component, OnInit, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { Router, ActivatedRoute } from '@angular/router';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { EncryptService } from '@services/encryption/encrypt.service';
import { BaseComponent } from '@shared/base/base.component';


interface Permission {
  id: number;
  name: string;
  fromRole: boolean;
  selected: boolean;
}
@Component({
    selector: 'app-usuario',
    templateUrl: './usuario.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule],
    changeDetection: ChangeDetectionStrategy.OnPush,
    
})
export class UsuarioComponent extends BaseComponent implements OnInit {
  public usuario: any = {
    password_show: false,
    password_confirmation_show: false,
  };
  public sucursales: any = [];
  public empleados: any = [];
  public roles: any = [];
  public rol: any = {};
  public loading = false;
  public mostrarCambioContrasena = false;
  public countries = [
    {
      code: 'SV',
      name: 'El Salvador',
      dial: '+503',
      flag: '🇸🇻',
      mask: '####-####',
      maxLength: 9,
    },
    {
      code: 'GT',
      name: 'Guatemala',
      dial: '+502',
      flag: '🇬🇹',
      mask: '####-####',
      maxLength: 8,
    },
    {
      code: 'HN',
      name: 'Honduras',
      dial: '+504',
      flag: '🇭🇳',
      mask: '####-####',
      maxLength: 8,
    },
    {
      code: 'NI',
      name: 'Nicaragua',
      dial: '+505',
      flag: '🇳🇮',
      mask: '####-####',
      maxLength: 8,
    },
    {
      code: 'CR',
      name: 'Costa Rica',
      dial: '+506',
      flag: '🇨🇷',
      mask: '####-####',
      maxLength: 8,
    },
    {
      code: 'PA',
      name: 'Panamá',
      dial: '+507',
      flag: '🇵🇦',
      mask: '####-####',
      maxLength: 8,
    },
    {
      code: 'US',
      name: 'Estados Unidos',
      dial: '+1',
      flag: '🇺🇸',
      mask: '(###) ###-####',
      maxLength: 14,
    },
    {
      code: 'CA',
      name: 'Canadá',
      dial: '+1',
      flag: '🇨🇦',
      mask: '(###) ###-####',
      maxLength: 14,
    },
    {
      code: 'MX',
      name: 'México',
      dial: '+52',
      flag: '🇲🇽',
      mask: '### ### ####',
      maxLength: 12,
    },
  ];
  public searchTerm: string = '';
  public filterModules: any[] = [];
  public selectedCountry = this.countries[0];

  // Img Upload
  public file?: File;
  public preview = false;
  public url_img_preview: string = '';

  public editandoEmail: boolean = false;
  public editandoPassword: boolean = false;
  public editandoCodigoAuth: boolean = false;
  public nuevoEmail: string = '';
  public newPassword: string = '';
  public nuevoCodigoAuth: string = '';

  public confirmPassword: string = '';
  public confirmarCodigoAuth: string = '';
  public showPassword: boolean = false;
  public showAuthCode: boolean = false;
  public showConfirmPassword: boolean = false;
  public showConfirmAuthCode: boolean = false;
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
    private router: Router,
    public encryptService: EncryptService,
    private cdr: ChangeDetectorRef
  ) {
    super();
  }
  public authUser: any = {};
  public empresas_supervisor_limitado = [13, 396, 397, 398, 427, 428, 429, 432, 438, 488, 543,569,657,538];


  ngOnInit() {
    const encryptedId = this.route.snapshot.paramMap.get('id')!;
    const id = this.encryptService.decrypt(encryptedId);

    if (id === 0 || isNaN(id)) {
      // Nuevo usuario
      this.usuario = {};
      this.usuario.rol_id = 2;
      this.usuario.sucursal_id = this.apiService.auth_user().sucursal_id;
      this.usuario.caja_id = 1;
      this.usuario.activo = true;
    } else {
      // Usuario existente
      this.loadAll(id);
    }

    this.apiService.getAll('sucursales/list')
      .pipe(this.untilDestroyed())
      .subscribe(
      (sucursales) => {
        this.sucursales = sucursales;
        this.loading = false;
        this.cdr.markForCheck();
      },
      (error) => {
        this.alertService.error(error);
        this.cdr.markForCheck();
      }
    );

    this.usuarioLogueado();
    this.loadPermissions(id);
  }

  public loadAll(id: number) {
    this.loading = true;
    this.cdr.markForCheck();
    this.apiService.read('usuario/', id)
      .pipe(this.untilDestroyed())
      .subscribe(
      (usuario) => {
        this.usuario = usuario;
        this.usuario.rol_id = usuario.roles[0].id;
        this.rol = usuario.roles[0];
        this.rol.name = this.rol.name
          .split('_')
          .map((word: string) => word.charAt(0).toUpperCase() + word.slice(1))
          .join(' ');

        this.nuevoCodigoAuth = usuario.codigo_autorizacion;

        if (usuario.telefono) {
          this.detectCountryFromPhone(usuario.telefono);
        }

        this.loading = false;
        this.cdr.markForCheck();
      },
      (error) => {
        this.alertService.error(error);
        this.loading = false;
        this.cdr.markForCheck();
      }
    );

    this.apiService.getAll('roles')
      .pipe(this.untilDestroyed())
      .subscribe(
      (roles) => {
        this.roles = roles.map((role: any) => {
          return {
            ...role,
            name: role.name
              .split('_')
              .map(
                (word: string) => word.charAt(0).toUpperCase() + word.slice(1)
              )
              .join(' '),
          };
        });
        this.cdr.markForCheck();
      },
      (error) => {
        this.alertService.error(error);
      }
    );
  }

  public onSubmit() {
    this.loading = true;
    this.cdr.markForCheck();

    if (this.usuario.rol_id == 2) {
      this.usuario.caja_id = null;
    }

    let formData: FormData = new FormData();

    for (var key in this.usuario) {
      if (key == 'activo' || key == 'empleado') {
        this.usuario[key] = this.usuario[key] ? 1 : 0;
      }

      // CORRECCIÓN: Manejar objetos complejos
      let value = this.usuario[key];

      if (value === null || value === undefined) {
        formData.append(key, '');
      } else if (typeof value === 'object') {
        // Para objetos (como roles), serializar a JSON string
        formData.append(key, JSON.stringify(value));
      } else {
        formData.append(key, value.toString());
      }
    }

    // Save the user
    this.apiService.store('usuario', formData)
      .pipe(this.untilDestroyed())
      .subscribe(
      (usuario) => {

        if (!this.usuario.id) {
          this.router.navigate(['/usuarios']);
        }
        this.usuario = usuario;

        if (this.usuario.roles && this.usuario.roles.length > 0) {
            this.usuario.rol_id = this.usuario.roles[0].id;
            this.rol = this.usuario.roles[0];
            this.rol.name = this.rol.name
                .split('_')
                .map((word: string) => word.charAt(0).toUpperCase() + word.slice(1))
                .join(' ');
        }

        this.loading = false;
        this.preview = false;
        this.alertService.success(
          'Usuario guardado',
          'El usuario fue guardado exitosamente.'
        );
        this.cdr.markForCheck();
      },
      (error) => {
        this.loading = false;

        if (error.status === 403 && error.error?.requires_authorization) {
          this.cdr.markForCheck();
          return;
        }

        let errorMessage = 'Ha ocurrido un error al guardar el usuario';

        if (error?.error?.message) {
          errorMessage = error.error.message;
        } else if (error?.message) {
          errorMessage = error.message;
        } else if (typeof error === 'string') {
          errorMessage = error;
        }

        this.alertService.error(errorMessage);
        this.cdr.markForCheck();
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
      this.cdr.markForCheck();
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
    if (this.usuario.password !== this.usuario.password_confirmation) {
      this.alertService.error('Las contraseñas no coinciden');
      return;
    }

    if (!this.usuario.password || !this.usuario.password_confirmation) {
      this.alertService.error('Todos los campos son requeridos');
      return;
    }

    this.loading = true;
    this.cdr.markForCheck();

    // Save the password changes
    this.apiService
      .update('usuario', this.usuario.id, {
        password: this.usuario.password,
      })
      .pipe(this.untilDestroyed())
      .subscribe(
        (response) => {

          this.loading = false;
          this.mostrarCambioContrasena = false;

          // Limpiar los campos de contraseña
          this.usuario.password = '';
          this.usuario.password_confirmation = '';
          this.usuario.password_show = false;
          this.usuario.password_confirmation_show = false;

          // Si la respuesta contiene información de autorización pendiente
          if (response && response.status === 'pending') {
            this.alertService.success(
              'Solicitud de autorización creada',
              `Se ha creado una solicitud de autorización con código: ${response.code}. La contraseña se actualizará una vez aprobada.`
            );
          } else {
            this.alertService.success(
              'Contraseña actualizada',
              'La contraseña se ha actualizado correctamente.'
            );
          }
          this.cdr.markForCheck();
        },
        (error) => {
          console.error('Error al actualizar contraseña:', error);
          this.loading = false;
          this.alertService.error(error);
          this.cdr.markForCheck();
        }
      );
  }

  editarEmail() {
    this.editandoEmail = true;
    this.nuevoEmail = this.usuario.email;
    this.cdr.markForCheck();
  }

  cancelarEmail() {
    this.editandoEmail = false;
    this.nuevoEmail = '';
    this.cdr.markForCheck();
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
      .pipe(this.untilDestroyed())
      .subscribe(
        () => {
          this.usuario.email = this.nuevoEmail;
          this.editandoEmail = false;
          this.alertService.success(
            'Correo actualizado correctamente',
            'El correo electrónico se ha actualizado correctamente.'
          );
          this.cdr.markForCheck();
        },
        (error) => {
          this.alertService.error(error);
          this.cdr.markForCheck();
        }
      );
  }

  // Password
  editarPassword() {
    this.editandoPassword = true;
    this.newPassword = '';
    this.confirmPassword = '';
    this.cdr.markForCheck();
  }

  editarCodigoAuth() {
    this.editandoCodigoAuth = true;
    this.newPassword = '';
    this.confirmPassword = '';
    this.cdr.markForCheck();
  }

  cancelarCodigoAuth() {
    this.editandoCodigoAuth = false;
    this.newPassword = '';
    this.confirmPassword = '';
    this.cdr.markForCheck();
  }

  guardarCodigoAuth() {

    if (!this.nuevoCodigoAuth) {
        this.alertService.error('El código de autorización es requerido');
        return;
    }

    if (this.nuevoCodigoAuth.length < 3 || this.nuevoCodigoAuth.length > 80) {
        this.alertService.error('El código debe tener entre 3 y 80 caracteres');
        return;
    }

    if (!/^\d+$/.test(this.nuevoCodigoAuth)) {
        this.alertService.error('El código debe ser numérico');
        return;
    }

    if (this.nuevoCodigoAuth !== this.confirmarCodigoAuth) {
        this.alertService.error('Los códigos no coinciden');
        return;
    }

    this.apiService
        .update('usuario/codigo-autorizacion', this.usuario.id, {
            codigo_autorizacion: this.nuevoCodigoAuth
        })
        .pipe(this.untilDestroyed())
        .subscribe(
            () => {
                this.usuario.codigo_autorizacion = this.nuevoCodigoAuth;
                this.editandoCodigoAuth = false;
                this.nuevoCodigoAuth = '';
                this.confirmarCodigoAuth = '';
                this.showAuthCode = false;
                this.showConfirmAuthCode = false;
                this.alertService.success(
                    'Código de autorización actualizado correctamente',
                    'El código de autorización se ha actualizado correctamente.'
                );
                this.cdr.markForCheck();
            },
            (error) => {
                this.alertService.error(error);
                this.cdr.markForCheck();
            }
        );
  }

  cancelarPassword() {
    this.editandoPassword = false;
    this.newPassword = '';
    this.confirmPassword = '';
    this.showPassword = false;
    this.showConfirmPassword = false;
    this.cdr.markForCheck();
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

    this.loading = true; // Agregar loading state
    this.cdr.markForCheck();

    this.apiService
      .update('usuario/password', this.usuario.id, {
        password: this.newPassword,
      })
      .pipe(this.untilDestroyed())
      .subscribe(
        (response: any) => {

          this.loading = false;
          this.editandoPassword = false;
          this.newPassword = '';
          this.confirmPassword = '';
          this.showPassword = false;
          this.showConfirmPassword = false;

          if (response && response.status === 'pending') {
            this.alertService.success(
              'Solicitud de autorización creada',
              `Se ha creado una solicitud de autorización con código: ${response.code}. La contraseña se actualizará una vez aprobada.`
            );
          } else {
            this.alertService.success(
              'Contraseña actualizada correctamente',
              'La contraseña se ha actualizado correctamente.'
            );
          }
          this.cdr.markForCheck();
        },
        (error) => {
          console.error('Error completo:', error);
          console.error('Tipo de error:', typeof error);
          console.error('Error.message:', error?.message);
          console.error('Error.error:', error?.error);

          this.loading = false;

          // Manejo mejorado del error
          let errorMessage = 'Ha ocurrido un error al actualizar la contraseña';

          if (error?.error?.message) {
            errorMessage = error.error.message;
          } else if (error?.message) {
            errorMessage = error.message;
          } else if (typeof error === 'string') {
            errorMessage = error;
          } else if (error?.error) {
            // Si error.error es un objeto, intentar extraer información útil
            if (typeof error.error === 'object') {
              errorMessage = JSON.stringify(error.error);
            } else {
              errorMessage = error.error;
            }
          }

          this.alertService.error(errorMessage);
          this.cdr.markForCheck();
        }
      );
  }

  loadPermissions(id: number) {
    this.permissionsLoading = true;
    this.cdr.markForCheck();

    this.apiService.getAll(`roles-permissions/user/${id}`)
      .pipe(this.untilDestroyed())
      .subscribe({
      next: (response: any) => {
        if (response.data) {
          this.modules = response.data.modules.map((module: any) => ({
            ...module,
            expanded: false,
          }));
          this.rolePermissions = response.data.rolePermissions || [];
          this.directPermissions = response.data.directPermissions || [];
          this.revokedPermissions = response.data.revokedPermissions || [];
          this.effectivePermissions = response.data.effectivePermissions || [];
        }
        this.permissionsLoading = false;
        this.cdr.markForCheck();
      },
      error: (error) => {
        console.error('Error cargando permisos:', error);
        this.alertService.error(error);
        this.permissionsLoading = false;
        this.cdr.markForCheck();
      },
    });
  }

  toggleModule(module: any) {
    module.expanded = !module.expanded;
    this.cdr.markForCheck();
  }

  sendFile(file: File) {
    let formData: FormData = new FormData();
    formData.append('file', file);
    formData.append('id', this.usuario.id);
    this.apiService.store('usuario/avatar', formData)
      .pipe(this.untilDestroyed())
      .subscribe(
      (response: any) => {
        this.cdr.markForCheck();
      },
      (error) => {
        console.error('Error completo:', error);
        this.cdr.markForCheck();
      }
    );
  }


  onCountryChange(country: any) {
    this.selectedCountry = country;
    this.usuario.telefono = '';
    this.cdr.markForCheck();
  }


  detectCountryFromPhone(phone: string) {
    if (!phone) {
      return;
    }

    let cleanPhone = phone.replace(/\D/g, '');

    if (phone.startsWith('+')) {

      for (let country of this.countries) {
        const dialCode = country.dial.replace('+', '');

        if (cleanPhone.startsWith(dialCode)) {
          this.selectedCountry = country;

          const localNumber = cleanPhone.substring(dialCode.length);

          this.usuario.telefono = localNumber;


          this.formatLocalPhone(localNumber);
          return;
        }
      }
    } else {

      if (cleanPhone.length <= 9) {
        this.selectedCountry =
          this.countries.find((c) => c.code === 'SV') || this.countries[0];
      } else if (cleanPhone.length === 10) {
        this.selectedCountry =
          this.countries.find((c) => c.code === 'US') || this.countries[0];
      }
      this.usuario.telefono = cleanPhone;
      this.formatLocalPhone(cleanPhone);
    }
  }

  formatLocalPhone(localNumber: string) {
    if (!localNumber) return;

    let formatted = '';
    const clean = localNumber.replace(/\D/g, '');

    if (
      this.selectedCountry.code === 'SV' ||
      ['GT', 'HN', 'NI', 'CR', 'PA'].includes(this.selectedCountry.code)
    ) {
      if (clean.length >= 4) {
        formatted = clean.substring(0, 4) + '-' + clean.substring(4, 8);
      } else {
        formatted = clean;
      }
    } else if (['US', 'CA'].includes(this.selectedCountry.code)) {
      if (clean.length >= 6) {
        formatted =
          '(' +
          clean.substring(0, 3) +
          ') ' +
          clean.substring(3, 6) +
          '-' +
          clean.substring(6, 10);
      } else if (clean.length >= 3) {
        formatted = '(' + clean.substring(0, 3) + ') ' + clean.substring(3);
      } else {
        formatted = clean;
      }
    } else if (this.selectedCountry.code === 'MX') {
      if (clean.length >= 6) {
        formatted =
          clean.substring(0, 3) +
          ' ' +
          clean.substring(3, 6) +
          ' ' +
          clean.substring(6, 10);
      } else if (clean.length >= 3) {
        formatted = clean.substring(0, 3) + ' ' + clean.substring(3);
      } else {
        formatted = clean;
      }
    }

    this.usuario.telefono = formatted;
  }

  formatPhone(event: any) {
    let value = event.target.value.replace(/\D/g, '');

    this.formatLocalPhone(value);
    event.target.value = this.usuario.telefono;
  }

  getFullPhoneNumber(): string {
    if (!this.usuario.telefono) return '';

    const cleanPhone = this.usuario.telefono.replace(/\D/g, '');
    return this.selectedCountry.dial + cleanPhone;
  }

  formatPhoneDisplay() {
    if (!this.usuario.telefono) return;

    const cleanPhone = this.usuario.telefono.replace(/\D/g, '');
    let formattedPhone = '';

    if (
      this.selectedCountry.code === 'SV' ||
      ['GT', 'HN', 'NI', 'CR', 'PA'].includes(this.selectedCountry.code)
    ) {
      if (cleanPhone.length >= 4) {
        formattedPhone =
          cleanPhone.substring(0, 4) + '-' + cleanPhone.substring(4, 8);
      } else {
        formattedPhone = cleanPhone;
      }
    } else if (['US', 'CA'].includes(this.selectedCountry.code)) {
      if (cleanPhone.length >= 6) {
        formattedPhone =
          '(' +
          cleanPhone.substring(0, 3) +
          ') ' +
          cleanPhone.substring(3, 6) +
          '-' +
          cleanPhone.substring(6, 10);
      } else if (cleanPhone.length >= 3) {
        formattedPhone =
          '(' + cleanPhone.substring(0, 3) + ') ' + cleanPhone.substring(3);
      } else {
        formattedPhone = cleanPhone;
      }
    } else if (this.selectedCountry.code === 'MX') {
      if (cleanPhone.length >= 6) {
        formattedPhone =
          cleanPhone.substring(0, 3) +
          ' ' +
          cleanPhone.substring(3, 6) +
          ' ' +
          cleanPhone.substring(6, 10);
      } else if (cleanPhone.length >= 3) {
        formattedPhone =
          cleanPhone.substring(0, 3) + ' ' + cleanPhone.substring(3);
      } else {
        formattedPhone = cleanPhone;
      }
    }

    this.usuario.telefono = formattedPhone;
  }

  getPlaceholder(): string {
    return this.selectedCountry.mask;
  }

  onCountrySelectChange(event: any) {
    const selectedCode = event.target.value;
    const country = this.countries.find((c) => c.code === selectedCode);
    if (country) {
      this.onCountryChange(country);
    }
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
    this.cdr.markForCheck();
  }

  savePermissions() {
    this.permissionsLoading = true;
    this.cdr.markForCheck();

    const data = {
      added_permissions: this.addedPermissions,
      removed_permissions: this.removedPermissions,
    };

    this.apiService
      .store(`roles-permissions/user/${this.usuario.id}`, data)
      .pipe(this.untilDestroyed())
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
          this.cdr.markForCheck();
        },
        error: (error) => {
          console.error('Error al guardar permisos:', error);
          this.alertService.error(error);
          this.permissionsLoading = false;
          this.cdr.markForCheck();
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

  filterModule(module: any): void {
    if (!module || !module.name) {
      console.error('Módulo inválido:', module);
      return;
    }

    this.filterModules = this.modules.filter((mod) => mod.name.includes(module.name));
    this.cdr.markForCheck();
  }

  public usuarioLogueado() {
    this.authUser = this.apiService.auth_user();
  }
}
