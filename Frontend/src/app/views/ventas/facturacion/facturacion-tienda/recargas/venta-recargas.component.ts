import { Component, OnInit, EventEmitter, Input, Output, TemplateRef } from '@angular/core';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';

import { ApiService } from '../../../../../services/api.service';
import { AlertService } from '../../../../../services/alert.service';

@Component({
  selector: 'app-venta-recargas',
  templateUrl: './venta-recargas.component.html'
})
export class VentaRecargasComponent implements OnInit {

    @Output() productoSelect = new EventEmitter();
    modalRef!: BsModalRef;

    public telefonias:any = [];
    public producto: any = {};
    public loading:boolean = false;
    public buscador:string = '';
    public lista: any = {};

    constructor( 
        private apiService: ApiService, private alertService: AlertService,
        private modalService: BsModalService
    ) { }

    ngOnInit() {

        this.lista = [
            { nombre: 'Tigo', cantidad: 1.05, tipo: 'Recarga'},
            { nombre: 'Tigo', cantidad: 1.60, tipo: 'Recarga'},
            { nombre: 'Tigo', cantidad: 2.10, tipo: 'Recarga'},
            { nombre: 'Tigo', cantidad: 3.25, tipo: 'Recarga'},
            { nombre: 'Tigo', cantidad: 5.25, tipo: 'Recarga'},
            { nombre: 'Tigo', cantidad: 10.50, tipo: 'Recarga'},
            { nombre: 'Tigo', cantidad: 20.90, tipo: 'Recarga'},
            { nombre: 'Tigo', cantidad: 1.05, tipo: 'Paquete'},
            { nombre: 'Tigo', cantidad: 1.60, tipo: 'Paquete'},
            { nombre: 'Tigo', cantidad: 2.10, tipo: 'Paquete'},
            { nombre: 'Tigo', cantidad: 3.25, tipo: 'Paquete'},
            { nombre: 'Tigo', cantidad: 4.05, tipo: 'Paquete'},
            { nombre: 'Tigo', cantidad: 5.25, tipo: 'Paquete'},
            { nombre: 'Tigo', cantidad: 7.10, tipo: 'Paquete'},
            { nombre: 'Tigo', cantidad: 10.50, tipo: 'Paquete'},
            { nombre: 'Tigo', cantidad: 15.75, tipo: 'Paquete'},
            { nombre: 'Tigo', cantidad: 21, tipo: 'Paquete'},
            { nombre: 'Tigo', cantidad: 26.25, tipo: 'Paquete'},
            { nombre: 'Tigo', cantidad: 31.50, tipo: 'Paquete'},
            { nombre: 'Movistar', cantidad: 0.55, tipo: 'Recarga'},
            { nombre: 'Movistar', cantidad: 1.60, tipo: 'Recarga'},
            { nombre: 'Movistar', cantidad: 2.10, tipo: 'Recarga'},
            { nombre: 'Movistar', cantidad: 2.60, tipo: 'Recarga'},
            { nombre: 'Movistar', cantidad: 3.25, tipo: 'Recarga'},
            { nombre: 'Movistar', cantidad: 5.25, tipo: 'Recarga'},
            { nombre: 'Movistar', cantidad: 10.50, tipo: 'Recarga'},
            { nombre: 'Movistar', cantidad: 15.00, tipo: 'Recarga'},
            { nombre: 'Movistar', cantidad: 20.00, tipo: 'Recarga'},
            { nombre: 'Claro', cantidad: 1.05, tipo: 'Recarga'},
            { nombre: 'Claro', cantidad: 1.60, tipo: 'Recarga'},
            { nombre: 'Claro', cantidad: 2.10, tipo: 'Recarga'},
            { nombre: 'Claro', cantidad: 2.5, tipo: 'Recarga'},
            { nombre: 'Claro', cantidad: 3.15, tipo: 'Recarga'},
            { nombre: 'Claro', cantidad: 4.00, tipo: 'Recarga'},
            { nombre: 'Claro', cantidad: 5.25, tipo: 'Recarga'},
            { nombre: 'Claro', cantidad: 10.50, tipo: 'Recarga'},
            { nombre: 'Claro', cantidad: 20.90, tipo: 'Recarga'},
            { nombre: 'Claro', cantidad: 15.70, tipo: 'Recarga'},
            { nombre: 'Digicel', cantidad: 1.05, tipo: 'Recarga'},
            { nombre: 'Digicel', cantidad: 1.60, tipo: 'Recarga'},
            { nombre: 'Digicel', cantidad: 2.10, tipo: 'Recarga'},
            { nombre: 'Digicel', cantidad: 3.15, tipo: 'Recarga'},
            { nombre: 'Digicel', cantidad: 5.25, tipo: 'Recarga'},
            { nombre: 'Digicel', cantidad: 10.50, tipo: 'Recarga'},
            { nombre: 'Digicel (7D)', cantidad: 1.05, tipo: 'Paquete'},
            { nombre: 'Digicel (7D)', cantidad: 3.15, tipo: 'Paquete'},
            { nombre: 'Digicel (7D O 30D)', cantidad: 5.25, tipo: 'Paquete'},
            { nombre: 'Digicel (30D)', cantidad: 10.5, tipo: 'Paquete'},
        ]
        this.loadAll();
        
    }

    openModal(template: TemplateRef<any>) {
        this.modalRef = this.modalService.show(template);
        this.producto.nombre = null;
    }

    loadAll(){
        this.loading = true;
        this.apiService.getAll('productos/telefonia').subscribe(telefonias => {
           this.telefonias = telefonias;
           this.loading = false;
           this.buscador = '';
        }, error => {this.alertService.error(error._body);this.loading = false;});
    }


    agregarDetalle(){
        console.log(this.producto);
        this.producto.producto_id = this.producto.id;
        this.producto.producto_nombre = this.producto.nombre;
       
  
    }

    setCantidad(producto:any){

        if(this.producto.producto_id) {
            this.producto.cantidad = 1;
            this.producto.precio = producto.cantidad;
            this.producto.detalle = producto.tipo;
            
        }else{
            this.producto = this.telefonias.data.filter((tel:any) => tel.nombre === producto.nombre)[0];
            this.producto.cantidad = 1;
            this.producto.tipo = producto.tipo;
            this.producto.precio = producto.cantidad;
            this.producto.detalle = producto.tipo;
            this.producto.producto_id = this.producto.id;
            this.producto.producto    = producto.nombre;
          

        }
        this.producto.combo = false;
        this.producto.descuento = 0;
        this.producto.iva = 0;
        // this.producto.cesc = this.producto.cantidad * 0.5;
        this.producto.cesc = 0;
        this.producto.otros = 0;
        
        this.productoSelect.emit({producto: this.producto});this.modalRef.hide();
        
        console.log(this.producto);
    }

}
