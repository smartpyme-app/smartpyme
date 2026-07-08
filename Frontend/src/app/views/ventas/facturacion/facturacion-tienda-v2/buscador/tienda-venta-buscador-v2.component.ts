import { Component, OnInit, EventEmitter, Input, Output, TemplateRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ReactiveFormsModule } from '@angular/forms';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { of } from 'rxjs';
import { FormControl } from '@angular/forms';
import { debounceTime, switchMap, filter, catchError, tap } from 'rxjs/operators';

import { SumPipe }     from '@pipes/sum.pipe';
import { FilterPipe } from '@pipes/filter.pipe';
import { ApiService } from '@services/api.service';
import { AlertService } from '@services/alert.service';
import {
    normalizarPorcentajeImpuestoDetalle,
    resolverPorcentajeImpuestoVenta,
    copiarImpuestosProductoAlDetalle,
} from '@utils/impuestos-venta.util';

@Component({
  selector: 'app-tienda-venta-buscador-v2',
  templateUrl: './tienda-venta-buscador-v2.component.html',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, SumPipe, FilterPipe],
  providers: [SumPipe],
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

    readonly minCaracteresBusqueda = 2;

    constructor( 
        private apiService: ApiService, private alertService: AlertService,
        private modalService: BsModalService, private sumPipe:SumPipe
    ) { }

    ngOnInit() {
        // Cachear verificación de Shopify una sola vez
        const empresa = this.apiService.auth_user()?.empresa;
        this.tieneShopify = !!empresa?.shopify_store_url;

        this.searchControl.valueChanges
          .pipe(
            debounceTime(500),
            tap((query: string | null) => {
              if (String(query ?? '').trim().length < this.minCaracteresBusqueda) {
                this.productos = [];
              }
            }),
            filter((query: string | null) => String(query ?? '').trim().length >= this.minCaracteresBusqueda),
            switchMap((query: any) => {
              const q = this.normalizeBusqueda(query);
              const params: any = { query: q };
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
        const pct = resolverPorcentajeImpuestoVenta(
            producto.porcentaje_impuesto,
            this.apiService.auth_user()?.empresa?.iva
        );
        return precio * (1 + pct / 100);
    }

    private ivaEmpresa(): number {
        return Number(this.apiService.auth_user()?.empresa?.iva ?? 0);
    }

    /** Precio sin IVA + % resuelto (producto o empresa) para armar el detalle de venta v2. */
    private armarPreciosDetalleV2(producto: any): {
        pctImpuesto: number;
        porcentajeImpuesto: number | null;
        precioSinIva: number;
        precioConIva: number;
    } {
        const ivaEmpresa = this.ivaEmpresa();
        const pctImpuesto = resolverPorcentajeImpuestoVenta(producto.porcentaje_impuesto, ivaEmpresa);
        const porcentajeImpuesto = normalizarPorcentajeImpuestoDetalle(producto.porcentaje_impuesto, ivaEmpresa);
        const precioSinIva = parseFloat(producto.precio) || 0;
        const precioConIva = pctImpuesto > 0
            ? precioSinIva * (1 + pctImpuesto / 100)
            : precioSinIva;
        return { pctImpuesto, porcentajeImpuesto, precioSinIva, precioConIva };
    }

    /** Lista de tarifas del producto (sin IVA) + precio de la fila, como en facturación v1. */
    private armarListaPreciosDetalleV2(producto: any, precioSinIva: number, pctImpuesto: number): any[] {
        const lista = producto.precios
            ? producto.precios.map((p: any) => {
                const sinIvaLista = parseFloat(p.precio);
                const conIva = pctImpuesto > 0
                    ? sinIvaLista * (1 + pctImpuesto / 100)
                    : sinIvaLista;
                return {
                    ...p,
                    precio: sinIvaLista.toFixed(4),
                    precio_sin_iva: sinIvaLista,
                    precio_con_iva: conIva.toFixed(4),
                };
            })
            : [];
        const conIvaBase = pctImpuesto > 0
            ? precioSinIva * (1 + pctImpuesto / 100)
            : precioSinIva;
        lista.unshift({
            precio: precioSinIva.toFixed(4),
            precio_sin_iva: precioSinIva,
            precio_con_iva: conIvaBase.toFixed(4),
        });
        return lista;
    }

    selectProducto(producto:any){
        this.detalle = Object.assign({}, producto);
        this.detalle.descripcion    = this.getNombreCompleto(producto);
        this.detalle.img            = producto.img;

        const esPlanoBuscador = producto.nombre_mostrar != null;
        const { pctImpuesto, porcentajeImpuesto, precioSinIva, precioConIva } =
            this.armarPreciosDetalleV2(producto);

        this.detalle.porcentaje_impuesto = porcentajeImpuesto;
        copiarImpuestosProductoAlDetalle(this.detalle, producto, this.ivaEmpresa());
        this.detalle.precio_iva          = precioConIva.toFixed(4);
        this.detalle.precio              = precioSinIva.toFixed(4);
        this.detalle.precio_base         = precioSinIva;
        this.detalle.precios             = this.armarListaPreciosDetalleV2(producto, precioSinIva, pctImpuesto);

        if(this.apiService.auth_user().empresa.valor_inventario == 'promedio' && producto.costo_promedio > 0){
            this.detalle.costo = parseFloat(producto.costo_promedio);
        }else{
            this.detalle.costo = parseFloat(producto.costo ?? 0);
        }

        if (esPlanoBuscador) {
            this.detalle.id_producto       = producto.id_producto;
            this.detalle.id_presentacion   = producto.id_presentacion ?? null;
            this.detalle.factor_conversion = producto.factor_conversion ?? 1;
            this.detalle.descripcion       = producto.nombre_mostrar;
            this.detalle.tipo              = producto.tipo;

            if (producto.tipo === 'Servicio') {
                this.detalle.stock                = null;
                this.detalle.inventario_por_lotes = false;
                this.detalle.lote_id              = null;
            } else if (
                producto.inventario_por_lotes &&
                producto.lotes?.length > 0 &&
                this.apiService.isLotesActivo()
            ) {
                const lotesBodega = this.venta.id_bodega
                    ? producto.lotes.filter((l: any) => l.id_bodega == this.venta.id_bodega)
                    : producto.lotes;
                let stockLotes = lotesBodega.reduce(
                    (sum: number, lote: any) => sum + (parseFloat(lote.stock) || 0), 0
                );
                const factor = parseFloat(String(producto.factor_conversion ?? 1)) || 1;
                if (factor > 0) {
                    stockLotes = stockLotes / factor;
                }
                this.detalle.stock                = stockLotes;
                this.detalle.inventario_por_lotes = true;
                this.detalle.lote_id              = null;
            } else {
                this.detalle.stock                = producto.stock_base_actual ?? null;
                this.detalle.inventario_por_lotes = false;
                this.detalle.lote_id              = null;
            }
        } else {
            this.detalle.id_producto       = producto.id;
            this.detalle.id_presentacion   = null;
            this.detalle.factor_conversion = 1;

            if (producto.tipo == 'Compuesto') {
                producto.composiciones.forEach((composicion:any) => {
                    composicion.compuesto.inventarios = composicion.compuesto.inventarios.filter(
                        (item:any) => item.id_bodega == this.venta.id_bodega
                    );
                    let stock = parseFloat(this.sumPipe.transform(composicion.compuesto.inventarios, 'stock'));
                    if(stock < composicion.cantidad){ producto.inventarios = []; }
                });
            }

            producto.inventarios = producto.inventarios?.filter((item:any) => item.id_bodega == this.venta.id_bodega) || [];

            if (
                producto.inventario_por_lotes &&
                producto.lotes &&
                producto.lotes.length > 0 &&
                this.apiService.isLotesActivo()
            ) {
                const lotesBodega = this.venta.id_bodega
                    ? producto.lotes.filter((l: any) => l.id_bodega == this.venta.id_bodega)
                    : producto.lotes;
                const stockLotes = lotesBodega.reduce(
                    (sum: number, lote: any) => sum + (parseFloat(lote.stock) || 0), 0
                );
                this.detalle.stock                = stockLotes;
                this.detalle.inventario_por_lotes = true;
                this.detalle.lote_id              = null;
            } else if (producto.tipo != 'Servicio' && producto.inventarios.length > 0) {
                this.detalle.stock                = parseFloat(this.sumPipe.transform(producto.inventarios, 'stock'));
                this.detalle.inventario_por_lotes = false;
                this.detalle.lote_id              = null;
            } else if (!esPlanoBuscador) {
                this.detalle.stock                = null;
                this.detalle.inventario_por_lotes = false;
                this.detalle.lote_id              = null;
            }
        }

        this.detalle.cantidad            = 1;
        this.detalle.descuento           = 0;
        this.detalle.descuento_porcentaje = 0;
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
     * Normaliza texto de búsqueda / escaneo: trim, saltos del lector, string.
     * Quita ~ final: lector con sufijo o teclado español vs emulación US del HID.
     */
    private normalizeBusqueda(val: any): string {
        return String(val ?? '')
            .trim()
            .replace(/[\r\n\u0000]+/g, '')
            .replace(/~+$/g, '');
    }

    get puedeMostrarResultadosBusqueda(): boolean {
        return String(this.searchControl.value ?? '').trim().length >= this.minCaracteresBusqueda;
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
            return `${producto.nombre_mostrar || producto.nombre} ${producto.nombre_variante}`;
        }
        return producto.nombre_mostrar || producto.nombre;
    }

}

