import { Component, OnInit, EventEmitter, Input, Output, TemplateRef } from '@angular/core';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';

import { ApiService } from '../../../../services/api.service';
import { AlertService } from '../../../../services/alert.service';

import { debounceTime, distinctUntilChanged, map } from 'rxjs/operators';
import { fromEvent, timer } from 'rxjs';

@Component({
  selector: 'app-orden-productos',
  templateUrl: './orden-productos.component.html'
})
export class OrdenProductosComponent implements OnInit {

    @Output() productoSelect = new EventEmitter();
    @Input() loadingOrden = false;
    modalRef!: BsModalRef;

    public producto:any = {};
    public productos:any = [];
    public buscador:any = '';
    public loading:boolean = false;

    constructor( 
        private apiService: ApiService, private alertService: AlertService,
        private modalService: BsModalService
    ) { }

    ngOnInit() {
    }

    openModal(template: TemplateRef<any>) {
        this.productos.total = 0;
        this.modalRef = this.modalService.show(template, {class: 'modal-lg'});
        const input = document.getElementById('example')!;
        const example = fromEvent(input, 'keyup').pipe(map(i => (<HTMLTextAreaElement>i.currentTarget).value));
        const debouncedInput = example.pipe(debounceTime(500));
        const subscribe = debouncedInput.subscribe(val => { this.searchProducto(); });
    }

    searchProducto(){
        if(this.buscador && this.buscador.length > 2) {
            this.loading = true;
            this.apiService.read('productos/buscar/', this.buscador).subscribe(productos => {
               this.productos = productos;
               this.loading = false;
            }, error => {this.alertService.error(error);this.loading = false;});
        }else if (!this.buscador  || this.buscador.length < 1 ){ this.loading = false; this.buscador = ''; this.productos.total = 0; }
    }


    agregarDetalle(producto:any){
        this.producto.producto_id  = producto.id;
        this.producto.producto_nombre     = producto.nombre;
        this.producto.cantidad     = 1;
        this.producto.estado = 'Agregada';

        this.producto.descuento    = 0;
        this.producto.precio       = producto.precio;
        
        this.producto.precio1       = producto.precio;
        this.producto.precio2       = producto.precio2;
        this.producto.precio3       = producto.precio3;

        this.producto.costo        = producto.costo;
        this.producto.tipo_impuesto = producto.tipo_impuesto;
        this.producto.iva         = 0;
        this.producto.fovial      = 0;
        this.producto.cotrans     = 0;

        // Descuento promoción si esta en fecha
        if (producto.promocion) {
            this.producto.descuento = this.producto.precio - producto.promocion.precio;
        }

        this.productoSelect.emit(this.producto);
        this.productos.data = [];
        this.buscador = '';
        this.modalRef.hide();
    }

}
