import { Component, OnInit, TemplateRef, Output, Input, EventEmitter  } from '@angular/core';
import { BsModalService } from 'ngx-bootstrap/modal';
import { BsModalRef } from 'ngx-bootstrap/modal/bs-modal-ref.service';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

import * as moment from 'moment';

@Component({
  selector: 'app-crear-evento',
  templateUrl: './crear-evento.component.html'
})
export class CrearEventoComponent implements OnInit {

    @Input() evento: any = {};
    public productos:any = [];
    public clientes:any = [];
    public usuarios:any = [];
    public detalle:any = {};
    public productoSeleccionado:any = undefined;
    @Output() update = new EventEmitter();
    public loading = false;
    public saving:boolean = false;

    modalRef?: BsModalRef;

    constructor( 
        private apiService: ApiService, private alertService: AlertService,
        private modalService: BsModalService
    ) {}

    ngOnInit() {
        this.apiService.getAll('usuarios/list').subscribe(usuarios => {
            this.usuarios = usuarios;
        }, error => {this.alertService.error(error);});

        this.apiService.getAll('productos/list').subscribe(productos => {
            this.productos = productos;
        }, error => {this.alertService.error(error);});
        
        this.apiService.getAll('clientes/list').subscribe(clientes => {
            this.clientes = clientes;
        }, error => {this.alertService.error(error);});
    }

    setTipo(){
        if(this.evento.tipo = 'Confirmado'){
            this.evento.tipo = 'Sin confirmar';
        }else{
            this.evento.tipo = 'Confirmado';
        }
        console.log(this.evento);
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
        if(this.evento.duracion == '8 horas'){
            this.evento.fin = fecha.add(8, 'hour').format('YYYY-MM-DD HH:mm:ss');
        }
        if(this.evento.duracion == '12 horas'){
            this.evento.fin = fecha.add(12, 'hour').format('YYYY-MM-DD HH:mm:ss');
        }
    }

    setFrecuenciaFin(){
        let fecha = moment(this.evento.inicio);

        if(!this.evento.veces){
            this.evento.veces = 1;
        }

        if(this.evento.frecuencia == "DAILY"){
            this.evento.frecuencia_fin = fecha.add(this.evento.veces, 'day').format('YYYY-MM-DD');
        }
        if(this.evento.frecuencia == "WEEKLY"){
            this.evento.frecuencia_fin = fecha.add(this.evento.veces, 'week').format('YYYY-MM-DD');
        }
        if(this.evento.frecuencia == "MONTHLY"){
            this.evento.frecuencia_fin = fecha.add(this.evento.veces, 'month').format('YYYY-MM-DD');
        }
        if(this.evento.frecuencia == "YEARLY"){
            this.evento.frecuencia_fin = fecha.add(this.evento.veces, 'year').format('YYYY-MM-DD');
        }
    }

    // Cliente
    public setCliente(cliente:any){
        if(!this.evento.id_cliente){
            this.clientes.push(cliente);
        }
        this.evento.id_cliente = cliente.id;
    }

    public onSubmit(){
        this.saving = true;
        this.apiService.store('evento', this.evento).subscribe(evento => {
            if (!this.evento.id) {
                this.alertService.success('Cita creada', 'La cita fue añadida exitosamente.');
            }else{
                this.alertService.success('Cita guardada', 'La cita fue guardada exitosamente.');
            }
            this.update.emit();
            this.saving = false;
            // this.modalRef!.hide();
        }, error => {this.alertService.error(error); this.saving = false;});
    }


    public agregarDetalle(){

        if(this.productoSeleccionado.id){
            this.detalle.nombre_producto = this.productoSeleccionado.nombre;
            this.detalle.id_producto = this.productoSeleccionado.id;
            this.detalle.cantidad = 1;
            this.detalle.id_evento = this.evento.id;

            let detalle = Object.assign({}, this.detalle);

            this.evento.productos.unshift(detalle);
            this.detalle = {};
            this.productoSeleccionado = null;
            console.log(this.productoSeleccionado);
        }

    }

    public eliminarDetalle(index: number) {
        this.evento.productos.splice(index, 1);
    }


}
