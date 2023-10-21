import { Component, OnInit, EventEmitter, Input, Output, TemplateRef } from '@angular/core';
import { debounceTime, distinctUntilChanged, map } from 'rxjs/operators';
import { fromEvent, timer } from 'rxjs';

import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';

import { ApiService } from '../../../../../services/api.service';
import { AlertService } from '../../../../../services/alert.service';

@Component({
  selector: 'app-mantenimiento-producto',
  templateUrl: './mantenimiento-producto.component.html'
})
export class MantenimientoProductoComponent implements OnInit {

	@Output() productoSelect = new EventEmitter();
	modalRef!: BsModalRef;

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
        this.producto = {};

        this.detalle.tipo = 'Gravada';
        this.detalle.descuento = 0;
        // this.detalle.otros = 0;

        this.modalRef = this.modalService.show(template, {class: 'modal-md', backdrop: 'static'});

        const input = document.getElementById('example')!;
        const example = fromEvent(input, 'keyup').pipe(map(i => (<HTMLTextAreaElement>i.currentTarget).value));
        const debouncedInput = example.pipe(debounceTime(500));
        const subscribe = debouncedInput.subscribe(val => { this.searchProducto(); });
    }

	searchProducto(){
            if(this.producto.nombre && this.producto.nombre.length > 1) {
            this.searching = true;
            this.apiService.read('repuestos-all/buscar/', this.producto.nombre).subscribe(productos => {
               if(productos.total == 1) {
                   this.selectProducto(productos.data[0]);
                   this.searching = false;
               }else{
                   this.productos = productos;
                   this.searching = false;
               }
            }, error => {this.alertService.error(error);this.searching = false;});
        }else if (!this.producto.nombre  || this.producto.nombre.length < 1 ){ this.searching = false; this.producto = {}; this.productos.total = 0; }
    }

    selectProducto(producto:any){
        this.producto = producto;
    	this.detalle.producto_id     = this.producto.id;
        this.detalle.nombre_producto = this.producto.nombre;
        this.detalle.costo           = this.producto.costo;
        this.detalle.inventarios     = this.producto.inventarios;
        if (this.detalle.inventarios.length > 0) {
            this.detalle.inventario_id    = this.detalle.inventarios[0].id;
        }

    	this.productos.total = 0;
    	document.getElementById('cantidad')!.focus();
    	// this.detalle.cantidad = 1;
    }

    
    calcularTotal(){
        this.detalle.total  = (parseFloat(this.detalle.costo) * parseFloat(this.detalle.cantidad)).toFixed(2);
    }

    agregarDetalle(){
        this.productoSelect.emit(this.detalle);
        this.modalRef.hide();
        this.clear();
	}

    clear(){
        if(this.productos.data && this.productos.data.length == 0) { this.productos = []; }
    }

}
