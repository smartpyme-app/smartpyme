import { Injectable } from '@angular/core';
import { HttpClient, HttpHeaders } from '@angular/common/http';
import { Observable, of } from 'rxjs';
import { catchError, map, shareReplay } from 'rxjs/operators';
import { ApiService } from '@services/api.service';
import { AlertService } from '@services/alert.service';
import { environment } from '../../../../environments/environment';

/** Ítem estándar para `app-dropdown-multi-filtro` en secciones del dashboard. */
export interface DashboardFiltroCatalogoItem {
  id: string;
  nombre: string;
}

/**
 * Catálogos para filtros del dashboard: sucursales vía Laravel (permisos por rol);
 * canales, clientes, vendedores, categorías, productos, proveedores y estados vía API Go.
 */
@Injectable({ providedIn: 'root' })
export class DashboardFiltrosCatalogoService {
  private sucursales$?: Observable<DashboardFiltroCatalogoItem[]>;
  private canales$?: Observable<DashboardFiltroCatalogoItem[]>;
  private clientes$?: Observable<DashboardFiltroCatalogoItem[]>;
  private vendedores$?: Observable<DashboardFiltroCatalogoItem[]>;
  private categorias$?: Observable<DashboardFiltroCatalogoItem[]>;
  private productos$?: Observable<DashboardFiltroCatalogoItem[]>;
  private proveedores$?: Observable<DashboardFiltroCatalogoItem[]>;
  private estadosVenta$?: Observable<DashboardFiltroCatalogoItem[]>;

  constructor(
    private api: ApiService,
    private alert: AlertService,
    private http: HttpClient
  ) {}

  private get idEmpresa(): number {
    return (
      this.api.auth_user()?.id_empresa ??
      this.api.auth_user()?.empresa?.id ??
      0
    );
  }

  private getFromGo(endpoint: string): Observable<DashboardFiltroCatalogoItem[]> {
    const url = `${environment.goApiUrl}${endpoint}?empresa=${this.idEmpresa}`;
    return this.http
      .get<DashboardFiltroCatalogoItem[]>(url, {
        headers: new HttpHeaders({
          Authorization: `Bearer ${environment.goApiSecret}`,
        }),
        params: { saltarJWT: 'true' },
      })
      .pipe(
        map((list) =>
          (list || []).map((x) => ({
            id: String(x.id),
            nombre: String(x.nombre ?? '').trim(),
          }))
        ),
        catchError(() => of([]))
      );
  }

  /** Útil tras logout o cambio de empresa/usuario si hiciera falta volver a pedir listas. */
  invalidarCache(): void {
    this.sucursales$ = undefined;
    this.canales$ = undefined;
    this.clientes$ = undefined;
    this.vendedores$ = undefined;
    this.categorias$ = undefined;
    this.productos$ = undefined;
    this.proveedores$ = undefined;
    this.estadosVenta$ = undefined;
  }

  /**
   * Sucursales visibles según rol (no administrador → solo su `id_sucursal`).
   * Misma regla que en Resultados / Ventas antes de centralizar.
   */
  sucursalesParaFiltro(): Observable<DashboardFiltroCatalogoItem[]> {
    if (!this.sucursales$) {
      this.sucursales$ = this.api.getAll('sucursales/list').pipe(
        map((list: any[]) => {
          let items = (list || []).map((s: any) => ({
            id: String(s.id),
            nombre: String(s.nombre ?? '').trim(),
          }));
          const user = this.api.auth_user();
          if (user?.tipo !== 'Administrador' && user?.id_sucursal != null) {
            const sid = String(user.id_sucursal);
            items = items.filter((s) => s.id === sid);
          }
          return items;
        }),
        catchError((err) => {
          this.alert.error(err);
          return of([]);
        }),
        shareReplay(1)
      );
    }
    return this.sucursales$;
  }

  canalesParaFiltro(): Observable<DashboardFiltroCatalogoItem[]> {
    if (!this.canales$) {
      this.canales$ = this.getFromGo('/api/dimensiones/canales').pipe(
        shareReplay(1)
      );
    }
    return this.canales$;
  }

  clientesParaFiltro(): Observable<DashboardFiltroCatalogoItem[]> {
    if (!this.clientes$) {
      this.clientes$ = this.getFromGo('/api/dimensiones/clientes').pipe(
        shareReplay(1)
      );
    }
    return this.clientes$;
  }

  vendedoresParaFiltro(): Observable<DashboardFiltroCatalogoItem[]> {
    if (!this.vendedores$) {
      this.vendedores$ = this.getFromGo('/api/dimensiones/vendedores').pipe(
        shareReplay(1)
      );
    }
    return this.vendedores$;
  }

  categoriasParaFiltro(): Observable<DashboardFiltroCatalogoItem[]> {
    if (!this.categorias$) {
      this.categorias$ = this.getFromGo('/api/dimensiones/categorias').pipe(
        shareReplay(1)
      );
    }
    return this.categorias$;
  }

  /**
   * Catálogo de productos para filtros del dashboard (valor = `id`, etiqueta = `nombre`).
   * GET /api/dimensiones/productos?empresa={idEmpresa}
   */
  productosParaFiltro(): Observable<DashboardFiltroCatalogoItem[]> {
    if (!this.productos$) {
      this.productos$ = this.getFromGo('/api/dimensiones/productos').pipe(
        shareReplay(1)
      );
    }
    return this.productos$;
  }

  proveedoresParaFiltro(): Observable<DashboardFiltroCatalogoItem[]> {
    if (!this.proveedores$) {
      this.proveedores$ = this.getFromGo('/api/dimensiones/proveedores').pipe(
        shareReplay(1)
      );
    }
    return this.proveedores$;
  }

  estadosVentaParaFiltro(): Observable<DashboardFiltroCatalogoItem[]> {
    if (!this.estadosVenta$) {
      this.estadosVenta$ = this.getFromGo('/api/dimensiones/estados-venta').pipe(
        shareReplay(1)
      );
    }
    return this.estadosVenta$;
  }
}
