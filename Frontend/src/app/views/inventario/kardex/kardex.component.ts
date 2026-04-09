import { Component, OnInit, DestroyRef, inject, ChangeDetectionStrategy, ChangeDetectorRef, OnDestroy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { Router, ActivatedRoute } from '@angular/router';
import { Subject, of, Subscription } from 'rxjs';
import { debounceTime, distinctUntilChanged, switchMap, catchError } from 'rxjs/operators';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';

@Component({
    selector: 'app-kardex',
    templateUrl: './kardex.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule],
    changeDetection: ChangeDetectionStrategy.OnPush
})
export class KardexComponent implements OnInit, OnDestroy {

  public producto: any = {};
  public productos: any[] = [];
  public bodegas: any[] = [];
  public lotes: any[] = [];
  public filtros: any = {};
  public loading = false;

  /** Producto seleccionado en el buscador (mismo flujo que facturación compras) */
  public productoSeleccionado: any = null;
  public searchProductos$ = new Subject<string>();
  public searchTerm = '';
  public searchResults: any[] = [];
  public searchLoading = false;
  private searchSub?: Subscription;
  private routeSub?: Subscription;

  private destroyRef = inject(DestroyRef);
  private untilDestroyed = subscriptionHelper(this.destroyRef);

  constructor(
    public apiService: ApiService,
    private alertService: AlertService,
    private route: ActivatedRoute,
    private router: Router,
    private cdr: ChangeDetectorRef
  ) {
    this.searchSub = this.searchProductos$.pipe(
      debounceTime(300),
      distinctUntilChanged(),
      switchMap(term => {
        if (!term || term.length < 2) {
          return of([]);
        }
        this.searchLoading = true;
        this.searchTerm = term;
        return this.apiService.store('productos/buscar-modal', {
          termino: term,
          id_empresa: this.apiService.auth_user().id_empresa,
          limite: 20
        }).pipe(
          catchError(() => of([]))
        );
      })
    ).subscribe(results => {
      this.searchResults = results || [];
      this.searchLoading = false;
    });
  }

  ngOnDestroy(): void {
    this.searchSub?.unsubscribe();
    this.routeSub?.unsubscribe();
  }

  compareProductos(a: any, b: any): boolean {
    return a && b && a.id === b.id;
  }

  /** Incluye el producto cargado en el desplegable para que se vea el seleccionado (typeahead solo pinta searchResults). */
  get opcionesBuscador(): any[] {
    if (!this.productoSeleccionado?.id) {
      return this.searchResults;
    }
    const resto = this.searchResults.filter((p: any) => p.id !== this.productoSeleccionado.id);
    return [this.productoSeleccionado, ...resto];
  }

  ngOnInit() {
    this.filtros.inicio = this.apiService.date();
    this.filtros.fin = this.apiService.date();
    this.filtros.id_inventario = this.apiService.auth_user().id_sucursal;
    this.filtros.lote_id = '';
    this.filtros.detalle = '';
    this.filtros.orden = 'fecha';
    this.filtros.direccion = 'desc';
    this.filtros.paginate = 10;

    this.apiService.getAll('bodegas/list').subscribe(bodegas => {
      this.bodegas = bodegas;
      this.loading = false;
    }, error => { this.alertService.error(error); this.loading = false; });

    this.routeSub = this.route.paramMap.subscribe(params => {
      const idParam = params.get('id');
      const id = idParam ? +idParam : NaN;
      if (!isNaN(id) && id > 0) {
        if (this.producto?.id !== id) {
          this.producto = {};
          this.filtros.lote_id = '';
        }
        this.filtros.id_producto = id;
        this.loadAll();
      } else {
        this.filtros.id_producto = '';
        this.productoSeleccionado = null;
        this.producto = { movimientos: [] };
        this.lotes = [];
      }
    });
  }

  onProductoDesdeBuscador(p: any): void {
    if (!p || !p.id) {
      this.filtros.id_producto = '';
      this.productoSeleccionado = null;
      this.producto = { movimientos: [] };
      this.lotes = [];
      this.router.navigate(['/kardex'], { replaceUrl: true });
      return;
    }
    this.router.navigate(['/kardex', p.id], { replaceUrl: true });
  }

  public loadAll() {
    if (!this.filtros.id_producto) {
      return;
    }
    const requiereLote = this.isLotesActivo()
      && this.producto?.id === this.filtros.id_producto
      && this.producto?.inventario_por_lotes;
    if (requiereLote && (!this.filtros.id_inventario || !this.filtros.lote_id)) {
      this.producto = { ...this.producto, movimientos: [] };
      return;
    }

    this.loading = true;
    this.apiService.getAll('productos/kardex', this.filtros).subscribe(producto => {
      this.producto = producto;
      if (this.producto?.id) {
        this.productoSeleccionado = {
          id: this.producto.id,
          nombre: this.producto.nombre,
          tipo: this.producto.tipo,
          codigo: this.producto.codigo,
          marca: this.producto.marca,
          inventario_por_lotes: this.producto.inventario_por_lotes
        };
      }
      if (this.producto?.inventario_por_lotes && this.isLotesActivo() && !this.filtros.lote_id) {
        this.producto.movimientos = [];
        this.cargarLotes();
      } else if (!this.producto?.inventario_por_lotes || !this.isLotesActivo()) {
        this.lotes = [];
      }
      this.loading = false;
    }, error => { this.alertService.error(error); this.loading = false; });
  }

  public isLotesActivo(): boolean {
    return this.apiService.isLotesActivo();
  }

  /** Lote en filtros, tarjeta y columna solo si el producto usa lotes y la empresa tiene lotes activos */
  get mostrarLote(): boolean {
    return !!(this.isLotesActivo() && (this.producto?.inventario_por_lotes || this.productoSeleccionado?.inventario_por_lotes));
  }

  get loteSeleccionadoNumero(): string {
    if (!this.mostrarLote || !this.filtros.lote_id || !this.lotes?.length) {
      return '—';
    }
    const lote = this.lotes.find((l: any) => String(l.id) === String(this.filtros.lote_id));
    return lote?.numero_lote ?? '—';
  }

  get colspanTabla(): number {
    return this.mostrarLote ? 17 : 16;
  }

  public cargarLotes() {
    if (!this.filtros.id_producto || !this.filtros.id_inventario) {
      this.lotes = [];
      this.filtros.lote_id = '';
      return;
    }
    const loteIdActual = this.filtros.lote_id ? String(this.filtros.lote_id) : '';
    this.apiService.getAll(`lotes/producto/${this.filtros.id_producto}`, {
      id_bodega: this.filtros.id_inventario
    }).subscribe((lotes: any[]) => {
      this.lotes = Array.isArray(lotes) ? lotes : [];
      if (loteIdActual) {
        const loteExiste = this.lotes.some((l: any) => String(l.id) === loteIdActual);
        this.filtros.lote_id = loteExiste ? loteIdActual : '';
      }
    }, error => {
      this.lotes = [];
      if (error) {
        this.filtros.lote_id = '';
      }
    });
  }

  selectProducto(producto: any) {
    this.filtros.id_producto = producto.id;
    this.filtros.lote_id = '';
    this.producto = producto;
    this.productoSeleccionado = producto;
    if (this.producto?.inventario_por_lotes && this.isLotesActivo()) {
      this.cargarLotes();
    } else {
      this.lotes = [];
      this.loadAll();
    }
  }

  public onBodegaChange() {
    if (this.producto?.inventario_por_lotes && this.isLotesActivo()) {
      this.filtros.lote_id = '';
      this.cargarLotes();
      this.producto = { ...this.producto, movimientos: [] };
    } else {
      this.loadAll();
    }
  }

  public descargarKardex() {
    if (!this.filtros.id_producto || !this.producto?.nombre) {
      this.alertService.warning('Kardex', 'Seleccione un producto para exportar.');
      return;
    }
    this.apiService.export('productos/kardex/exportar', this.filtros).subscribe((data: Blob) => {
      const blob = new Blob([data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = this.apiService.slug(this.producto.nombre) + '-kardex.xlsx';
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      window.URL.revokeObjectURL(url);
    }, (error) => { console.error('Error al exportar kardex:', error); });
  }
}
