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
import { Subscription } from 'rxjs';
import { distinctUntilChanged, skip, debounceTime } from 'rxjs/operators';
import { LazyImageDirective } from '../../../directives/lazy-image.directive';

@Component({
    selector: 'app-traslados',
    templateUrl: './traslados.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, NgSelectModule, TruncatePipe, PopoverModule, TooltipModule, PaginationComponent, LazyImageDirective],
    changeDetection: ChangeDetectionStrategy.OnPush
})
export class TrasladosComponent extends BaseCrudComponent<any> implements OnInit, OnDestroy {

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
    }

    protected aplicarFiltros(): void {
        this.filtrarTraslados();
    }

    ngOnInit() {
        const empresa = this.apiService.auth_user()?.empresa;
        this.tieneShopify = !!empresa?.shopify_store_url;

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

        this.filtrarTrasladosSinNavegar();

        this.queryParamsSubscription = this.route.queryParams
            .pipe(
                skip(1),
                debounceTime(300),
                distinctUntilChanged((prev, curr) => {
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
                this.cdr.markForCheck();
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
        this.filtros.orden = 'created_at';
        this.filtros.direccion = 'desc';
        this.filtros.paginate = 10;
        this.filtros.page = 1;

        this.loading = true;
        this.filtrarTraslados();
    }

    public filtrarTraslados(){
        if(this.modalRef){
            this.closeModal();
        }
        this.isNavigating = true;
        this.router.navigate([], {
            relativeTo: this.route,
            queryParams: this.filtros,
            queryParamsHandling: 'merge',
        });
        setTimeout(() => {
            this.isNavigating = false;
        }, 100);
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
                this.cdr.markForCheck();
            }, error => {this.alertService.error(error); this.cdr.markForCheck();});
        }
        super.openLargeModal(template);
    }

    public openFilter(template: TemplateRef<any>) {
        if(!this.productos.length && !this.isLoadingProductos){
            this.isLoadingProductos = true;
            this.apiService.getAll('productos/list').pipe(this.untilDestroyed()).subscribe({
                next: (productos) => { 
                    this.productos = productos;
                    this.isLoadingProductos = false;
                    this.cdr.markForCheck();
                },
                error: (error) => {
                    this.alertService.error(error);
                    this.isLoadingProductos = false;
                    this.cdr.markForCheck();
                }
            });
        }
        this.isNavigating = true;
        super.openModal(template, undefined, {class: 'modal-md', backdrop: 'static'});
        setTimeout(() => {
            this.isNavigating = false;
        }, 100);
    }

    generarPartidaContable(traslado:any){
        this.apiService.store('contabilidad/partida/traslado', traslado).pipe(this.untilDestroyed()).subscribe(traslado => {
            this.alertService.success('Partida generada.', 'La partida contable fue generada exitosamente.');
            this.cdr.markForCheck();
        },error => {this.alertService.error(error); this.cdr.markForCheck();});
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
            this.cdr.markForCheck();
          }, (error) => { this.alertService.error(error); this.downloading = false; this.cdr.markForCheck(); }
        );
    }

    getNombreCompleto(producto: any): string {
        if (this.tieneShopify && producto.nombre_variante) {
            return `${producto.nombre} ${producto.nombre_variante}`;
        }
        return producto.nombre;
    }

}
