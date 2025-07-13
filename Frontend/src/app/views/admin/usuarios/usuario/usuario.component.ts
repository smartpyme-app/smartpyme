import { Component, OnInit } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

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
  public searchTerm: string = '';
  public filterModules: any[] = [];

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
    private router: Router
  ) {}

  ngOnInit() {
    const id = this.route.snapshot.paramMap.get('id')!;
    const idNum = parseInt(id);

    this.loadAll(idNum);

    this.apiService.getAll('sucursales/list').subscribe(
      (sucursales) => {
        this.sucursales = sucursales;
        this.loading = false;
      },
      (error) => {
        this.alertService.error(error);
      }
    );
  }

  public loadAll(id: number) {
    this.loading = true;
    this.apiService.read('usuario/', id).subscribe(
      (usuario) => {
        this.usuario = usuario;
        this.nuevoCodigoAuth = usuario.codigo_autorizacion;

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
	formData.append('telefono', this.usuario.telefono);
	formData.append('tipo', this.usuario.tipo);
	formData.append('codigo', this.usuario.codigo);
	formData.append('id_sucursal', this.usuario.id_sucursal);


	console.log(formData);
    this.apiService.store('usuario/informacion', formData).subscribe(
      (usuario) => {
        console.log('✅ Usuario guardado exitosamente:', usuario);

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
        console.log('❌ Error en onSubmit:', error);
        this.loading = false;

        if (error.status === 403 && error.error?.requires_authorization) {
          console.log(
            '🔐 Se requiere autorización - el interceptor manejará esto'
          );
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

  editarCodigoAuth() {
    this.editandoCodigoAuth = true;
    this.newPassword = '';
    this.confirmPassword = '';
  }

  cancelarCodigoAuth() {
    this.editandoCodigoAuth = false;
    this.newPassword = '';
    this.confirmPassword = '';
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
          console.log('Respuesta exitosa:', response);

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
          console.error('Tipo de error:', typeof error);
          console.error('Error.message:', error?.message);
          console.error('Error.error:', error?.error);

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

}
