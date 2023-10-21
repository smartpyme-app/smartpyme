import { Component, OnInit, EventEmitter, Input, Output, TemplateRef } from '@angular/core';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';

import { ApiService } from '../../../../../services/api.service';
import { AlertService } from '../../../../../services/alert.service';

@Component({
  selector: 'app-venta-gasolina',
  templateUrl: './venta-gasolina.component.html'
})
export class VentaGasolinaComponent implements OnInit {

    @Output() productoSelect = new EventEmitter();
    modalRef!: BsModalRef;

    public producto: any = {};
    public gasolinas:any = [];
    public bombas:any = [];


    constructor( 
        private apiService: ApiService, private alertService: AlertService,
        private modalService: BsModalService
    ) { }

    ngOnInit() {
        this.apiService.getAll('productos/gasolina').subscribe(gasolinas => {
            this.gasolinas = gasolinas;
        }, error => {this.alertService.error(error);});
    }

    setGasolina(producto:any, template: TemplateRef<any>){
        this.producto = Object.assign({}, producto);
        this.producto.tipo = 'dinero';
        this.producto.cantidad = '';
        this.producto.tanque_id = this.producto.tanques[0].id;
        this.modalRef = this.modalService.show(template, {class: 'modal-sm'});
    }

    setTanque(){
        document.getElementById('submit')!.focus();
    }

    agregarDetalle(){
        this.producto.producto_id = this.producto.id;
        this.producto.producto_nombre = this.producto.nombre;
        this.producto.descuento = 0;
        this.producto.escombo = false;

        // Descuento promoción si esta en fecha
        if (this.producto.promocion) {
            this.producto.descuento = this.producto.precio - this.producto.promocion.precio;
        }

        // Cantidades
        if(this.producto.tipo == 'dinero') {  // Solo gasolina
            this.producto.ingresado = this.producto.cantidad;
            this.producto.cantidad = (this.producto.cantidad / this.producto.precio).toFixed(4);
        }


        // Impuestos
        this.producto.precio        = this.producto.precio - (0.30);
        // this.producto.precio        = this.producto.precio - (0.20);
        this.producto.iva = 0;

        if(this.producto.tipo_impuesto == 'Gravada'){
            let iva:number = 0.13;
            // if (this.producto.id == 1) { //super
            //     iva = 0.0475;
            //     console.log('super');
            // }
            // if (this.producto.id == 2) { //regular
            //     iva = 0.05;
            //     console.log('regular');
            // }
            // if (this.producto.id == 3) { //diesel
            //     iva = 0.0175;
            //     console.log('diesel');
            // }

            this.producto.iva = ((this.producto.precio / (1 + iva)) * this.producto.cantidad) * iva;
        }

        this.producto.fovial = this.producto.cantidad * 0.20;
        this.producto.cotrans = this.producto.cantidad * 0.10;
        // this.producto.cotrans       = this.producto.cantidad * 0;

        this.productoSelect.emit({producto: this.producto});
        this.producto = {};
        this.modalRef.hide();
    }

}
