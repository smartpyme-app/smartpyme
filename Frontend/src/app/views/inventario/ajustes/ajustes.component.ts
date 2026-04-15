import { Component, OnInit, TemplateRef, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
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
import { Subject, Observable } from 'rxjs';
import { debounceTime, distinctUntilChanged, switchMap, catchError } from 'rxjs/operators';
import { of } from 'rxjs';
import { TruncatePipe } from '@pipes/truncate.pipe';
import { PaginationComponent } from '@shared/parts/pagination/pagination.component';
import { BaseCrudComponent } from '@shared/base/base-crud.component';
import { LazyImageDirective } from '../../../directives/lazy-image.directive';
import { BsModalService } from 'ngx-bootstrap/modal';

@Component({
    selector: 'app-ajustes',
    templateUrl: './ajustes.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, NgSelectModule, TruncatePipe, PopoverModule, TooltipModule, PaginationComponent, LazyImageDirective],
    changeDetection: ChangeDetectionStrategy.OnPush,
})
export class AjustesComponent extends BaseCrudComponent<any> implements OnInit {

	public ajustes:any = [];
    public ajuste:any = {};
    public downloading:boolean = false;
    public productos:any = [];

    public override filtros:any = {};
    public bodegas:any = [];
    public usuarios:any = [];
    public producto:any = {};
    public productoFiltro:any = {};
    public sucursal:any = {};
    private tieneShopify: boolean = false;
    public productosInput$ = new Subject<string>();
    public loadingProductos: boolean = false;

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
            endpoint: 'ajuste',
            itemsProperty: 'ajustes',
            itemProperty: 'ajuste',
            reloadAfterSave: true,
            reloadAfterDelete: true,
            messages: {
                created: 'El ajuste fue guardado exitosamente.',
                updated: 'El ajuste fue guardado exitosamente.',
                createTitle: 'Ajuste guardado',
                updateTitle: 'Ajuste guardado',
                deleted: 'El ajuste fue eliminado exitosamente.',
                deleteTitle: 'Ajuste eliminado'
            },
            afterSave: () => {
                this.ajuste = {};
            },
            afterDelete: () => {
                this.ajuste = {};
            }
        });

        this.productosInput$.pipe(
            debounceTime(300),
            distinctUntilChanged(),
            switchMap(term => this.searchProductos(term)),
            this.untilDestroyed()
        ).subscribe(productos => {
            this.productos = productos;
            this.loadingProductos = false;
            this.cdr.markForCheck();
        });
    }

    protected aplicarFiltros(): void {
        this.filtrarAjustes();
    }

    ngOnInit() {
        // Cachear verificación de Shopify una sola vez
        const empresa = this.apiService.auth_user()?.empresa;
        this.tieneShopify = !!empresa?.shopify_store_url;

        this.route.queryParams
            .pipe(this.untilDestroyed())
            .subscribe(params => {
            this.filtros = {
                search: params['search'] || '',
                id_bodega: +params['id_bodega'] || '',
                id_producto: +params['id_producto'] || '',
                id_usuario: +params['id_usuario'] || '',
                id_sucursal: +params['id_sucursal'] || '',
                estado: params['estado'] || '',
                orden: params['orden'] || 'id',
                direccion: params['direccion'] || 'desc',
                paginate: params['paginate'] || 10,
                page: params['page'] || 1,
            };

            this.filtrarAjustes();
        });

        this.apiService.getAll('bodegas/list')
            .pipe(this.untilDestroyed())
            .subscribe(bodegas => {
                this.bodegas = bodegas;
                this.cdr.markForCheck();
            }, error => {this.alertService.error(error); this.cdr.markForCheck(); });
    }

    private searchProductos(term: string): Observable<any[]> {
        if (!term || term.length < 2) {
            return of([]);
        }

        this.loadingProductos = true;
        return this.apiService.getAll('productos/list/search', { search: term, limit: 20 }).pipe(
            catchError(() => of([]))
        );
    }

    public override loadAll() {
        this.filtros.id_bodega = '';
        this.filtros.id_producto = '';
        this.filtros.id_usuario = '';
        this.filtros.estado = '';
        this.filtros.search = '';
        this.filtros.orden = 'created_at';
        this.filtros.direccion = 'desc';
        this.filtros.paginate = 10;
        this.filtros.page = 1;

        this.loading = true;
        this.filtrarAjustes();
    }

    public filtrarAjustes(){
        this.router.navigate([], {
            relativeTo: this.route,
            queryParams: this.filtros,
            queryParamsHandling: 'merge',
        });
        this.loading = true;
        this.apiService.getAll('ajustes', this.filtros)
            .pipe(this.untilDestroyed())
            .subscribe(ajustes => {
                this.ajustes = ajustes;
                this.loading = false;
                this.cdr.markForCheck();
            }, error => {this.alertService.error(error); this.loading = false; this.cdr.markForCheck(); });
    }

    public setOrden(columna: string) {
        if (columna === 'created_at') {
            if (this.filtros.orden === 'created_at') {
                this.filtros.direccion = this.filtros.direccion === 'asc' ? 'desc' : 'asc';
            } else {
                this.filtros.orden = 'created_at';
                this.filtros.direccion = 'desc';
            }
        } else {
            if (this.filtros.orden === columna) {
                this.filtros.direccion = this.filtros.direccion === 'asc' ? 'desc' : 'asc';
            } else {
                this.filtros.orden = columna;
                this.filtros.direccion = 'asc';
            }
        }
        this.filtrarAjustes();
    }

    public setEstado(ajuste:any){
        this.ajuste = ajuste;
        if (this.ajuste.estado == 'Cancelado') {
            if (confirm('¿Confirma cancelar el ajuste?')) {
                this.delete(this.ajuste.id);
            }
        }else{
            if (confirm('¿Confirma confirmar el ajuste?')) {
                this.onSubmit();
            }
        }
    }

    public lotes: any[] = [];
    public loadingLotes: boolean = false;

    /** Al elegir producto en el ng-select (mismo patrón que traslados). */
    public setProducto(): void {
        const id = this.ajuste?.id_producto;
        if (id == null || id === '') {
            this.limpiarProducto();
            return;
        }
        const p = this.productos?.find((item: any) => item.id == id);
        if (p) {
            this.productoSelect(p);
        }
    }

    public productoSelect(producto: any) {
        const aplicar = (p: any) => {
            this.producto = p;
            this.ajuste.id_producto = p.id;
            this.ajuste.costo = p.costo;
            this.ajuste.lote_id = null;
            this.lotes = [];

            if (this.producto?.inventario_por_lotes && this.ajuste.id_bodega) {
                this.cargarLotes();
            }
            this.cdr.markForCheck();
        };

        if (producto?.inventarios && Array.isArray(producto.inventarios)) {
            aplicar(producto);
            return;
        }

        this.apiService
            .getAll(`productos/${producto.id}/inventarios`)
            .pipe(this.untilDestroyed())
            .subscribe(
                (inventarios) => {
                    aplicar({ ...producto, inventarios: Array.isArray(inventarios) ? inventarios : [] });
                },
                () => {
                    aplicar({ ...producto, inventarios: [] });
                }
            );
    }

    public limpiarProducto() {
        this.producto = {};
        this.ajuste.id_producto = null;
        this.ajuste.id_bodega = null;
        this.ajuste.lote_id = null;
        this.ajuste.stock_actual = null;
        this.ajuste.stock_real = null;
        this.ajuste.ajuste = null;
        this.lotes = [];
        this.sucursal = {};
        this.cdr.markForCheck();
    }

    public setBodega(){
        this.sucursal = this.producto?.inventarios.find((item:any) => item.id_bodega == this.ajuste.id_bodega);
        console.log(this.sucursal);

        // Si el producto tiene inventario por lotes, no establecer stock_actual todavía
        // Se establecerá cuando se seleccione el lote
        if (this.producto?.inventario_por_lotes && this.isLotesActivo()) {
            this.ajuste.stock_actual = null;
        } else {
            // Para productos sin lotes, usar el stock del inventario tradicional
            this.ajuste.stock_actual = this.sucursal?.stock || 0;
        }

        this.ajuste.lote_id = null;
        this.lotes = [];
        this.ajuste.stock_real = null;
        this.ajuste.ajuste = null;

        // Si el producto tiene inventario por lotes, cargar los lotes de la bodega
        if (this.producto?.inventario_por_lotes && this.ajuste.id_bodega) {
            this.cargarLotes();
        }
    }

    public cargarLotes() {
        if (!this.ajuste.id_producto || !this.ajuste.id_bodega) return;

        this.loadingLotes = true;
        this.apiService.getAll(`lotes/producto/${this.ajuste.id_producto}`, {
            id_bodega: this.ajuste.id_bodega
        }).subscribe(lotes => {
            this.lotes = Array.isArray(lotes) ? lotes : [];
            this.loadingLotes = false;
        }, error => {
            this.alertService.error(error);
            this.loadingLotes = false;
            this.lotes = [];
        });
    }

    public setLote() {
        // Cuando se selecciona un lote, establecer el stock_actual del lote
        if (this.ajuste.lote_id) {
            const loteSeleccionado = this.lotes.find((l: any) => l.id == this.ajuste.lote_id);
            if (loteSeleccionado) {
                this.ajuste.stock_actual = loteSeleccionado.stock || 0;
                this.ajuste.stock_real = null;
                this.ajuste.ajuste = null;
            }
        } else {
            this.ajuste.stock_actual = null;
            this.ajuste.stock_real = null;
            this.ajuste.ajuste = null;
        }
    }

    public calAjuste(){
        if (this.ajuste.stock_actual !== null && this.ajuste.stock_actual !== undefined &&
            this.ajuste.stock_real !== null && this.ajuste.stock_real !== undefined) {
            this.ajuste.ajuste = this.ajuste.stock_real - this.ajuste.stock_actual;
        } else {
            this.ajuste.ajuste = null;
        }
    }

    public override openModal(template: TemplateRef<any>) {
        this.ajuste = {};
        this.producto = {};
        this.sucursal = {};
        this.lotes = [];
        this.ajuste.id_producto = null;
        this.ajuste.id_bodega = null;
        this.ajuste.lote_id = null;

        this.ajuste.id_usuario = this.apiService.auth_user().id;
        this.ajuste.id_empresa = this.apiService.auth_user().id_empresa;

        this.alertService.modal = true;

        this.modalRef = this.modalService.show(template);
    }

    public openFilter(template: TemplateRef<any>) {
        this.productoFiltro = {};
        this.apiService.getAll('usuarios/list').subscribe(usuarios => {
            this.usuarios = usuarios;
        }, error => {this.alertService.error(error); });
        this.modalRef = this.modalService.show(template);
    }

    public productoFiltroSelect(producto: any) {
        this.productoFiltro = producto;
        this.filtros.id_producto = producto.id;
    }

    public limpiarProductoFiltro() {
        this.productoFiltro = {};
        this.filtros.id_producto = null;
    }

    public override async onSubmit(_item?: any, _isStatusChange?: boolean): Promise<void> {
        // Validar que si el producto tiene lotes, se haya seleccionado un lote
        if (this.producto?.inventario_por_lotes && this.isLotesActivo() && !this.ajuste.lote_id) {
            this.alertService.error('Debe seleccionar un lote para este producto.');
            return;
        }

        this.saving = true;
        this.apiService.store('ajuste', this.ajuste).subscribe(ajuste => {
            this.ajuste = {};
            this.alertService.success('Ajuste guardado', 'El ajuste fue guardado exitosamente.');
            this.modalRef?.hide();
            this.loadAll();
            this.saving = false;
        }, error => {this.alertService.error(error); this.saving = false;});
    }

    public override delete(item: any | number): void {
        const itemToDelete = typeof item === 'number' ? item : (item as any).id;

        this.saving = true;
        this.apiService.delete('ajuste/', itemToDelete)
            .pipe(this.untilDestroyed())
            .subscribe({
                next: () => {
                    this.ajuste = {};
                    this.alertService.success('Ajuste eliminado', 'El ajuste fue eliminado exitosamente.');
                    this.closeModal();
                    this.filtrarAjustes();
                    this.saving = false;
                    this.cdr.markForCheck();
                },
                error: (error: any) => {
                    this.alertService.error(error);
                    this.saving = false;
                    this.cdr.markForCheck();
                }
            });
    }

    generarPartidaContable(ajuste:any){
        this.apiService.store('contabilidad/partida/ajuste', ajuste)
            .pipe(this.untilDestroyed())
            .subscribe(ajuste => {
            this.alertService.success('Partida generada.', 'La partida contable fue generada exitosamente.');
            this.cdr.markForCheck();
        },error => {this.alertService.error(error); this.cdr.markForCheck();});
    }

    public descargar(){
        this.downloading = true;
        this.apiService.export('ajustes/exportar', this.filtros)
            .pipe(this.untilDestroyed())
            .subscribe((data:Blob) => {
            const blob = new Blob([data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'ajustes.xlsx';
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

    public isLotesActivo(): boolean {
        return this.apiService.isLotesActivo();
    }

}
