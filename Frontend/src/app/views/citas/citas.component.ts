import { Component, OnInit, TemplateRef, ViewChild } from '@angular/core';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

import { CalendarioComponent } from './calendario/calendario.component';

import * as moment from 'moment';
import Swal from 'sweetalert2';

@Component({
  selector: 'app-citas',
  templateUrl: './citas.component.html'
})

export class CitasComponent implements OnInit {
    @ViewChild('calendario') calendario!: CalendarioComponent;

    public eventos:any = [];
    public evento:any = {};
    public usuarios:any = [];
    public servicios:any = [];
    public clientes:any = [];
    public loading:boolean = false;
    public saving:boolean = false;
    public filtros:any = {};

    modalRef!: BsModalRef;

    constructor(public apiService: ApiService, private alertService: AlertService,
                private modalService: BsModalService
    ){}

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
    }

    public loadAll() {
        this.filtros.id_sucursal = '';
        this.filtros.id_cliente = '';
        this.filtros.id_usuario = '';
        this.filtros.inicio = moment().startOf('day').format('YYYY-MM-DD HH:mm:ss');
        this.filtros.fin = moment().endOf('month').format('YYYY-MM-DD HH:mm:ss');
        this.filtros.tipo = '';
        this.filtros.estado = '';
        this.filtros.buscador = '';
        this.filtros.orden = 'inicio';
        this.filtros.direccion = 'asc';
        this.filtros.paginate = 10;

        this.filtrarEventos();
    }

    public filtrarEventos(){
        this.loading = true;
        this.apiService.getAll('eventos', this.filtros).subscribe(eventos => { 
            this.eventos = eventos;
            this.loading = false;
            if(this.modalRef){
                this.modalRef.hide();
            }
        }, error => {this.alertService.error(error); this.loading = false;});
    }

    setTipo(){
        if(this.evento.confirmado){
            this.evento.tipo = 'Confirmado';
        }else{
            this.evento.tipo = 'Sin confirmar';
        }
    }

    setTime(){
        let fecha = moment(this.evento.inicio);

        if(this.evento.duracion == '15 minutos'){
            this.evento.fin = fecha.add(15, 'minutes').format('YYYY-MM-DD HH:mm:ss');
        }
        if(this.evento.duracion == '30 minutos'){
            this.evento.fin = fecha.add(30, 'minutes').format('YYYY-MM-DD HH:mm:ss');
        }
        if(this.evento.duracion == '1 hora'){
            this.evento.fin = fecha.add(1, 'hour').format('YYYY-MM-DD HH:mm:ss');
        }
        if(this.evento.duracion == '2 horas'){
            this.evento.fin = fecha.add(2, 'hour').format('YYYY-MM-DD HH:mm:ss');
        }
        if(this.evento.duracion == '3 horas'){
            this.evento.fin = fecha.add(3, 'hour').format('YYYY-MM-DD HH:mm:ss');
        }
        if(this.evento.duracion == '5 horas'){
            this.evento.fin = fecha.add(5, 'hour').format('YYYY-MM-DD HH:mm:ss');
        
        }
    }

    public loadClientes(){
        this.apiService.getAll('clientes/list').subscribe(clientes => {
            this.clientes = clientes;
        }, error => {this.alertService.error(error);});
    }

    // Cliente
    public setCliente(cliente:any){
        if(!this.evento.id_cliente){
            this.clientes.push(cliente);
        }
        this.evento.id_cliente = cliente.id;
    }

    public openModal(template: TemplateRef<any>, evento:any) {
        this.evento = evento;

        this.apiService.getAll('usuarios/list').subscribe(usuarios => {
            this.usuarios = usuarios;
        }, error => {this.alertService.error(error);});

        this.apiService.getAll('servicios/list').subscribe(servicios => {
            this.servicios = servicios;
        }, error => {this.alertService.error(error);});


        if (!this.evento.id) {
            this.evento.id_empresa = this.apiService.auth_user().id_empresa;
            this.evento.id_usuario = this.apiService.auth_user().id;
            this.evento.frecuencia = '';
            this.evento.tipo = 'Sin confirmar';
            this.evento.duracion = "1 hora";
            this.evento.estado = "Activo";
            this.evento.id_empresa = this.apiService.auth_user().id_empresa;
            this.evento.inicio =  moment().format('YYYY-MM-DD HH:mm:ss');
            this.setTime();
        }
        this.modalRef = this.modalService.show(template, {class: 'modal-lg', backdrop: 'static'});
    }

    public setEstado(evento:any, estado:any){
        this.evento = evento;
        this.evento.tipo = estado;
        this.onSubmit();
    }

    public onSubmit(){
        this.saving = true;
        this.apiService.store('evento', this.evento).subscribe(evento => {
            if (!this.evento.id) {
                this.loadAll();
                this.alertService.success('Cita creada', 'La cita fue añadida exitosamente.');
            }else{
                this.alertService.success('Cita guardada', 'La cita fue guardada exitosamente.');
            }
            this.calendario.loadAll();
            this.saving = false;
            this.modalRef.hide();
        }, error => {this.alertService.error(error); this.saving = false;});
    }

    public delete(evento:any){

        Swal.fire({
          title: '¿Estás seguro?',
          text: '¡No podrás revertir esto!',
          icon: 'warning',
          showCancelButton: true,
          confirmButtonText: 'Sí, eliminarlo',
          cancelButtonText: 'Cancelar'
        }).then((result) => {
          if (result.isConfirmed) {
                this.apiService.delete('evento/', evento.id) .subscribe(data => {
                    for (let i = 0; i < this.eventos.data.length; i++) { 
                        if (this.eventos.data[i].id == data.id )
                            this.eventos.data.splice(i, 1);
                    }
                }, error => {this.alertService.error(error); });
          } else if (result.dismiss === Swal.DismissReason.cancel) {
            // Swal.fire('Cancelado', 'Tu archivo está seguro :)', 'info');
          }
        });

    }

}
