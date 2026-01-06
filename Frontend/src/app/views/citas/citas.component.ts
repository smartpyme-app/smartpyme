import { Component, OnInit, TemplateRef, ViewChild, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { PopoverModule } from 'ngx-bootstrap/popover';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { ModalManagerService } from '@services/modal-manager.service';
import { BaseCrudComponent } from '@shared/base/base-crud.component';

import { CalendarioComponent } from './calendario/calendario.component';
import { CrearEventoComponent } from '@shared/modals/crear-evento/crear-evento.component';
import { TruncatePipe } from '@pipes/truncate.pipe';

import * as moment from 'moment';
import Swal from 'sweetalert2';
import { NgSelectModule } from '@ng-select/ng-select';
import { LazyImageDirective } from '../../directives/lazy-image.directive';

@Component({
    selector: 'app-citas',
    templateUrl: './citas.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, CalendarioComponent, NgSelectModule, PopoverModule, TooltipModule, CrearEventoComponent, TruncatePipe, LazyImageDirective],
    changeDetection: ChangeDetectionStrategy.OnPush,
    
})

export class CitasComponent extends BaseCrudComponent<any> implements OnInit {
  @ViewChild('calendario') calendario!: CalendarioComponent;

  public eventos: any = [];
  public evento: any = {};

  public clientes: any = [];
  public usuario: any = {};
  public usuarios: any = [];
  public sucursales: any = [];
  public formaPagos: any = [];
  public documentos: any = [];
  public canales: any = [];
  public filtrado: boolean = false;
  public usuarioActual: any = {};

  constructor(
    protected override apiService: ApiService,
    protected override alertService: AlertService,
    protected override modalManager: ModalManagerService,
    private cdr: ChangeDetectorRef
  ) {
    super(apiService, alertService, modalManager, {
      endpoint: 'evento',
      itemsProperty: 'eventos',
      itemProperty: 'evento',
      reloadAfterSave: false,
      reloadAfterDelete: false,
      messages: {
        created: 'La cita fue añadida exitosamente.',
        updated: 'La cita fue guardada exitosamente.',
        deleted: 'Cita eliminada exitosamente.',
        createTitle: 'Cita creada',
        updateTitle: 'Cita guardada',
        deleteTitle: 'Cita eliminada',
        deleteConfirm: '¿Estás seguro?'
      },
      initNewItem: (item) => {
        item.id_empresa = apiService.auth_user().id_empresa;
        item.id_usuario = apiService.auth_user().id;
        item.frecuencia = '';
        item.tipo = 'Sin confirmar';
        item.duracion = "1 hora";
        item.estado = "Activo";
        item.id_cliente = '';
        item.id_servicio = '';
        item.productos = [];
        item.id_sucursal = apiService.auth_user().id_sucursal;
        item.inicio = moment().format('YYYY-MM-DD HH') + ':00';
        return item;
      },
      afterSave: () => {
        this.calendario?.loadAll();
        this.filtrarEventos();
      },
      afterDelete: () => {
        this.calendario?.loadAll();
        this.filtrarEventos();
      }
    });
  }

  protected aplicarFiltros(): void {
    this.filtrarEventos();
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

  public override loadAll() {
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
    this.cdr.markForCheck();
    this.apiService.getAll('eventos', this.filtros)
      .pipe(this.untilDestroyed())
      .subscribe(eventos => {
      this.eventos = eventos;
      this.loading = false;
      if (this.modalRef) {
        this.closeModal();
      }
      this.cdr.markForCheck();
    }, error => { this.alertService.error(error); this.loading = false; this.cdr.markForCheck(); });

  }

  updateCalendar() {
    if (this.calendario) {
      this.calendario.loadAll();
    }
    this.filtrarEventos();
    this.cdr.markForCheck();
  }

  onEventoUpdate() {
    // Actualizar lista y calendario sin resetear filtros
    this.filtrarEventos();
    this.updateCalendar();
    // Cerrar el modal si está abierto
    if (this.modalRef) {
      this.closeModal();
    }
    this.cdr.markForCheck();
  }

  // Cuando se abra un modal, comprueba si no existen datos en clientes y si no existen, obtenerlas
  public override openModal(template: TemplateRef<any>, evento?: any) {
    // Comprobar si no existen datos en clientes y si no existen, obtenerlas
    if (!this.clientes || this.clientes.length === 0) {
      this.obtenerClientes();
    }

    super.openModal(template, evento, { class: 'modal-lg', backdrop: 'static' });
    
    if (!this.evento.id && this.evento.inicio) {
      this.setTimeForEvento(this.evento);
    }
  }

  public setEstado(evento: any, estado: any) {
    evento.tipo = estado;
    this.onSubmit(evento, true);
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

  public override delete(evento: any) {
    Swal.fire({
      title: '¿Estás seguro?',
      text: '¡No podrás revertir esto!',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Sí, eliminarlo',
      cancelButtonText: 'Cancelar'
    }).then((result) => {
      if (result.isConfirmed) {
        super.delete(evento.id || evento);
      }
    });
  }

  public override async onSubmit(item?: any, isStatusChange: boolean = false) {
    const eventoToSave = item || this.evento;
    
    if (!isStatusChange && eventoToSave.inicio && eventoToSave.duracion) {
      this.setTimeForEvento(eventoToSave);
    }

    await super.onSubmit(eventoToSave, isStatusChange);
    
    if (!eventoToSave.id) {
      this.loadAll();
      }
  }

  private setTimeForEvento(evento: any) {
    let fecha = moment(evento.inicio);

    if (evento.duracion == '15 minutos') {
      evento.fin = fecha.add(15, 'minutes').format('YYYY-MM-DD HH:mm');
    }
    if (evento.duracion == '30 minutos') {
      evento.fin = fecha.add(30, 'minutes').format('YYYY-MM-DD HH:mm');
    }
    if (evento.duracion == '1 hora') {
      evento.fin = fecha.add(1, 'hour').format('YYYY-MM-DD HH:mm');
    }
    if (evento.duracion == '2 horas') {
      evento.fin = fecha.add(2, 'hour').format('YYYY-MM-DD HH:mm');
      }
    if (evento.duracion == '3 horas') {
      evento.fin = fecha.add(3, 'hour').format('YYYY-MM-DD HH:mm');
      }
    if (evento.duracion == '5 horas') {
      evento.fin = fecha.add(5, 'hour').format('YYYY-MM-DD HH:mm');
    }
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
    this.apiService.getAll('clientes/list')
      .pipe(this.untilDestroyed())
      .subscribe(clientes => {
      this.clientes = clientes;
      this.cdr.markForCheck();
    }, error => {
      this.alertService.error(error);
    });
  }

}
