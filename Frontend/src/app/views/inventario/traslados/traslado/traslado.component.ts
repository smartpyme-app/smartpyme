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
    public lotes: any[] = [];
    public lotesDestino: any[] = [];
    public loadingLotes: boolean = false;
    public loadingLotesDestino: boolean = false;

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
        // Limpiar datos al abrir el modal para agregar nuevo producto
        this.producto = {};
        this.detalle = {};
        this.lotes = [];
        this.lotesDestino = [];
        this.modalRef = this.modalService.show(template);
    }

    setOrigen(id:any){
    	if(id == '1')
    		this.traslado.destino_id = 2;
    	else
    		this.traslado.destino_id = 1;

    	// Si hay un producto seleccionado con lotes, recargar lotes
    	if (this.producto?.inventario_por_lotes && this.detalle.producto_id) {
            this.cargarLotes();
        }
    }
    setDestino(id:any){
    	if(id == '1')
    		this.traslado.origen_id = 2;
    	else
    		this.traslado.origen_id = 1;

    	// Si hay un producto seleccionado con lotes, recargar lotes
    	if (this.producto?.inventario_por_lotes && this.detalle.producto_id) {
            this.cargarLotes();
        }
    }

    productoSelect(producto:any){
    	this.producto = producto;
        this.detalle.producto_id = this.producto.id;
        this.detalle.nombre_producto = this.producto.nombre;
        this.detalle.medida = this.producto.medida;
        this.detalle.nombre_categoria = this.producto.nombre_categoria;
        this.detalle.lote_id = null;
        this.detalle.lote_id_destino = null;
        this.lotes = [];
        this.lotesDestino = [];

        // Si el producto tiene inventario por lotes, cargar los lotes de la bodega origen
        if (this.producto?.inventario_por_lotes && this.traslado?.origen_id) {
            this.cargarLotes();
        }

        // Si ya hay bodega destino, cargar lotes de destino
        if (this.producto?.inventario_por_lotes && this.traslado?.destino_id) {
            this.cargarLotesDestino();
        }

        // Solo hacer focus si el elemento existe (no en el modal de edición)
        const cantidadInput = document.getElementById('cantidad');
        if (cantidadInput) {
            cantidadInput.focus();
        }
    }

    limpiarProducto() {
        this.producto = {};
        this.detalle.producto_id = null;
        this.detalle.nombre_producto = null;
        this.detalle.medida = null;
        this.detalle.nombre_categoria = null;
        this.detalle.lote_id = null;
        this.lotes = [];
    }

    cargarLotes() {
        if (!this.detalle.producto_id || !this.traslado?.origen_id) return;

        this.loadingLotes = true;
        this.apiService.getAll(`lotes/producto/${this.detalle.producto_id}`, {
            id_bodega: this.traslado.origen_id
        }).subscribe(lotes => {
            this.lotes = Array.isArray(lotes) ? lotes : [];
            this.loadingLotes = false;
        }, error => {
            this.alertService.error(error);
            this.loadingLotes = false;
            this.lotes = [];
        });
    }

    public setLoteOrigen() {
        // Recargar lotes para obtener stock actualizado cuando se selecciona un lote
        if (this.producto?.inventario_por_lotes && this.isLotesActivo() && this.detalle.lote_id && this.traslado?.origen_id) {
            this.cargarLotes();
        }
    }

    public validarStockLote() {
        // Recargar lotes para obtener stock actualizado cuando se cambia la cantidad
        if (this.producto?.inventario_por_lotes && this.isLotesActivo() && this.detalle.lote_id && this.traslado?.origen_id) {
            this.cargarLotes();
        }
    }

    public stockLoteSuficiente(): boolean {
        if (!this.producto?.inventario_por_lotes || !this.isLotesActivo() || !this.detalle.lote_id || !this.detalle.cantidad) {
            return true;
        }

        const loteSeleccionado = this.lotes.find((l: any) => l.id == this.detalle.lote_id);
        if (!loteSeleccionado) {
            return false;
        }

        const stockDisponible = parseFloat(loteSeleccionado.stock) || 0;
        const cantidadRequerida = parseFloat(this.detalle.cantidad) || 0;

        return stockDisponible >= cantidadRequerida;
    }

    public getStockOrigen(): number {
        // Si tiene lotes activos y hay un lote seleccionado, usar el stock del lote
        if (this.producto?.inventario_por_lotes && this.isLotesActivo() && this.detalle.lote_id) {
            const loteSeleccionado = this.lotes.find((l: any) => l.id == this.detalle.lote_id);
            if (loteSeleccionado) {
                return parseFloat(loteSeleccionado.stock) || 0;
            }
        }
        // Si no tiene lotes, usar el stock tradicional del inventario
        // Buscar el inventario de la bodega origen
        if (this.producto?.inventarios && this.traslado?.origen_id) {
            const inventario = this.producto.inventarios.find((inv: any) => inv.id_bodega == this.traslado.origen_id);
            if (inventario) {
                return parseFloat(inventario.stock) || 0;
            }
            // Fallback: usar índice si existe
            if (this.producto.inventarios[this.traslado.origen_id - 1]) {
                return parseFloat(this.producto.inventarios[this.traslado.origen_id - 1].stock) || 0;
            }
        }
        return 0;
    }

    public getStockDestino(): number {
        // Si tiene lotes activos y hay un lote destino seleccionado, usar el stock del lote
        if (this.producto?.inventario_por_lotes && this.isLotesActivo() && this.detalle.lote_id_destino) {
            const loteDestinoSeleccionado = this.lotesDestino.find((l: any) => l.id == this.detalle.lote_id_destino);
            if (loteDestinoSeleccionado) {
                return parseFloat(loteDestinoSeleccionado.stock) || 0;
            }
        }
        // Si no tiene lotes, usar el stock tradicional del inventario
        // Buscar el inventario de la bodega destino
        if (this.producto?.inventarios && this.traslado?.destino_id) {
            const inventario = this.producto.inventarios.find((inv: any) => inv.id_bodega == this.traslado.destino_id);
            if (inventario) {
                return parseFloat(inventario.stock) || 0;
            }
            // Fallback: usar índice si existe
            if (this.producto.inventarios[this.traslado.destino_id - 1]) {
                return parseFloat(this.producto.inventarios[this.traslado.destino_id - 1].stock) || 0;
            }
        }
        return 0;
    }

    public getNombreBodegaOrigen(): string {
        if (this.producto?.inventarios && this.traslado?.origen_id) {
            const inventario = this.producto.inventarios.find((inv: any) => inv.id_bodega == this.traslado.origen_id);
            if (inventario) {
                return inventario.nombre_bodega || 'Bodega Origen';
            }
            // Fallback: usar índice si existe
            if (this.producto.inventarios[this.traslado.origen_id - 1]) {
                return this.producto.inventarios[this.traslado.origen_id - 1].nombre_bodega || 'Bodega Origen';
            }
        }
        return 'Bodega Origen';
    }

    public getNombreBodegaDestino(): string {
        if (this.producto?.inventarios && this.traslado?.destino_id) {
            const inventario = this.producto.inventarios.find((inv: any) => inv.id_bodega == this.traslado.destino_id);
            if (inventario) {
                return inventario.nombre_bodega || 'Bodega Destino';
            }
            // Fallback: usar índice si existe
            if (this.producto.inventarios[this.traslado.destino_id - 1]) {
                return this.producto.inventarios[this.traslado.destino_id - 1].nombre_bodega || 'Bodega Destino';
            }
        }
        return 'Bodega Destino';
    }

    public getStockOrigenDespues(): number {
        if (!this.detalle.cantidad) {
            return this.getStockOrigen();
        }
        const cantidad = Number(this.detalle.cantidad) || 0;
        const stockOrigen = this.getStockOrigen();
        return Math.max(0, stockOrigen - cantidad);
    }

    public getStockDestinoDespues(): number {
        if (!this.detalle.cantidad) {
            return this.getStockDestino();
        }
        const cantidad = Number(this.detalle.cantidad) || 0;
        const stockDestino = this.getStockDestino();
        return stockDestino + cantidad;
    }

    cargarLotesDestino() {
        if (!this.detalle.producto_id || !this.traslado?.destino_id) return;

        this.loadingLotesDestino = true;
        this.apiService.getAll(`lotes/producto/${this.detalle.producto_id}`, {
            id_bodega: this.traslado.destino_id
        }).subscribe(lotes => {
            this.lotesDestino = Array.isArray(lotes) ? lotes : [];
            this.loadingLotesDestino = false;
        }, error => {
            this.alertService.error(error);
            this.loadingLotesDestino = false;
            this.lotesDestino = [];
        });
    }

    isLotesActivo(): boolean {
        return this.apiService.isLotesActivo();
    }


	agregarDetalle(){
		// Validar que si el producto tiene lotes, se haya seleccionado un lote
		if (this.producto?.inventario_por_lotes && this.isLotesActivo() && !this.detalle.lote_id) {
			this.alertService.error('Debe seleccionar un lote para este producto.');
			return;
		}

		// Validar stock del lote antes de agregar
		if (this.producto?.inventario_por_lotes && this.isLotesActivo() && this.detalle.lote_id && this.detalle.cantidad) {
			const loteSeleccionado = this.lotes.find((l: any) => l.id == this.detalle.lote_id);
			if (loteSeleccionado) {
				const stockDisponible = parseFloat(loteSeleccionado.stock) || 0;
				const cantidadRequerida = parseFloat(this.detalle.cantidad) || 0;
				if (stockDisponible < cantidadRequerida) {
					this.alertService.error(`El lote no tiene stock suficiente. Stock disponible: ${stockDisponible.toFixed(2)}, Cantidad requerida: ${cantidadRequerida.toFixed(2)}`);
					// Recargar lotes para obtener stock actualizado
					this.cargarLotes();
					return;
				}
			}
		}

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
        this.detalle = {...detalle}; // Crear copia del detalle
        this.producto = {};
        this.lotes = [];
        this.lotesDestino = [];

        // Cargar el producto desde la API
        if (detalle.producto_id) {
            this.loading = true;
            this.apiService.read('producto/', detalle.producto_id).subscribe(producto => {
                this.producto = producto;
                this.loading = false;

                // Si el producto tiene inventario por lotes, cargar los lotes
                if (this.producto?.inventario_por_lotes && this.traslado?.origen_id) {
                    this.cargarLotes();
                }

                // Si el producto tiene inventario por lotes, cargar los lotes de destino
                if (this.producto?.inventario_por_lotes && this.traslado?.destino_id) {
                    this.cargarLotesDestino();
                }
            }, error => {
                this.alertService.error(error);
                this.loading = false;
            });
        }

        this.modalRef = this.modalService.show(template);
    }

    public editDetalle() {
        // Validar que si el producto tiene lotes, se haya seleccionado un lote
        if (this.producto?.inventario_por_lotes && this.isLotesActivo() && !this.detalle.lote_id) {
            this.alertService.error('Debe seleccionar un lote para este producto.');
            return;
        }

        // Validar stock del lote antes de guardar
        if (this.producto?.inventario_por_lotes && this.isLotesActivo() && this.detalle.lote_id && this.detalle.cantidad) {
            const loteSeleccionado = this.lotes.find((l: any) => l.id == this.detalle.lote_id);
            if (loteSeleccionado) {
                const stockDisponible = parseFloat(loteSeleccionado.stock) || 0;
                const cantidadRequerida = parseFloat(this.detalle.cantidad) || 0;
                if (stockDisponible < cantidadRequerida) {
                    this.alertService.error(`El lote no tiene stock suficiente. Stock disponible: ${stockDisponible.toFixed(2)}, Cantidad requerida: ${cantidadRequerida.toFixed(2)}`);
                    // Recargar lotes para obtener stock actualizado
                    this.cargarLotes();
                    return;
                }
            }
        }

        // Actualizar información del producto en el detalle si se cambió
        if (this.producto?.id) {
            this.detalle.producto_id = this.producto.id;
            this.detalle.nombre_producto = this.producto.nombre;
            this.detalle.medida = this.producto.medida;
            this.detalle.nombre_categoria = this.producto.nombre_categoria;
        }

        if(this.detalle.id) {
            this.loading = true;
    	    this.apiService.store('traslado/detalle', this.detalle).subscribe(data => {
                // Actualizar el detalle en el array local
                const index = this.traslado.detalles.findIndex((d: any) => d.id === this.detalle.id);
                if (index !== -1) {
                    this.traslado.detalles[index] = {...this.detalle};
                }
    	    	this.detalle = {};
                this.producto = {};
                this.lotes = [];
    			this.loading = false;
                this.modalRef.hide();
    		}, error => {this.alertService.error(error); this.loading = false; });
        } else {
            // Si no tiene ID, es un detalle nuevo en un traslado existente
            // Actualizar el detalle en el array local
            const index = this.traslado.detalles.findIndex((d: any) => d.producto_id === this.detalle.producto_id && !d.id);
            if (index !== -1) {
                this.traslado.detalles[index] = {...this.detalle};
            }
            this.detalle = {};
            this.producto = {};
            this.lotes = [];
            this.modalRef.hide();
        }
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
