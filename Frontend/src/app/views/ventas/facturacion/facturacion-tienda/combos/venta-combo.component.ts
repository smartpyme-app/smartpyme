import { Component, OnInit, ViewChild, EventEmitter, Input, Output, TemplateRef } from '@angular/core';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';

import { ApiService } from '../../../../../services/api.service';
import { AlertService } from '../../../../../services/alert.service';

@Component({
  selector: 'app-venta-combo',
  templateUrl: './venta-combo.component.html'
})
export class VentaComboComponent implements OnInit {

	@Output() productoSelect = new EventEmitter();
	modalRef!: BsModalRef;
    @ViewChild('mcomboDetalle')
    public wizardRef!: TemplateRef<any>;  

	public combos:any = [];
    public combo: any = {};
    public producto: any = {};
	public categorias:any[] = [];
    public loading:boolean = false;
    public buscador:string = '';

	constructor( 
	    private apiService: ApiService, private alertService: AlertService,
	    private modalService: BsModalService
	) { }

	ngOnInit() {
        if (localStorage.getItem('categorias')) { 
            this.categorias = JSON.parse(localStorage.getItem('categorias')!);
        } else {
            this.apiService.getAll('categorias').subscribe(categorias => {
                localStorage.setItem('categorias', JSON.stringify(categorias));
                this.categorias = categorias;
            }, error => {this.alertService.error(error);});
        }
	}

	openModal(template: TemplateRef<any>) {
        this.cargarCombos();
        this.combo = {};
        this.buscador = '';
        this.modalRef = this.modalService.show(template);
    }

    cargarCombos(){
        this.loading = true;
        this.apiService.getAll('combos-all').subscribe(combos => {
            this.combos = combos;
            localStorage.setItem('combos', JSON.stringify(combos));
            this.loading = false;
        }, error => {this.alertService.error(error);});
    }

    selectCombo(combo:any){
        this.combo = combo;
        this.combos.data = [];
        this.modalRef.hide();
        this.modalRef = this.modalService.show(this.wizardRef);
    }

    agregarDetalle(producto:any){
        this.producto.producto_id  = producto.id;
        this.producto.producto_nombre     = producto.nombre;
        this.producto.descuento    = 0;
        this.producto.precio       = producto.precio;
        this.producto.costo        = producto.costo;
        this.producto.escombo      = true;
        this.producto.detalles     = producto.detalles;
        this.producto.cantidad     = 1;
        this.producto.tipo_impuesto = "Gravada";
        this.producto.iva         = 0;
        this.producto.fovial      = 0;
        this.producto.cotrans     = 0;

        // Descuento promoción si esta en fecha
        if (producto.promocion) {
            this.producto.descuento = this.producto.precio - producto.promocion.precio;
        }

        this.productoSelect.emit(this.producto);
        this.combos.data = [];
        this.modalRef.hide();
    }

    changeOpcion(detalle:any, opcion:any){
        let aux = Object.assign({}, detalle);

        detalle.producto_id = opcion.producto_id;
        detalle.producto     = opcion.producto;

        opcion.producto_id  = aux.producto_id;
        opcion.producto      = aux.producto;

    }

}
