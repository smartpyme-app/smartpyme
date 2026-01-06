import { Component, OnInit, TemplateRef, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { Router, ActivatedRoute } from '@angular/router';
import { PopoverModule } from 'ngx-bootstrap/popover';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { NgSelectModule } from '@ng-select/ng-select';
import { ImportarExcelComponent } from '@shared/parts/importar-excel/importar-excel.component';
import { PaginationComponent } from '@shared/parts/pagination/pagination.component';
import { NotificacionesContainerComponent } from '@shared/parts/notificaciones/notificaciones-container.component';
import { DescargarInventarioComponent } from '@shared/parts/descargar-inventario/descargar-inventario.component';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { ModalManagerService } from '@services/modal-manager.service';
import { SumPipe } from '@pipes/sum.pipe';
import { BaseCrudComponent } from '@shared/base/base-crud.component';
import { LazyImageDirective } from '../../../directives/lazy-image.directive';

@Component({
    selector: 'app-productos',
    templateUrl: './productos.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, NgSelectModule, ImportarExcelComponent, PaginationComponent, NotificacionesContainerComponent, DescargarInventarioComponent, SumPipe, PopoverModule, TooltipModule, LazyImageDirective],
    changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ProductosComponent extends BaseCrudComponent<any> implements OnInit {

    public productos: any = [];
    public downloading: boolean = false;
    public producto: any = {};
    public bodegas: any = [];
    public categorias: any = [];
    public proveedores: any = [];
    public marcas: any = [];
    public ajuste: any = {};
    public inventario: any = {};
    public filtrosKardex: any = {
        fecha_inicio: '',
        fecha_fin: ''
    };
    public emailKardex: string = '';

    constructor(
        apiService: ApiService, 
        alertService: AlertService,
        modalManager: ModalManagerService,
        private router: Router, 
        private route: ActivatedRoute,
        private cdr: ChangeDetectorRef
    ) {
        super(apiService, alertService, modalManager, {
            endpoint: 'producto',
            itemsProperty: 'productos',
            itemProperty: 'producto',
            reloadAfterSave: false,
            reloadAfterDelete: false,
            messages: {
                created: 'El producto fue guardado exitosamente.',
                updated: 'El producto fue guardado exitosamente.',
                deleted: 'Producto eliminado exitosamente.',
                createTitle: 'Producto guardado',
                updateTitle: 'Producto guardado',
                deleteTitle: 'Producto eliminado',
                deleteConfirm: '¿Desea eliminar el Registro?'
            },
            afterSave: () => {
                this.producto = {};
            }
        });
    }

    protected aplicarFiltros(): void {
        this.filtrarProductos();
    }

    ngOnInit() {
        // Verificar si Shopify está activo y obtener la bodega del usuario
        const empresa = this.apiService.auth_user()?.empresa;
        const usuario = this.apiService.auth_user();
        const shopifyActivo = !!(empresa?.shopify_store_url);

        this.route.queryParams
            .pipe(this.untilDestroyed())
            .subscribe(params => {
            this.filtros = {
                buscador: params['buscador'] || '',
                id_bodega: +params['id_bodega'] || '',
                id_categoria: +params['id_categoria'] || '',
                id_proveedor: +params['id_proveedor'] || '',
                id_sucursal: +params['id_sucursal'] || '',
                estado: params['estado'] || '',
                marca: params['marca'] || '',
                sin_stock: params['sin_stock'] || '',
                compuestos: params['compuestos'] || '',
                orden: params['orden'] || 'id',
                direccion: params['direccion'] || 'desc',
                paginate: params['paginate'] || 10,
                page: params['page'] || 1,
            };

            // Si Shopify está activo y no hay bodega seleccionada, seleccionar automáticamente la bodega del usuario
            if (shopifyActivo && !this.filtros.id_bodega && usuario?.id_bodega) {
                this.filtros.id_bodega = usuario.id_bodega;
            }

            this.filtrarProductos();
        });

        if(this.route.snapshot.routeConfig?.path == 'producto-combos') this.verCombos();

        this.apiService.getAll('categorias/list')
            .pipe(this.untilDestroyed())
            .subscribe(categorias => {
                this.categorias = categorias;
                this.cdr.markForCheck();
            }, error => { this.alertService.error(error); });

        this.apiService.getAll('bodegas/list')
            .pipe(this.untilDestroyed())
            .subscribe(bodegas => {
                this.bodegas = bodegas;
                this.cdr.markForCheck();
            }, error => { this.alertService.error(error); });

        this.apiService.getAll('productos/marca-productos')
            .pipe(this.untilDestroyed())
            .subscribe(marcas => {
                this.marcas = marcas;
                this.cdr.markForCheck();
            }, error => { this.alertService.error(error); });

    }

    verCombos(){
        this.filtros.tipo = 'Compuesto';
        this.filtrarProductos();
    }

    public override loadAll() {
        // Verificar si Shopify está activo para mantener el filtro de bodega
        const empresa = this.apiService.auth_user()?.empresa;
        const usuario = this.apiService.auth_user();
        const shopifyActivo = !!(empresa?.shopify_store_url);

        // Guardar temporalmente la bodega si Shopify está activo
        const bodegaActual = shopifyActivo && this.filtros.id_bodega ? this.filtros.id_bodega : '';

        this.filtros.id_bodega = '';
        this.filtros.id_categoria = '';
        this.filtros.id_proveedor = '';
        this.filtros.id_sucursal = '';
        this.filtros.marca = '';
        this.filtros.estado = '';
        this.filtros.buscador = '';
        this.filtros.orden = 'nombre';
        this.filtros.direccion = 'asc';
        this.filtros.sin_stock = '';
        this.filtros.paginate = 10;
        this.filtros.page = 1;
        this.filtros.tipo = '';

        // Si Shopify está activo, restaurar la bodega del usuario
        if (shopifyActivo) {
            this.filtros.id_bodega = bodegaActual || usuario?.id_bodega || '';
        }

        this.filtrarProductos();
    }

    public filtrarProductos() {
        this.router.navigate([], {
            relativeTo: this.route,
            queryParams: this.filtros,
            queryParamsHandling: 'merge',
        });

        this.loading = true;

        if (!this.filtros.sin_stock) {
            this.filtros.sin_stock = '';
        }

        if (!this.filtros.id_categoria) {
            this.filtros.id_categoria = '';
        }

        if (!this.filtros.marca) {
            this.filtros.marca = '';
        }

        this.apiService.getAll('productos', this.filtros)
            .pipe(this.untilDestroyed())
            .subscribe(productos => {
                this.productos = productos;
                this.loading = false;
                this.closeModal();
                this.cdr.markForCheck();
            }, error => { this.alertService.error(error); this.loading = false; this.cdr.markForCheck(); });
    }

    public setEstado(producto: any) {
        this.onSubmit(producto, true);
    }

    public override delete(id: number) {
        super.delete(id);
    }

    public setOrden(columna: string) {
        if (this.filtros.orden === columna) {
            this.filtros.direccion = this.filtros.direccion === 'asc' ? 'desc' : 'asc';
        } else {
            this.filtros.orden = columna;
            this.filtros.direccion = 'asc';
        }

        this.filtrarProductos();
    }

    public override async onSubmit(item?: any, isStatusChange: boolean = false) {
        await super.onSubmit(item, isStatusChange);
        this.filtrarProductos();
    }

    public openDescargar(template: TemplateRef<any>) {
        this.openModal(template);
    }

    public descargar() {
        this.downloading = true;
        this.apiService.export('productos/exportar', this.filtros)
            .pipe(this.untilDestroyed())
            .subscribe((data: Blob) => {
            const blob = new Blob([data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'productos.xlsx';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
            this.downloading = false;
            this.cdr.markForCheck();
        }, (error) => { this.alertService.error(error); this.downloading = false; this.cdr.markForCheck(); }
        );
    }

    public openFilter(template: TemplateRef<any>) {
        this.apiService.getAll('proveedores/list')
            .pipe(this.untilDestroyed())
            .subscribe(proveedores => {
                this.proveedores = proveedores;
                this.cdr.markForCheck();
            }, error => { this.alertService.error(error); });

        this.openModal(template, { class: 'modal-md', backdrop: 'static' });
    }

    public openModalAjuste(template: TemplateRef<any>, producto: any) {
        this.ajuste = {};
        this.producto = producto;
        this.inventario = this.producto.inventarios.find((item: any) => item.id_bodega == this.filtros.id_bodega);
        //console.log(this.filtros);
        //console.log(this.producto);
        this.ajuste.stock_actual = this.inventario.stock;
        super.openModal(template, { class: 'modal-lg', backdrop: 'static' });
    }

    public calAjuste() {
        this.ajuste.ajuste = parseFloat(this.ajuste.stock_real) - parseFloat(this.ajuste.stock_actual);
    }

    public onSubmitAjuste() {
        this.loading = true;
        this.ajuste.id_producto = this.producto.id;
        this.ajuste.id_bodega = this.inventario.id_bodega;
        this.ajuste.id_empresa = this.apiService.auth_user().id_empresa;
        this.ajuste.id_usuario = this.apiService.auth_user().id;

        this.apiService.store('ajuste', this.ajuste)
            .pipe(this.untilDestroyed())
            .subscribe(ajuste => {
            this.filtrarProductos();
            this.closeModal();
            this.alertService.modal = false;
            this.loading = false;
            this.cdr.markForCheck();
        }, error => { this.alertService.error(error); this.loading = false; this.cdr.markForCheck(); });

    }

    public descargarKardex() {
        this.loading = true;

        // Usar directamente los productos de la página actual
        const productoIds = this.productos.data.map((p: any) => p.id);
        const filtrosConProductos = {
            producto_ids: productoIds.join(','), // Enviar como string separado por comas
            inicio: undefined, // Sin filtro de fecha
            fin: undefined // Sin filtro de fecha
        };

        this.apiService.export('productos/kardex/exportar-filtrado', filtrosConProductos)
            .pipe(this.untilDestroyed())
            .subscribe((data:Blob) => {
                const blob = new Blob([data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = 'kardex-filtrado.xlsx';
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                window.URL.revokeObjectURL(url);
                this.loading = false;
                this.cdr.markForCheck();
            }, (error) => {
                this.alertService.error(error);
                this.loading = false;
                this.cdr.markForCheck();
            });
    }

    public openDescargarKardex(template: TemplateRef<any>) {
        // Cerrar el modal actual (modal de descarga)
        if (this.modalRef) {
            this.closeModal();
        }

        // Resetear filtros de kardex
        this.filtrosKardex = {
            fecha_inicio: '',
            fecha_fin: ''
        };

        // Abrir el modal de kardex
        this.openModal(template);
    }

    public descargarKardexConFiltros() {
        // Validar que las fechas estén completas
        if (!this.filtrosKardex.fecha_inicio || !this.filtrosKardex.fecha_fin) {
            this.alertService.error('Debe seleccionar fecha de inicio y fecha fin');
            return;
        }

        this.loading = true;

        // Usar directamente los productos de la página actual
        const productoIds = this.productos.data.map((p: any) => p.id);

        const filtrosConProductos = {
            producto_ids: productoIds.join(','), // Enviar como string separado por comas
            inicio: this.filtrosKardex.fecha_inicio,
            fin: this.filtrosKardex.fecha_fin
        };

        this.apiService.export('productos/kardex/exportar-filtrado', filtrosConProductos)
            .pipe(this.untilDestroyed())
            .subscribe((data:Blob) => {
                const blob = new Blob([data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = 'kardex-filtrado.xlsx';
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                window.URL.revokeObjectURL(url);
                this.loading = false;
                this.closeModal();
            }, (error) => {
                this.alertService.error(error);
                this.loading = false;
            });
    }

    public openDescargarKardexMasivo(template: TemplateRef<any>) {
        // Cerrar el modal actual (modal de descarga)
        if (this.modalRef) {
            this.closeModal();
        }

        // Resetear email
        this.emailKardex = '';

        // Abrir el modal de kardex masivo
        this.openModal(template);
    }

    public solicitarKardexMasivo() {
        // Validar email
        if (!this.emailKardex || !this.emailKardex.includes('@')) {
            this.alertService.error('Debe ingresar un correo electrónico válido');
            return;
        }

        this.loading = true;

        const datosSolicitud = {
            email: this.emailKardex,
            id_empresa: this.apiService.auth_user().id_empresa
        };

        this.apiService.store('productos/kardex/solicitar-masivo', datosSolicitud)
            .pipe(this.untilDestroyed())
            .subscribe((response: any) => {
            this.alertService.success('Solicitud registrada', 'Su solicitud ha sido registrada en la cola de procesamiento. Recibirá un correo electrónico cuando el kardex esté listo.');
            this.loading = false;
            this.closeModal();
            this.cdr.markForCheck();
        }, (error) => {
            this.alertService.error(error);
            this.loading = false;
            this.cdr.markForCheck();
        });
    }

    /**
     * Verifica si Shopify está activo en la empresa
     */
    public isShopifyActive(): boolean {
        const empresa = this.apiService.auth_user()?.empresa;
        if (!empresa) return false;

        // Verificar si Shopify está configurado y conectado
        return !!(empresa.shopify_store_url &&
            empresa.shopify_consumer_secret &&
            empresa.shopify_status === 'connected');
    }

}
