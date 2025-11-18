import { Component, OnInit, TemplateRef, ViewChild, forwardRef, Output, EventEmitter, LOCALE_ID, AfterViewInit, DestroyRef, inject } from '@angular/core';
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
import { subscriptionHelper } from '@shared/utils/subscription.helper';

import * as moment from 'moment';
import { registerLocaleData } from '@angular/common';
import localeEs from '@angular/common/locales/es';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { NgSelectModule } from '@ng-select/ng-select';
registerLocaleData(localeEs);
@Component({
    selector: 'app-calendario',
    templateUrl: './calendario.component.html',
    standalone: true,
    imports: [CommonModule, FullCalendarModule, FormsModule, NgSelectModule, CrearEventoComponent],
    providers: [{ provide: LOCALE_ID, useValue: 'es-ES' }],
    
})
export class CalendarioComponent implements OnInit {

  @Output() update = new EventEmitter();
  public eventos: any = [];
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

  private destroyRef = inject(DestroyRef);
  private untilDestroyed = subscriptionHelper(this.destroyRef);

  constructor(public apiService: ApiService, public alertService: AlertService,
    private route: ActivatedRoute, private router: Router,
    private modalService: BsModalService
  ) { }

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
      this.filtros.id_usuario = null;
    }

    this.apiService.getAll('usuarios/list')
      .pipe(this.untilDestroyed())
      .subscribe(usuarios => {
      this.usuarios = usuarios;
    }, error => { this.alertService.error(error); });


    this.apiService.getAll('clientes/list')
      .pipe(this.untilDestroyed())
      .subscribe(clientes => {
      this.clientes = clientes;
    }, error => { this.alertService.error(error); });

    this.apiService.getAll('sucursales/list')
      .pipe(this.untilDestroyed())
      .subscribe(sucursales => {
      this.sucursales = sucursales;
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
          slotLabelFormat: {
            hour: 'numeric',
            minute: '2-digit',
            omitZeroMinute: false,
            meridiem: 'short'
          },
          headerToolbar: false,

          events: [

          ]
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
    this.apiService.getAll('eventos/list', filtrosEnvio)
      .pipe(this.untilDestroyed())
      .subscribe(eventos => {
      this.loading = false;
      if (this.calendarOptions) {
        this.calendarOptions.events = [...eventos];
        this.updateMinMaxTime(eventos);


      }
      if (this.modalRef) {
        this.modalRef.hide();
      }
      this.update.emit();
    }, error => { this.alertService.error(error); this.loading = false; });
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
    }, error => { this.alertService.error(error); this.saving = false; });
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
  }
  setNowSelection() {
    this.selectedPeriodType = "day";
    this.calendar?.changeView('timeGridDay');
  }
  setMonthSelection() {
    this.selectedPeriodType = "month";
    this.calendar?.changeView('dayGridMonth');

  }
  setWeekSelection() {
    this.selectedPeriodType = "week";
    this.calendar?.changeView('timeGridWeek');

  }
  setYearSelection() {
    this.selectedPeriodType = "year";
    this.calendar?.changeView('multiMonthYear');
  }

  nextDay() {
    this.calendar?.next();
  }
  prevDay() {
    this.calendar?.prev();
  }

}

