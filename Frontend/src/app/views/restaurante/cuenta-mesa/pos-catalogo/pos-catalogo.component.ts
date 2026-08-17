import {
  ChangeDetectionStrategy,
  ChangeDetectorRef,
  Component,
  DestroyRef,
  EventEmitter,
  inject,
  OnInit,
  Output,
} from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { FormControl } from '@angular/forms';
import { of } from 'rxjs';
import { catchError, debounceTime, distinctUntilChanged, switchMap } from 'rxjs/operators';

import { ApiService } from '@services/api.service';
import { AlertService } from '@services/alert.service';
import {
  PosMenuCategoria,
  PosMenuContenido,
  PosMenuProducto,
  PosMenuSubcategoria,
  RestauranteService
} from '@services/restaurante.service';
import { resolveCategoriaTap, trackFichaPos } from '../pos/pos-menu-nav';

type NivelCatalogo = 'raiz' | 'subcategorias' | 'productos';

@Component({
  standalone: false,
  selector: 'app-pos-catalogo',
  templateUrl: './pos-catalogo.component.html',
  styleUrls: ['./pos-catalogo.component.css'],
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class PosCatalogoComponent implements OnInit {
  private readonly destroyRef = inject(DestroyRef);
  private readonly cdr = inject(ChangeDetectorRef);

  @Output() productoElegido = new EventEmitter<PosMenuProducto>();

  nivel: NivelCatalogo = 'raiz';
  categorias: PosMenuCategoria[] = [];
  subcategorias: PosMenuSubcategoria[] = [];
  productos: PosMenuProducto[] = [];
  categoriaActual: PosMenuCategoria | null = null;
  subcategoriaActual: PosMenuSubcategoria | null = null;

  loading = false;
  buscando = false;
  resultadosBusqueda: PosMenuProducto[] = [];
  searchControl = new FormControl('');

  constructor(
    private restauranteService: RestauranteService,
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
          return this.restauranteService.posMenuBuscar(query).pipe(
            catchError((err) => {
              this.buscando = false;
              this.alertService.error(err);
              this.cdr.markForCheck();
              return of([] as PosMenuProducto[]);
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

  cargarCategorias(): void {
    this.loading = true;
    this.cdr.markForCheck();
    this.restauranteService
      .posMenuCategorias()
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
        }
      });
  }

  tapCategoria(cat: PosMenuCategoria): void {
    this.categoriaActual = cat;
    this.subcategoriaActual = null;
    this.loading = true;
    this.cdr.markForCheck();
    this.restauranteService
      .posMenuContenidoCategoria(cat.id)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (res: PosMenuContenido) => {
          this.loading = false;
          if (resolveCategoriaTap(res?.modo, cat.subcategorias_count) === 'subcategorias') {
            this.subcategorias = (res?.items as PosMenuSubcategoria[]) || [];
            this.nivel = 'subcategorias';
          } else {
            this.productos = (res?.items as PosMenuProducto[]) || [];
            this.nivel = 'productos';
          }
          this.cdr.markForCheck();
        },
        error: (err) => {
          this.loading = false;
          this.alertService.error(err);
          this.cdr.markForCheck();
        }
      });
  }

  tapSubcategoria(sub: PosMenuSubcategoria): void {
    this.subcategoriaActual = sub;
    this.loading = true;
    this.cdr.markForCheck();
    this.restauranteService
      .posMenuProductosSubcategoria(sub.id)
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
        }
      });
  }

  tapProducto(p: PosMenuProducto): void {
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

  /** default.jpg / default.png del backend (o vacío) cuenta como sin foto real. */
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
