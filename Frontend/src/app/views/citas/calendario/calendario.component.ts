import { Component, OnInit, TemplateRef, ViewChild, forwardRef, Output, EventEmitter, LOCALE_ID, AfterViewInit } from '@angular/core';
import { CalendarOptions, Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import interactionPlugin from '@fullcalendar/interaction';
import { FullCalendarComponent } from '@fullcalendar/angular';
import timeGridPlugin from '@fullcalendar/timegrid';
import listPlugin from '@fullcalendar/list';
import esLocale from '@fullcalendar/core/locales/es';
import multiMonthPlugin from '@fullcalendar/multimonth'
import rrulePlugin from '@fullcalendar/rrule'

import { Router, ActivatedRoute } from '@angular/router';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

import * as moment from 'moment';
import { registerLocaleData } from '@angular/common';
import localeEs from '@angular/common/locales/es';
registerLocaleData(localeEs);
@Component({
  selector: 'app-calendario',
  templateUrl: './calendario.component.html',
  providers: [{ provide: LOCALE_ID, useValue: 'es-ES' }]
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
    this.filtros.id_usuario = this.apiService.auth_user().id;
    this.apiService.getAll('usuarios/list').subscribe(usuarios => {
      this.usuarios = usuarios;
    }, error => { this.alertService.error(error); });


    this.apiService.getAll('clientes/list').subscribe(clientes => {
      this.clientes = clientes;
    }, error => { this.alertService.error(error); });

    this.apiService.getAll('sucursales/list').subscribe(sucursales => {
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

    this.loading = true;
    this.apiService.getAll('eventos/list', this.filtros).subscribe(eventos => {
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
    this.evento.inicio = moment(arg.dateStr + ' ' + moment().format('HH:mm')).format('YYYY-MM-DD HH:mm:ss');
    this.setTime();
    this.alertService.modal = true;
    this.modalRef = this.modalService.show(this.meventoTemplate, { class: 'modal-lg' });
  }


  handleEventClick(arg: any) {
    this.evento = arg.event.extendedProps.data;
    this.alertService.modal = true;
    this.modalRef = this.modalService.show(this.meventoTemplate, { class: 'modal-lg p-2' });
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

  handleEventChange(arg: any) {
    this.evento = arg.event.extendedProps.data;
    this.evento.inicio = moment(arg.event.start).format('YYYY-MM-DD HH:mm');
    this.setTime();
    this.onSubmit();
  }

  public onSubmit() {
    this.saving = true;
    this.apiService.store('evento', this.evento).subscribe(evento => {
      if (!this.evento.id) {
        this.alertService.success('Cita creada', 'La cita fue añadida exitosamente.');
      } else {
        this.alertService.success('Cita guardada', 'La cita fue guardada exitosamente.');
      }
      this.loadAll();
      this.saving = false;
      this.modalRef.hide();
      this.alertService.modal = false;
    }, error => { this.alertService.error(error); this.saving = false; });
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

