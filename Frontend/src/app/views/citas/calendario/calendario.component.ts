import { Component, OnInit, TemplateRef, ViewChild, forwardRef, Output, EventEmitter, LOCALE_ID, AfterViewInit, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
import { CalendarOptions, Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import interactionPlugin from '@fullcalendar/interaction';
import { FullCalendarModule } from '@fullcalendar/angular';
import { FullCalendarComponent } from '@fullcalendar/angular';
import timeGridPlugin from '@fullcalendar/timegrid';
import listPlugin from '@fullcalendar/list';
import esLocale from '@fullcalendar/core/locales/es';
import multiMonthPlugin from '@fullcalendar/multimonth'
import rrulePlugin from '@fullcalendar/rrule'

import { Router, ActivatedRoute } from '@angular/router';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { CrearEventoComponent } from '@shared/modals/crear-evento/crear-evento.component';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { BaseComponent } from '@shared/base/base.component';

import * as moment from 'moment';
import { registerLocaleData } from '@angular/common';
import localeEs from '@angular/common/locales/es';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { NgSelectComponent, NgSelectModule } from '@ng-select/ng-select';
registerLocaleData(localeEs);
@Component({
    selector: 'app-calendario',
    templateUrl: './calendario.component.html',
    standalone: true,
    imports: [CommonModule, FullCalendarModule, FormsModule, NgSelectModule, CrearEventoComponent],
    providers: [{ provide: LOCALE_ID, useValue: 'es-ES' }],
    changeDetection: ChangeDetectionStrategy.OnPush,
    
})
export class CalendarioComponent extends BaseComponent implements OnInit {

  static readonly PALETA_ENCARGADO = [
    '#DBEAFE', '#FDE68A', '#BBF7D0', '#E9D5FF', '#FBCFE8', '#CCFBF1',
    '#FED7AA', '#C7D2FE', '#FEF3C7', '#F5D0FE', '#FECACA', '#BAE6FD',
  ];

  @Output() update = new EventEmitter();
  public eventos: any = [];
  public encargadoPorAgregar: number | null = null;
  public evento: any = {};
  public filtros: any = {};
  public loading: boolean = false;
  public saving: boolean = false;
  public calendarOptions?: CalendarOptions;
  eventsModel: any;

  @ViewChild('mevento')
  public meventoTemplate!: TemplateRef<any>;
  modalRef!: BsModalRef;
  selectedPeriodType: "day" | "week" | "month" | "year" = "day";
  usuarios: any = [];
  clientes: any = [];
  sucursales: any = [];
  usuarioActual: any = {};

  constructor(public apiService: ApiService, public alertService: AlertService,
    private route: ActivatedRoute, private router: Router,
    private modalService: BsModalService,
    private cdr: ChangeDetectorRef
  ) {
    super();
  }

  @ViewChild('encargadoSelect') encargadoSelect?: NgSelectComponent;
  @ViewChild('fullcalendar') fullcalendar?: FullCalendarComponent;
  @ViewChild("fullCalendarContainer") fullCalendarContainer?: any;
  get calendar(): Calendar | undefined {
    return this.fullcalendar?.getApi();
  }
  get currentDate(): Date {
    return this.calendar?.getDate() || new Date();
  }
  timeGridMinTime = '08:00:00';
  timeGridMaxTime = '17:30:00';
  ngOnInit() {
    this.usuarioActual = this.apiService.auth_user();

    // Solo filtrar por usuario si es de tipo Citas
    if (this.isCitas()) {
      this.filtros.id_usuario = this.usuarioActual.id;
    } else {
      // Si no es Citas, no filtrar por usuario para mostrar todos los eventos
      this.filtros.id_usuario = [];
    }

    this.apiService.getAll('usuarios/list')
      .pipe(this.untilDestroyed())
      .subscribe(usuarios => {
      this.usuarios = usuarios;
      this.cdr.markForCheck();
    }, error => { this.alertService.error(error); });


    this.apiService.getAll('clientes/list')
      .pipe(this.untilDestroyed())
      .subscribe(clientes => {
      this.clientes = clientes;
      this.cdr.markForCheck();
    }, error => { this.alertService.error(error); });

    this.apiService.getAll('sucursales/list')
      .pipe(this.untilDestroyed())
      .subscribe(sucursales => {
      this.sucursales = sucursales;
      this.cdr.markForCheck();
    }, error => { this.alertService.error(error); });

    forwardRef(() => Calendar);

    this.calendarOptions = {
      // plugins: [interactionPlugin, dayGridPlugin, timeGridPlugin, listPlugin, multiMonthPlugin],
      plugins: [interactionPlugin, dayGridPlugin, timeGridPlugin, multiMonthPlugin, rrulePlugin],
      editable: true,
      navLinks: true,
      firstDay: 1,
      timeZone: 'America/El_Salvador',
      locale: esLocale,
      // themeSystem: 'bootstrap5',
      themeSystem: 'bootstrap',
      businessHours: [ // specify an array instead
        {
          daysOfWeek: [1, 2, 3, 4, 5], // Monday, Tuesday, Wednesday
          startTime: '08:00', // 8am
          endTime: '17:00' // 5pm
        },
        {
          daysOfWeek: [6], // Thursday, Friday
          startTime: '08:00', // 10am
          endTime: '12:00' // 4pm
        }
      ],
      headerToolbar: {
        left: '',
        center: '',
        right: ''
      },

      customButtons: {
        myCustomButton: {
          text: 'Nuevo',
          click: this.handleDateClick.bind(this)
        }
      },
      eventMinHeight: 48,
      displayEventTime: false,
      initialView: 'timeGridDay',
      views: {
        timeGridDay: {
          slotLabelFormat: {
            hour: 'numeric',
            minute: '2-digit',
            omitZeroMinute: false,
            meridiem: 'short'
          },
          slotMinTime: this.timeGridMinTime,
          slotMaxTime: this.timeGridMaxTime,
          headerToolbar: false,
        },
        timeGridWeek: {
          slotLabelFormat: {
            hour: 'numeric',
            minute: '2-digit',
            omitZeroMinute: false,
            meridiem: 'short'
          },
          titleFormat: {
            day: '2-digit',
            weekday: 'long',

          },
          headerToolbar: false,
        },

        dayGridMonth: {
          eventDisplay: 'block',
          headerToolbar: false,
        },
        multiMonthYear: {
          slotLabelFormat: {
            month: 'short',
            year: 'numeric'
          },
          headerToolbar: false,


        }
      },
      dateClick: this.handleDateClick.bind(this),
      eventClick: this.handleEventClick.bind(this),
      eventChange: this.handleEventChange.bind(this),
      eventContent: (arg) => this.contenidoEvento(arg),
      eventDidMount: (info) => this.pintarEvento(info),
      events: []
    };

    this.filtros.id_sucursal = this.apiService.auth_user().id_sucursal;
    this.loadAll();
  }

  public loadAll() {
    this.filtros.orden = 'inicio';
    this.filtros.direccion = 'desc';

    // Crear una copia de los filtros para no modificar el original
    const filtrosEnvio = { ...this.filtros };

    // Si los filtros son null, undefined o string vacío, no enviarlos en la petición
    const encargados = filtrosEnvio.id_usuario;
    if (Array.isArray(encargados)) {
      filtrosEnvio.id_usuario = encargados.length ? encargados.join(',') : undefined;
    }
    if (!filtrosEnvio.id_usuario) {
      delete filtrosEnvio.id_usuario;
    }
    if (!filtrosEnvio.id_cliente) {
      delete filtrosEnvio.id_cliente;
    }
    if (!filtrosEnvio.id_sucursal) {
      delete filtrosEnvio.id_sucursal;
    }

    this.loading = true;
    this.cdr.markForCheck();
    this.apiService.getAll('eventos/list', filtrosEnvio)
      .pipe(this.untilDestroyed())
      .subscribe(eventos => {
      this.loading = false;
      if (this.calendarOptions) {
        this.calendarOptions.events = eventos.map((evento: any) => {
          const estilo = this.estiloCita(evento?.data?.tipo);
          return {
            ...evento,
            classNames: [estilo.className],
            color: estilo.borderColor,
            backgroundColor: estilo.backgroundColor,
            borderColor: estilo.borderColor,
            textColor: estilo.textColor,
          };
        });
        this.updateMinMaxTime(eventos);


      }
      if (this.modalRef) {
        this.modalRef.hide();
      }
      this.update.emit();
      this.cdr.markForCheck();
    }, error => { this.alertService.error(error); this.loading = false; this.cdr.markForCheck(); });
  }


  usuariosParaFiltro(): any[] {
    const ids = new Set((this.filtros.id_usuario || []).map((id: any) => Number(id)));
    return (this.usuarios || []).filter((usuario: any) => !ids.has(Number(usuario.id)));
  }

  encargadosSeleccionados(): any[] {
    const ids = Array.isArray(this.filtros.id_usuario) ? this.filtros.id_usuario : [];
    return ids
      .map((id: number) => (this.usuarios || []).find((usuario: any) => Number(usuario.id) === Number(id)))
      .filter(Boolean);
  }

  agregarEncargado(id: number | null): void {
    if (id == null) {
      return;
    }
    const ids = Array.isArray(this.filtros.id_usuario) ? this.filtros.id_usuario : [];
    if (!ids.some((item: number) => Number(item) === Number(id))) {
      this.filtros.id_usuario = [...ids, id];
      this.loadAll();
    }
    this.encargadoPorAgregar = null;
    this.encargadoSelect?.writeValue(null);
    this.cdr.markForCheck();
  }

  quitarEncargado(id: number): void {
    this.filtros.id_usuario = (this.filtros.id_usuario || []).filter((item: number) => Number(item) !== Number(id));
    if (!this.filtros.id_usuario.length) {
      this.encargadoPorAgregar = null;
      this.encargadoSelect?.writeValue(null);
    }
    this.loadAll();
  }

  colorEncargado(id: number): string {
    const n = Math.abs(Math.trunc(Number(id))) || 0;
    return CalendarioComponent.PALETA_ENCARGADO[n % CalendarioComponent.PALETA_ENCARGADO.length];
  }

  /** Tonos 80–95 de la paleta. El azul no marca estados. */
  private estiloCita(tipo: string): { className: string; backgroundColor: string; borderColor: string; textColor: string } {
    if (tipo === 'Cancelado') {
      return { className: 'cita-cancelada', backgroundColor: '#FFFFFF', borderColor: '#C7C6CA', textColor: '#919094' };
    }
    if (tipo === 'Pagado') {
      return { className: 'cita-pagada', backgroundColor: '#BBF7D0', borderColor: '#86EFAC', textColor: '#14532D' };
    }
    if (tipo === 'Confirmado') {
      return { className: 'cita-confirmada', backgroundColor: '#D7E2FF', borderColor: '#ABC7FF', textColor: '#001B3F' };
    }
    if (tipo === 'Pendiente') {
      return { className: 'cita-pendiente', backgroundColor: '#FFFFFF', borderColor: '#919094', textColor: '#919094' };
    }
    return { className: 'cita-abierta', backgroundColor: '#FFFFFF', borderColor: '#FFB777', textColor: '#2F1500' };
  }

  contenidoEvento(arg: any) {
    if (arg.view?.type === 'multiMonthYear') {
      return;
    }
    if (arg.view?.type === 'dayGridMonth') {
      const hora = arg.timeText ? `${this.textoPlano(arg.timeText)} ` : '';
      const titulo = this.textoPlano(arg.event.title || '');
      return { html: `<div class="cita-evento-mes">${hora}${titulo}</div>` };
    }
    const datos = arg.event.extendedProps?.data || {};
    const lineas = [datos.descripcion || arg.event.title, datos.nombre_cliente, datos.nombre_usuario]
      .filter((linea) => linea);
    const html = lineas.map((linea) => `<div class="cita-linea">${this.textoPlano(String(linea))}</div>`).join('');
    return { html: `<div class="cita-evento">${html}</div>` };
  }

  textoPlano(value: string): string {
    return String(value).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  }

  pintarEvento(info: any) {
    const estilo = this.estiloCita(info.event.extendedProps?.data?.tipo);
    info.el.classList.add(estilo.className);
    info.el.style.backgroundColor = estilo.backgroundColor;
    info.el.style.borderColor = estilo.borderColor;
    info.el.style.color = estilo.textColor;
    info.el.style.overflow = 'hidden';
    info.el.addEventListener('mouseenter', (event: MouseEvent) => this.mostrarFicha(info.event, event));
    info.el.addEventListener('mouseleave', () => this.ocultarFicha());
  }

  private fichaCita?: HTMLDivElement;

  private mostrarFicha(fcEvent: any, mouse: MouseEvent): void {
    const datos = fcEvent.extendedProps?.data || {};
    const productos = (datos.productos || [])
      .map((linea: any) => linea?.nombre_producto)
      .filter(Boolean)
      .join(', ');
    const inicio = fcEvent.start ? moment(fcEvent.start).format('DD/MM/YYYY hh:mm a') : '';
    const fin = fcEvent.end ? moment(fcEvent.end).format('hh:mm a') : '';
    const titulo = this.textoPlano(String(datos.descripcion || fcEvent.title || ''));
    const dato = (etiqueta: string, valor: string) =>
      valor ? `<div><strong>${etiqueta}:</strong> ${this.textoPlano(valor)}</div>` : '';
    const ficha = this.asegurarFicha();
    ficha.innerHTML = [
      titulo ? `<div class="cita-ficha-titulo"><strong>${titulo}</strong></div>` : '',
      dato('Cliente', datos.nombre_cliente || ''),
      dato('Encargado', datos.nombre_usuario || ''),
      dato('Estado', datos.tipo || ''),
      dato('Horario', inicio ? `${inicio}${fin ? ' – ' + fin : ''}` : ''),
      dato('Productos', productos),
    ].join('');
    ficha.hidden = false;
    ficha.style.left = `${Math.min(mouse.clientX + 12, window.innerWidth - 300)}px`;
    ficha.style.top = `${Math.min(mouse.clientY + 12, window.innerHeight - 180)}px`;
  }

  private ocultarFicha(): void {
    if (this.fichaCita) {
      this.fichaCita.hidden = true;
    }
  }

  private asegurarFicha(): HTMLDivElement {
    if (this.fichaCita) {
      return this.fichaCita;
    }
    const ficha = document.createElement('div');
    ficha.className = 'cita-ficha';
    ficha.hidden = true;
    document.body.appendChild(ficha);
    this.destroyRef.onDestroy(() => ficha.remove());
    this.fichaCita = ficha;
    return ficha;
  }

  isCitas() {
    return this.usuarioActual.tipo === 'Citas';
  }

  isVentas() {
    return this.usuarioActual.tipo === 'Ventas' || this.usuarioActual.tipo === 'Ventas Limitado';
  }

  isVentasOrCitas() {
    return this.isVentas() || this.isCitas();
  }


  updateMinMaxTime(events: any[]) {
    // if (events.length == 0) return;
    let minTime = moment().set('hour', 8).set('minute', 0).set('second', 0).format('HH:mm:ss');
    let maxTime = moment().set('hour', 17).set('minute', 30).set('second', 0).format('HH:mm:ss');
    for (let index = 0; index < events.length; index++) {
      const event = events[index];

      let start = moment(event.start).format('HH:mm:ss');
      let end = moment(event.end).format('HH:mm:ss');
      if (start < minTime) {
        minTime = start;
      }
      if (end > maxTime) {
        maxTime = end;
      }
    }
    this.timeGridMinTime = minTime;
    this.timeGridMaxTime = maxTime;

    //set slotMinTime and slotMaxTime on HH:00:00 format of minTime and maxTime
    // this.calendar?.setOption('slotMinTime', moment(this.timeGridMinTime).format('HH:00:00'));
    // this.calendar?.setOption('slotMaxTime', moment(this.timeGridMaxTime).format('HH:00:00'));

    this.calendar?.setOption('slotMinTime', this.timeGridMinTime);
    this.calendar?.setOption('slotMaxTime', this.timeGridMaxTime);

  }
  handleDateClick(arg: any) {
    this.evento = {};
    this.evento.frecuencia = '';
    this.evento.tipo = 'Sin confirmar';
    this.evento.duracion = "1 hora";
    this.evento.estado = "Activo";
    this.evento.id_cliente = '';
    this.evento.id_servicio = '';
    this.evento.productos = [];
    this.evento.id_empresa = this.apiService.auth_user().id_empresa;
    this.evento.id_usuario = this.apiService.auth_user().id;
    this.evento.id_sucursal = this.apiService.auth_user().id_sucursal;
    
    // Formatear la fecha correctamente para datetime-local (YYYY-MM-DDTHH:mm)
    const fechaClick = moment(arg.dateStr);
    const horaActual = moment().format('HH:mm');
    // Formato para el input datetime-local
    this.evento.inicio = fechaClick.format('YYYY-MM-DD') + 'T' + horaActual;
    this.setTime();
    
    this.alertService.modal = true;
    this.modalRef = this.modalService.show(this.meventoTemplate, { class: 'modal-lg', backdrop: 'static' });
  }


  handleEventClick(arg: any) {
    // Obtener el evento completo desde extendedProps.data
    const eventoData = arg.event.extendedProps?.data;
    
    if (eventoData) {
      // Si el evento tiene un ID, cargarlo completo desde el backend para asegurar que tenga todos los datos
      if (eventoData.id) {
        this.apiService.read('evento/', eventoData.id)
          .pipe(this.untilDestroyed())
          .subscribe((eventoCompleto: any) => {
          this.evento = eventoCompleto;
          // Asegurar que los productos estén inicializados
          if (!this.evento.productos) {
            this.evento.productos = [];
          }
          this.alertService.modal = true;
          this.modalRef = this.modalService.show(this.meventoTemplate, { class: 'modal-lg', backdrop: 'static' });
        }, error => {
          // Si falla la carga, usar los datos del evento del calendario
          this.evento = eventoData;
          if (!this.evento.productos) {
            this.evento.productos = [];
          }
          this.alertService.modal = true;
          this.modalRef = this.modalService.show(this.meventoTemplate, { class: 'modal-lg', backdrop: 'static' });
        });
      } else {
        // Si no tiene ID, usar los datos directamente
        this.evento = eventoData;
        if (!this.evento.productos) {
          this.evento.productos = [];
        }
        this.alertService.modal = true;
        this.modalRef = this.modalService.show(this.meventoTemplate, { class: 'modal-lg', backdrop: 'static' });
      }
    } else {
      // Si no hay datos en extendedProps, intentar obtenerlos del evento directamente
      this.evento = {
        id: arg.event.id,
        descripcion: arg.event.title,
        inicio: arg.event.startStr,
        fin: arg.event.endStr,
        productos: []
      };
      this.alertService.modal = true;
      this.modalRef = this.modalService.show(this.meventoTemplate, { class: 'modal-lg', backdrop: 'static' });
    }
  }

  setTime() {
    if (!this.evento.inicio) {
      return;
    }
    
    let fecha = moment(this.evento.inicio);

    if (this.evento.duracion == '15 minutos') {
      this.evento.fin = fecha.clone().add(15, 'minutes').format('YYYY-MM-DD HH:mm:ss');
    }
    if (this.evento.duracion == '30 minutos') {
      this.evento.fin = fecha.clone().add(30, 'minutes').format('YYYY-MM-DD HH:mm:ss');
    }
    if (this.evento.duracion == '1 hora') {
      this.evento.fin = fecha.clone().add(1, 'hour').format('YYYY-MM-DD HH:mm:ss');
    }
    if (this.evento.duracion == '2 horas') {
      this.evento.fin = fecha.clone().add(2, 'hours').format('YYYY-MM-DD HH:mm:ss');
    }
    if (this.evento.duracion == '3 horas') {
      this.evento.fin = fecha.clone().add(3, 'hours').format('YYYY-MM-DD HH:mm:ss');
    }
    if (this.evento.duracion == '5 horas') {
      this.evento.fin = fecha.clone().add(5, 'hours').format('YYYY-MM-DD HH:mm:ss');
    }
    if (this.evento.duracion == '8 horas') {
      this.evento.fin = fecha.clone().add(8, 'hours').format('YYYY-MM-DD HH:mm:ss');
    }
    if (this.evento.duracion == '12 horas') {
      this.evento.fin = fecha.clone().add(12, 'hours').format('YYYY-MM-DD HH:mm:ss');
    }
  }

  handleEventChange(arg: any) {
    this.evento = arg.event.extendedProps.data;
    this.evento.inicio = moment(arg.event.start).format('YYYY-MM-DD HH:mm');
    this.setTime();
    this.onSubmit();
  }

  public onSubmit() {
    this.saving = true;
    this.cdr.markForCheck();
    this.apiService.store('evento', this.evento)
      .pipe(this.untilDestroyed())
      .subscribe(evento => {
      if (!this.evento.id) {
        this.alertService.success('Cita creada', 'La cita fue añadida exitosamente.');
      } else {
        this.alertService.success('Cita guardada', 'La cita fue guardada exitosamente.');
      }
      this.loadAll();
      this.saving = false;
      if (this.modalRef) {
        this.modalRef.hide();
      }
      this.alertService.modal = false;
      this.cdr.markForCheck();
    }, error => { this.alertService.error(error); this.saving = false; this.cdr.markForCheck(); });
  }

  onEventoUpdate() {
    // Actualizar calendario y emitir evento para que el componente padre también se actualice
    this.loadAll();
    this.update.emit();
    // Cerrar el modal si está abierto
    if (this.modalRef) {
      this.modalRef.hide();
      this.alertService.modal = false;
    }
    this.cdr.markForCheck();
  }
  setNowSelection() {
    this.selectedPeriodType = "day";
    this.calendar?.changeView('timeGridDay');
    this.cdr.markForCheck();
  }
  setMonthSelection() {
    this.selectedPeriodType = "month";
    this.calendar?.changeView('dayGridMonth');
    this.cdr.markForCheck();
  }
  setWeekSelection() {
    this.selectedPeriodType = "week";
    this.calendar?.changeView('timeGridWeek');
    this.cdr.markForCheck();
  }
  setYearSelection() {
    this.selectedPeriodType = "year";
    this.calendar?.changeView('multiMonthYear');
    this.cdr.markForCheck();
  }

  nextDay() {
    this.calendar?.next();
    this.cdr.markForCheck();
  }
  prevDay() {
    this.calendar?.prev();
    this.cdr.markForCheck();
  }

}

