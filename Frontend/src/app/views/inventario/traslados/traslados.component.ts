import { Component, OnInit, TemplateRef, OnDestroy } from '@angular/core';
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
import { BaseFilteredPaginatedModalComponent } from '@shared/base/base-filtered-paginated-modal.component';
import { Subscription } from 'rxjs';
import { distinctUntilChanged, skip, debounceTime } from 'rxjs/operators';

@Component({
    selector: 'app-traslados',
    templateUrl: './traslados.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, NgSelectModule, TruncatePipe, PopoverModule, TooltipModule, PaginationComponent],

})
export class TrasladosComponent extends BaseFilteredPaginatedModalComponent implements OnInit, OnDestroy {

    public traslados:any = [];
    public traslado:any = {};
    public downloading:boolean = false;
    public productos:any = [];
    public sucursales:any = [];
    public producto:any = {};
    public bodegaDe:any = {};
    public bodegaPara:any = {};
    private tieneShopify: boolean = false;
    private queryParamsSubscription?: Subscription;
    private isNavigating: boolean = false;
    private isLoadingProductos: boolean = false;

    constructor(
        apiService: ApiService, 
        alertService: AlertService,
        modalManager: ModalManagerService,
        private router: Router, 
        private route: ActivatedRoute
    ){
        super(apiService, alertService, modalManager);
    }

    protected aplicarFiltros(): void {
        this.filtrarTraslados();
    }

    ngOnInit() {
        // Cachear verificación de Shopify una sola vez
        const empresa = this.apiService.auth_user()?.empresa;
        this.tieneShopify = !!empresa?.shopify_store_url;

        // Inicializar filtros desde query params
        const params = this.route.snapshot.queryParams;
        this.filtros = {
            search: params['search'] || '',
            id_bodega_de: +params['id_bodega_de'] || '',
            id_bodega_para: +params['id_bodega_para'] || '',
            id_sucursal: +params['id_sucursal'] || '',
            estado: params['estado'] || '',
            orden: params['orden'] || 'id',
            direccion: params['direccion'] || 'desc',
            paginate: params['paginate'] || 10,
            page: params['page'] || 1,
        };

        // Cargar datos iniciales
        this.filtrarTrasladosSinNavegar();

        // Suscribirse a cambios en query params, pero solo cuando NO estamos navegando nosotros mismos
        this.queryParamsSubscription = this.route.queryParams
            .pipe(
                skip(1), // Saltar la primera emisión (valores iniciales)
                debounceTime(300), // Esperar 300ms antes de procesar cambios
                distinctUntilChanged((prev, curr) => {
                    // Comparar solo los valores relevantes
                    return JSON.stringify({
                        search: prev['search'] || '',
                        id_bodega_de: +prev['id_bodega_de'] || '',
                        id_bodega_para: +prev['id_bodega_para'] || '',
                        id_sucursal: +prev['id_sucursal'] || '',
                        estado: prev['estado'] || '',
                        orden: prev['orden'] || 'id',
                        direccion: prev['direccion'] || 'desc',
                        paginate: prev['paginate'] || 10,
                        page: prev['page'] || 1,
                    }) === JSON.stringify({
                        search: curr['search'] || '',
                        id_bodega_de: +curr['id_bodega_de'] || '',
                        id_bodega_para: +curr['id_bodega_para'] || '',
                        id_sucursal: +curr['id_sucursal'] || '',
                        estado: curr['estado'] || '',
                        orden: curr['orden'] || 'id',
                        direccion: curr['direccion'] || 'desc',
                        paginate: curr['paginate'] || 10,
                        page: curr['page'] || 1,
                    });
                }),
                this.untilDestroyed()
            )
            .subscribe(params => {
                if (!this.isNavigating) {
                    this.filtros = {
                        search: params['search'] || '',
                        id_bodega_de: +params['id_bodega_de'] || '',
                        id_bodega_para: +params['id_bodega_para'] || '',
                        id_sucursal: +params['id_sucursal'] || '',
                        estado: params['estado'] || '',
                        orden: params['orden'] || 'id',
                        direccion: params['direccion'] || 'desc',
                        paginate: params['paginate'] || 10,
                        page: params['page'] || 1,
                    };
                    this.filtrarTrasladosSinNavegar();
                }
                this.isNavigating = false;
            });

        this.apiService.getAll('sucursales/list').pipe(this.untilDestroyed()).subscribe(sucursales => { 
            this.sucursales = sucursales;
        }, error => {this.alertService.error(error); });
    }

    ngOnDestroy() {
        if (this.queryParamsSubscription) {
            this.queryParamsSubscription.unsubscribe();
        }
    }

    public loadAll() {
        this.filtros.id_bodega_de = '';
        this.filtros.id_bodega_para = '';
        this.filtros.id_producto = '';
        this.filtros.id_sucursal = '';
        this.filtros.estado = '';
        this.filtros.search = '';
        this.filtros.orden = 'created_at';
        this.filtros.direccion = 'desc';
        this.filtros.paginate = 10;
        this.filtros.page = 1;

        this.loading = true;
        this.filtrarTraslados();
        
    }

