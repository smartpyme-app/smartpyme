import { Component, OnInit, TemplateRef, ViewChild } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { PopoverModule } from 'ngx-bootstrap/popover';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { ModalManagerService } from '@services/modal-manager.service';
import { BaseModalComponent } from '@shared/base/base-modal.component';

import { CalendarioComponent } from './calendario/calendario.component';
import { CrearEventoComponent } from '@shared/modals/crear-evento/crear-evento.component';
import { TruncatePipe } from '@pipes/truncate.pipe';

import * as moment from 'moment';
import Swal from 'sweetalert2';
import { NgSelectModule } from '@ng-select/ng-select';

@Component({
    selector: 'app-citas',
    templateUrl: './citas.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, CalendarioComponent, NgSelectModule, PopoverModule, TooltipModule, CrearEventoComponent, TruncatePipe],
    
})

export class CitasComponent extends BaseModalComponent implements OnInit {
  @ViewChild('calendario') calendario!: CalendarioComponent;

  public eventos: any = [];
  public evento: any = {};
  public override loading: boolean = false;
  public override saving: boolean = false;

  public clientes: any = [];
  public usuario: any = {};
  public usuarios: any = [];
  public sucursales: any = [];
  public formaPagos: any = [];
  public documentos: any = [];
  public canales: any = [];
  public filtros: any = {};
  public filtrado: boolean = false;
  public usuarioActual: any = {};
  // public filtros:any = {};

  constructor(
    public apiService: ApiService,
    protected override alertService: AlertService,
    protected override modalManager: ModalManagerService
  ) {
    super(modalManager, alertService);
  }

  ngOnInit() {
    this.loadAll();
  }

  public setOrden(columna: string) {
    if (this.filtros.orden === columna) {
      this.filtros.direccion = this.filtros.direccion === 'asc' ? 'desc' : 'asc';
    } else {
      this.filtros.orden = columna;
      this.filtros.direccion = 'asc';
    }

    this.filtrarEventos();

    this.usuarioActual = this.apiService.auth_user();
    
    if (this.isCitas()) {
      this.filtros.id_usuario = this.usuarioActual.id;
    }
  }

  isCitas() {
    return this.apiService.auth_user().rol === 'Citas';
  }

  public loadAll() {
    this.filtros.id_sucursal = this.apiService.auth_user().id_sucursal;
    this.filtros.id_cliente = '';
    this.filtros.id_usuario = '';
    // Mostrar eventos desde hoy en adelante
    this.filtros.inicio = moment().startOf('day').format('YYYY-MM-DD HH:mm');
    this.filtros.fin = moment().add(30, 'days').endOf('day').format('YYYY-MM-DD HH:mm');
    this.filtros.tipo = '';
    this.filtros.estado = '';
    this.filtros.buscador = '';
    this.filtros.orden = 'inicio';
    this.filtros.direccion = 'asc';
    this.filtros.paginate = 10;
    this.filtrarEventos();
  }

  public filtrarEventos() {
    this.loading = true;
    this.apiService.getAll('eventos', this.filtros).subscribe(eventos => {
      this.eventos = eventos;
      this.loading = false;
      if (this.modalRef) {
        this.closeModal();
      }
    }, error => { this.alertService.error(error); this.loading = false; });

  }

  updateCalendar() {
    if (this.calendario) {
      this.calendario.loadAll();
    }
    this.filtrarEventos();
  }

  onEventoUpdate() {
    // Actualizar lista y calendario sin resetear filtros
    this.filtrarEventos();
    this.updateCalendar();
    // Cerrar el modal si está abierto
    if (this.modalRef) {
      this.closeModal();
    }
  }

  // Cuando se abra un modal, comprueba si no existen datos en clientes y si no existen, obtenerlas
  public override openModal(template: TemplateRef<any>, evento: any) {
    this.evento = evento;

    // Comprobar si no existen datos en clientes y si no existen, obtenerlas
    if (!this.clientes || this.clientes.length === 0) {
      this.obtenerClientes();
    }

    if (!this.evento.id) {
      this.evento.id_empresa = this.apiService.auth_user().id_empresa;
      this.evento.id_usuario = this.apiService.auth_user().id;
      this.evento.frecuencia = '';
      this.evento.tipo = 'Sin confirmar';
      this.evento.duracion = "1 hora";
      this.evento.estado = "Activo";
      this.evento.id_cliente = '';
      this.evento.id_servicio = '';
      this.evento.productos = [];
      this.evento.id_sucursal = this.apiService.auth_user().id_sucursal;
      this.evento.inicio = moment().format('YYYY-MM-DD HH') + ':00';
      this.setTime();
    }
    super.openModal(template, { class: 'modal-lg', backdrop: 'static' });
  }

