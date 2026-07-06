import { Component, OnInit, TemplateRef, ViewChild } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { DistribucionLotesModalComponent } from '@shared/modals/distribucion-lotes/distribucion-lotes-modal.component';
import { textoResumenLotesDetalle } from '@utils/lotes-venta.util';

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

    @ViewChild('lotesModal') lotesModal!: DistribucionLotesModalComponent;

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
            this.loading = true;
            this.apiService.read('salida/', id).subscribe(salida => {
	            this.salida = salida;
                this.normalizarDetallesLotes();
            	this.loading = false;
            }, error => {this.alertService.error(error); this.loading = false; });
        }
	}

    private normalizarDetallesLotes(): void {
        (this.salida?.detalles || []).forEach((detalle: any) => {
            if (detalle.lote_asignaciones?.length) {
                detalle.lotes_asignados = detalle.lote_asignaciones.map((item: any) => ({
                    lote_id: item.lote_id,
                    numero_lote: item.lote?.numero_lote,
                    cantidad: item.cantidad,
                }));
            }
        });
    }

	productoSelect(producto:any){
        const detalleNuevo = {
            id_producto: producto.id_producto || producto.id,
            id_presentacion: producto.id_presentacion || null,
            factor_conversion: producto.factor_conversion || 1,
            nombre_producto: producto.nombre_mostrar || producto.nombre,
            medida: producto.medida,
            costo: producto.costo,
            categoria_nombre: producto.categoria_nombre,
            inventario_por_lotes: producto.inventario_por_lotes,
            cantidad: 1,
            total: producto.costo,
            lote_id: null,
            lotes_asignados: null,
        };
        detalleNuevo.total = detalleNuevo.cantidad * detalleNuevo.costo;
        this.salida.detalles.push(detalleNuevo);

        if (this.requiereDistribucionLotes(detalleNuevo)) {
            setTimeout(() => this.abrirModalLote(detalleNuevo), 100);
        }
    }
    
    isLotesActivo(): boolean {
        return this.apiService.isLotesActivo();
    }

    requiereDistribucionLotes(detalle: any): boolean {
        return !!detalle?.inventario_por_lotes
            && this.isLotesActivo()
            && this.apiService.getLotesMetodologia() === 'Manual';
    }
    
    abrirModalLote(detalle: any) {
        if (!this.salida.id_bodega) {
            this.alertService.warning('Bodega requerida', 'Seleccione una bodega antes de asignar lotes.');
            return;
        }
        this.lotesModal.abrir(detalle, this.salida.id_bodega);
    }

    onLotesConfirmados(detalle: any): void {
        detalle.total = detalle.cantidad * detalle.costo;
    }

    textoLotesDetalle(detalle: any): string {
        return textoResumenLotesDetalle(detalle);
    }
   
    updateDetalle(detalle:any){
        detalle.total = detalle.cantidad * detalle.costo;
        if (this.requiereDistribucionLotes(detalle)) {
            detalle.lotes_asignados = null;
            detalle.lote_id = null;
            detalle.lote = null;
        }
    }

	public onSubmit() {
        const faltanLotes = (this.salida.detalles || []).some((d: any) =>
            this.requiereDistribucionLotes(d) && !(d.lotes_asignados?.length || d.lote_id)
        );
        if (faltanLotes) {
            this.alertService.error('Debe distribuir los lotes de todos los productos con inventario por lotes.');
            return;
        }

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
					this.salida.detalles = this.salida.detalles.filter((d: any) => d.id !== detalle.id);
		        	this.alertService.success("Eliminado", "El registro fue eliminado exitosamente.");
	        	}, error => {this.alertService.error(error); });
			}else{
                const idx = this.salida.detalles.indexOf(detalle);
                if (idx !== -1) {
                    this.salida.detalles.splice(idx, 1);
                }
	        	this.alertService.success("Eliminado", "El registro fue eliminado exitosamente.");
			}
		}
	}


}
