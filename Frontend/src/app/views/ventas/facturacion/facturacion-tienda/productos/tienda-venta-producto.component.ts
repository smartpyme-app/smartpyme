import { Component, OnInit, EventEmitter, Input, Output, TemplateRef } from '@angular/core';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';

import { FormControl } from '@angular/forms';
import { debounceTime, switchMap, filter  } from 'rxjs/operators';

import { SumPipe }     from '@pipes/sum.pipe';
import { ApiService } from '@services/api.service';
import { AlertService } from '@services/alert.service';

@Component({
  selector: 'app-tienda-venta-producto',
  templateUrl: './tienda-venta-producto.component.html'
})
export class TiendaVentaProductoComponent implements OnInit {

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

    constructor( 
        private apiService: ApiService, private alertService: AlertService,
        private modalService: BsModalService, private sumPipe:SumPipe
    ) { }

    ngOnInit() {

        this.searchControl.valueChanges
              .pipe(
                debounceTime(500),
                filter((query: string) => query.trim().length > 0),
                switchMap((query: any) => this.apiService.read('productos/buscar/', query))
              )
              .subscribe((results: any[]) => {
                this.productos = Array.isArray(results) ? results : [];
                this.loading = false;

                if (results && (results.length == 1 ) && (this.buscador == results[0].codigo)) { 
                    this.selectProducto(results[0]);
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
        this.detalle.costo          = parseFloat(producto.costo);
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
            this.detalle.costo          = parseFloat(producto.costo);
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

}
