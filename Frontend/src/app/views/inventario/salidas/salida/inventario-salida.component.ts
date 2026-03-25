import { Component, OnInit, TemplateRef, ViewChild } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

@Component({
  selector: 'app-inventario-salida',
  templateUrl: './inventario-salida.component.html'
})
export class InventarioSalidaComponent implements OnInit {

	public salida: any = {};
	public detalle: any = {};

	public productos: any = [];
    public bodegas: any = [];
	public producto: any = {};

    public loading = false;
    public saving = false;
    modalRef!: BsModalRef;
    
    // Lotes
    @ViewChild('mlote') public mloteTemplate!: TemplateRef<any>;
    public lotes: any[] = [];
    public loteSeleccionado: any = null;

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
            this.apiService.read('salida/', id).subscribe(salida => {
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
        this.detalle.inventario_por_lotes = this.producto.inventario_por_lotes;
        this.detalle.cantidad = 1;
        this.detalle.total = this.detalle.cantidad * this.detalle.costo;
        this.salida.detalles.push(this.detalle);
        this.producto = {};
        this.detalle = {};
        // document.getElementById('cantidad')!.focus();
    }
    
    isLotesActivo(): boolean {
        return this.apiService.isLotesActivo();
    }
    
    abrirModalLote(detalle: any) {
        this.detalle = detalle;
        this.loteSeleccionado = null;
        this.cargarLotesDisponibles();
        setTimeout(() => {
            this.modalRef = this.modalService.show(this.mloteTemplate, {class: 'modal-lg', backdrop: 'static'});
        }, 100);
    }
    
    cargarLotesDisponibles() {
        if (!this.detalle.id_producto || !this.salida.id_bodega) {
            this.lotes = [];
            return;
        }
        
        this.loading = true;
        this.apiService.getAll(`lotes/producto/${this.detalle.id_producto}`, {
            id_bodega: this.salida.id_bodega
        }).subscribe(lotes => {
            this.lotes = Array.isArray(lotes) ? lotes : [];
            this.loading = false;
        }, error => {
            console.error('Error al cargar lotes:', error);
            this.alertService.error('Error al cargar los lotes del producto');
            this.loading = false;
            this.lotes = [];
        });
    }
    
    seleccionarLote(lote: any) {
        this.loteSeleccionado = lote;
        this.detalle.lote_id = lote.id;
        this.detalle.lote = lote;
        this.modalRef.hide();
    }
    
    cerrarModalLote() {
        if (this.modalRef) {
            this.modalRef.hide();
        }
    }
    
    isLoteVencido(lote: any): boolean {
        if (!lote.fecha_vencimiento) return false;
        const hoy = new Date();
        const vencimiento = new Date(lote.fecha_vencimiento);
        return vencimiento < hoy;
    }
   
    updateDetalle(detalle:any){
        detalle.total = detalle.cantidad * detalle.costo;
    }

	public onSubmit() {
        this.saving = true;
        this.apiService.store('salida', this.salida).subscribe(salida => {
            this.router.navigateByUrl('/salidas');
            this.saving = false;
        }, error => {this.alertService.error(error); this.saving = false; });
    }

    openModalDetalle(template: TemplateRef<any>, detalle:any) {
        this.detalle = detalle;
        this.modalRef = this.modalService.show(template);
    }

    public editDetalle() {
        if(this.detalle.id) {
            this.loading = true;
    	    this.apiService.store('salida/detalle', this.detalle).subscribe(data => {
    	    	this.detalle = {};
    			this.loading = false;
    		}, error => {this.alertService.error(error); this.loading = false; });
        }
        this.modalRef.hide();
	}


	public eliminarDetalle(detalle:any){
		if (confirm('¿Desea eliminar el Registro?')) {
			if(detalle.id) {
				this.apiService.delete('salida/detalle/', detalle.id).subscribe(detalle => {
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