  setTime() {
    let fecha = moment(this.evento.inicio);

    if (this.evento.duracion == '15 minutos') {
      this.evento.fin = fecha.add(15, 'minutes').format('YYYY-MM-DD HH:mm');
    }
    if (this.evento.duracion == '30 minutos') {
      this.evento.fin = fecha.add(30, 'minutes').format('YYYY-MM-DD HH:mm');
    }
    if (this.evento.duracion == '1 hora') {
      this.evento.fin = fecha.add(1, 'hour').format('YYYY-MM-DD HH:mm');
    }
    if (this.evento.duracion == '2 horas') {
      this.evento.fin = fecha.add(2, 'hour').format('YYYY-MM-DD HH:mm');
    }
    if (this.evento.duracion == '3 horas') {
      this.evento.fin = fecha.add(3, 'hour').format('YYYY-MM-DD HH:mm');
    }
    if (this.evento.duracion == '5 horas') {
      this.evento.fin = fecha.add(5, 'hour').format('YYYY-MM-DD HH:mm');
    }
  }

  public setEstado(evento: any, estado: any) {
    this.evento = evento;
    this.evento.tipo = estado;
    this.onSubmit();
  }

  agregarEventoAlCalendario(evento: any) {
    let detalles = (evento.detalles ? evento.detalles : '') + ' - ' + (evento.cliente.nombre_completo ? evento.cliente.nombre_completo : '') + ' ' + (evento.cliente.correo ? evento.cliente.correo : '');
    const event = {
      title: evento.descripcion ? evento.descripcion : '',
      description: detalles,
      location: '',
      startDate: evento.inicio,
      endDate: evento.fin,
      attendees: [
        { email: evento.correo },
        // Agrega otros asistentes si es necesario
      ],
    };

    const enlaceCalendario = this.apiService.generateGoogleCalendarLink(event);

    // Abre una nueva ventana o pestaña con el enlace al evento de Google Calendar
    window.open(enlaceCalendario, '_blank');
  }

  public delete(evento: any) {

    Swal.fire({
      title: '¿Estás seguro?',
      text: '¡No podrás revertir esto!',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Sí, eliminarlo',
      cancelButtonText: 'Cancelar'
    }).then((result) => {
      if (result.isConfirmed) {
        this.apiService.delete('evento/', evento.id).subscribe(data => {
          for (let i = 0; i < this.eventos.data.length; i++) {
            if (this.eventos.data[i].id == data.id)
              this.eventos.data.splice(i, 1);
          }
          this.calendario.loadAll();
          this.filtrarEventos();
        }, error => { this.alertService.error(error); });
      } else if (result.dismiss === Swal.DismissReason.cancel) {
        // Swal.fire('Cancelado', 'Tu archivo está seguro :)', 'info');
      }
    });

  }

  public onSubmit() {
    this.saving = true;
    this.apiService.store('evento', this.evento).subscribe(evento => {
      if (!this.evento.id) {
        this.loadAll();
        this.alertService.success('Cita creada', 'La cita fue añadida exitosamente.');
      } else {
        this.alertService.success('Cita guardada', 'La cita fue guardada exitosamente.');
      }
      this.calendario.loadAll();
      this.saving = false;
      if (this.modalRef) {
        this.closeModal();
      }
    }, error => { this.alertService.error(error); this.saving = false; });
  }

  // Cuando se abra un modal de filtro, comprueba si no existen datos en clientes y si no existen, obtenerlas
  public openFilter(template: TemplateRef<any>) {
    this.filtros.inicio = '';
    this.filtros.fin = '';

    if (!this.clientes || this.clientes.length === 0) {
      this.obtenerClientes();
    }

    this.openModal(template, null);
  }

  obtenerClientes() {
    this.apiService.getAll('clientes/list').subscribe(clientes => {
      this.clientes = clientes;
    }, error => {
      this.alertService.error(error);
    });
  }

}
