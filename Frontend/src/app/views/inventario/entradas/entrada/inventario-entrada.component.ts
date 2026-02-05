import { Component, OnInit, TemplateRef, ViewChild } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

@Component({
  selector: 'app-inventario-entrada',
  templateUrl: './inventario-entrada.component.html'
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
    
    // Lotes
    @ViewChild('mlote') public mloteTemplate!: TemplateRef<any>;
    public lotes: any[] = [];
    public loteSeleccionado: any = null;
    public nuevoLote: any = {
        numero_lote: '',
        fecha_vencimiento: null,
        fecha_fabricacion: null,
        observaciones: ''
    };
    public crearNuevoLote: boolean = false;

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
        this.detalle.inventario_por_lotes = this.producto.inventario_por_lotes;
        this.detalle.cantidad = 1;
		this.detalle.total = this.detalle.cantidad * this.detalle.costo;
		this.entrada.detalles.push(this.detalle);
		this.producto = {};
		this.detalle = {};
    	// document.getElementById('cantidad')!.focus();
    }
    
    isLotesActivo(): boolean {
        const empresa = this.apiService.auth_user()?.empresa;
        if (!empresa || !empresa.custom_empresa) return false;
        const customConfig = typeof empresa.custom_empresa === 'string' 
            ? JSON.parse(empresa.custom_empresa) 
            : empresa.custom_empresa;
        return customConfig?.configuraciones?.lotes_activo ?? false;
    }
    
    abrirModalLote(detalle: any) {
        this.detalle = detalle;
        this.crearNuevoLote = false;
        this.loteSeleccionado = null;
        this.nuevoLote = {
            numero_lote: '',
            fecha_vencimiento: null,
            fecha_fabricacion: null,
            observaciones: ''
        };
        this.cargarLotesDisponibles();
        setTimeout(() => {
            this.modalRef = this.modalService.show(this.mloteTemplate, {class: 'modal-lg', backdrop: 'static'});
        }, 100);
    }
    
    cargarLotesDisponibles() {
        if (!this.detalle.id_producto || !this.entrada.id_bodega) {
            this.lotes = [];
            return;
        }
        
        this.loading = true;
        this.apiService.getAll(`lotes/producto/${this.detalle.id_producto}`, {
            id_bodega: this.entrada.id_bodega
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
        this.crearNuevoLote = false;
        this.detalle.lote_id = lote.id;
        this.detalle.lote = lote;
        this.modalRef.hide();
    }
    
    cambiarModoLote(crear: boolean) {
        this.crearNuevoLote = crear;
        if (crear) {
            this.loteSeleccionado = null;
        }
    }
    
    crearLote() {
        if (!this.detalle.id_producto || !this.entrada.id_bodega) {
            this.alertService.error('Faltan datos para crear el lote');
            return;
        }

        if (!this.nuevoLote.numero_lote || this.nuevoLote.numero_lote.trim() === '') {
            this.alertService.error('El número de lote es requerido');
            return;
        }

        this.loading = true;
        const loteData = {
            id_producto: this.detalle.id_producto,
            id_bodega: this.entrada.id_bodega,
            numero_lote: this.nuevoLote.numero_lote.trim(),
            fecha_vencimiento: this.nuevoLote.fecha_vencimiento,
            fecha_fabricacion: this.nuevoLote.fecha_fabricacion,
            stock: 0,
            observaciones: this.nuevoLote.observaciones
        };

        this.apiService.store('lotes', loteData).subscribe(lote => {
            this.detalle.lote_id = lote.id;
            this.detalle.lote = lote;
            this.alertService.success('Lote creado', 'El lote fue creado exitosamente.');
            this.nuevoLote = {
                numero_lote: '',
                fecha_vencimiento: null,
                fecha_fabricacion: null,
                observaciones: ''
            };
            this.crearNuevoLote = false;
            this.loading = false;
            this.modalRef.hide();
        }, error => {
            this.alertService.error(error);
            this.loading = false;
        });
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
