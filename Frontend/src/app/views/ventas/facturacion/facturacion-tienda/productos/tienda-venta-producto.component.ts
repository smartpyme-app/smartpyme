import { Component, OnInit, EventEmitter, Input, Output, TemplateRef } from '@angular/core';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';

import { ApiService } from '../../../../../services/api.service';
import { AlertService } from '../../../../../services/alert.service';

import { debounceTime, distinctUntilChanged, map } from 'rxjs/operators';
import { fromEvent, timer } from 'rxjs';

@Component({
  selector: 'app-tienda-venta-producto',
  templateUrl: './tienda-venta-producto.component.html'
})
export class TiendaVentaProductoComponent implements OnInit {

    @Output() productoSelect = new EventEmitter();
    modalRef!: BsModalRef;

    public detalle:any = {};
    public productos:any = [];
    public buscador:any = '';
    public loading:boolean = false;

    constructor( 
        private apiService: ApiService, private alertService: AlertService,
        private modalService: BsModalService
    ) { }

    ngOnInit() {
        this.buscador = '';
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


    selectProducto(producto:any){
        this.detalle = Object.assign({}, producto);
        this.detalle.producto_id  = producto.id;
        this.detalle.nombre_producto     = producto.nombre;
        this.detalle.escombo      = false;
        this.detalle.cantidad     = 1;
        this.detalle.descuento    = 0;

        // Descuento promoción si esta en fecha
        if (producto.promocion) {
            this.detalle.descuento = parseFloat((producto.precio - producto.promocion.precio).toFixed(2));
        }
        document.getElementById('pcantidad')!.focus();
        // this.productos.data = [];
        this.buscador = '';
    }

    onSubmit(){
        this.productoSelect.emit(this.detalle);
        this.detalle = {};
        this.modalRef.hide();
    }

}
