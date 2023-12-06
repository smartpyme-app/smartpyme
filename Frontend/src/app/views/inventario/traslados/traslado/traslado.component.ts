import { Component, OnInit, TemplateRef } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';

import { AlertService } from '../../../../services/alert.service';
import { ApiService } from '../../../../services/api.service';

@Component({
  selector: 'app-traslado',
  templateUrl: './traslado.component.html'
})
export class TrasladoComponent implements OnInit {

	public traslado: any = {};
	public detalle: any = {};

	public productos: any = [];
    public bodegas: any = [];
	public producto: any = {};

    public loading = false;
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
        this.apiService.getAll('bodegas').subscribe(bodegas => {
            this.bodegas = bodegas;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false; });

	}

	public loadAll(){
	    const id = +this.route.snapshot.paramMap.get('id')!;
	        
        if(isNaN(id)){
    		this.traslado = {};
            this.traslado.fecha = this.apiService.date();
            this.traslado.usuario_id = this.apiService.auth_user().id;
            this.traslado.origen_id = 1;
            this.traslado.destino_id = 2;
            this.traslado.estado = "En Proceso";
            this.traslado.detalles = [];
        }
        else{
            // Optenemos el traslado
            this.loading = true;
            this.apiService.read('traslado/', id).subscribe(traslado => {
	            this.traslado = traslado;
            	this.loading = false;
            }, error => {this.alertService.error(error); this.loading = false; });
        }
	}


	openModal(template: TemplateRef<any>) {
        this.modalRef = this.modalService.show(template);
    }

    setOrigen(id:any){
    	if(id == '1')
    		this.traslado.destino_id = 2;
    	else
    		this.traslado.destino_id = 1;    	
    }
    setDestino(id:any){
    	if(id == '1')
    		this.traslado.origen_id = 2;
    	else
    		this.traslado.origen_id = 1; 
    }

    productoSelect(producto:any){
    	this.producto = producto;
        this.detalle.producto_id = this.producto.id;
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
        this.modalRef.hide();
	}

	public onSubmit() {
        this.loading = true;
        this.apiService.store('traslado', this.traslado).subscribe(traslado => {
            this.router.navigateByUrl('/traslado/'+ traslado.id);
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false; });
    }

    openModalDetalle(template: TemplateRef<any>, detalle:any) {
        this.detalle = detalle;
        this.modalRef = this.modalService.show(template);
    }

    public editDetalle() {
        if(this.detalle.id) {
            this.loading = true;
    	    this.apiService.store('traslado/detalle', this.detalle).subscribe(data => {
    	    	this.detalle = {};
    			this.loading = false;
    		}, error => {this.alertService.error(error); this.loading = false; });
        }
        this.modalRef.hide();
	}

	public onGenerar(){
		if (confirm('¿Confirma la generación del traslado de inventario?')) {
			this.traslado.estado = "En Proceso";
			this.onSubmit();
		}
	}

	public onAprobar(){
		if (confirm('¿Confirma la aprobación del traslado de inventario?')) {
			this.traslado.estado = 'Aprobado';
			this.onSubmit();
		}
	}

	public eliminarDetalle(detalle:any){
		if (confirm('¿Desea eliminar el Registro?')) {
			if(detalle.id) {
				this.apiService.delete('traslado/detalle/', detalle.id).subscribe(detalle => {
					for (var i = 0; i < this.traslado.detalles.length; ++i) {
						if (this.traslado.detalles[i].id === detalle.id ){
							this.traslado.detalles.splice(i, 1);
						}
					}
		        	this.alertService.success('Detalle eliminado', 'El detalle fue eliminado exitosamente.');
	        	}, error => {this.alertService.error(error); });
			}else{
				for (var i = 0; i < this.traslado.detalles.length; ++i) {
					if (this.traslado.detalles[i].producto_id === detalle.producto_id ){
						this.traslado.detalles.splice(i, 1);
					}
				}
	        	this.alertService.success('Detalle eliminado', 'El detalle fue eliminado exitosamente.');
			}
		}
	}

	// Automatico
	    openModalStock(template: TemplateRef<any>) {
	    	this.loading = true;

		    this.apiService.getAll('traslados/requisicion/' + this.traslado.origen_id + '/' + this.traslado.destino_id).subscribe(productos => {
		       this.productos = productos;
		       this.loading = false;
			}, error => {this.alertService.error(error);this.loading = false;});

	        this.modalRef = this.modalService.show(template, {class: 'modal-lg'});
	    }

	    eliminarProducto(producto:any){
			for (var i = 0; i < this.productos.length; ++i) {
				if (this.productos[i].producto_id === producto.producto_id ){
					this.productos.splice(i, 1);
				}
			}
	    }

	    agregarProductos(){
	    	if(this.productos.length > 0) {
	    		this.traslado.detalles = this.productos;
	    		this.modalRef.hide();
	    	}
	    }

}
