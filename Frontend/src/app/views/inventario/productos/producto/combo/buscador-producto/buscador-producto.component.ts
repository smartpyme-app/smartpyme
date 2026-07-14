import { Component, OnInit, EventEmitter, Input, Output, TemplateRef, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule, ReactiveFormsModule, FormControl } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { NgSelectModule } from '@ng-select/ng-select';
import { PopoverModule } from 'ngx-bootstrap/popover';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { debounceTime, switchMap, filter, catchError } from 'rxjs/operators';
import { of } from 'rxjs';

import { SumPipe } from '@pipes/sum.pipe';
import { FilterPipe } from '@pipes/filter.pipe';
import { ApiService } from '@services/api.service';
import { AlertService } from '@services/alert.service';
import { ModalManagerService } from '@services/modal-manager.service';
import { BaseModalComponent } from '@shared/base/base-modal.component';
import { PaginationComponent } from '@shared/parts/pagination/pagination.component';
import { LazyImageDirective } from '../../../../../../directives/lazy-image.directive';

@Component({
    selector: 'app-buscar-producto',
    templateUrl: './buscador-producto.component.html',
    standalone: true,
    imports: [
      CommonModule, FormsModule, ReactiveFormsModule, RouterModule,
      NgSelectModule, PopoverModule, TooltipModule,
      SumPipe, FilterPipe, LazyImageDirective, PaginationComponent,
    ],
    changeDetection: ChangeDetectionStrategy.OnPush,
})
export class BuscadorProductoComponent extends BaseModalComponent implements OnInit {

  @Input() producto: any = {};
  @Output() productoSelect = new EventEmitter();
  searchControl = new FormControl();

  public productos: any = [];
  public categorias: any = [];
  public detalle: any = {};
  public filtros: any = {};

  constructor(
    public apiService: ApiService,
    protected override alertService: AlertService,
    protected override modalManager: ModalManagerService,
    private sumPipe: SumPipe,
    private cdr: ChangeDetectorRef
  ) {
    super(modalManager, alertService);
  }

  ngOnInit() {
    this.searchControl.valueChanges
      .pipe(
        debounceTime(500),
        filter((query: string) => !!query?.trim().length),
        switchMap((query: string) =>
          this.apiService.getAll(`productos/buscar-by-query?query=${encodeURIComponent(query)}`).pipe(
            catchError(() => {
              this.productos = [];
              this.loading = false;
              this.cdr.markForCheck();
              return of([]);
            })
          )
        ),
        this.untilDestroyed()
      )
      .subscribe((results: any[]) => {
        this.productos = Array.isArray(results) ? results : [];
        this.loading = false;
        this.cdr.markForCheck();

        if (results?.length === 1 && (this.searchControl.value == results[0].codigo || this.searchControl.value == results[0].barcode)) {
          this.selectProducto(results[0]);
        }
      });
  }

  public override openModal(template: TemplateRef<any>) {
    this.filtros.id_categoria = '';
    this.filtros.buscador = '';
    this.filtros.orden = 'nombre';
    this.filtros.direccion = 'asc';
    this.filtros.paginate = 5;
    this.filtros.page = 1;

    this.apiService.getAll('categorias')
      .pipe(this.untilDestroyed())
      .subscribe(categorias => {
        this.categorias = categorias;
        this.cdr.markForCheck();
      }, error => { this.alertService.error(error); this.cdr.markForCheck(); });

    this.loadAll();
    super.openModal(template, { class: 'modal-xl', backdrop: 'static' });
  }

  loadAll() {
    this.filtros.page = 1;
    this.filtrar();
  }

  filtrar() {
    this.loading = true;
    this.cdr.markForCheck();
    this.apiService.getAll('productos', this.filtros)
      .pipe(this.untilDestroyed())
      .subscribe(productos => {
        this.productos = productos;
        this.loading = false;
        this.cdr.markForCheck();
      }, error => { this.alertService.error(error); this.loading = false; this.cdr.markForCheck(); });
  }

  setOrden(columna: string) {
    if (this.filtros.orden === columna) {
      this.filtros.direccion = this.filtros.direccion === 'asc' ? 'desc' : 'asc';
    } else {
      this.filtros.orden = columna;
      this.filtros.direccion = 'asc';
    }
    this.loadAll();
  }

  setPagination(event: any) {
    this.filtros.page = event.page;
    this.filtrar();
  }

  selectProducto(producto: any) {
    this.mapDetalle(producto);
    this.onSubmit();
  }

  onCheckProducto(producto: any) {
    this.mapDetalle(producto);
    const radio = document.getElementById('producto' + this.detalle.id_producto) as HTMLInputElement;
    if (radio) {
      radio.checked = true;
    }
    this.cdr.markForCheck();
  }

  private mapDetalle(producto: any) {
    this.detalle = Object.assign({}, producto);
    this.detalle.id_producto = producto.id;
    this.detalle.nombre_producto = producto.nombre;
    this.detalle.img = producto.img;
    this.detalle.precio = parseFloat(producto.precio);
    this.detalle.costo = parseFloat(producto.costo);
    this.detalle.stock = parseFloat(this.sumPipe.transform(producto.inventarios, 'stock'));
    this.detalle.cantidad = 1;
    this.detalle.descuento = 0;
  }

  onSubmit() {
    if (!this.detalle?.id_producto) {
      return;
    }
    this.productos = [];
    this.searchControl.setValue('');
    this.productoSelect.emit(this.detalle);
    this.closeModal();
  }

}
