import { Component, OnInit, EventEmitter, Input, Output, TemplateRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule, ReactiveFormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';

import { of } from 'rxjs';
import { FormControl } from '@angular/forms';
import { debounceTime, switchMap, filter,catchError  } from 'rxjs/operators';

import { SumPipe }     from '@pipes/sum.pipe';
import { FilterPipe } from '@pipes/filter.pipe';
import { ApiService } from '@services/api.service';
import { AlertService } from '@services/alert.service';
import { CrearProductoComponent } from '@shared/modals/crear-producto/crear-producto.component';
import { TooltipModule } from 'ngx-bootstrap/tooltip';

@Component({
  selector: 'app-compra-producto',
  templateUrl: './compra-producto.component.html',
  standalone: true,
  imports: [CommonModule, FormsModule, ReactiveFormsModule, RouterModule, TooltipModule, CrearProductoComponent, SumPipe, FilterPipe],
  providers: [SumPipe],
})
export class CompraProductoComponent implements OnInit {

    @Input() compra: any = {};
    @Output() productoSelect = new EventEmitter();
    modalRef!: BsModalRef;
    modalCreateProductRef?: BsModalRef;
    searchControl = new FormControl();

    public productos:any = [];
    public categorias:any = [];
    public sucursales:any = [];
    public detalle:any = {};
    public filtros:any = {};
    public buscador:any = '';
    public loading:boolean = false;
    public search:any = '';
    public tieneShopify: boolean = false;
    public descripcionesExpandidas: { [key: number]: boolean } = {};

    public isComponenteQuimicoHabilitado(): boolean {
        return this.apiService.isComponenteQuimicoHabilitado();
    }

    constructor( 
        private apiService: ApiService, private alertService: AlertService,
        private modalService: BsModalService, private sumPipe:SumPipe,
        
    ) { }

    ngOnInit() {
        this.alertService.modal = false;

        // Cachear verificación de Shopify una sola vez
        const empresa = this.apiService.auth_user()?.empresa;
        this.tieneShopify = !!empresa?.shopify_store_url;

        this.searchControl.valueChanges
              .pipe(
                debounceTime(500),
                filter((query: string) => query?.trim().length > 0),
                switchMap((query: any) => {
                    const q = this.normalizeBusqueda(query);
                    const params: any = { query: q };
                    if (this.compra?.id_bodega) params.id_bodega = this.compra.id_bodega;
                    if (this.compra?.id_sucursal) params.id_sucursal = this.compra.id_sucursal;
                    return this.apiService.getAll('productos/buscar-by-query', params).pipe(
                        catchError(error => {
                            console.error('Error en la búsqueda:', error);
                            this.productos = [];
                            this.loading = false;
                            return of([]);
                        })
                    );
                })
              )
              .subscribe({
                next: (results: any[]) => {
                    this.productos = Array.isArray(results) ? results : [];
                    this.loading = false;

                    const busqueda = this.normalizeBusqueda(this.searchControl.value);
                    if (!busqueda || !this.productos.length) {
                        return;
                    }

                    const porCodigoExacto = this.productos.filter((p: any) => {
                        const cod = this.normalizeBusqueda(p?.codigo);
                        const bar = this.normalizeBusqueda(p?.barcode);
                        return cod === busqueda || bar === busqueda;
                    });

                    if (porCodigoExacto.length === 1) {
                        this.selectProducto(porCodigoExacto[0]);
                        return;
                    }

                    if (
                        this.productos.length === 1 &&
                        (this.normalizeBusqueda(this.productos[0].codigo) === busqueda ||
                        this.normalizeBusqueda(this.productos[0].barcode) === busqueda)
                    ) {
                        this.selectProducto(this.productos[0]);
                    }
                },
                error: (err) => {
                    console.error('Error no controlado:', err);
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
        this.apiService.getAll('productos', this.filtros).subscribe(productos => { 
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

    public setPagination(event:any):void{
        this.loading = true;
        this.apiService.paginate(this.productos.path + '?page='+ event.page, this.filtros).subscribe(productos => { 
            this.productos = productos;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});
    }


    public openModal(template: TemplateRef<any>) {
        this.filtros.id_sucursal = this.compra.id_sucursal;
        this.filtros.id_categoria = '';
        this.filtros.buscador = '';
        this.filtros.orden = 'nombre';
        this.filtros.direccion = 'asc';
        this.filtros.paginate = 5;

        this.apiService.getAll('categorias').subscribe(categorias => {
            this.categorias = categorias;
        }, error => {this.alertService.error(error);});

        if (this.filtros.id_categoria == null) {
            this.filtros.id_categoria = '';
        }
        if (this.filtros.id_sucursal == null) {
            this.filtros.id_sucursal = '';
        }

        this.loading = true;
        this.apiService.getAll('productos', this.filtros).subscribe(productos => { 
            this.productos = productos;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});

        this.modalRef = this.modalService.show(template, { class: 'modal-xl', backdrop: 'static' });
    }

    crearProducto(template: TemplateRef<any>) {
        this.modalCreateProductRef = this.modalService.show(template, {
            class: 'modal-lg',
            backdrop: 'static',
            keyboard: false,
            ignoreBackdropClick: true
        });
    }

    onProductoCreated(producto: any) {
        producto.id_producto    = producto.id;
        producto.tipo = producto.tipo || 'Producto';
        
        // Si la empresa tiene shopify_store_url configurado, concatenar nombre_variante al nombre
        producto.nombre_producto = this.getNombreCompleto(producto);
        producto.img            = producto.img;
        producto.precio         = parseFloat(producto.precio);
        producto.costo          = parseFloat(producto.costo);
        producto.inventarios        = producto?.inventarios?.filter((item:any) => item.id_sucursal == this.compra.id_sucursal) || [];
        producto.stock          = parseFloat(this.sumPipe.transform(producto.inventarios, 'stock'));
        producto.cantidad       = 1;
        producto.descuento      = 0;
        producto.inventario_por_lotes = producto.inventario_por_lotes || false;
        producto.lote_id = null;
        producto.porcentaje_impuesto = producto.porcentaje_impuesto ?? this.apiService.auth_user()?.empresa?.iva;
        
        console.log('Producto creado:', producto);
        
        this.productoSelect.emit(producto);
    }

    selectProducto(producto:any){
        this.detalle = Object.assign({}, producto);
        
        const esPlanoBuscador = producto.nombre_mostrar != null;

        if (esPlanoBuscador) {
            this.detalle.id_producto      = producto.id_producto;
            this.detalle.id_presentacion  = producto.id_presentacion ?? null;
            this.detalle.factor_conversion = producto.factor_conversion ?? 1;
            this.detalle.nombre_producto  = producto.nombre_mostrar;
            this.detalle.img              = producto.img; // la imagen del producto base viene en producto.img
            this.detalle.precio           = parseFloat(producto.precio ?? 0);
            
            // Para compras, si elegimos una presentación, el costo mostrado debe ser el del empaque
            const costoBase = parseFloat(producto.costo ?? 0);
            this.detalle.costo            = costoBase * this.detalle.factor_conversion;
            
            this.detalle.stock            = producto.stock_base_actual ?? null;
            this.detalle.cantidad         = 1;
            this.detalle.descuento        = 0;
            this.detalle.inventario_por_lotes = producto.inventario_por_lotes || false;
            this.detalle.lote_id          = null;
            this.detalle.porcentaje_impuesto = producto.porcentaje_impuesto ?? this.apiService.auth_user()?.empresa?.iva;
        } else {
            this.detalle.id_producto      = producto.id;
            this.detalle.id_presentacion  = null;
            this.detalle.factor_conversion = 1;
            
            // Si la empresa tiene shopify_store_url configurado, concatenar nombre_variante al nombre
            this.detalle.nombre_producto  = this.getNombreCompleto(producto);
            this.detalle.img              = producto.img;
            this.detalle.precio           = parseFloat(producto.precio);
            this.detalle.costo            = parseFloat(producto.costo);
            producto.inventarios          = producto.inventarios.filter((item:any) => item.id_sucursal == this.compra.id_sucursal);
            this.detalle.stock            = parseFloat(this.sumPipe.transform(producto.inventarios, 'stock'));
            this.detalle.cantidad         = 1;
            this.detalle.descuento        = 0;
            this.detalle.inventario_por_lotes = producto.inventario_por_lotes || false;
            this.detalle.lote_id          = null;
            this.detalle.porcentaje_impuesto = producto.porcentaje_impuesto ?? this.apiService.auth_user()?.empresa?.iva;
        }
        
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
        this.detalle.inventario_por_lotes = producto.inventario_por_lotes || false;
        this.detalle.lote_id = null;

        console.log(this.detalle);
        let radio = document.getElementById('producto' + this.detalle.id_producto) as HTMLInputElement;
        if(radio){
            radio.checked = true
        }
    }

    onSubmit(){
        console.log(this.detalle);
        this.productos = [];
        this.searchControl.setValue('');
        this.productoSelect.emit(this.detalle);
        if(this.modalRef){
            this.modalRef.hide();
        }
    }

    private normalizeBusqueda(val: any): string {
        return String(val ?? '')
            .trim()
            .replace(/[\r\n\u0000]+/g, '')
            .replace(/~+$/g, '');
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
