import { Component, OnInit,TemplateRef, ViewChild, forwardRef } from '@angular/core';
import { CalendarOptions, Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import interactionPlugin from '@fullcalendar/interaction';
import { FullCalendarComponent } from '@fullcalendar/angular';
import timeGridPlugin from '@fullcalendar/timegrid';
import listPlugin from '@fullcalendar/list';
import esLocale from '@fullcalendar/core/locales/es';

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

    public eventos:any = [];
    public productos:any = [];
    public clientes:any = [];
    public usuarios:any = [];
    public evento:any = {};
    public filtros:any = {};
    public loading:boolean = false;
    public saving:boolean = false;
    public calendarOptions?: CalendarOptions;
    eventsModel: any;

    @ViewChild('mevento')
    public meventoTemplate!: TemplateRef<any>;
    modalRef!: BsModalRef;

    constructor(private apiService: ApiService, private alertService: AlertService,  
        private route: ActivatedRoute, private router: Router,
        private modalService: BsModalService
    ){ }

    @ViewChild('fullcalendar') fullcalendar?: FullCalendarComponent;

    ngOnInit() {
        forwardRef(() => Calendar);

        this.calendarOptions = {
            plugins: [interactionPlugin, dayGridPlugin, timeGridPlugin, listPlugin],
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
              left: 'prev,next',
              center: 'title',
              right: 'today dayGridMonth,timeGridWeek,timeGridDay,listWeek'
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

        this.filtros.orden = 'inicio';
        this.filtros.direccion = 'desc';

        this.loading = true;
        this.apiService.getAll('eventos/list', this.filtros).subscribe(eventos => { 
            this.loading = false;
            if(this.calendarOptions){
                this.calendarOptions.events = eventos;
            }
        }, error => {this.alertService.error(error); this.loading = false;});

    }

    handleDateClick(arg:any) {
        this.evento = {};
        this.evento.confirmado = false;
        this.evento.duracion = "1 hora";
        this.evento.inicio =  moment(arg.dateStr).format('YYYY-MM-DD HH:mm:ss');
        console.log(this.evento);
        this.modalRef = this.modalService.show(this.meventoTemplate, {class: 'modal-md'});
    }

    handleEventClick(arg:any) {

        this.apiService.getAll('usuarios/list').subscribe(usuarios => {
            this.usuarios = usuarios;
        }, error => {this.alertService.error(error);});
        this.apiService.getAll('clientes/list').subscribe(clientes => {
            this.clientes = clientes;
        }, error => {this.alertService.error(error);});

        this.apiService.getAll('productos/list').subscribe(productos => {
            this.productos = productos;
        }, error => {this.alertService.error(error);});

        this.evento = arg.event.extendedProps.data;

        this.modalRef = this.modalService.show(this.meventoTemplate, {class: 'modal-md'});
    }

    handleEventChange(arg:any) {
        this.evento = arg.event.extendedProps.data;
        this.evento.inicio = moment(arg.event.start).format('YYYY-MM-DD HH:mm:ss');
        console.log(this.evento);
        this.onSubmit();
    }

    public onSubmit(){
        this.saving = true;
        this.apiService.store('evento', this.evento).subscribe(evento => {
            if (!this.evento.id) {
                this.eventos.push(evento);
                this.alertService.success('Cita creada', 'La cita fue añadida exitosamente.');
            }else{
                this.alertService.success('Cita guardada', 'La cita fue guardada exitosamente.');
            }
            this.saving = false;

            if(this.modalRef){
                this.modalRef.hide();
            }
        }, error => {this.alertService.error(error); this.saving = false;});
    }


}
