import { Component, OnInit, EventEmitter, Input, Output, TemplateRef, DestroyRef, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { debounceTime, distinctUntilChanged, map } from 'rxjs/operators';
import { fromEvent, timer } from 'rxjs';

import { ApiService } from '../../../services/api.service';
import { AlertService } from '../../../services/alert.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';
import { ModalManagerService } from '../../../services/modal-manager.service';
import { BaseModalComponent } from '../../base/base-modal.component';

@Component({
    selector: 'app-compra-producto',
    templateUrl: './compra-producto.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule],
    
})
export class CompraProductoComponent extends BaseModalComponent implements OnInit {

	@Output() productoSelect = new EventEmitter();

	public productos:any = [];
    public producto: any = {};
	public detalle: any = {};
    public searching:boolean = false;

	private destroyRef = inject(DestroyRef);
	private untilDestroyed = subscriptionHelper(this.destroyRef);

	constructor( 
	    private apiService: ApiService,
        protected override alertService: AlertService,
        protected override modalManager: ModalManagerService
	) {
        super(modalManager, alertService);
    }

	ngOnInit() {
	}

	override openModal(template: TemplateRef<any>) {
        this.detalle = {};

        this.detalle.tipo = 'Gravada';
        this.detalle.descuento = 0;
        this.detalle.otros = 0;

        super.openModal(template, {class: 'modal-lg'});
        const input = document.getElementById('example')!;
        const example = fromEvent(input, 'keyup').pipe(map(i => (<HTMLTextAreaElement>i.currentTarget).value));
        const debouncedInput = example.pipe(debounceTime(500));
        const subscribe = debouncedInput.pipe(this.untilDestroyed()).subscribe(val => { this.searchProducto(); });
    }

	searchProducto(){
            if(this.producto.nombre && this.producto.nombre.length > 1) {
            this.searching = true;
            this.apiService.read('productos/buscar/', this.producto.nombre)
                .pipe(this.untilDestroyed())
                .subscribe(productos => {
               this.productos = productos;
               this.searching = false;
            }, error => {this.alertService.error(error);this.searching = false;});
        }else if (!this.producto.nombre  || this.producto.nombre.length < 1 ){ this.searching = false; this.producto = {}; this.productos.total = 0; }
    }

    selectProducto(producto: any){
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
        this.closeModal();
	}

    clear(){
        if(this.productos.data && this.productos.data.length == 0) { this.productos = []; }
    }

}
