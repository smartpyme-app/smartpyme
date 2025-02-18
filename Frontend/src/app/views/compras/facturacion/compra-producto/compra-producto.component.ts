import { Component, OnInit, EventEmitter, Input, Output, TemplateRef } from '@angular/core';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';

import { of } from 'rxjs';
import { FormControl } from '@angular/forms';
import { debounceTime, switchMap, filter,catchError  } from 'rxjs/operators';

import { SumPipe }     from '@pipes/sum.pipe';
import { ApiService } from '@services/api.service';
import { AlertService } from '@services/alert.service';

@Component({
  selector: 'app-compra-producto',
  templateUrl: './compra-producto.component.html'
})
export class CompraProductoComponent implements OnInit {

    @Input() compra: any = {};
    @Output() productoSelect = new EventEmitter();
    modalRef!: BsModalRef;
    searchControl = new FormControl();

    public productos:any = [];
    public categorias:any = [];
    public sucursales:any = [];
    public detalle:any = {};
    public filtros:any = {};
    public buscador:any = '';
    public loading:boolean = false;

    constructor( 
        private apiService: ApiService, private alertService: AlertService,
        private modalService: BsModalService, private sumPipe:SumPipe
    ) { }

    ngOnInit() {
        console.log(this.compra);
        this.searchControl.valueChanges
          .pipe(
            debounceTime(500),
            filter((query: string) => query?.trim().length > 0), // Validación para evitar errores con `null` o `undefined`.
            switchMap((query: any) => 
              this.apiService.getAll(`productos/buscar-by-query?query=${encodeURIComponent(query)}`).pipe(
                catchError(error => {
                  console.error('Error en la búsqueda:', error);
                  this.productos = []; // Limpiar resultados en caso de error.
                  this.loading = false; // Asegurar que el estado de carga se actualice.
                  return of([]); // Retornar un observable vacío para que el flujo continúe.
                })
              )
            )
          )
          .subscribe({
            next: (results: any[]) => {
              this.productos = Array.isArray(results) ? results : [];
              this.loading = false;

              if (
                results &&
                results.length === 1 &&
                this.buscador === results[0].codigo
              ) {
                this.selectProducto(results[0]);
              }
            },
            error: (err) => {
              console.error('Error no controlado:', err); // Log en caso de un error en la suscripción.
            }
          });

        // this.buscador = '';
        // const input = document.getElementById('producto')!;
        // const producto = fromEvent(input, 'keyup').pipe(map(i => (<HTMLTextAreaElement>i.currentTarget).value));
        // const debouncedInput = producto.pipe(debounceTime(500));
        // const subscribe = debouncedInput.subscribe(val => { this.searchProducto(); });
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


    selectProducto(producto:any){
        this.detalle = Object.assign({}, producto);
        this.detalle.id_producto    = producto.id;
        this.detalle.nombre_producto = producto.nombre;
        this.detalle.img            = producto.img;
        this.detalle.precio         = parseFloat(producto.precio);
        // this.detalle.costo          = parseFloat(producto.costo);
        this.detalle.costo          = null;
        producto.inventarios        = producto.inventarios.filter((item:any) => item.id_sucursal == this.compra.id_sucursal);
        this.detalle.stock          = parseFloat(this.sumPipe.transform(producto.inventarios, 'stock'));
        this.detalle.cantidad       = 1;
        this.detalle.descuento      = 0;
        this.onSubmit();
    }

    onCheckProducto(producto:any){
        this.detalle = Object.assign({}, producto);
        this.detalle.id_producto    = producto.id;
        this.detalle.nombre_producto = producto.nombre;
        this.detalle.img            = producto.img;
        this.detalle.precio         = parseFloat(producto.precio);
        // this.detalle.costo          = parseFloat(producto.costo);
        this.detalle.costo          = null;
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
        this.productos = [];
        this.searchControl.setValue('');
        this.productoSelect.emit(this.detalle);
        if(this.modalRef){
            this.modalRef.hide();
        }
    }

}
