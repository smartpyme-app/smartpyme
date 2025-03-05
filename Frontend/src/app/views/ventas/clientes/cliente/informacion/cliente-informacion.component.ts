import { Component, OnInit, TemplateRef } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';

import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import Swal from 'sweetalert2';

@Component({
  selector: 'app-cliente-informacion',
  templateUrl: './cliente-informacion.component.html',
})
export class ClienteInformacionComponent implements OnInit {
  public cliente: any = {
    contactos: [], // Inicializar el array de contactos
  };
  public loading = false;
  public saving = false;
  public paises: any = [];
  public departamentos: any = [];
  public distritos: any = [];
  public municipios: any = [];
  public actividad_economicas: any = [];
  public contacto: any = {};
  //loading
  public loading_contacto = false;
  public esNuevo = false;

  modalRef?: BsModalRef;

  constructor(
    public apiService: ApiService,
    private alertService: AlertService,
    private route: ActivatedRoute,
    private router: Router,
    private modalService: BsModalService
  ) {}

  ngOnInit() {
    this.loadAll();
    this.paises = JSON.parse(localStorage.getItem('paises')!);
    this.departamentos = JSON.parse(localStorage.getItem('departamentos')!);
    this.distritos = JSON.parse(localStorage.getItem('distritos')!);
    this.municipios = JSON.parse(localStorage.getItem('municipios')!);
    this.actividad_economicas = JSON.parse(
      localStorage.getItem('actividad_economicas')!
    );
  }

  public loadAll() {
    this.route.params.subscribe((params: any) => {
      if (params.id) {
        this.esNuevo = false;
        this.loading = true;
        this.apiService.read('cliente/', params.id).subscribe(
          (cliente) => {
            this.cliente = cliente;
            this.loading = false;
            if (!this.cliente.contactos) {
              this.cliente.contactos = [];
            }
          },
          (error) => {
            this.alertService.error(error);
            this.loading = false;
          }
        );
      } else {
        this.esNuevo = true;
        this.cliente = {};
        this.cliente.tipo = 'Persona';
        this.cliente.contactos = [];
        this.cliente.tipo_contribuyente = '';
        this.cliente.id_empresa = this.apiService.auth_user().id_empresa;
        this.cliente.id_usuario = this.apiService.auth_user().id;
      }
    });
  }

  public setTipo(tipo: any) {
    this.cliente.tipo = tipo;
  }

  setPais() {
    this.cliente.pais = this.paises.find(
      (item: any) => item.cod == this.cliente.cod_pais
    ).nombre;
  }

  setGiro() {
    this.cliente.giro = this.actividad_economicas.find(
      (item: any) => item.cod == this.cliente.cod_giro
    ).nombre;
    console.log(this.cliente.giro);
  }

  setDistrito() {
    let distrito = this.distritos.find(
      (item: any) =>
        item.cod == this.cliente.cod_distrito &&
        item.cod_departamento == this.cliente.cod_departamento
    );
    console.log(distrito);
    if (distrito) {
      this.cliente.cod_municipio = distrito.cod_municipio;
      this.setMunicipio();
      this.cliente.distrito = distrito.nombre;
      this.cliente.cod_distrito = distrito.cod;
    }
  }

  setMunicipio() {
    let municipio = this.municipios.find(
      (item: any) =>
        item.cod == this.cliente.cod_municipio &&
        item.cod_departamento == this.cliente.cod_departamento
    );
    if (municipio) {
      this.cliente.municipio = municipio.nombre;
      this.cliente.cod_municipio = municipio.cod;

      this.cliente.distrito = '';
      this.cliente.cod_distrito = '';
    }
  }

  setDepartamento() {
    let departamento = this.departamentos.find(
      (item: any) => item.cod == this.cliente.cod_departamento
    );
    if (departamento) {
      this.cliente.departamento = departamento.nombre;
      this.cliente.cod_departamento = departamento.cod;
    }
    this.cliente.municipio = '';
    this.cliente.cod_municipio = '';
    this.cliente.distrito = '';
    this.cliente.cod_distrito = '';
  }

  //   public onSubmit(): void {
  //     this.saving = true;
  //     console.log('Cliente', this.cliente);

  //     this.apiService.store('cliente', this.cliente).subscribe(
  //       (cliente) => {
  //         if (!this.cliente.id) {
  //           this.alertService.success(
  //             'Cliente guardado',
  //             'El cliente fue guardado exitosamente.'
  //           );
  //         } else {
  //           this.alertService.success(
  //             'Cliente creado',
  //             'El cliente fue añadido exitosamente.'
  //           );
  //           this.router.navigate(['/clientes']);
  //           this.cliente = cliente;
  //           this.saving = false;
  //         }
  //       },
  //       (error) => {
  //         this.alertService.error(error);
  //         this.saving = false;
  //       }
  //     );
  //   }

  public onSubmit(): void {
    this.saving = true;

    this.apiService.store('cliente', this.cliente).subscribe({
      next: (cliente) => {
        const titulo = this.esNuevo ? 'Cliente creado' : 'Cliente actualizado';
        const mensaje = this.esNuevo
          ? 'El cliente fue creado exitosamente.'
          : 'El cliente fue actualizado exitosamente.';

        this.alertService.success(titulo, mensaje);

        this.cliente = cliente;
        if (this.esNuevo) {
          //this.router.navigate(['/clientes']);
          //cliente/editar
          this.router.navigate(['/cliente/editar', cliente.id]);
        }

        this.saving = false;
      },
      error: (error) => {
        this.alertService.error(error);
        this.saving = false;
      },
    });
  }

