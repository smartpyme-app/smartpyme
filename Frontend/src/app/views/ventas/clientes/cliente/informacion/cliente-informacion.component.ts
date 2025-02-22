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
        this.loading = true;
        this.apiService.read('cliente/', params.id).subscribe(
          (cliente) => {
            this.cliente = cliente;
            this.loading = false;
            // Asegurarse que contactos existe
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

  public onSubmit(): void {
    this.saving = true;
    console.log('Cliente', this.cliente);

    this.apiService.store('cliente', this.cliente).subscribe(
      (cliente) => {
        if (!this.cliente.id) {
          this.alertService.success(
            'Cliente guardado',
            'El cliente fue guardado exitosamente.'
          );
        } else {
          this.alertService.success(
            'Cliente creado',
            'El cliente fue añadido exitosamente.'
          );
        }
        this.router.navigate(['/clientes']);
        this.cliente = cliente;
        this.saving = false;
      },
      (error) => {
        this.alertService.error(error);
        this.saving = false;
      }
    );
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

  openModal(template: TemplateRef<any>, contacto: any) {
    this.contacto = contacto;
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
        'Debes ingresar al menos un telefono o correo.',
        'warning'
      );
      return;
    }

    const nuevoContacto = {
      id: this.contacto.id || Date.now(), // Generar ID temporal si es nuevo
      nombre: this.contacto.nombre,
      apellido: this.contacto.apellido,
      correo: this.contacto.correo,
      telefono: this.contacto.telefono,
      cargo: this.contacto.cargo,
      fecha_nacimiento: this.contacto.fecha_nacimiento,
      red_social: this.contacto.red_social,
      nota: this.contacto.nota,
      sexo: this.contacto.sexo,
    };

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
  }
  eliminarContacto(contacto: any) {
    Swal.fire({
      title: '¿Estás seguro?',
      text: '¡No podrás revertir esto!',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#3085d6',
      cancelButtonColor: '#d33',
      confirmButtonText: 'Si, eliminar',
      cancelButtonText: 'Cancelar',
    }).then((result) => {
      if (result.isConfirmed) {
        const index = this.cliente.contactos.findIndex(
          (c: any) => c.id === contacto.id
        );
        if (index !== -1) {
          this.cliente.contactos.splice(index, 1);
        }
      }
    });
  }
}
