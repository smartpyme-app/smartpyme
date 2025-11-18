import { Component, OnInit, EventEmitter, Input, Output, TemplateRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule, ReactiveFormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { NgSelectModule } from '@ng-select/ng-select';
import { PaginationComponent } from '@shared/parts/pagination/pagination.component';
import { BasePaginatedModalComponent, PaginatedResponse } from '@shared/base/base-paginated-modal.component';

import { FormControl } from '@angular/forms';
import { debounceTime, switchMap, filter  } from 'rxjs/operators';

import { SumPipe }     from '@pipes/sum.pipe';
import { ApiService } from '@services/api.service';
import { AlertService } from '@services/alert.service';
import { ModalManagerService } from '@services/modal-manager.service';

@Component({
    selector: 'app-tienda-venta-producto',
    templateUrl: './tienda-venta-producto.component.html',
    standalone: true,
    imports: [CommonModule, FormsModule, ReactiveFormsModule, RouterModule, NgSelectModule, PaginationComponent],
    
})
export class TiendaVentaProductoComponent extends BasePaginatedModalComponent implements OnInit {

    @Input() venta: any = {};
    @Output() productoSelect = new EventEmitter();
    searchControl = new FormControl();

    public productos:any = [];
    public productosData: PaginatedResponse<any> = {} as PaginatedResponse;
    public categorias:any = [];
    public sucursales:any = [];
    public detalle:any = {};
    public detalles:any = [];
    public override filtros:any = {};
    public buscador:any = '';

    constructor( 
        apiService: ApiService, 
        alertService: AlertService,
        modalManager: ModalManagerService,
        private sumPipe:SumPipe
    ) {
        super(apiService, alertService, modalManager);
    }

    protected override getPaginatedData(): PaginatedResponse | null {
        return this.productosData;
    }

    protected override setPaginatedData(data: PaginatedResponse): void {
        this.productosData = data;
    }

    ngOnInit() {

        this.searchControl.valueChanges
              .pipe(
                debounceTime(500),
                filter((query: string) => query.trim().length > 0),
                switchMap((query: any) => this.apiService.read('productos/buscar/', query)),
                this.untilDestroyed()
              )
              .subscribe((results: any[]) => {
                this.productos = Array.isArray(results) ? results : [];
                this.loading = false;

                if (results && (results.length == 1 ) && (this.buscador == results[0].codigo)) { 
                    this.selectProducto(results[0]);
                }
              });
    }

    override openModal(template: TemplateRef<any>) {

        this.apiService.getAll('categorias').pipe(this.untilDestroyed()).subscribe(categorias => {
            this.categorias = categorias;
        }, error => {this.alertService.error(error);});

        this.loadAll();
        super.openModal(template, { class: 'modal-xl', backdrop: 'static' });
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
        this.apiService.getAll('productos', this.filtros).pipe(this.untilDestroyed()).subscribe(productos => { 
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

    // setPagination() ahora se hereda de BasePaginatedComponent


    selectProducto(producto:any){
        this.detalle = Object.assign({}, producto);
        this.detalle.id_producto    = producto.id;
        this.detalle.descripcion = producto.nombre;
        this.detalle.img            = producto.img;
        this.detalle.precio         = parseFloat(producto.precio);
        this.detalle.precios        = producto.precios;
        this.detalle.precios.unshift({
                'precio' : this.detalle.precio
            });
        
        if(this.apiService.auth_user().empresa.valor_inventario == 'promedio' && producto.costo_promedio > 0){
            this.detalle.costo          = parseFloat(producto.costo_promedio);
        }else{
            this.detalle.costo          = parseFloat(producto.costo);
        }
        producto.inventarios        = producto.inventarios.filter((item:any) => item.id_sucursal == this.venta.id_sucursal);
        if(producto.inventarios.length > 0){
            this.detalle.stock          = parseFloat(this.sumPipe.transform(producto.inventarios, 'stock'));
        }else{
            this.detalle.stock = null;
        }
        this.detalle.cantidad       = 1;
        this.detalle.descuento      = 0;
        this.detalle.descuento_porcentaje      = 0;
        console.log(this.detalle);
        this.onSubmit();
    }

    onCheckProducto(producto:any){
        let radio = document.getElementById('producto' + producto.id) as HTMLInputElement;
        if(radio.checked){
            // radio.checked = true
            this.detalle = Object.assign({}, producto);
            this.detalle.id_producto    = producto.id;
            this.detalle.descripcion = producto.nombre;
            this.detalle.img            = producto.img;
            this.detalle.precio         = parseFloat(producto.precio);
            this.detalle.precios        = producto.precios;
            this.detalle.precios.unshift({
                    'precio' : this.detalle.precio
                });
            
            if(this.apiService.auth_user().empresa.valor_inventario == 'promedio' && producto.costo_promedio > 0){
                this.detalle.costo          = parseFloat(producto.costo_promedio);
            }else{
                this.detalle.costo          = parseFloat(producto.costo);
            }
            producto.inventarios        = producto.inventarios.filter((item:any) => item.id_sucursal == this.venta.id_sucursal);
            if(producto.tipo != 'Servicio' && producto.inventarios.length > 0){
                this.detalle.stock          = parseFloat(this.sumPipe.transform(producto.inventarios, 'stock'));
            }else{
                this.detalle.stock = null;
            }
            this.detalle.cantidad       = 1;
            this.detalle.descuento      = 0;
            this.detalle.descuento_porcentaje      = 0;
            this.detalles.unshift(this.detalle);
        }else{
            // radio.checked = false;
            const indexAEliminar = this.detalles.findIndex((item:any) => item.id_producto === producto.id);
            if (indexAEliminar !== -1) {
              this.detalles.splice(indexAEliminar, 1);
            }
            console.log(indexAEliminar);
        }

        console.log(this.detalles);
    }

    onSubmit(){
        this.productos = [];
        this.searchControl.setValue('');
        this.productoSelect.emit(this.detalle);
        if(this.modalRef){
            this.closeModal();
        }
    }

    agregarDetalles(){
        for (let i = 0; i < this.detalles.length; i++) { 
            this.productoSelect.emit(this.detalles[i]);
        }

        this.searchControl.setValue('');
        if(this.modalRef){
            this.closeModal();
        }
    }

}