    public filtrarTraslados(){
        // Cerrar el modal si está abierto
        if(this.modalRef){
            this.closeModal();
        }
        this.isNavigating = true;
        this.router.navigate([], {
            relativeTo: this.route,
            queryParams: this.filtros,
            queryParamsHandling: 'merge', // mantiene otros params si hay
        });
        // Resetear la bandera después de un pequeño delay
        setTimeout(() => {
            this.isNavigating = false;
        }, 100);
    }

    private filtrarTrasladosSinNavegar(){
        this.loading = true;
        this.apiService.getAll('traslados', this.filtros).pipe(this.untilDestroyed()).subscribe(traslados => { 
            this.traslados = traslados;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false; });
    }

    public setOrden(columna: string) {
        // Solo permitir ordenar por fecha de creación o por cantidad
        if (columna !== 'created_at' && columna !== 'cantidad') {
            return;
        }

        if (this.filtros.orden === columna) {
            this.filtros.direccion = this.filtros.direccion === 'asc' ? 'desc' : 'asc';
        } else {
            this.filtros.orden = columna;
            // Por defecto, si es fecha, descendente (más reciente primero), si es cantidad, ascendente
            this.filtros.direccion = columna === 'created_at' ? 'desc' : 'asc';
        }

        this.filtrarTraslados();
    }

    // setPagination() ahora se hereda de BaseFilteredPaginatedComponent

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

    public setProducto(){
        this.producto = this.productos.find((item:any) => item.id == this.traslado.id_producto);
        this.traslado.costo = this.producto.costo;
    }

    public setSucursalDe(){
        this.bodegaDe = this.producto?.inventarios.find((item:any) => item.id_bodega == this.traslado.id_bodega_de);
    }

    public setSucursalPara(){
        this.bodegaPara = this.producto?.inventarios.find((item:any) => item.id_bodega == this.traslado.id_bodega);
    }

    override openModal(template: TemplateRef<any>) {
        this.traslado.id_producto = '';
        this.traslado.id_bodega = '';
        this.traslado.id_bodega_de = '';

        this.traslado.id_usuario = this.apiService.auth_user().id;
        this.traslado.id_empresa = this.apiService.auth_user().id_empresa;
        this.traslado.estado = 'Confirmado';

        if(!this.productos.length){
            this.apiService.getAll('productos/list').pipe(this.untilDestroyed()).subscribe(productos => {
                this.productos = productos;
            }, error => {this.alertService.error(error);});
        }
        super.openLargeModal(template);
    }

    public openFilter(template: TemplateRef<any>) {
        // Solo cargar productos si no están cargados y no se está cargando ya
        if(!this.productos.length && !this.isLoadingProductos){
            this.isLoadingProductos = true;
            this.apiService.getAll('productos/list').pipe(this.untilDestroyed()).subscribe({
                next: (productos) => { 
                    this.productos = productos;
                    this.isLoadingProductos = false;
                },
                error: (error) => {
                    this.alertService.error(error);
                    this.isLoadingProductos = false;
                }
            });
        }
        // Marcar que estamos navegando para evitar que el subscribe procese cambios
        this.isNavigating = true;
        // Usar openModal directamente sin pasar por el override que resetea campos
        super.openModal(template, {class: 'modal-md', backdrop: 'static'});
        // Resetear la bandera después de un pequeño delay para permitir que el modal se abra
        setTimeout(() => {
            this.isNavigating = false;
        }, 100);
    }

    public onSubmit() {
        this.saving = true;
        this.traslado.id_usuario = this.apiService.auth_user().id;
        this.apiService.store('traslado', this.traslado).pipe(this.untilDestroyed()).subscribe(traslado => { 
            this.traslado = {};
            this.alertService.success('Traslado realizado', 'El traslado fue añadido exitosamente.');
            this.closeModal();
            this.loadAll();
            this.saving = false;
        }, error => {this.alertService.error(error); this.saving = false;});
    }

    public delete(id:number) {
        this.saving = true;
        this.apiService.delete('traslado/', id).pipe(this.untilDestroyed()).subscribe(traslado => { 
            this.traslado = {};
            this.alertService.success('Traslado cancelado', 'El traslado fue cancelado exitosamente.');
            this.closeModal();
            this.loadAll();
            this.saving = false;
        }, error => {this.alertService.error(error); this.saving = false;});
    }

    generarPartidaContable(traslado:any){
        this.apiService.store('contabilidad/partida/traslado', traslado).pipe(this.untilDestroyed()).subscribe(traslado => {
            this.alertService.success('Partida generada.', 'La partida contable fue generada exitosamente.');
        },error => {this.alertService.error(error);});
    }

    public descargar(){
        this.downloading = true;
        this.apiService.export('traslados/exportar', this.filtros).pipe(this.untilDestroyed()).subscribe((data:Blob) => {
            const blob = new Blob([data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'traslados.xlsx';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
            this.downloading = false;
          }, (error) => { this.alertService.error(error); this.downloading = false; }
        );
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

}
