import { Component, OnInit, EventEmitter, Input, Output, ViewChild, TemplateRef } from '@angular/core';

import { debounceTime, distinctUntilChanged, map } from 'rxjs/operators';
import { fromEvent, timer } from 'rxjs';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';

import { ApiService } from '../../../../../services/api.service';
import { AlertService } from '../../../../../services/alert.service';

@Component({
  selector: 'app-codigo-barra',
  templateUrl: './codigo-barra.component.html'
})
export class CodigoBarraComponent implements OnInit {

	@Output() productoSelect = new EventEmitter();

    public detalle: any = {};
    public productos: any = [];
    public codigo:any = '';
    public cantidad:any = 1;
    public loading = false;

    modalRef!: BsModalRef;

    @ViewChild('mproductos')
    public productoTemplate!: TemplateRef<any>;

	constructor( 
        private apiService: ApiService, private alertService: AlertService,
        private modalService: BsModalService
    ) { }

    ngOnInit() {
        const input = document.getElementById('lector')!;
        const example = fromEvent(input, 'keyup').pipe(map(i => (<HTMLTextAreaElement>i.currentTarget).value));
        const debouncedInput = example.pipe(debounceTime(500));
        const subscribe = debouncedInput.subscribe(val => { this.searchProducto(); });
    }

    searchProducto(){
        this.codigo = this.codigo.trim();
        if(this.codigo && this.codigo.length > 2){
            this.loading = true;
            this.apiService.read('productos/buscar-codigo/', this.codigo).subscribe(producto => { 
                if (producto && (producto.length == 1 ) && (this.codigo == producto[0].codigo)) { 
                    this.selectProducto(producto[0]);
                }
                else if(producto && (producto.length > 0 )){
                    this.productos = producto;
                    this.modalRef = this.modalService.show(this.productoTemplate, {class: 'modal-md'});
                }
                else{
                    this.alertService.error('Producto no encontrado');
                }
                this.codigo = "";
            }, error => {this.alertService.error(error); this.loading = false;this.codigo = "";});
        }

    }

    selectProducto(producto:any){
        this.detalle = Object.assign({}, producto);
        this.detalle.producto_id  = producto.id;
        this.detalle.nombre_producto     = producto.nombre;
        this.detalle.escombo      = false;
        if (!this.cantidad) {
            this.detalle.cantidad = 1;
        }else{
            this.detalle.cantidad = this.cantidad;
        }
        this.detalle.descuento    = 0;

        // Descuento promoción si esta en fecha
        if (producto.promocion) {
            this.detalle.descuento = producto.precio - producto.promocion.precio;
        }
        this.productoSelect.emit(this.detalle);
        // document.getElementById('cantidad')!.focus();
        // this.productos.data = [];
    }


    clear(){
        if(this.productos.data && this.productos.data.length == 0) { this.productos = []; }
    }

}
