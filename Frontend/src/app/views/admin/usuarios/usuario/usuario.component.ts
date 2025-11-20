import { Component, OnInit } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { EncryptService } from '@services/encryption/encrypt.service';

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
  public Object = Object;
  public modules: any[] = [];
  public searchText: string = '';

  constructor(
    public apiService: ApiService,
    private alertService: AlertService,
    private route: ActivatedRoute,
    private router: Router,
    public encryptService: EncryptService
  ) {}
  public authUser: any = {};
  public empresas_supervisor_limitado = [13, 396, 397, 398, 427, 428, 429, 432, 438, 488, 543,569];


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

    this.apiService.getAll('sucursales/list').subscribe(
      (sucursales) => {
        this.sucursales = sucursales;
        this.loading = false;
      },
      (error) => {
        this.alertService.error(error);
      }
    );

    this.usuarioLogueado();
  }

  public loadAll(id: number) {
    this.loading = true;
    this.apiService.read('usuario/', id).subscribe(
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
      },
      (error) => {
        this.alertService.error(error);
        this.loading = false;
      }
    );
  }

  public onSubmit() {
    this.loading = true;

    let formData: FormData = new FormData();
    formData.append('id', this.usuario.id);
    formData.append('name', this.usuario.name);
    formData.append('telefono', this.getFullPhoneNumber());
    formData.append('tipo', this.usuario.tipo);
    formData.append('codigo', this.usuario.codigo);
    formData.append('id_sucursal', this.usuario.id_sucursal);



    this.apiService.store('usuario/informacion', formData).subscribe(
      (usuario) => {

        if (!this.usuario.id) {
          this.router.navigate(['/usuarios']);
        }
       // this.usuario = usuario;

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
    this.sendFile(this.file!);
  }

  mostrarPassword(campo: string): boolean {
    return this.usuario[`${campo}_show`] || false;
  }

  togglePassword(campo: string): void {
    this.usuario[`${campo}_show`] = !this.usuario[`${campo}_show`];
  }

  confirmarCambioEmail() {
    if (confirm('¿Está seguro que desea cambiar el correo electrónico?')) {
      this.usuario.email_editable = true;
    }
  }

  confirmarCambioContrasena() {
    if (confirm('¿Está seguro que desea cambiar la contraseña?')) {
      this.mostrarCambioContrasena = true;
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

  editarPassword() {
    this.editandoPassword = true;
    this.newPassword = '';
    this.confirmPassword = '';
  }

  editarCodigoAuth() {
    this.editandoCodigoAuth = true;
    this.nuevoCodigoAuth = '';
    this.confirmarCodigoAuth = '';
  }

  cancelarCodigoAuth() {
    this.editandoCodigoAuth = false;
    this.nuevoCodigoAuth = '';
    this.confirmarCodigoAuth = '';
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
        codigo_autorizacion: this.nuevoCodigoAuth,
      })
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
        },
        (error) => {
          this.alertService.error(error);
        }
      );
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

    this.loading = true;

    this.apiService
      .update('usuario/password', this.usuario.id, {
        password: this.newPassword,
      })
      .subscribe(
        (response: any) => {

          this.loading = false;
          this.editandoPassword = false;
          this.newPassword = '';
          this.confirmPassword = '';
          this.showPassword = false;
          this.showConfirmPassword = false;

          this.alertService.success(
            'Contraseña actualizada correctamente',
            'La contraseña se ha actualizado correctamente.'
          );
        },
        (error) => {
          console.error('Error completo:', error);
          this.loading = false;

          let errorMessage = 'Ha ocurrido un error al actualizar la contraseña';
          if (error?.error?.message) {
            errorMessage = error.error.message;
          } else if (error?.message) {
            errorMessage = error.message;
          } else if (typeof error === 'string') {
            errorMessage = error;
          } else if (error?.error) {
            if (typeof error.error === 'object') {
              errorMessage = JSON.stringify(error.error);
            } else {
              errorMessage = error.error;
            }
          }

          this.alertService.error(errorMessage);
        }
      );
  }

  toggleModule(module: any) {
    module.expanded = !module.expanded;
  }

  sendFile(file: File) {
    let formData: FormData = new FormData();
    formData.append('file', file);
    formData.append('id', this.usuario.id);
    this.apiService.store('usuario/avatar', formData).subscribe(
      (response: any) => {
      },
      (error) => {
        console.error('Error completo:', error);
      }
    );
  }


  onCountryChange(country: any) {
    this.selectedCountry = country;
    this.usuario.telefono = '';
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

  public usuarioLogueado() {
    this.authUser = this.apiService.auth_user();
  }
}
