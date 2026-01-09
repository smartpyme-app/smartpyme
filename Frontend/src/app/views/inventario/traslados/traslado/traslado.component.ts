import { Component, OnInit, TemplateRef, inject, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { Router, ActivatedRoute } from '@angular/router';
import { NgSelectModule } from '@ng-select/ng-select';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';
import { ModalManagerService } from '@services/modal-manager.service';
import { BaseModalComponent } from '@shared/base/base-modal.component';

@Component({
    selector: 'app-traslado',
    templateUrl: './traslado.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, NgSelectModule],
    changeDetection: ChangeDetectionStrategy.OnPush
})
export class TrasladoComponent extends BaseModalComponent implements OnInit {

	public traslado: any = {};
	public detalle: any = {};

	public productos: any = [];
    public bodegas: any = [];
	public producto: any = {};
	public bodegaDe:any = {};
	public bodegaPara:any = {};

	constructor( 
	    public apiService: ApiService, 
	    protected override alertService: AlertService,
	    protected override modalManager: ModalManagerService,
	    private route: ActivatedRoute, 
	    private router: Router,
	    private cdr: ChangeDetectorRef
    ) { 
        super(modalManager, alertService);
        this.router.routeReuseStrategy.shouldReuseRoute = function() {return false; };
    }

    ngOnInit() {
        this.loadAll();
        this.loading = true;
        this.apiService.getAll('bodegas/list').pipe(this.untilDestroyed()).subscribe(bodegas => {
            this.bodegas = bodegas;

            this.traslado.id_bodega_de = this.bodegas[0].id;
            this.traslado.id_bodega = this.bodegas[1].id;
            this.loading = false;
            this.cdr.markForCheck();
        }, error => {this.alertService.error(error); this.loading = false; this.cdr.markForCheck(); });

	}

	public loadAll(){
	    const id = +this.route.snapshot.paramMap.get('id')!;
	        
        if(isNaN(id)){
    		this.traslado = {};
            this.traslado.fecha = this.apiService.date();
            this.traslado.id_usuario = this.apiService.auth_user().id;
            this.traslado.id_empresa = this.apiService.auth_user().id_empresa;
            this.traslado.estado = "Pendiente";
            this.traslado.detalles = [];
        }
        else{
            // Optenemos el traslado
            this.loading = true;
            this.apiService.read('traslado/', id).pipe(this.untilDestroyed()).subscribe(traslado => {
	            this.traslado = traslado;
            	this.loading = false;
            	this.cdr.markForCheck();
            }, error => {this.alertService.error(error); this.loading = false; this.cdr.markForCheck(); });
        }
	}

	override openModal(template: TemplateRef<any>) {
		if(!this.productos.length){
		    this.apiService.getAll('productos/list').pipe(this.untilDestroyed()).subscribe(productos => {
		        this.productos = productos;
		        this.cdr.markForCheck();
		    }, error => {this.alertService.error(error); this.cdr.markForCheck();});
		}
        super.openModal(template);
    }

    selectProducto(){
    	this.producto = this.productos.find((item:any) => item.id == this.detalle.id_producto);
        this.bodegaDe = this.producto?.inventarios.find((item:any) => item.id_bodega == this.traslado.id_bodega_de);
        this.bodegaPara = this.producto?.inventarios.find((item:any) => item.id_bodega == this.traslado.id_bodega);

    	// console.log(this.producto);
    	// console.log(this.traslado);
    	// console.log(this.bodegaDe);
    	// console.log(this.bodegaPara);

        this.detalle.id_producto = this.producto.id;
        this.detalle.nombre_producto = this.producto.nombre;
        this.detalle.medida = this.producto.medida;
        this.detalle.medida = this.producto.medida;
        this.detalle.nombre_categoria = this.producto.nombre_categoria;
    	document.getElementById('cantidad')!.focus();
    }
	

	agregarDetalle(){
		this.traslado.detalles.push(this.detalle);
		this.producto = {};
		this.detalle = {};
        this.alertService.modal = false;
        this.closeModal();
        this.cdr.markForCheck();
	}

	public async onSubmit() {
        this.saving = true;
        try {
            await this.apiService.store('traslado', this.traslado)
                .pipe(this.untilDestroyed())
                .toPromise();
            
            this.router.navigateByUrl('/traslados');
            this.cdr.markForCheck();
        } catch (error: any) {
            this.alertService.error(error);
            this.cdr.markForCheck();
        } finally {
            this.saving = false;
            this.cdr.markForCheck();
        }
    }

    openModalDetalle(template: TemplateRef<any>, detalle:any) {
        this.detalle = detalle;
        this.openModal(template);
    }

    public editDetalle() {
        if(this.detalle.id) {
            this.saving = true;
    	    this.apiService.store('traslado/detalle', this.detalle).pipe(this.untilDestroyed()).subscribe(data => {
    	    	this.detalle = {};
    			this.saving = false;
    			this.cdr.markForCheck();
    		}, error => {this.alertService.error(error); this.saving = false; this.cdr.markForCheck(); });
        }
        this.alertService.modal = false;
        this.closeModal();
	}

	public onGenerar(){
		if (confirm('¿Confirma la generación del traslado de inventario?')) {
			this.traslado.estado = "Pendiente";
			this.onSubmit();
		}
	}

	public onAprobar(){
		if (confirm('¿Confirma la aprobación del traslado de inventario?')) {
			this.traslado.estado = 'Confirmado';
			this.onSubmit();
		}
	}

	public eliminarDetalle(detalle:any){
		if (confirm('¿Desea eliminar el Registro?')) {
			if(detalle.id) {
				this.apiService.delete('traslado/detalle/', detalle.id).pipe(this.untilDestroyed()).subscribe(detalle => {
					for (var i = 0; i < this.traslado.detalles.length; ++i) {
						if (this.traslado.detalles[i].id === detalle.id ){
							this.traslado.detalles.splice(i, 1);
						}
					}
		        	this.alertService.success('Detalle eliminado', 'El detalle fue eliminado exitosamente.');
		        	this.cdr.markForCheck();
	        	}, error => {this.alertService.error(error); this.cdr.markForCheck(); });
			}else{
				for (var i = 0; i < this.traslado.detalles.length; ++i) {
					if (this.traslado.detalles[i].id_producto === detalle.id_producto ){
						this.traslado.detalles.splice(i, 1);
					}
				}
	        	this.alertService.success('Detalle eliminado', 'El detalle fue eliminado exitosamente.');
	        	this.cdr.markForCheck();
			}
		}
	}

	// Automatico
	    openModalStock(template: TemplateRef<any>) {
	    	this.loading = true;

		    this.apiService.getAll('traslados/requisicion/' + this.traslado.id_origen + '/' + this.traslado.id_destino).pipe(this.untilDestroyed()).subscribe(productos => {
		       this.productos = productos;
		       this.loading = false;
		       this.cdr.markForCheck();
			}, error => {this.alertService.error(error);this.loading = false; this.cdr.markForCheck();});

	        super.openLargeModal(template);
	    }

	    eliminarProducto(producto:any){
			for (var i = 0; i < this.productos.length; ++i) {
				if (this.productos[i].id_producto === producto.id_producto ){
					this.productos.splice(i, 1);
				}
			}
			this.cdr.markForCheck();
	    }

	    agregarProductos(){
	    	if(this.productos.length > 0) {
	    		this.traslado.detalles = this.productos;
	    		this.alertService.modal = false;
	    		this.closeModal();
	    		this.cdr.markForCheck();
	    	}
	    }

}
