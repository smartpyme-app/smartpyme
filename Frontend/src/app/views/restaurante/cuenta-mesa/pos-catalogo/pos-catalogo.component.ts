import { Component, EventEmitter, OnDestroy, OnInit, Output } from '@angular/core';
import { FormControl } from '@angular/forms';
import { Subscription, of } from 'rxjs';
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
import { resolveCategoriaTap } from '../pos/pos-menu-nav';

type NivelCatalogo = 'raiz' | 'subcategorias' | 'productos';

@Component({
  standalone: false,
  selector: 'app-pos-catalogo',
  templateUrl: './pos-catalogo.component.html',
  styleUrls: ['./pos-catalogo.component.css']
})
export class PosCatalogoComponent implements OnInit, OnDestroy {
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

  private searchSub?: Subscription;

  constructor(
    private restauranteService: RestauranteService,
    private alertService: AlertService,
    public apiService: ApiService
  ) {}

  ngOnInit(): void {
    this.cargarCategorias();
    this.searchSub = this.searchControl.valueChanges
      .pipe(
        debounceTime(300),
        distinctUntilChanged(),
        switchMap((q) => {
          const query = (q || '').trim();
          if (!query) {
            this.resultadosBusqueda = [];
            this.buscando = false;
            return of(null);
          }
          this.buscando = true;
          return this.restauranteService.posMenuBuscar(query).pipe(
            catchError((err) => {
              this.buscando = false;
              this.alertService.error(err);
              return of([] as PosMenuProducto[]);
            })
          );
        })
      )
      .subscribe((resultados) => {
        this.buscando = false;
        if (resultados) {
          this.resultadosBusqueda = resultados;
        }
      });
  }

  ngOnDestroy(): void {
    this.searchSub?.unsubscribe();
  }

  get enBusqueda(): boolean {
    return !!this.searchControl.value?.trim();
  }

  cargarCategorias(): void {
    this.loading = true;
    this.restauranteService.posMenuCategorias().subscribe({
      next: (categorias) => {
        this.categorias = categorias || [];
        this.loading = false;
      },
      error: (err) => {
        this.loading = false;
        this.alertService.error(err);
      }
    });
  }

  tapCategoria(cat: PosMenuCategoria): void {
    this.categoriaActual = cat;
    this.subcategoriaActual = null;
    this.loading = true;
    this.restauranteService.posMenuContenidoCategoria(cat.id).subscribe({
      next: (res: PosMenuContenido) => {
        this.loading = false;
        if (resolveCategoriaTap(res?.modo, cat.subcategorias_count) === 'subcategorias') {
          this.subcategorias = (res?.items as PosMenuSubcategoria[]) || [];
          this.nivel = 'subcategorias';
        } else {
          this.productos = (res?.items as PosMenuProducto[]) || [];
          this.nivel = 'productos';
        }
      },
      error: (err) => {
        this.loading = false;
        this.alertService.error(err);
      }
    });
  }

  tapSubcategoria(sub: PosMenuSubcategoria): void {
    this.subcategoriaActual = sub;
    this.loading = true;
    this.restauranteService.posMenuProductosSubcategoria(sub.id).subscribe({
      next: (productos) => {
        this.productos = productos || [];
        this.nivel = 'productos';
        this.loading = false;
      },
      error: (err) => {
        this.loading = false;
        this.alertService.error(err);
      }
    });
  }

  tapProducto(p: PosMenuProducto): void {
    this.productoElegido.emit(p);
  }

  volver(): void {
    if (this.nivel === 'productos' && this.subcategoriaActual) {
      this.nivel = 'subcategorias';
      this.subcategoriaActual = null;
      return;
    }
    this.irRaiz();
  }

  irRaiz(): void {
    this.nivel = 'raiz';
    this.categoriaActual = null;
    this.subcategoriaActual = null;
  }

  /** default.jpg / default.png del backend (o vacío) cuenta como sin foto real. */
  esPlaceholder(img?: string | null): boolean {
    const name = String(img || '').trim();
    if (!name) return true;
    return name.endsWith('default.jpg') || name.endsWith('default.png');
  }

  imgUrl(img?: string | null): string {
    return `${this.apiService.baseUrl}/img/${img}`;
  }
}