  public verificarSiExiste() {
    if (this.cliente.nombre && this.cliente.apellido) {
      this.apiService
        .getAll('clientes', {
          nombre: this.cliente.nombre,
          apellido: this.cliente.apellido,
          estado: 1,
        })
        .subscribe(
          (clientes) => {
            if (clientes.data[0]) {
              this.alertService.warning(
                '🚨 Alerta duplicado: Hemos encontrado otro registro similar con estos datos.',
                'Por favor, verifica su información acá: <a class="btn btn-link" target="_blank" href="' +
                  this.apiService.appUrl +
                  '/cliente/editar/' +
                  clientes.data[0].id +
                  '">Ver cliente</a>. <br> Puedes ignorar esta alerta si consideras que no estas duplicando el registros.'
              );
            }
            this.loading = false;
          },
          (error) => {
            this.alertService.error(error);
            this.loading = false;
          }
        );
    }
  }

  // openModal(template: TemplateRef<any>, contacto: any) {
  //   this.contacto = contacto;
  //   this.modalRef = this.modalService.show(template, {
  //     class: 'modal-lg',
  //     backdrop: 'static',
  //   });
  // }

  openModal(template: TemplateRef<any>, contacto: any) {

    if (!contacto || contacto === null) {
      
      this.contacto = {};
    } else {
      
      this.contacto = { ...contacto };
    }

    this.modalRef = this.modalService.show(template, {
      class: 'modal-lg',
      backdrop: 'static',
    });
  }

  
  agregarContacto(template: TemplateRef<any>) {
    this.contacto = {};
    this.modalRef = this.modalService.show(template, {
      class: 'modal-lg',
      backdrop: 'static',
    });
  }

  submit(event: Event) {
    event.preventDefault();

    if (!this.cliente.contactos) {
      this.cliente.contactos = [];
    }

    if (!this.contacto.nombre && !this.contacto.apellido) {
      Swal.fire(
        '🚨 Alerta',
        'Debes ingresar al menos un nombre o apellido.',
        'warning'
      );
      return;
    }

    if (!this.contacto.telefono && !this.contacto.correo) {
      Swal.fire(
        '🚨 Alerta',
        'Debes ingresar al menos un teléfono o correo.',
        'warning'
      );
      return;
    }
    const nuevoContacto = {
      id: this.contacto.id || Date.now(),
      nombre: this.contacto.nombre,
      apellido: this.contacto.apellido,
      correo: this.contacto.correo,
      telefono: this.contacto.telefono,
      cargo: this.contacto.cargo,
      fecha_nacimiento: this.contacto.fecha_nacimiento,
      red_social: this.contacto.red_social,
      nota: this.contacto.nota,
      sexo: this.contacto.sexo,
      id_cliente: this.cliente.id,
    };

    if (this.cliente.id) {
      this.loading_contacto = true;

      this.apiService.store('cliente/contacto', nuevoContacto).subscribe({
        next: (contactoGuardado) => {
          const index = this.cliente.contactos.findIndex(
            (c: any) => c.id === contactoGuardado.id
          );

          if (index !== -1) {
            this.cliente.contactos[index] = contactoGuardado;
          } else {
            this.cliente.contactos.push(contactoGuardado);
          }

          this.alertService.success(
            'Contacto guardado',
            'El contacto fue guardado exitosamente.'
          );

          this.contacto = {};
          this.loading_contacto = false;
          if (this.modalRef) {
            this.modalRef.hide();
          }
        },
        error: (error) => {
          this.alertService.error('Error al guardar el contacto: ' + error);
          this.loading_contacto = false;
        },
      });
    } else {
      const index = this.cliente.contactos.findIndex(
        (c: any) => c.id === nuevoContacto.id
      );

      if (index !== -1) {
        this.cliente.contactos[index] = { ...nuevoContacto };
      } else {
        this.cliente.contactos.push(nuevoContacto);
      }

      this.contacto = {};
      if (this.modalRef) {
        this.modalRef.hide();
      }

      this.alertService.success(
        'Contacto agregado',
        'El contacto fue agregado a la lista. Se guardará cuando guarde el cliente.'
      );
    }
  }

  eliminarContacto(contacto: any) {
    Swal.fire({
      title: '¿Estás seguro?',
      text: '¡No podrás revertir esto!',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#3085d6',
      cancelButtonColor: '#d33',
      confirmButtonText: 'Sí, eliminar',
      cancelButtonText: 'Cancelar',
    }).then((result) => {
      if (result.isConfirmed) {
        if (contacto.id && this.cliente.id) {
          this.loading = true;

          this.apiService.delete('cliente/contacto/', contacto.id).subscribe({
            next: () => {
              const index = this.cliente.contactos.findIndex(
                (c: any) => c.id === contacto.id
              );
              if (index !== -1) {
                this.cliente.contactos.splice(index, 1);
              }

              this.alertService.success(
                'Contacto eliminado',
                'El contacto fue eliminado exitosamente.'
              );
              this.loading = false;
            },
            error: (error) => {
              this.alertService.error(
                'Error al eliminar el contacto: ' + error
              );
              this.loading = false;
            },
          });
        } else {
          const index = this.cliente.contactos.findIndex(
            (c: any) => c.id === contacto.id
          );
          if (index !== -1) {
            this.cliente.contactos.splice(index, 1);
            this.alertService.success(
              'Contacto eliminado',
              'El contacto fue eliminado de la lista.'
            );
          }
        }
      }
    });
  }
}
