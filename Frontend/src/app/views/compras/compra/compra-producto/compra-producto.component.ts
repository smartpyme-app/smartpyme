import { Component, OnInit, EventEmitter, Input, Output, TemplateRef } from '@angular/core';
import { debounceTime, distinctUntilChanged, map } from 'rxjs/operators';
import { fromEvent, timer } from 'rxjs';

import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';

import { ApiService } from '../../../../services/api.service';
import { AlertService } from '../../../../services/alert.service';

@Component({
  selector: 'app-compra-producto',
  templateUrl: './compra-producto.component.html'
})
export class CompraProductoComponent implements OnInit {

	@Output() productoSelect = new EventEmitter();
	modalRef!: BsModalRef;

	public detalle: any = {};
	public productos:any = [];
    public searching:boolean = false;


	constructor( 
	    private apiService: ApiService, private alertService: AlertService,
	    private modalService: BsModalService
	) { }

	ngOnInit() {
	}

	openModal(template: TemplateRef<any>) {
        this.detalle = {};
        this.modalRef = this.modalService.show(template, {class: 'modal-lg', backdrop: 'static'});
        const input = document.getElementById('example')!;
        const example = fromEvent(input, 'keyup').pipe(map(i => (<HTMLTextAreaElement>i.currentTarget).value));
        const debouncedInput = example.pipe(debounceTime(500));
        const subscribe = debouncedInput.subscribe(val => { this.searchProducto(); });
    }

	searchProducto(){
            if(this.detalle.nombre_producto && this.detalle.nombre_producto.length > 1) {
            this.searching = true;
            this.apiService.read('productos-all/buscar/', this.detalle.nombre_producto).subscribe(productos => {
               if(productos.total == 1) {
                   this.setProducto(productos.data[0]);
                   this.searching = false;
               }else{
                   this.productos = productos;
                   this.searching = false;
               }
            }, error => {this.alertService.error(error);this.searching = false;});
        }else if (!this.detalle.detalle  || this.detalle.detalle.length < 1 ){ this.searching = false; this.detalle = {}; this.productos.total = 0; }
    }


    public setProducto(producto:any){
        this.detalle = Object.assign({}, producto);
    	this.detalle.producto_id     = producto.id;
        this.detalle.nombre_producto = producto.nombre;
        this.detalle.costo_actual    = producto.costo;
        this.detalle.costo            = null;
        this.detalle.descuento        = 0;
        this.detalle.precio_nuevo    = producto.precio;

    	this.productos.total = 0;
    	this.detalle.cantidad = 1;
    	document.getElementById('cantidad')!.focus();
    }

    public calcularByCosto(){
        if (!this.detalle.exenta) { this.detalle.exenta = 0; }
        if (!this.detalle.gravada) { this.detalle.gravada = 0; }
        if (!this.detalle.no_sujeta) { this.detalle.no_sujeta = 0; }
        if (!this.detalle.iva) { this.detalle.iva = 0; }
        if(this.detalle.costo) {
            // this.detalle.costo_iva  = (parseFloat(this.detalle.costo) + (this.detalle.costo * 0.13)).toFixed(2);
            this.detalle.subtotal = ((this.detalle.cantidad * this.detalle.costo) - this.detalle.descuento).toFixed(2);
            this.detalle.gravada = ((this.detalle.cantidad * this.detalle.costo) - this.detalle.descuento).toFixed(2);
        
            this.detalle.iva = (this.detalle.subtotal * this.detalle.impuesto).toFixed(2);        
            this.detalle.total = (parseFloat(this.detalle.subtotal) + parseFloat(this.detalle.exenta) + parseFloat(this.detalle.no_sujeta) + parseFloat(this.detalle.iva)).toFixed(2);
        }
  
    }
    
    public updateTotal(){
        if (!this.detalle.exenta) { this.detalle.exenta = 0; }
        if (!this.detalle.gravada) { this.detalle.gravada = 0; }
        if (!this.detalle.no_sujeta) { this.detalle.no_sujeta = 0; }

        if (this.detalle.gravada) {
            let valorIva   = (parseFloat(this.detalle.gravada) + parseFloat(this.detalle.gravada) * parseFloat(this.detalle.impuesto)).toFixed(2);
            this.detalle.gravada   = this.detalle.gravada;
            this.detalle.iva       = (parseFloat(valorIva) - parseFloat(this.detalle.gravada)).toFixed(2);
        }else{
            this.detalle.iva       = 0;
        }
    }

    public onSubmit(){
        this.productoSelect.emit(this.detalle);
        this.detalle = {};
        this.modalRef.hide();
	}

    public clear(){
        if(this.productos.data && this.productos.data.length == 0) { this.productos = []; }
    }

}
