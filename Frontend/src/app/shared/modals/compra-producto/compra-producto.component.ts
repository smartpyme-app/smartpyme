import { Component, OnInit, EventEmitter, Input, Output, TemplateRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { debounceTime, distinctUntilChanged, map } from 'rxjs/operators';
import { fromEvent, timer } from 'rxjs';

import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';

import { ApiService } from '../../../services/api.service';
import { AlertService } from '../../../services/alert.service';

@Component({
    selector: 'app-compra-producto',
    templateUrl: './compra-producto.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule],
    
})
export class CompraProductoComponent implements OnInit {

	@Output() productoSelect = new EventEmitter();
	modalRef?: BsModalRef;

	public productos:any = [];
    public producto: any = {};
	public detalle: any = {};
    public searching:boolean = false;


	constructor( 
	    private apiService: ApiService, private alertService: AlertService,
	    private modalService: BsModalService
	) { }

	ngOnInit() {
	}

	openModal(template: TemplateRef<any>) {
        this.detalle = {};

        this.detalle.tipo = 'Gravada';
        this.detalle.descuento = 0;
        this.detalle.otros = 0;

        this.modalRef = this.modalService.show(template, {class: 'modal-lg'})
        const input = document.getElementById('example')!;
        const example = fromEvent(input, 'keyup').pipe(map(i => (<HTMLTextAreaElement>i.currentTarget).value));
        const debouncedInput = example.pipe(debounceTime(500));
        const subscribe = debouncedInput.subscribe(val => { this.searchProducto(); });
    }

	searchProducto(){
            if(this.producto.nombre && this.producto.nombre.length > 1) {
            this.searching = true;
            this.apiService.read('productos/buscar/', this.producto.nombre).subscribe(productos => {
               this.productos = productos;
               this.searching = false;
            }, error => {this.alertService.error(error);this.searching = false;});
        }else if (!this.producto.nombre  || this.producto.nombre.length < 1 ){ this.searching = false; this.producto = {}; this.productos.total = 0; }
    }

    selectProducto(producto){
        this.producto = producto;
    	this.detalle.producto_id = producto.id;
        this.detalle.categoria_id = producto.categoria_id;
        this.detalle.producto = producto.nombre;
        this.detalle.precio = producto.precio;
        this.detalle.medida = producto.medida;
        this.detalle.bodega_id = 1;

    	this.productos.total = 0;
    	document.getElementById('cantidad')?.focus();
    	this.producto.cantidad = 1;
    }

    calcularImpuestos(){
    	this.detalle.sub_total = (this.detalle.cantidad * this.detalle.costo) - this.detalle.descuento + this.detalle.otros;
        if(this.detalle.tipo == 'Gravada') {
            this.detalle.iva = this.detalle.sub_total * 0.13;
        }else{ this.detalle.iva = 0; }
        this.detalle.fovial = 0;
        this.detalle.cotrans = 0;
    }

    agregarDetalle(){
        this.productoSelect.emit({detalle: this.detalle});
        this.modalRef?.hide();
	}

    clear(){
        if(this.productos.data && this.productos.data.length == 0) { this.productos = []; }
    }

}
