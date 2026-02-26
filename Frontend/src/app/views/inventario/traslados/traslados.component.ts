import { Component, OnInit, TemplateRef, OnDestroy, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { Router, ActivatedRoute } from '@angular/router';
import { PopoverModule } from 'ngx-bootstrap/popover';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { NgSelectModule } from '@ng-select/ng-select';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { ModalManagerService } from '@services/modal-manager.service';
import { TruncatePipe } from '@pipes/truncate.pipe';
import { PaginationComponent } from '@shared/parts/pagination/pagination.component';
import { BaseCrudComponent } from '@shared/base/base-crud.component';
import { BsModalService } from 'ngx-bootstrap/modal';
import { Subscription } from 'rxjs';
import { distinctUntilChanged, debounceTime } from 'rxjs/operators';
import { LazyImageDirective } from '../../../directives/lazy-image.directive';

@Component({
    selector: 'app-traslados',
    templateUrl: './traslados.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, NgSelectModule, TruncatePipe, PopoverModule, TooltipModule, PaginationComponent, LazyImageDirective],
    changeDetection: ChangeDetectionStrategy.OnPush
})
export class TrasladosComponent extends BaseCrudComponent<any> implements OnInit, OnDestroy {

    // Propiedades propias del componente
    public traslados:any = [];
    public traslado:any = {};
    public downloading:boolean = false;
    public productos:any = [];
    public sucursales:any = [];
    public conceptos:any = [];
    public producto:any = {};
    public productoFiltro:any = {};
    public bodegaDe:any = {};
    public bodegaPara:any = {};
    public lotes: any[] = [];
    public lotesDestino: any[] = [];
    public loadingLotes: boolean = false;
    public loadingLotesDestino: boolean = false;
    private tieneShopify: boolean = false;
    private queryParamsSubscription?: Subscription;
    private isNavigating: boolean = false;
    private isLoadingProductos: boolean = false;

    // Propiedades heredadas - declaradas explícitamente para TypeScript
    declare public filtros: any;
    declare public loading: boolean;
    declare public saving: boolean;
    declare public modalRef?: any;
    declare protected untilDestroyed: <T>() => (source: import('rxjs').Observable<T>) => import('rxjs').Observable<T>;
    declare public closeModal: () => void;
    declare public openLargeModal: (template: TemplateRef<any>, config?: any) => void;

    constructor(
        apiService: ApiService,
        alertService: AlertService,
        modalManager: ModalManagerService,
        private modalService: BsModalService,
        private router: Router,
        private route: ActivatedRoute,
        private cdr: ChangeDetectorRef
    ){
        super(apiService, alertService, modalManager, {
            endpoint: 'traslado',
            itemsProperty: 'traslados',
            itemProperty: 'traslado',
            reloadAfterSave: true,
            reloadAfterDelete: true,
            messages: {
                created: 'El traslado fue añadido exitosamente.',
                updated: 'El traslado fue añadido exitosamente.',
                deleted: 'El traslado fue cancelado exitosamente.',
                createTitle: 'Traslado realizado',
                updateTitle: 'Traslado realizado',
                deleteTitle: 'Traslado cancelado'
            },
            beforeSave: (item) => {
                item.id_usuario = apiService.auth_user().id;
                return item;
            },
            afterSave: () => {
                this.traslado = {};
            },
            afterDelete: () => {
                this.traslado = {};
            }
        });
        // Inicializar filtros después de llamar a super()
        this.filtros = {};
    }

    protected aplicarFiltros(): void {
        this.filtrarTraslados();
    }

    ngOnInit() {
        const empresa = this.apiService.auth_user()?.empresa;
        this.tieneShopify = !!empresa?.shopify_store_url;

        // Inicializar filtros desde queryParams o valores por defecto
        const params = this.route.snapshot.queryParams;
        this.filtros = {
            search: params['search'] || '',
            id_bodega_de: +params['id_bodega_de'] || '',
            id_bodega_para: +params['id_bodega_para'] || '',
            id_sucursal: +params['id_sucursal'] || '',
            estado: params['estado'] || '',
            concepto: params['concepto'] || '',
            orden: params['orden'] || 'id',
            direccion: params['direccion'] || 'desc',
            paginate: params['paginate'] || 10,
            page: params['page'] || 1,
        };

        // Cargar datos iniciales
        this.filtrarTrasladosSinNavegar();

        // Suscribirse a cambios en queryParams
        this.queryParamsSubscription = this.route.queryParams
            .pipe(
                distinctUntilChanged(),
                debounceTime(100) // Evitar múltiples llamadas rápidas
            )
            .subscribe(params => {
                // Solo cargar datos si no estamos navegando (para evitar loops infinitos)
                if (!this.isNavigating) {
                    this.filtros = {
                        search: params['search'] || '',
                        id_bodega_de: +params['id_bodega_de'] || '',
                        id_bodega_para: +params['id_bodega_para'] || '',
                        id_sucursal: +params['id_sucursal'] || '',
                        estado: params['estado'] || '',
                        concepto: params['concepto'] || '',
                        orden: params['orden'] || 'id',
                        direccion: params['direccion'] || 'desc',
                        paginate: params['paginate'] || 10,
                        page: params['page'] || 1,
                    };

                    // Cargar los datos después de actualizar los filtros
                    this.filtrarTrasladosSinNavegar();
                }
            });

        this.apiService.getAll('sucursales/list').pipe(this.untilDestroyed()).subscribe(sucursales => {
            this.sucursales = sucursales;
            this.cdr.markForCheck();
        }, error => {this.alertService.error(error); this.cdr.markForCheck(); });
    }

    ngOnDestroy() {
        if (this.queryParamsSubscription) {
            this.queryParamsSubscription.unsubscribe();
        }
    }

    public override loadAll() {
        this.filtros.id_bodega_de = '';
        this.filtros.id_bodega_para = '';
        this.filtros.id_producto = '';
        this.filtros.id_sucursal = '';
        this.filtros.estado = '';
        this.filtros.search = '';
        this.filtros.concepto = '';
        this.filtros.orden = 'created_at';
        this.filtros.direccion = 'desc';
        this.filtros.paginate = 10;
        this.filtros.page = 1;

        this.loading = true;
        this.filtrarTraslados();
    }

    public filtrarTraslados(){
        if(this.modalRef){
            (this as any).closeModal();
        }
        this.isNavigating = true;
        this.router.navigate([], {
            relativeTo: this.route,
            queryParams: this.filtros,
            queryParamsHandling: 'merge',
        }).then(() => {
            // Después de navegar, esperar un momento y luego cargar los datos
            setTimeout(() => {
                this.isNavigating = false;
                // Los datos se cargarán automáticamente cuando cambien los queryParams
            }, 100);
        });
    }

    private filtrarTrasladosSinNavegar(){
        this.loading = true;
        this.apiService.getAll('traslados', this.filtros).pipe(this.untilDestroyed()).subscribe(traslados => {
            this.traslados = traslados;
            this.loading = false;
            this.cdr.markForCheck();
        }, error => {this.alertService.error(error); this.loading = false; this.cdr.markForCheck(); });
    }

    public setOrden(columna: string) {
        if (columna !== 'created_at' && columna !== 'cantidad') {
            return;
        }

        if (this.filtros.orden === columna) {
            this.filtros.direccion = this.filtros.direccion === 'asc' ? 'desc' : 'asc';
        } else {
            this.filtros.orden = columna;
            this.filtros.direccion = columna === 'created_at' ? 'desc' : 'asc';
        }

        this.filtrarTraslados();
    }

    public setEstado(traslado:any, estado:any){
        this.traslado = traslado;
        this.traslado.estado = estado;
        if (this.traslado.estado == 'Cancelado') {
            if (confirm('¿Confirma cancelar el traslado?')) {
                this.delete(this.traslado.id);
            }
        }else{
            if (confirm('¿Confirma aplicar el traslado?')) {
                this.onSubmit();
            }
        }
    }

    public productoSelect(producto: any) {
        this.producto = producto;
        this.traslado.id_producto = producto.id;
        this.traslado.costo = producto.costo;
        this.traslado.lote_id = null;
        this.traslado.lote_id_destino = null;
        this.lotes = [];
        this.lotesDestino = [];

        // Si el producto tiene inventario por lotes, cargar los lotes cuando se seleccione la bodega origen
        if (this.producto?.inventario_por_lotes && this.traslado.id_bodega_de) {
            this.cargarLotes();
        }

        // Si ya hay bodega destino, cargar lotes de destino
        if (this.producto?.inventario_por_lotes && this.traslado.id_bodega) {
            this.cargarLotesDestino();
        }
    }

    public limpiarProducto() {
        this.producto = {};
        this.traslado.id_producto = null;
        this.traslado.id_bodega_de = null;
        this.traslado.id_bodega = null;
        this.traslado.lote_id = null;
        this.traslado.lote_id_destino = null;
        this.traslado.cantidad = null;
        this.lotes = [];
        this.lotesDestino = [];
        this.bodegaDe = {};
        this.bodegaPara = {};
    }

    public productoFiltroSelect(producto: any) {
        this.productoFiltro = producto;
        this.filtros.id_producto = producto.id;
    }

    public limpiarProductoFiltro() {
        this.productoFiltro = {};
        this.filtros.id_producto = null;
    }

    public cargarLotes() {
        if (!this.traslado.id_producto || !this.traslado.id_bodega_de) return;

        this.loadingLotes = true;
        this.apiService.getAll(`lotes/producto/${this.traslado.id_producto}`, {
            id_bodega: this.traslado.id_bodega_de
        }).subscribe(lotes => {
            this.lotes = Array.isArray(lotes) ? lotes : [];
            this.loadingLotes = false;
        }, error => {
            this.alertService.error(error);
            this.loadingLotes = false;
            this.lotes = [];
        });
    }

    public cargarLotesDestino() {
        if (!this.traslado.id_producto || !this.traslado.id_bodega) return;

        this.loadingLotesDestino = true;
        this.apiService.getAll(`lotes/producto/${this.traslado.id_producto}`, {
            id_bodega: this.traslado.id_bodega
        }).subscribe(lotes => {
            this.lotesDestino = Array.isArray(lotes) ? lotes : [];
            this.loadingLotesDestino = false;
        }, error => {
            this.alertService.error(error);
            this.loadingLotesDestino = false;
            this.lotesDestino = [];
        });
    }

    public setLoteOrigen() {
        // Recargar lotes para obtener stock actualizado cuando se selecciona un lote
        if (this.producto?.inventario_por_lotes && this.isLotesActivo() && this.traslado.lote_id && this.traslado.id_bodega_de) {
            this.cargarLotes();
        }
    }

    public validarStockLote() {
        // Recargar lotes para obtener stock actualizado cuando se cambia la cantidad
        if (this.producto?.inventario_por_lotes && this.isLotesActivo() && this.traslado.lote_id && this.traslado.id_bodega_de) {
            this.cargarLotes();
        }
    }

    public stockLoteSuficiente(): boolean {
        if (!this.producto?.inventario_por_lotes || !this.isLotesActivo() || !this.traslado.lote_id || !this.traslado.cantidad) {
            return true;
        }

        const loteSeleccionado = this.lotes.find((l: any) => l.id == this.traslado.lote_id);
        if (!loteSeleccionado) {
            return false;
        }

        const stockDisponible = parseFloat(loteSeleccionado.stock) || 0;
        const cantidadRequerida = parseFloat(this.traslado.cantidad) || 0;

        return stockDisponible >= cantidadRequerida;
    }

    public getStockOrigen(): number {
        // Si tiene lotes activos y hay un lote seleccionado, usar el stock del lote
        if (this.producto?.inventario_por_lotes && this.isLotesActivo() && this.traslado.lote_id) {
            const loteSeleccionado = this.lotes.find((l: any) => l.id == this.traslado.lote_id);
            if (loteSeleccionado) {
                return parseFloat(loteSeleccionado.stock) || 0;
            }
        }
        // Si no tiene lotes, usar el stock tradicional de la bodega
        return this.bodegaDe?.stock ? parseFloat(this.bodegaDe.stock) : 0;
    }

    public getStockDestino(): number {
        // Si tiene lotes activos y hay un lote destino seleccionado, usar el stock del lote
        if (this.producto?.inventario_por_lotes && this.isLotesActivo() && this.traslado.lote_id_destino) {
            const loteDestinoSeleccionado = this.lotesDestino.find((l: any) => l.id == this.traslado.lote_id_destino);
            if (loteDestinoSeleccionado) {
                return parseFloat(loteDestinoSeleccionado.stock) || 0;
            }
        }
        // Si no tiene lotes, usar el stock tradicional de la bodega
        return this.bodegaPara?.stock ? parseFloat(this.bodegaPara.stock) : 0;
    }

    public getStockOrigenDespues(): number {
        if (!this.traslado.cantidad) {
            return this.getStockOrigen();
        }
        const cantidad = Number(this.traslado.cantidad) || 0;
        const stockOrigen = this.getStockOrigen();
        return Math.max(0, stockOrigen - cantidad);
    }

    public getStockDestinoDespues(): number {
        if (!this.traslado.cantidad) {
            return this.getStockDestino();
        }
        const cantidad = Number(this.traslado.cantidad) || 0;
        const stockDestino = this.getStockDestino();
        return stockDestino + cantidad;
    }

    public isLotesActivo(): boolean {
        return this.apiService.isLotesActivo();
    }

  public setProducto(){
    this.producto = this.productos.find((item:any) => item.id == this.traslado.id_producto);
    this.traslado.costo = this.producto.costo;
  }

    public setSucursalDe(){
        this.bodegaDe = this.producto?.inventarios.find((item:any) => item.id_bodega == this.traslado.id_bodega_de);
        this.traslado.lote_id = null;
        this.lotes = [];

        // Si el producto tiene inventario por lotes, cargar los lotes
        if (this.producto?.inventario_por_lotes && this.traslado.id_bodega_de) {
            this.cargarLotes();
        }
    }

    public setSucursalPara(){
        this.bodegaPara = this.producto?.inventarios.find((item:any) => item.id_bodega == this.traslado.id_bodega);
        this.traslado.lote_id_destino = null;
        this.lotesDestino = [];

        // Si el producto tiene inventario por lotes, cargar los lotes de destino
        if (this.producto?.inventario_por_lotes && this.traslado.id_bodega) {
            this.cargarLotesDestino();
        }
    }

    public override openModal(template: TemplateRef<any>) {
        this.traslado = {};
        this.producto = {};
        this.bodegaDe = {};
        this.bodegaPara = {};
        this.lotes = [];
        this.lotesDestino = [];
        this.traslado.id_producto = null;
        this.traslado.id_bodega = null;
        this.traslado.id_bodega_de = null;
        this.traslado.lote_id = null;
        this.traslado.lote_id_destino = null;

        this.traslado.id_usuario = this.apiService.auth_user().id;
        this.traslado.id_empresa = this.apiService.auth_user().id_empresa;
        this.traslado.estado = 'Confirmado';

        if(!this.productos.length){
            this.apiService.getAll('productos/list').pipe(this.untilDestroyed()).subscribe(productos => {
                this.productos = productos;
                this.cdr.markForCheck();
            }, error => {this.alertService.error(error); this.cdr.markForCheck();});
        }
        (this as any).openLargeModal(template);

        this.alertService.modal = true;
        this.modalRef = this.modalService.show(template, {class: 'modal-lg', backdrop:'static'});
    }

    public openFilter(template: TemplateRef<any>) {
        this.apiService.getAll('productos/list').subscribe(productos => {
            this.productos = productos;
        }, error => {this.alertService.error(error); });
        this.apiService.getAll('traslados/conceptos').subscribe(conceptos => {
            this.conceptos = conceptos;
        }, error => {this.alertService.error(error); });
        this.modalRef = this.modalManager.openModal(template);
    }

    public override async onSubmit(_item?: any, _isStatusChange?: boolean): Promise<void> {
        // Validar que si el producto tiene lotes, se haya seleccionado un lote
        if (this.producto?.inventario_por_lotes && this.isLotesActivo() && !this.traslado.lote_id) {
            this.alertService.error('Debe seleccionar un lote para este producto.');
            return;
        }

        // Validar stock del lote antes de enviar
        if (this.producto?.inventario_por_lotes && this.isLotesActivo() && this.traslado.lote_id && this.traslado.cantidad) {
            const loteSeleccionado = this.lotes.find((l: any) => l.id == this.traslado.lote_id);
            if (loteSeleccionado) {
                const stockDisponible = parseFloat(loteSeleccionado.stock) || 0;
                const cantidadRequerida = parseFloat(this.traslado.cantidad) || 0;
                if (stockDisponible < cantidadRequerida) {
                    this.alertService.error(`El lote no tiene stock suficiente. Stock disponible: ${stockDisponible.toFixed(2)}, Cantidad requerida: ${cantidadRequerida.toFixed(2)}`);
                    // Recargar lotes para obtener stock actualizado
                    this.cargarLotes();
                    return;
                }
            }
        }

        this.saving = true;
        this.traslado.id_usuario = this.apiService.auth_user().id;
        this.apiService.store('traslado', this.traslado).subscribe(traslado => {
            this.traslado = {};
            this.producto = {};
            this.bodegaDe = {};
            this.bodegaPara = {};
            this.lotes = [];
            this.alertService.success('Traslado realizado', 'El traslado fue añadido exitosamente.');
            this.modalRef.hide();
            this.loadAll();
            this.saving = false;
        }, error => {this.alertService.error(error); this.saving = false;});
    }

    generarPartidaContable(traslado:any){
        this.apiService.store('contabilidad/partida/traslado', traslado).pipe(this.untilDestroyed()).subscribe(traslado => {
            this.alertService.success('Partida generada.', 'La partida contable fue generada exitosamente.');
            this.cdr.markForCheck();
        },error => {this.alertService.error(error); this.cdr.markForCheck();});
    }

  public override delete(id:number) {
    this.saving = true;
    this.apiService.delete('traslado/', id).subscribe(traslado => {
      this.traslado = {};
      this.alertService.success('Traslado cancelado', 'El traslado fue cancelado exitosamente.');
      this.modalRef.hide();
      this.loadAll();
      this.saving = false;
    }, error => {this.alertService.error(error); this.saving = false;});
  }

    public descargar(){
        this.downloading = true;
        this.apiService.export('traslados/exportar', this.filtros).pipe(this.untilDestroyed()).subscribe({
            next: (data: unknown) => {
                const blob = new Blob([data as Blob], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = 'traslados.xlsx';
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                window.URL.revokeObjectURL(url);
                this.downloading = false;
                this.cdr.markForCheck();
            },
            error: (error) => {
                this.alertService.error(error);
                this.downloading = false;
                this.cdr.markForCheck();
            }
        });
    }

    public descargarPdfFiltrados(){
        this.downloading = true;
        const params = new URLSearchParams();
        Object.keys(this.filtros).forEach(key => {
            if (this.filtros[key] !== '' && this.filtros[key] !== null && this.filtros[key] !== undefined) {
                params.append(key, this.filtros[key]);
            }
        });

        this.apiService.download(`traslados/exportar-pdf?${params.toString()}`).subscribe({
            next: (response) => {
                const blob = new Blob([response], { type: 'application/pdf' });
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `traslados-${new Date().toISOString().split('T')[0]}.pdf`;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                window.URL.revokeObjectURL(url);
                this.downloading = false;
                this.alertService.success('PDF descargado', 'El reporte de traslados se ha descargado correctamente.');
            },
            error: (error) => {
                this.alertService.error('Error al descargar el PDF');
                this.downloading = false;
            }
        });
    }

    public descargarPdf(traslado: any) {
        this.downloading = true;
        this.apiService.download(`traslado/${traslado.id}/pdf`).subscribe({
            next: (response) => {
                const blob = new Blob([response], { type: 'application/pdf' });
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `traslado-${traslado.id}.pdf`;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                window.URL.revokeObjectURL(url);
                this.downloading = false;
                this.alertService.success('PDF descargado', 'El documento de traslado se ha descargado correctamente.');
            },
            error: (error) => {
                this.alertService.error('Error al descargar el PDF');
                this.downloading = false;
            }
        });
    }

    /**
     * Obtiene el nombre completo del producto (nombre + nombre_variante si aplica)
     */
    getNombreCompleto(producto: any): string {
        if (this.tieneShopify && producto.nombre_variante) {
            return `${producto.nombre} ${producto.nombre_variante}`;
        }
        return producto.nombre;
    }

    public imprimir(traslado:any){
        window.open(this.apiService.baseUrl + '/api/traslado/' + traslado.id + '/pdf?token=' + this.apiService.auth_token());
    }

}
