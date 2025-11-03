import { Component, OnInit, TemplateRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { Router, ActivatedRoute } from '@angular/router';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { BuscadorProductosComponent } from '@shared/parts/buscador-productos/buscador-productos.component';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

@Component({
    selector: 'app-inventario-entrada',
    templateUrl: './inventario-entrada.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, BuscadorProductosComponent],
    
})
export class InventarioEntradaComponent implements OnInit {

	public entrada: any = {};
	public detalle: any = {};

	public productos: any = [];
    public bodegas: any = [];
	public producto: any = {};

    public loading = false;
    public saving = false;
    modalRef!: BsModalRef;

	constructor( 
	    public apiService: ApiService, private alertService: AlertService,
	    private route: ActivatedRoute, private router: Router,
	    private modalService: BsModalService
    ) { 
        this.router.routeReuseStrategy.shouldReuseRoute = function() {return false; };
    }

    ngOnInit() {
        this.loadAll();
        this.loading = true;
        this.apiService.getAll('bodegas/list').subscribe(bodegas => {
            this.bodegas = bodegas;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false; });

	}

	public loadAll(){
	    const id = +this.route.snapshot.paramMap.get('id')!;
	        
        if(!id){
    		this.entrada = {};
            this.entrada.fecha = this.apiService.date();
            this.entrada.id_usuario = this.apiService.auth_user().id;
            this.entrada.id_bodega = this.apiService.auth_user().id_bodega;
            this.entrada.id_empresa = this.apiService.auth_user().id_empresa;
            this.entrada.detalles = [];
        }
        else{
            // Optenemos el entrada
            this.loading = true;
            this.apiService.read('entrada/', id).subscribe(entrada => {
	            this.entrada = entrada;
            	this.loading = false;
            }, error => {this.alertService.error(error); this.loading = false; });
        }
	}


	openModal(template: TemplateRef<any>) {
        this.modalRef = this.modalService.show(template);
    }

    productoSelect(producto:any){
    	this.producto = producto;
        this.detalle.id_producto = this.producto.id;
        this.detalle.nombre_producto = this.producto.nombre;
        this.detalle.medida = this.producto.medida;
        this.detalle.costo = this.producto.costo;
        this.detalle.categoria_nombre = this.producto.categoria_nombre;
        this.detalle.cantidad = 1;
		this.detalle.total = this.detalle.cantidad * this.detalle.costo;
		this.entrada.detalles.push(this.detalle);
		this.producto = {};
		this.detalle = {};
    	// document.getElementById('cantidad')!.focus();
    }
   
    updateDetalle(detalle:any){
        detalle.total = detalle.cantidad * detalle.costo;
    }
	
	public onSubmit() {
        this.saving = true;
        this.apiService.store('entrada', this.entrada).subscribe(entrada => {
            this.router.navigateByUrl('/entradas');
            this.saving = false;
        }, error => {this.alertService.error(error); this.saving = false; });
    }


	public eliminarDetalle(detalle:any){
		if (confirm('¿Desea eliminar el Registro?')) {
			if(detalle.id) {
				this.apiService.delete('entrada/detalle/', detalle.id).subscribe(detalle => {
					for (var i = 0; i < this.entrada.detalles.length; ++i) {
						if (this.entrada.detalles[i].id === detalle.id ){
							this.entrada.detalles.splice(i, 1);
						}
					}
		        	this.alertService.success("Eliminado", "El registro fue eliminado exitosamente.");
	        	}, error => {this.alertService.error(error); });
			}else{
				for (var i = 0; i < this.entrada.detalles.length; ++i) {
					if (this.entrada.detalles[i].id_producto === detalle.id_producto ){
						this.entrada.detalles.splice(i, 1);
					}
				}
	        	this.alertService.success("Eliminado", "El registro fue eliminado exitosamente.");
			}
		}
	}


}
