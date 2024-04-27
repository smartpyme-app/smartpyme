import { Component, OnInit, EventEmitter, Input, Output, TemplateRef } from '@angular/core';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';

import { FormControl } from '@angular/forms';
import { debounceTime, switchMap, filter  } from 'rxjs/operators';

import { SumPipe }     from '@pipes/sum.pipe';
import { ApiService } from '@services/api.service';
import { AlertService } from '@services/alert.service';

import * as moment from 'moment';

@Component({
  selector: 'app-tienda-venta-citas',
  templateUrl: './tienda-venta-citas.component.html'
})
export class TiendaVentaCitasComponent implements OnInit {

    @Input() venta: any = {};
    @Output() productoSelect = new EventEmitter();
    modalRef!: BsModalRef;

    public citas:any = [];
    public clientes:any = [];
    public detalle:any = {};
    public detalles:any = [];
    public servicio:any = {};
    public filtros:any = {};
    public buscador:any = '';
    public loading:boolean = false;

    constructor( 
        private apiService: ApiService, private alertService: AlertService,
        private modalService: BsModalService, private sumPipe:SumPipe
    ) { }

    ngOnInit() {

    }

    public openModal(template: TemplateRef<any>) {
        this.apiService.getAll('clientes/list').subscribe(clientes => { 
            this.clientes = clientes;
        }, error => {this.alertService.error(error); });
        this.citas = [];
        this.loadAll();
        this.modalRef = this.modalService.show(template, { class: 'modal-xl', backdrop: 'static' });        
    }

    public loadAll() {
        this.filtros.id_cliente = this.venta.id_cliente;
        this.filtros.id_usuario = '';
        this.filtros.tipo = '';
        this.filtros.buscador = '';
        this.filtros.orden = 'inicio';
        this.filtros.direccion = 'asc';
        this.filtros.paginate = 5;
        
        this.filtros.time = 'week';
        this.filtros.inicio = moment().startOf(this.filtros.time).format('YYYY-MM-DD');
        this.filtros.fin = moment().endOf(this.filtros.time).format('YYYY-MM-DD');
        this.filtrarCitas();

    }

    public setTime($time:any){
        this.filtros.time = $time;
        this.filtros.inicio = moment().startOf(this.filtros.time).format('YYYY-MM-DD');
        this.filtros.fin = moment().endOf(this.filtros.time).format('YYYY-MM-DD');
        this.filtrarCitas();
    }

    public filtrarCitas(){
        this.loading = true;
        this.venta.id_cliente = this.filtros.id_cliente;
        this.apiService.getAll('eventos', this.filtros).subscribe(citas => { 
            this.citas = citas;
            
            this.detalles = [];
            let radio = document.getElementById('marcarPaquetes') as HTMLInputElement;
            radio.checked = false;

            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});

    }

    public setOrden(columna: string) {
        if (this.filtros.orden === columna) {
          this.filtros.direccion = this.filtros.direccion === 'asc' ? 'desc' : 'asc';
        } else {
          this.filtros.orden = columna;
          this.filtros.direccion = 'asc';
        }

        this.filtrarCitas();
    }

    public setPagination(event:any):void{
        this.loading = true;
        this.apiService.paginate(this.citas.path + '?page='+ event.page, this.filtros).subscribe(citas => { 
            this.citas = citas;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});
    }


    selectProducto(cita:any){
        this.detalle = Object.assign({}, cita);
        this.detalle.id_cita    = cita.id;
        this.detalle.descripcion   = cita.servicio.nombre;
        this.detalle.img            = cita.servicio.img;
        this.detalle.precio         = parseFloat(this.servicio.precio);
        this.detalle.precios        = this.servicio.precios;
        this.detalle.precios.unshift({
                'precio' : this.detalle.precio
            });
        this.detalle.costo          = parseFloat(this.servicio.costo);
        this.servicio.inventarios        = this.servicio.inventarios.filter((item:any) => item.id_sucursal == this.venta.id_sucursal);
        if(this.servicio.inventarios.length > 0 && this.servicio.tipo != 'Servicio'){
            this.detalle.stock          = parseFloat(this.sumPipe.transform(this.servicio.inventarios, 'stock'));
        }else{
            this.detalle.stock = null;
        }
        this.detalle.cantidad       = 1;
        this.detalle.descuento      = 0;
        this.detalle.descuento_porcentaje      = 0;
        console.log(this.detalle);
        this.onSubmit();
    }

    onCheckPaquete(cita:any){
        console.log(cita);
        let radio = document.getElementById('cita' + cita.id) as HTMLInputElement;
        this.servicio = cita.servicio;
        if(radio.checked){
            this.detalle = Object.assign({}, this.servicio);
            this.detalle.id_producto    = this.servicio.id;
            this.detalle.descripcion = this.servicio.nombre;
            this.detalle.img            = this.servicio.img;
            this.detalle.id_cita        = cita.id;
            this.detalle.costo          = parseFloat(this.servicio.costo);
            this.detalle.precio         = parseFloat(this.servicio.precio);
            this.detalle.precios        = this.servicio.precios;
            this.detalle.precios.unshift({
                    'precio' : this.detalle.precio
                });
            this.detalle.costo          = parseFloat(this.servicio.costo);
            this.servicio.inventarios        = this.servicio.inventarios.filter((item:any) => item.id_sucursal == this.venta.id_sucursal);
            if(this.servicio.inventarios.length > 0 && this.servicio.tipo != 'Servicio'){
                this.detalle.stock          = parseFloat(this.sumPipe.transform(this.servicio.inventarios, 'stock'));
            }else{
                this.detalle.stock = null;
            }

            this.detalle.cantidad       = 1;
            this.detalle.descuento      = 0;
            this.detalle.descuento_porcentaje      = 0;
            this.detalles.unshift(this.detalle);

        }else{
            // radio.checked = false;
            const indexAEliminar = this.detalles.findIndex((item:any) => item.id_cita === cita.id);
            if (indexAEliminar !== -1) {
              this.detalles.splice(indexAEliminar, 1);
            }
            console.log(indexAEliminar);
        }

        console.log(this.detalles);
    }

    onCheckAllPaquete(){
        let marcarPaquetes = document.getElementById('marcarPaquetes') as HTMLInputElement;
        this.citas.data.forEach((cita:any) => {
            let radio = document.getElementById('cita' + cita.id) as HTMLInputElement;
            radio.checked = marcarPaquetes.checked;
            this.onCheckPaquete(cita);
        });
    }

    onSubmit(){
        this.citas = [];
        this.productoSelect.emit(this.detalle);
        if(this.modalRef){
            this.modalRef.hide();
        }
    }

    agregarDetalles(){
        for (let i = 0; i < this.detalles.length; i++) { 
            this.productoSelect.emit(this.detalles[i]);
        }

        if(this.modalRef){
            this.modalRef.hide();
        }
    }


}
