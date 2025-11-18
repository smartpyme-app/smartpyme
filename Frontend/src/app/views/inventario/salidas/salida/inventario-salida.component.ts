import { Component, OnInit, TemplateRef, DestroyRef, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { Router, ActivatedRoute } from '@angular/router';
import { BuscadorProductosComponent } from '@shared/parts/buscador-productos/buscador-productos.component';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';
import { ModalManagerService } from '@services/modal-manager.service';
import { BaseModalComponent } from '@shared/base/base-modal.component';

@Component({
    selector: 'app-inventario-salida',
    templateUrl: './inventario-salida.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, BuscadorProductosComponent],
    
})
export class InventarioSalidaComponent extends BaseModalComponent implements OnInit {

	public salida: any = {};
	public detalle: any = {};

	public productos: any = [];
    public bodegas: any = [];
	public producto: any = {};

    private destroyRef = inject(DestroyRef);
    private untilDestroyed = subscriptionHelper(this.destroyRef);

	constructor( 
	    public apiService: ApiService, 
	    protected override alertService: AlertService,
	    protected override modalManager: ModalManagerService,
	    private route: ActivatedRoute, 
	    private router: Router
    ) { 
        super(modalManager, alertService);
        this.router.routeReuseStrategy.shouldReuseRoute = function() {return false; };
    }

    ngOnInit() {
        this.loadAll();
        this.loading = true;
        this.apiService.getAll('bodegas/list').pipe(this.untilDestroyed()).subscribe(bodegas => {
            this.bodegas = bodegas;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false; });

	}

	public loadAll(){
	    const id = +this.route.snapshot.paramMap.get('id')!;
	        
        if(!id){
    		this.salida = {};
            this.salida.fecha = this.apiService.date();
            this.salida.id_usuario = this.apiService.auth_user().id;
            this.salida.id_bodega = this.apiService.auth_user().id_bodega;
            this.salida.id_empresa = this.apiService.auth_user().id_empresa;
            this.salida.detalles = [];
        }
        else{
            // Optenemos el salida
            this.loading = true;
            this.apiService.read('salida/', id).pipe(this.untilDestroyed()).subscribe(salida => {
	            this.salida = salida;
            	this.loading = false;
            }, error => {this.alertService.error(error); this.loading = false; });
        }
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
        this.salida.detalles.push(this.detalle);
        this.producto = {};
        this.detalle = {};
        // document.getElementById('cantidad')!.focus();
    }
   
    updateDetalle(detalle:any){
        detalle.total = detalle.cantidad * detalle.costo;
    }

	public onSubmit() {
        this.saving = true;
        this.apiService.store('salida', this.salida).pipe(this.untilDestroyed()).subscribe(salida => {
            this.router.navigateByUrl('/salidas');
            this.saving = false;
        }, error => {this.alertService.error(error); this.saving = false; });
    }

    openModalDetalle(template: TemplateRef<any>, detalle:any) {
        this.detalle = detalle;
        super.openModal(template);
    }

    public editDetalle() {
        if(this.detalle.id) {
            this.loading = true;
    	    this.apiService.store('salida/detalle', this.detalle).pipe(this.untilDestroyed()).subscribe(data => {
    	    	this.detalle = {};
    			this.loading = false;
    		}, error => {this.alertService.error(error); this.loading = false; });
        }
        this.closeModal();
	}


	public eliminarDetalle(detalle:any){
		if (confirm('¿Desea eliminar el Registro?')) {
			if(detalle.id) {
				this.apiService.delete('salida/detalle/', detalle.id).pipe(this.untilDestroyed()).subscribe(detalle => {
					for (var i = 0; i < this.salida.detalles.length; ++i) {
						if (this.salida.detalles[i].id === detalle.id ){
							this.salida.detalles.splice(i, 1);
						}
					}
		        	this.alertService.success("Eliminado", "El registro fue eliminado exitosamente.");
	        	}, error => {this.alertService.error(error); });
			}else{
				for (var i = 0; i < this.salida.detalles.length; ++i) {
					if (this.salida.detalles[i].id_producto === detalle.id_producto ){
						this.salida.detalles.splice(i, 1);
					}
				}
	        	this.alertService.success("Eliminado", "El registro fue eliminado exitosamente.");
			}
		}
	}


}
