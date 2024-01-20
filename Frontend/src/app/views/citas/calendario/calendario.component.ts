import { Component, OnInit,TemplateRef, ViewChild, forwardRef, Output, EventEmitter } from '@angular/core';
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

@Component({
  selector: 'app-calendario',
  templateUrl: './calendario.component.html'
})
export class CalendarioComponent implements OnInit {

    @Output() update = new EventEmitter();
    public eventos:any = [];
    public evento:any = {};
    public filtros:any = {};
    public loading:boolean = false;
    public saving:boolean = false;
    public calendarOptions?: CalendarOptions;
    eventsModel: any;

    @ViewChild('mevento')
    public meventoTemplate!: TemplateRef<any>;
    modalRef!: BsModalRef;

    constructor(private apiService: ApiService, public alertService: AlertService,  
        private route: ActivatedRoute, private router: Router,
        private modalService: BsModalService
    ){ }

    @ViewChild('fullcalendar') fullcalendar?: FullCalendarComponent;

    ngOnInit() {
        forwardRef(() => Calendar);

        this.calendarOptions = {
            // plugins: [interactionPlugin, dayGridPlugin, timeGridPlugin, listPlugin, multiMonthPlugin],
            plugins: [interactionPlugin, dayGridPlugin, timeGridPlugin, multiMonthPlugin, rrulePlugin],
            editable: true,
            navLinks: true,
            firstDay: 0,
            timeZone: 'America/El_Salvador',
            locale: esLocale,
            themeSystem: 'bootstrap',
            businessHours: [ // specify an array instead
              {
                daysOfWeek: [ 1, 2, 3, 4, 5 ], // Monday, Tuesday, Wednesday
                startTime: '08:00', // 8am
                endTime: '17:00' // 5pm
              },
              {
                daysOfWeek: [ 6 ], // Thursday, Friday
                startTime: '08:00', // 10am
                endTime: '12:00' // 4pm
              }
            ],
            headerToolbar: {
              left: 'prev,next today',
              center: 'title',
              right: 'dayGridMonth,timeGridWeek,timeGridDay,multiMonthYear,listWeek'
            },
            customButtons: {
              myCustomButton: {
                text: 'Nuevo',
                click: this.handleDateClick.bind(this)
              }
            },
            dateClick: this.handleDateClick.bind(this),
            eventClick: this.handleEventClick.bind(this),
            eventChange: this.handleEventChange.bind(this),
            events: []
        };

        this.loadAll();
    }

    public loadAll(){
        this.filtros.id_sucursal = this.apiService.auth_user().id_sucursal;
        this.filtros.orden = 'inicio';
        this.filtros.direccion = 'desc';
        this.loading = true;
        this.apiService.getAll('eventos/list', this.filtros).subscribe(eventos => { 
            this.loading = false;
            if(this.calendarOptions){
                this.calendarOptions.events = eventos;
            }
            if(this.modalRef){
                this.modalRef.hide();
            }
            console.log('siu');
            this.update.emit();
        }, error => {this.alertService.error(error); this.loading = false;});
    }

    handleDateClick(arg:any) {
        this.evento = {};
        this.evento.frecuencia = '';
        this.evento.tipo = 'Sin confirmar';
        this.evento.duracion = "1 hora";
        this.evento.estado = "Activo";
        this.evento.id_cliente = '';
        this.evento.id_servicio = '';
        this.evento.id_empresa = this.apiService.auth_user().id_empresa;
        this.evento.id_usuario = this.apiService.auth_user().id;
        this.evento.id_sucursal = this.apiService.auth_user().id_sucursal;
        this.evento.inicio =  moment(arg.dateStr + ' ' + moment().format('HH:mm')).format('YYYY-MM-DD HH:mm:ss');
        this.setTime();
        this.modalRef = this.modalService.show(this.meventoTemplate, {class: 'modal-lg'});
    }


    handleEventClick(arg:any) {
        this.evento = arg.event.extendedProps.data;
        this.alertService.modal = true;
        this.modalRef = this.modalService.show(this.meventoTemplate, {class: 'modal-lg'});
    }

    setTime(){
        let fecha = moment(this.evento.inicio);

        if(this.evento.duracion == '15 minutos'){
            this.evento.fin = fecha.add(15, 'minutes').format('YYYY-MM-DD HH:mm');
        }
        if(this.evento.duracion == '30 minutos'){
            this.evento.fin = fecha.add(30, 'minutes').format('YYYY-MM-DD HH:mm');
        }
        if(this.evento.duracion == '1 hora'){
            this.evento.fin = fecha.add(1, 'hour').format('YYYY-MM-DD HH:mm');
        }
        if(this.evento.duracion == '2 horas'){
            this.evento.fin = fecha.add(2, 'hour').format('YYYY-MM-DD HH:mm');
        }
        if(this.evento.duracion == '3 horas'){
            this.evento.fin = fecha.add(3, 'hour').format('YYYY-MM-DD HH:mm');
        }
        if(this.evento.duracion == '5 horas'){
            this.evento.fin = fecha.add(5, 'hour').format('YYYY-MM-DD HH:mm');
        }
    }

    handleEventChange(arg:any) {
        this.evento = arg.event.extendedProps.data;
        this.evento.inicio = moment(arg.event.start).format('YYYY-MM-DD HH:mm');
        this.setTime();
        console.log(this.evento);
        this.onSubmit();
    }

    public onSubmit(){
        this.saving = true;
        this.apiService.store('evento', this.evento).subscribe(evento => {
            if (!this.evento.id) {
                this.alertService.success('Cita creada', 'La cita fue añadida exitosamente.');
            }else{
                this.alertService.success('Cita guardada', 'La cita fue guardada exitosamente.');
            }
            this.loadAll();
            this.saving = false;
            this.modalRef.hide();
            this.alertService.modal = false;
        }, error => {this.alertService.error(error); this.saving = false;});
    }


}
