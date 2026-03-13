import { Component, OnInit, EventEmitter, Input, Output, TemplateRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { CurrencyPipe } from '@pipes/currency-format.pipe';
import { FormsModule, ReactiveFormsModule } from '@angular/forms';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { of } from 'rxjs';
import { FormControl } from '@angular/forms';
import { debounceTime, switchMap, filter,catchError  } from 'rxjs/operators';

import { SumPipe }     from '@pipes/sum.pipe';
import { ApiService } from '@services/api.service';
import { AlertService } from '@services/alert.service';

@Component({
  selector: 'app-tienda-venta-buscador-v2',
  templateUrl: './tienda-venta-buscador-v2.component.html',
  standalone: true,
  imports: [CommonModule, FormsModule, ReactiveFormsModule, CurrencyPipe],
  providers: [SumPipe]
})
export class TiendaVentaBuscadorV2Component implements OnInit {

    @Input() venta: any = {};
    @Output() productoSelect = new EventEmitter();
    modalRef!: BsModalRef;
    searchControl = new FormControl();

    public productos:any = [];
    public productosData:any = [];
    public categorias:any = [];
    public sucursales:any = [];
    public detalle:any = {};
    public detalles:any = [];
    public filtros:any = {};
    public buscador:any = '';
    public loading:boolean = false;
    private tieneShopify: boolean = false;

    constructor( 
        public apiService: ApiService, private alertService: AlertService,
        private modalService: BsModalService, private sumPipe:SumPipe
    ) { }

    ngOnInit() {
        // Cachear verificación de Shopify una sola vez
        const empresa = this.apiService.auth_user()?.empresa;
        this.tieneShopify = !!empresa?.shopify_store_url;

        this.searchControl.valueChanges
          .pipe(
            debounceTime(500),
            filter((query: string) => query?.trim().length > 0), // Validación para evitar errores con `null` o `undefined`.
            switchMap((query: any) => {
              const params: any = { query: query };
              if (this.venta?.id_bodega) params.id_bodega = this.venta.id_bodega;
              if (this.venta?.id_sucursal) params.id_sucursal = this.venta.id_sucursal;
              return this.apiService.getAll('productos/buscar-by-query', params).pipe(
                catchError(error => {
                  console.error('Error en la búsqueda:', error);
                  this.productos = []; // Limpiar resultados en caso de error.
                  this.loading = false; // Asegurar que el estado de carga se actualice.
                  return of([]); // Retornar un observable vacío para que el flujo continúe.
                })
              );
            })
          )
          .subscribe({
            next: (results: any[]) => {
              this.productos = Array.isArray(results) ? results : [];
              this.loading = false;

              if (
                results &&
                results.length == 1 &&
                (this.searchControl.value == results[0].codigo || this.searchControl.value == results[0].barcode)
              ) {
                this.selectProducto(results[0]);
              }
            },
            error: (err) => {
              console.error('Error no controlado:', err); // Log en caso de un error en la suscripción.
            }
          });

    }

    public openModal(template: TemplateRef<any>) {

        this.apiService.getAll('categorias').subscribe(categorias => {
            this.categorias = categorias;
        }, error => {this.alertService.error(error);});

        this.loadAll();
        this.modalRef = this.modalService.show(template, { class: 'modal-xl', backdrop: 'static' });
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
        if (this.venta?.id_bodega && !this.filtros.id_bodega) {
            this.filtros.id_bodega = this.venta.id_bodega;
        } else if (this.venta?.id_sucursal && !this.filtros.id_sucursal) {
            this.filtros.id_sucursal = this.venta.id_sucursal;
        }
        this.apiService.getAll('productos', this.filtros).subscribe(productos => { 
            this.productosData = productos;
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
        this.apiService.paginate(this.productosData.path + '?page='+ event.page, this.filtros).subscribe(productosData => { 
            this.productosData = productosData;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});
    }

    /**
     * Calcula el precio con IVA incluido usando el % del producto si tiene, si no el de la empresa.
     */
    public getPrecioConIva(producto: any): number {
        if (!producto) return 0;
        const precio = parseFloat(producto.precio) || 0;
        const pct = (producto.porcentaje_impuesto != null && producto.porcentaje_impuesto !== '')
            ? Number(producto.porcentaje_impuesto) : (this.apiService.auth_user()?.empresa?.iva ?? 0);
        return precio * (1 + pct / 100);
    }

    selectProducto(producto:any){
        this.detalle = Object.assign({}, producto);
        this.detalle.id_producto    = producto.id;
        this.detalle.descripcion    = this.getNombreCompleto(producto);
        this.detalle.img            = producto.img;
        
        // En v2, el precio se muestra con IVA pero se guarda sin IVA. Usar % del producto si tiene.
        const pctImpuesto = (producto.porcentaje_impuesto != null && producto.porcentaje_impuesto !== '')
            ? Number(producto.porcentaje_impuesto) : (this.apiService.auth_user()?.empresa?.iva ?? 0);
        this.detalle.porcentaje_impuesto = producto.porcentaje_impuesto ?? this.apiService.auth_user()?.empresa?.iva;
        const precioSinIva = parseFloat(producto.precio);
        const precioConIva = precioSinIva * (1 + pctImpuesto / 100);
        
        // precio_iva: precio con IVA (para cálculos y visualización)
        this.detalle.precio_iva     = precioConIva.toFixed(4);
        // precio: precio sin IVA (para guardar en BD)
        this.detalle.precio         = precioSinIva.toFixed(4);
        
        // Guardar también el precio base para referencia
        this.detalle.precio_base    = precioSinIva;
        
        // Actualizar precios con IVA incluido para el selector (con % del producto)
        this.detalle.precios        = producto.precios ? producto.precios.map((p: any) => ({
            ...p,
            precio: (parseFloat(p.precio) * (1 + pctImpuesto / 100)).toFixed(4),
            precio_sin_iva: parseFloat(p.precio)
        })) : [];
        
        this.detalle.precios.unshift({
                'precio' : this.detalle.precio_iva,
                'precio_sin_iva': precioSinIva
            });
            
        if(this.apiService.auth_user().empresa.valor_inventario == 'promedio' && producto.costo_promedio > 0){
            this.detalle.costo          = parseFloat(producto.costo_promedio);
        }else{
            this.detalle.costo          = parseFloat(producto.costo);
        }

        // Verificar que los compuestos tengan stock
            if(producto.tipo == 'Compuesto'){

                producto.composiciones.forEach((composicion:any) => {
                    composicion.compuesto.inventarios = composicion.compuesto.inventarios.filter((item:any) => item.id_bodega == this.venta.id_bodega);
                    let stock          = parseFloat(this.sumPipe.transform(composicion.compuesto.inventarios, 'stock'));
                    if(stock < composicion.cantidad){
                        producto.inventarios = [];
                        console.log("No tiene stock suficiente:" + composicion.nombre_compuesto);
                    }
                });

            }

        producto.inventarios        = producto.inventarios?.filter((item:any) => item.id_bodega == this.venta.id_bodega) || [];
        // Si el producto tiene inventario por lotes, usar stock de lotes (como en v1)
        if (producto.inventario_por_lotes && producto.lotes && producto.lotes.length > 0) {
            const lotesBodega = this.venta.id_bodega
                ? producto.lotes.filter((l: any) => l.id_bodega == this.venta.id_bodega)
                : producto.lotes;
            const stockLotes = lotesBodega.reduce((sum: number, lote: any) => sum + (parseFloat(lote.stock) || 0), 0);
            this.detalle.stock = stockLotes;
            this.detalle.inventario_por_lotes = true;
            this.detalle.lote_id = null;
        } else if (producto.tipo != 'Servicio' && producto.inventarios.length > 0) {
            this.detalle.stock = parseFloat(this.sumPipe.transform(producto.inventarios, 'stock'));
            this.detalle.inventario_por_lotes = false;
            this.detalle.lote_id = null;
        } else {
            this.detalle.stock = null;
            this.detalle.inventario_por_lotes = false;
            this.detalle.lote_id = null;
        }
        this.detalle.cantidad       = 1;
        this.detalle.descuento      = 0;
        this.detalle.descuento_porcentaje      = 0;
        console.log(this.detalle);
        this.onSubmit();
    }

    onSubmit(){
        this.productos = [];
        this.searchControl.setValue('');
        this.productoSelect.emit(this.detalle);
        if(this.modalRef){
            this.modalRef.hide();
        }
    }

    agregarDetalles(){
        for (let i = 0; i < this.detalles.length; i++) { 
            this.productoSelect.emit(this.detalles[i]);
        }

        this.searchControl.setValue('');
        if(this.modalRef){
            this.modalRef.hide();
        }
    }

    /**
     * Verifica si el componente químico está habilitado en la empresa
     */
    public isComponenteQuimicoHabilitado(): boolean {
        return this.apiService.isComponenteQuimicoHabilitado();
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
     * Obtiene el stock del producto filtrado por bodega
     */
    getStock(producto: any): number {
        if (!producto.inventarios || !Array.isArray(producto.inventarios) || producto.inventarios.length === 0) {
            return 0;
        }

        if (!this.venta || !this.venta.id_bodega) {
            // Si no hay bodega seleccionada, sumar todo el stock
            return parseFloat(this.sumPipe.transform(producto.inventarios, 'stock')) || 0;
        }

        // Filtrar inventarios por bodega y sumar el stock
        const inventariosFiltrados = producto.inventarios.filter((inv: any) => inv.id_bodega == this.venta.id_bodega);
        return parseFloat(this.sumPipe.transform(inventariosFiltrados, 'stock')) || 0;
    }

}

