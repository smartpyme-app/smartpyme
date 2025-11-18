import { Component, OnInit, EventEmitter, Input, Output, TemplateRef, DestroyRef, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule, ReactiveFormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';

import { FormControl } from '@angular/forms';
import { debounceTime, switchMap, filter } from 'rxjs/operators';

import { SumPipe } from '@pipes/sum.pipe';
import { FilterPipe } from '@pipes/filter.pipe';
import { ApiService } from '@services/api.service';
import { AlertService } from '@services/alert.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';

@Component({
    selector: 'app-buscar-producto',
    templateUrl: './buscador-producto.component.html',
    standalone: true,
    imports: [CommonModule, FormsModule, ReactiveFormsModule, RouterModule, SumPipe, FilterPipe],
    
})
export class BuscadorProductoComponent implements OnInit {

  @Input() producto: any = {};
  @Output() productoSelect = new EventEmitter();
  modalRef!: BsModalRef;
  searchControl = new FormControl();

  public productos: any = [];
  public categorias: any = [];
  public sucursales: any = [];
  public detalle: any = {};
  public filtros: any = {};
  public buscador: any = '';
  public loading: boolean = false;

  private destroyRef = inject(DestroyRef);
  private untilDestroyed = subscriptionHelper(this.destroyRef);

  constructor(
    private apiService: ApiService, private alertService: AlertService,
    private modalService: BsModalService, private sumPipe: SumPipe
  ) { }

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

        if (results && (results.length == 1) && (this.buscador == results[0].codigo)) {
          this.selectProducto(results[0]);
        }
      });

    // this.buscador = '';
    // const input = document.getElementById('producto')!;
    // const producto = fromEvent(input, 'keyup').pipe(map(i => (<HTMLTextAreaElement>i.currentTarget).value));
    // const debouncedInput = producto.pipe(debounceTime(500));
    // const subscribe = debouncedInput.subscribe(val => { this.searchProducto(); });
  }


  public openModal(template: TemplateRef<any>) {
    // this.filtros.id_sucursal = this.compra.id_sucursal;
    this.filtros.id_categoria = '';
    this.filtros.buscador = '';
    this.filtros.orden = 'nombre';
    this.filtros.direccion = 'asc';
    this.filtros.paginate = 5;

    this.apiService.getAll('categorias')
      .pipe(this.untilDestroyed())
      .subscribe(categorias => {
      this.categorias = categorias;
    }, error => { this.alertService.error(error); });

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
    }, error => { this.alertService.error(error); this.loading = false; });

    this.modalRef = this.modalService.show(template, { class: 'modal-xl', backdrop: 'static' });
  }


  selectProducto(producto: any) {
    this.detalle = Object.assign({}, producto);
    this.detalle.id_producto = producto.id;
    this.detalle.nombre_producto = producto.nombre;
    this.detalle.img = producto.img;
    this.detalle.precio = parseFloat(producto.precio);
    this.detalle.costo = parseFloat(producto.costo);
    // producto.inventarios        = producto.inventarios.filter((item:any) => item.id_sucursal == this.compra.id_sucursal);
    this.detalle.stock = parseFloat(this.sumPipe.transform(producto.inventarios, 'stock'));
    this.detalle.cantidad = 1;
    this.detalle.descuento = 0;
    this.onSubmit();
  }

  onCheckProducto(producto: any) {
    this.detalle = Object.assign({}, producto);
    this.detalle.id_producto = producto.id;
    this.detalle.nombre_producto = producto.nombre;
    this.detalle.img = producto.img;
    this.detalle.precio = parseFloat(producto.precio);
    this.detalle.costo = parseFloat(producto.costo);
    // producto.inventarios        = producto.inventarios.filter((item:any) => item.id_sucursal == this.compra.id_sucursal);
    this.detalle.stock = parseFloat(this.sumPipe.transform(producto.inventarios, 'stock'));
    this.detalle.cantidad = 1;
    this.detalle.descuento = 0;

    console.log(this.detalle);
    let radio = document.getElementById('producto' + this.detalle.id_producto) as HTMLInputElement;
    if (radio) {
      radio.checked = true
    }
  }

  onSubmit() {
    this.productos = [];
    this.searchControl.setValue('');
    this.productoSelect.emit(this.detalle);
    if (this.modalRef) {
      this.modalRef.hide();
    }
  }

}
