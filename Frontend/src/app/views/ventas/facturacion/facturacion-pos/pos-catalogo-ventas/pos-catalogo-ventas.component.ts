import {
  ChangeDetectionStrategy,
  ChangeDetectorRef,
  Component,
  DestroyRef,
  EventEmitter,
  inject,
  Input,
  OnInit,
  Output,
} from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { FormControl, ReactiveFormsModule } from '@angular/forms';
import { CommonModule } from '@angular/common';
import { of } from 'rxjs';
import { catchError, debounceTime, distinctUntilChanged, switchMap } from 'rxjs/operators';

import { ApiService } from '@services/api.service';
import { AlertService } from '@services/alert.service';
import {
  PosMenuVentasCategoria,
  PosMenuVentasContenido,
  PosMenuVentasProducto,
  PosMenuVentasService,
  PosMenuVentasSubcategoria,
} from '@services/pos-menu-ventas.service';
import { CurrencyPipe } from '@pipes/currency-format.pipe';
import { resolveCategoriaTap, trackFichaPos } from '@views/restaurante/cuenta-mesa/pos/pos-menu-nav';

type NivelCatalogo = 'raiz' | 'subcategorias' | 'productos';

@Component({
  selector: 'app-pos-catalogo-ventas',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, CurrencyPipe],
  templateUrl: './pos-catalogo-ventas.component.html',
  styleUrls: ['./pos-catalogo-ventas.component.css'],
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class PosCatalogoVentasComponent implements OnInit {
  private readonly destroyRef = inject(DestroyRef);
  private readonly cdr = inject(ChangeDetectorRef);

  @Input() idBodega: number | null = null;
  @Output() productoElegido = new EventEmitter<PosMenuVentasProducto>();

  nivel: NivelCatalogo = 'raiz';
  categorias: PosMenuVentasCategoria[] = [];
  subcategorias: PosMenuVentasSubcategoria[] = [];
  productos: PosMenuVentasProducto[] = [];
  categoriaActual: PosMenuVentasCategoria | null = null;
  subcategoriaActual: PosMenuVentasSubcategoria | null = null;

  loading = false;
  buscando = false;
  resultadosBusqueda: PosMenuVentasProducto[] = [];
  searchControl = new FormControl('');

  constructor(
    private posMenuVentas: PosMenuVentasService,
    private alertService: AlertService,
    public apiService: ApiService
  ) {}

  ngOnInit(): void {
    this.cargarCategorias();
    this.searchControl.valueChanges
      .pipe(
        debounceTime(300),
        distinctUntilChanged(),
        switchMap((q) => {
          const query = (q || '').trim();
          if (!query) {
            this.resultadosBusqueda = [];
            this.buscando = false;
            this.cdr.markForCheck();
            return of(null);
          }
          this.buscando = true;
          this.cdr.markForCheck();
          return this.posMenuVentas.buscar(query, this.queryParams()).pipe(
            catchError((err) => {
              this.buscando = false;
              this.alertService.error(err);
              this.cdr.markForCheck();
              return of([] as PosMenuVentasProducto[]);
            })
          );
        }),
        takeUntilDestroyed(this.destroyRef)
      )
      .subscribe((resultados) => {
        this.buscando = false;
        if (resultados) {
          this.resultadosBusqueda = resultados;
        }
        this.cdr.markForCheck();
      });
  }

  get enBusqueda(): boolean {
    return !!this.searchControl.value?.trim();
  }

  private queryParams(): { id_bodega?: number } {
    return this.idBodega ? { id_bodega: this.idBodega } : {};
  }

  cargarCategorias(): void {
    this.loading = true;
    this.cdr.markForCheck();
    this.posMenuVentas
      .categorias(this.queryParams())
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (categorias) => {
          this.categorias = categorias || [];
          this.loading = false;
          this.cdr.markForCheck();
        },
        error: (err) => {
          this.loading = false;
          this.alertService.error(err);
          this.cdr.markForCheck();
        },
      });
  }

  tapCategoria(cat: PosMenuVentasCategoria): void {
    this.categoriaActual = cat;
    this.subcategoriaActual = null;
    this.loading = true;
    this.cdr.markForCheck();
    this.posMenuVentas
      .contenidoCategoria(cat.id, this.queryParams())
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (res: PosMenuVentasContenido) => {
          this.loading = false;
          if (resolveCategoriaTap(res?.modo, cat.subcategorias_count) === 'subcategorias') {
            this.subcategorias = (res?.items as PosMenuVentasSubcategoria[]) || [];
            this.nivel = 'subcategorias';
          } else {
            this.productos = (res?.items as PosMenuVentasProducto[]) || [];
            this.nivel = 'productos';
          }
          this.cdr.markForCheck();
        },
        error: (err) => {
          this.loading = false;
          this.alertService.error(err);
          this.cdr.markForCheck();
        },
      });
  }

  tapSubcategoria(sub: PosMenuVentasSubcategoria): void {
    this.subcategoriaActual = sub;
    this.loading = true;
    this.cdr.markForCheck();
    this.posMenuVentas
      .productosSubcategoria(sub.id, this.queryParams())
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (productos) => {
          this.productos = productos || [];
          this.nivel = 'productos';
          this.loading = false;
          this.cdr.markForCheck();
        },
        error: (err) => {
          this.loading = false;
          this.alertService.error(err);
          this.cdr.markForCheck();
        },
      });
  }

  tapProducto(p: PosMenuVentasProducto): void {
    this.productoElegido.emit(p);
  }

  readonly trackFichaPos = trackFichaPos;

  volver(): void {
    if (this.nivel === 'productos' && this.subcategoriaActual) {
      this.nivel = 'subcategorias';
      this.subcategoriaActual = null;
      this.cdr.markForCheck();
      return;
    }
    this.irRaiz();
  }

  irRaiz(): void {
    this.nivel = 'raiz';
    this.categoriaActual = null;
    this.subcategoriaActual = null;
    this.cdr.markForCheck();
  }

  esPlaceholder(img?: string | null): boolean {
    const name = String(img || '').trim();
    if (!name) return true;
    return name.endsWith('default.jpg') || name.endsWith('default.png');
  }

  imgUrl(img?: string | null): string {
    const path = String(img || '').replace(/^\/+/, '');
    return `${this.apiService.baseUrl}/img/${path}`;
  }
}
