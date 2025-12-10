import { Component, OnInit, EventEmitter, Input, Output, TemplateRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule, ReactiveFormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { CrearProductoComponent } from '@shared/modals/crear-producto/crear-producto.component';
import { BasePaginatedModalComponent, PaginatedResponse } from '@shared/base/base-paginated-modal.component';

import { of } from 'rxjs';
import { FormControl } from '@angular/forms';
import { debounceTime, switchMap, filter,catchError  } from 'rxjs/operators';

import { SumPipe }     from '@pipes/sum.pipe';
import { FilterPipe }  from '@pipes/filter.pipe';
import { ApiService } from '@services/api.service';
import { AlertService } from '@services/alert.service';
import { ModalManagerService } from '@services/modal-manager.service';
import { LazyImageDirective } from '../../../../directives/lazy-image.directive';

@Component({
    selector: 'app-compra-producto',
    templateUrl: './compra-producto.component.html',
    standalone: true,
    imports: [CommonModule, FormsModule, ReactiveFormsModule, RouterModule, CrearProductoComponent, SumPipe, FilterPipe, LazyImageDirective],
    
})
export class CompraProductoComponent extends BasePaginatedModalComponent implements OnInit {

    @Input() compra: any = {};
    @Output() productoSelect = new EventEmitter();
    modalCreateProductRef?: any; // BsModalRef
    searchControl = new FormControl();

    public productos: PaginatedResponse<any> = {} as PaginatedResponse;
    public categorias:any = [];
    public sucursales:any = [];
    public detalle:any = {};
    public override filtros:any = {};
    public buscador:any = '';
    public search:any = '';
    public tieneShopify: boolean = false;
    public descripcionesExpandidas: { [key: number]: boolean } = {};

    constructor(
        protected override apiService: ApiService,
        protected override alertService: AlertService,
        protected override modalManager: ModalManagerService,
        private sumPipe:SumPipe,

    ) {
        super(apiService, alertService, modalManager);
    }

    protected getPaginatedData(): PaginatedResponse | null {
        return this.productos;
    }

    protected setPaginatedData(data: PaginatedResponse): void {
        this.productos = data;
    }

    ngOnInit() {
        this.alertService.modal = false;

        // Cachear verificación de Shopify una sola vez
        const empresa = this.apiService.auth_user()?.empresa;
        this.tieneShopify = !!empresa?.shopify_store_url;

        this.searchControl.valueChanges
              .pipe(
                debounceTime(500),
                filter((query: string) => query.trim().length > 0),
                switchMap((query: any) =>
                  this.apiService.getAll(`productos/buscar/${encodeURIComponent(query)}`).pipe(
                    catchError(error => {
                      console.error('Error en la búsqueda:', error);
                      this.productos = {} as PaginatedResponse; // Limpiar resultados en caso de error.
                      this.loading = false; // Asegurar que el estado de carga se actualice.
                      return of([]); // Retornar un observable vacío para que el flujo continúe.
                    })
                  )
                ),
                this.untilDestroyed()
              )
              .subscribe((results: any[]) => {
                // Si results es un array, lo convertimos a PaginatedResponse
                if (Array.isArray(results)) {
                    this.productos = {
                        data: results,
                        current_page: 1,
                        last_page: 1,
                        per_page: results.length,
                        total: results.length,
                        from: 1,
                        to: results.length,
                        path: '',
                        first_page_url: '',
                        last_page_url: '',
                        next_page_url: null,
                        prev_page_url: null
                    } as PaginatedResponse;
                } else {
                    this.productos = results || {} as PaginatedResponse;
                }
                this.loading = false;

                if (results && (results.length == 1 ) && (this.buscador == results[0].codigo)) {
                    this.selectProducto(results[0]);
                }
              });
    }

    public loadAll() {
        this.filtros.id_sucursal = '';
        this.filtros.id_categoria = '';
        this.filtros.id_proveedor = '';
        this.filtros.estado = '';
        this.filtros.buscador = '';
        this.filtros.orden = 'nombre';
        this.filtros.direccion = 'asc';
        this.filtros.paginate = 5;

        this.filtrarProductos();
    }

    public filtrarProductos(){
        this.loading = true;
        this.apiService.getAll('productos', this.filtros)
            .pipe(this.untilDestroyed())
            .subscribe(productos => {
                this.productos = productos;
                this.loading = false;
            }, error => {this.alertService.error(error); this.loading = false;});
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

    // setPagination() ahora se hereda de BasePaginatedComponent


    public override openModal(template: TemplateRef<any>, config?: any) {
        this.filtros.id_sucursal = this.compra.id_sucursal;
        this.filtros.id_categoria = '';
        this.filtros.buscador = '';
        this.filtros.orden = 'nombre';
        this.filtros.direccion = 'asc';
        this.filtros.paginate = 5;

        this.apiService.getAll('categorias')
            .pipe(this.untilDestroyed())
            .subscribe(categorias => {
                this.categorias = categorias;
            }, error => {this.alertService.error(error);});

        if (this.filtros.id_categoria == null) {
            this.filtros.id_categoria = '';
        }
        if (this.filtros.id_sucursal == null) {
            this.filtros.id_sucursal = '';
        }

        this.loading = true;
        this.apiService.getAll('productos', this.filtros)
            .pipe(this.untilDestroyed())
            .subscribe(productos => {
                this.productos = productos;
                this.loading = false;
            }, error => {this.alertService.error(error); this.loading = false;});

        super.openModal(template, config || { class: 'modal-xl', backdrop: 'static' });
    }

    crearProducto(template: TemplateRef<any>) {
        this.modalCreateProductRef = this.modalManager.openModal(template, {
            class: 'modal-lg',
            backdrop: 'static',
            keyboard: false
        });
    }

    onProductoCreated(producto: any) {
        // Aquí puedes manejar el producto creado si es necesario
        producto.id_producto    = producto.id;

        // Si la empresa tiene shopify_store_url configurado, concatenar nombre_variante al nombre
        producto.nombre_producto = this.getNombreCompleto(producto);
        producto.img            = producto.img;
        producto.precio         = parseFloat(producto.precio);
        producto.costo          = parseFloat(producto.costo);
        producto.inventarios        = producto?.inventarios?.filter((item:any) => item.id_sucursal == this.compra.id_sucursal) || [];
        producto.stock          = parseFloat(this.sumPipe.transform(producto.inventarios, 'stock'));
        producto.cantidad       = 1;
        producto.descuento      = 0;

        console.log('Producto creado:', producto);

        this.productoSelect.emit(producto);
    }

    selectProducto(producto:any){
        this.detalle = Object.assign({}, producto);
        this.detalle.id_producto    = producto.id;

        // Si la empresa tiene shopify_store_url configurado, concatenar nombre_variante al nombre
        this.detalle.nombre_producto = this.getNombreCompleto(producto);
        this.detalle.img            = producto.img;
        this.detalle.precio         = parseFloat(producto.precio);
        this.detalle.costo          = parseFloat(producto.costo);
        producto.inventarios        = producto.inventarios.filter((item:any) => item.id_sucursal == this.compra.id_sucursal);
        this.detalle.stock          = parseFloat(this.sumPipe.transform(producto.inventarios, 'stock'));
        this.detalle.cantidad       = 1;
        this.detalle.descuento      = 0;
        this.onSubmit();
    }

    onCheckProducto(producto:any){
        this.detalle = Object.assign({}, producto);
        this.detalle.id_producto    = producto.id;

        // Si la empresa tiene shopify_store_url configurado, concatenar nombre_variante al nombre
        this.detalle.nombre_producto = this.getNombreCompleto(producto);
        this.detalle.img            = producto.img;
        this.detalle.precio         = parseFloat(producto.precio);
        this.detalle.costo          = parseFloat(producto.costo);
        producto.inventarios        = producto.inventarios.filter((item:any) => item.id_sucursal == this.compra.id_sucursal);
        this.detalle.stock          = parseFloat(this.sumPipe.transform(producto.inventarios, 'stock'));
        this.detalle.cantidad       = 1;
        this.detalle.descuento      = 0;

        console.log(this.detalle);
        let radio = document.getElementById('producto' + this.detalle.id_producto) as HTMLInputElement;
        if(radio){
            radio.checked = true
        }
    }

    onSubmit(){
        console.log(this.detalle);
        this.productos = {} as PaginatedResponse;
        this.searchControl.setValue('');
        this.productoSelect.emit(this.detalle);
        if(this.modalRef){
            this.closeModal();
        }
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

    /**
     * Obtiene la descripción del producto (completa o truncada según el estado)
     */
    getDescripcion(producto: any): string {
        if (!producto.descripcion) {
            return '';
        }
        const estaExpandida = this.descripcionesExpandidas[producto.id] || false;
        if (estaExpandida || producto.descripcion.length <= 20) {
            return producto.descripcion;
        }
        return producto.descripcion.substring(0, 20) + '...';
    }

    /**
     * Verifica si la descripción está truncada (necesita "ver más")
     */
    necesitaVerMas(producto: any): boolean {
        if (!producto.descripcion) {
            return false;
        }
        const estaExpandida = this.descripcionesExpandidas[producto.id] || false;
        return !estaExpandida && producto.descripcion.length > 20;
    }

    /**
     * Verifica si la descripción está expandida (muestra "ver menos")
     */
    estaExpandida(producto: any): boolean {
        return this.descripcionesExpandidas[producto.id] || false;
    }

    /**
     * Alterna el estado de expansión de la descripción
     */
    toggleDescripcion(event: Event, producto: any): void {
        event.stopPropagation(); // Previene que se seleccione el producto
        const estadoActual = this.descripcionesExpandidas[producto.id] || false;
        this.descripcionesExpandidas[producto.id] = !estadoActual;
    }

}
