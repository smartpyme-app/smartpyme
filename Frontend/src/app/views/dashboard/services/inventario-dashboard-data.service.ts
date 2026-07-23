import { Injectable } from '@angular/core';
import { Observable, forkJoin, merge, of } from 'rxjs';
import { map, scan, catchError } from 'rxjs/operators';
import { DashboardAnalyticsApiService } from './dashboard-analytics-api.service';

export interface DetalleProductosTotales {
  stock: number;
  inversionPromedio: number;
  ventasEsperadas: number;
}

export interface DetalleProductosPagina {
  items: any[];
  total: number;
  limite: number;
  offset: number;
  totales: DetalleProductosTotales;
}

export interface DetalleProductosPageParams {
  limite: number;
  offset: number;
  q?: string;
}

export interface DetalleAjustesTotales {
  costoTotal: number;
}

export interface DetalleAjustesPagina {
  items: any[];
  total: number;
  limite: number;
  offset: number;
  totales: DetalleAjustesTotales;
}

export interface DetalleAjustesPageParams {
  limite: number;
  offset: number;
  q?: string;
}

export interface DetalleEsTotales {
  entradas: number;
  valorEntradas: number;
  salidas: number;
  valorSalidas: number;
}

export interface DetalleEsPagina {
  items: any[];
  total: number;
  limite: number;
  offset: number;
  totales: DetalleEsTotales;
}

export interface DetalleEsPageParams {
  limite: number;
  offset: number;
  q?: string;
}

@Injectable({
  providedIn: 'root',
})
export class InventarioDashboardDataService {
  private static readonly MESES_ES = [
    'Enero',
    'Febrero',
    'Marzo',
    'Abril',
    'Mayo',
    'Junio',
    'Julio',
    'Agosto',
    'Septiembre',
    'Octubre',
    'Noviembre',
    'Diciembre',
  ] as const;

  constructor(private analytics: DashboardAnalyticsApiService) {}

  /**
   * Barras agrupadas: `app-bar-chart` detecta multi-serie cuando `data` es
   * `[{ name, data }, …]` (no un solo array numérico + `dataExtra`).
   */
  private buildEntradasSalidasPorMesChart(esPorMes: any[] | null | undefined) {
    const porMes = esPorMes ?? [];
    return {
      type: 'bar' as const,
      labels: porMes.map((i: any) => i.nombreMes),
      data: [
        {
          name: 'Entradas',
          data: porMes.map((i: any) => Number(i.entradasUnidades) || 0),
        },
        {
          name: 'Salidas',
          data: porMes.map((i: any) => Number(i.salidasUnidades) || 0),
        },
      ],
      colors: ['rgb(124, 171, 255)', 'rgb(241, 149, 71)'],
    };
  }

  private buildQueryPaths(filtros: any): { pSnap: string; pMov: string } {
    const empresa = this.analytics.idEmpresa;
    const pBase = `empresa=${empresa}`;
    const pAnio = filtros?.anio ? `&anio=${filtros.anio}` : '';
    const pMes = filtros?.mes ? `&mes=${filtros.mes}` : '';
    const pSuc =
      filtros?.sucursal && filtros.sucursal !== 'todas'
        ? `&sucursal=${filtros.sucursal}`
        : '';
    const pCat = filtros?.categoria ? `&categoria=${filtros.categoria}` : '';
    const pProd = filtros?.producto ? `&producto=${filtros.producto}` : '';
    const pProv = filtros?.proveedor ? `&proveedor=${filtros.proveedor}` : '';
    const pSnap = `${pBase}${pSuc}${pCat}${pProd}${pProv}`;
    const pMov = `${pBase}${pAnio}${pMes}${pSuc}${pCat}${pProd}${pProv}`;
    return { pSnap, pMov };
  }

  private mapDetalleProductoItem(i: any) {
    return {
      producto: i.producto,
      categoria: i.categoria,
      stock: i.stock,
      costo: i.costo,
      inversionPromedio: i.inversionPromedio,
      precio: i.precio,
      ventasEsperadas: i.ventasEsperadas,
      utilidadEsperada: i.utilidadEsperada,
    };
  }

  private sumarTotalesDetalle(items: any[]): DetalleProductosTotales {
    return (items ?? []).reduce(
      (acc, i) => ({
        stock: acc.stock + (Number(i.stock) || 0),
        inversionPromedio: acc.inversionPromedio + (Number(i.inversionPromedio) || 0),
        ventasEsperadas: acc.ventasEsperadas + (Number(i.ventasEsperadas) || 0),
      }),
      { stock: 0, inversionPromedio: 0, ventasEsperadas: 0 },
    );
  }

  /**
   * Acepta array plano (compat) o envelope `{ items, total, limite, offset, totales }`.
   * Si llega array completo, aplica `q`/slice en cliente hasta que el backend pagine.
   */
  normalizarDetalleProductosResponse(
    data: any,
    page: { limite: number; offset: number },
    q?: string,
  ): DetalleProductosPagina {
    const qNorm = q != null ? String(q).trim().toLowerCase() : '';

    // Compat: array plano = listado completo (backend aún sin paginar o export).
    if (Array.isArray(data)) {
      let all = data.map((i) => this.mapDetalleProductoItem(i));
      if (qNorm) {
        all = all.filter((i) =>
          String(i.producto ?? '')
            .toLowerCase()
            .includes(qNorm),
        );
      }
      const limite = page.limite > 0 ? page.limite : all.length;
      const offset = page.offset >= 0 ? page.offset : 0;
      const items =
        page.limite > 0 ? all.slice(offset, offset + limite) : all;
      return {
        items,
        total: all.length,
        limite,
        offset,
        totales: this.sumarTotalesDetalle(all),
      };
    }

    const rawItems = data?.items ?? data?.data ?? [];
    const items = (Array.isArray(rawItems) ? rawItems : []).map((i: any) =>
      this.mapDetalleProductoItem(i),
    );
    const totalesApi = data?.totales;
    const totales =
      totalesApi && typeof totalesApi === 'object'
        ? {
            stock: Number(totalesApi.stock) || 0,
            inversionPromedio: Number(totalesApi.inversionPromedio) || 0,
            ventasEsperadas: Number(totalesApi.ventasEsperadas) || 0,
          }
        : this.sumarTotalesDetalle(items);

    return {
      items,
      total: Number(data?.total) >= 0 ? Number(data.total) : items.length,
      limite: Number(data?.limite) >= 0 ? Number(data.limite) : page.limite,
      offset: Number(data?.offset) >= 0 ? Number(data.offset) : page.offset,
      totales,
    };
  }

  private buildDetalleProductosUrl(
    filtros: any,
    opts?: { limite?: number; offset?: number; q?: string },
  ): string {
    const api = this.analytics.baseUrl;
    const { pSnap } = this.buildQueryPaths(filtros);
    let url = `${api}/api/inventario/detalle-productos?${pSnap}`;
    if (opts?.limite != null) {
      url += `&limite=${opts.limite}`;
    }
    if (opts?.offset != null) {
      url += `&offset=${opts.offset}`;
    }
    const q = opts?.q != null ? String(opts.q).trim() : '';
    if (q) {
      url += `&q=${encodeURIComponent(q)}`;
    }
    return url;
  }

  /** Una página del detalle de productos (lazy / paginación server-side). */
  obtenerDetalleProductosPagina(
    filtros: any = {},
    page: DetalleProductosPageParams,
  ): Observable<DetalleProductosPagina> {
    const limite = page.limite > 0 ? page.limite : 50;
    const offset = page.offset >= 0 ? page.offset : 0;
    const url = this.buildDetalleProductosUrl(filtros, {
      limite,
      offset,
      q: page.q,
    });
    return this.analytics.getSafe(url).pipe(
      map((data) =>
        this.normalizarDetalleProductosResponse(
          data,
          { limite, offset },
          page.q,
        ),
      ),
      catchError((err) => {
        console.error('Error loading /api/inventario/detalle-productos (página):', err);
        return of({
          items: [],
          total: 0,
          limite,
          offset,
          totales: { stock: 0, inversionPromedio: 0, ventasEsperadas: 0 },
        });
      }),
    );
  }

  /** Listado completo (export). Sin limite/offset → backend compat array plano. */
  obtenerDetalleProductosCompleto(
    filtros: any = {},
    opts: { q?: string } = {},
  ): Observable<DetalleProductosPagina> {
    const url = this.buildDetalleProductosUrl(filtros, { q: opts.q });
    return this.analytics.getSafe(url).pipe(
      map((data) =>
        this.normalizarDetalleProductosResponse(
          data,
          { limite: 0, offset: 0 },
          opts.q,
        ),
      ),
      catchError((err) => {
        console.error('Error loading /api/inventario/detalle-productos (completo):', err);
        return of({
          items: [],
          total: 0,
          limite: 0,
          offset: 0,
          totales: { stock: 0, inversionPromedio: 0, ventasEsperadas: 0 },
        });
      }),
    );
  }

  private mapDetalleAjusteItem(i: any) {
    const mesNum =
      i.mes != null && !Number.isNaN(Number(i.mes)) ? Number(i.mes) : null;
    let mesLabel = '';
    if (i.nombreMes != null && String(i.nombreMes).trim() !== '') {
      mesLabel = String(i.nombreMes).trim();
    } else if (mesNum != null && mesNum >= 1 && mesNum <= 12) {
      mesLabel = InventarioDashboardDataService.MESES_ES[mesNum - 1];
    } else if (i.fecha != null && String(i.fecha).trim() !== '') {
      mesLabel = String(i.fecha).trim();
    }
    return {
      fecha: mesLabel || '-',
      mes: mesNum,
      nombreMes: i.nombreMes,
      producto: i.producto,
      concepto: i.concepto,
      stockInicial: i.stockInicial,
      stockReal: i.stockReal,
      ajuste: i.ajuste,
      costoTotal: i.costoTotal,
      unidadesPerdidas: i.unidadesPerdidas,
      unidadesRecuperadas: i.unidadesRecuperadas,
    };
  }

  private sumarTotalesAjustes(items: any[]): DetalleAjustesTotales {
    return (items ?? []).reduce(
      (acc, i) => ({
        costoTotal: acc.costoTotal + (Number(i.costoTotal) || 0),
      }),
      { costoTotal: 0 },
    );
  }

  normalizarDetalleAjustesResponse(
    data: any,
    page: { limite: number; offset: number },
    q?: string,
  ): DetalleAjustesPagina {
    const qNorm = q != null ? String(q).trim().toLowerCase() : '';

    if (Array.isArray(data)) {
      let all = data.map((i) => this.mapDetalleAjusteItem(i));
      if (qNorm) {
        all = all.filter(
          (i) =>
            String(i.producto ?? '')
              .toLowerCase()
              .includes(qNorm) ||
            String(i.concepto ?? '')
              .toLowerCase()
              .includes(qNorm),
        );
      }
      const limite = page.limite > 0 ? page.limite : all.length;
      const offset = page.offset >= 0 ? page.offset : 0;
      const items =
        page.limite > 0 ? all.slice(offset, offset + limite) : all;
      return {
        items,
        total: all.length,
        limite,
        offset,
        totales: this.sumarTotalesAjustes(all),
      };
    }

    const rawItems = data?.items ?? data?.data ?? [];
    const items = (Array.isArray(rawItems) ? rawItems : []).map((i: any) =>
      this.mapDetalleAjusteItem(i),
    );
    const totalesApi = data?.totales;
    const totales =
      totalesApi && typeof totalesApi === 'object'
        ? { costoTotal: Number(totalesApi.costoTotal) || 0 }
        : this.sumarTotalesAjustes(items);

    return {
      items,
      total: Number(data?.total) >= 0 ? Number(data.total) : items.length,
      limite: Number(data?.limite) >= 0 ? Number(data.limite) : page.limite,
      offset: Number(data?.offset) >= 0 ? Number(data.offset) : page.offset,
      totales,
    };
  }

  private buildDetalleAjustesUrl(
    filtros: any,
    opts?: { limite?: number; offset?: number; q?: string },
  ): string {
    const api = this.analytics.baseUrl;
    const { pMov } = this.buildQueryPaths(filtros);
    let url = `${api}/api/inventario/ajustes/detalle?${pMov}`;
    if (opts?.limite != null) {
      url += `&limite=${opts.limite}`;
    }
    if (opts?.offset != null) {
      url += `&offset=${opts.offset}`;
    }
    const q = opts?.q != null ? String(opts.q).trim() : '';
    if (q) {
      url += `&q=${encodeURIComponent(q)}`;
    }
    return url;
  }

  obtenerDetalleAjustesPagina(
    filtros: any = {},
    page: DetalleAjustesPageParams,
  ): Observable<DetalleAjustesPagina> {
    const limite = page.limite > 0 ? page.limite : 50;
    const offset = page.offset >= 0 ? page.offset : 0;
    const url = this.buildDetalleAjustesUrl(filtros, {
      limite,
      offset,
      q: page.q,
    });
    return this.analytics.getSafe(url).pipe(
      map((data) =>
        this.normalizarDetalleAjustesResponse(
          data,
          { limite, offset },
          page.q,
        ),
      ),
      catchError((err) => {
        console.error('Error loading /api/inventario/ajustes/detalle (página):', err);
        return of({
          items: [],
          total: 0,
          limite,
          offset,
          totales: { costoTotal: 0 },
        });
      }),
    );
  }

  obtenerDetalleAjustesCompleto(
    filtros: any = {},
    opts: { q?: string } = {},
  ): Observable<DetalleAjustesPagina> {
    const url = this.buildDetalleAjustesUrl(filtros, { q: opts.q });
    return this.analytics.getSafe(url).pipe(
      map((data) =>
        this.normalizarDetalleAjustesResponse(
          data,
          { limite: 0, offset: 0 },
          opts.q,
        ),
      ),
      catchError((err) => {
        console.error('Error loading /api/inventario/ajustes/detalle (completo):', err);
        return of({
          items: [],
          total: 0,
          limite: 0,
          offset: 0,
          totales: { costoTotal: 0 },
        });
      }),
    );
  }

  private sumarTotalesEs(items: any[]): DetalleEsTotales {
    return (items ?? []).reduce(
      (acc, i) => ({
        entradas: acc.entradas + (Number(i.entradas) || 0),
        valorEntradas: acc.valorEntradas + (Number(i.valorEntradas) || 0),
        salidas: acc.salidas + (Number(i.salidas) || 0),
        valorSalidas: acc.valorSalidas + (Number(i.valorSalidas) || 0),
      }),
      { entradas: 0, valorEntradas: 0, salidas: 0, valorSalidas: 0 },
    );
  }

  normalizarDetalleEsResponse(
    data: any,
    page: { limite: number; offset: number },
    q?: string,
  ): DetalleEsPagina {
    const qNorm = q != null ? String(q).trim().toLowerCase() : '';

    if (Array.isArray(data)) {
      let all = data.map((i) => this.mapEsDetalleItem(i));
      if (qNorm) {
        all = all.filter(
          (i) =>
            String(i.producto ?? '')
              .toLowerCase()
              .includes(qNorm) ||
            String(i.concepto ?? '')
              .toLowerCase()
              .includes(qNorm) ||
            String(i.referencia ?? '')
              .toLowerCase()
              .includes(qNorm),
        );
      }
      const limite = page.limite > 0 ? page.limite : all.length;
      const offset = page.offset >= 0 ? page.offset : 0;
      const items =
        page.limite > 0 ? all.slice(offset, offset + limite) : all;
      return {
        items,
        total: all.length,
        limite,
        offset,
        totales: this.sumarTotalesEs(all),
      };
    }

    const rawItems = data?.items ?? data?.data ?? [];
    const items = (Array.isArray(rawItems) ? rawItems : []).map((i: any) =>
      this.mapEsDetalleItem(i),
    );
    const totalesApi = data?.totales;
    const totales =
      totalesApi && typeof totalesApi === 'object'
        ? {
            entradas: Number(totalesApi.entradas) || 0,
            valorEntradas: Number(totalesApi.valorEntradas) || 0,
            salidas: Number(totalesApi.salidas) || 0,
            valorSalidas: Number(totalesApi.valorSalidas) || 0,
          }
        : this.sumarTotalesEs(items);

    return {
      items,
      total: Number(data?.total) >= 0 ? Number(data.total) : items.length,
      limite: Number(data?.limite) >= 0 ? Number(data.limite) : page.limite,
      offset: Number(data?.offset) >= 0 ? Number(data.offset) : page.offset,
      totales,
    };
  }

  private buildDetalleEsUrl(
    filtros: any,
    opts?: { limite?: number; offset?: number; q?: string },
  ): string {
    const api = this.analytics.baseUrl;
    const { pMov } = this.buildQueryPaths(filtros);
    let url = `${api}/api/inventario/es/detalle?${pMov}`;
    if (opts?.limite != null) {
      url += `&limite=${opts.limite}`;
    }
    if (opts?.offset != null) {
      url += `&offset=${opts.offset}`;
    }
    const q = opts?.q != null ? String(opts.q).trim() : '';
    if (q) {
      url += `&q=${encodeURIComponent(q)}`;
    }
    return url;
  }

  obtenerDetalleEsPagina(
    filtros: any = {},
    page: DetalleEsPageParams,
  ): Observable<DetalleEsPagina> {
    const limite = page.limite > 0 ? page.limite : 50;
    const offset = page.offset >= 0 ? page.offset : 0;
    const url = this.buildDetalleEsUrl(filtros, {
      limite,
      offset,
      q: page.q,
    });
    return this.analytics.getSafe(url).pipe(
      map((data) =>
        this.normalizarDetalleEsResponse(data, { limite, offset }, page.q),
      ),
      catchError((err) => {
        console.error('Error loading /api/inventario/es/detalle (página):', err);
        return of({
          items: [],
          total: 0,
          limite,
          offset,
          totales: {
            entradas: 0,
            valorEntradas: 0,
            salidas: 0,
            valorSalidas: 0,
          },
        });
      }),
    );
  }

  obtenerDetalleEsCompleto(
    filtros: any = {},
    opts: { q?: string } = {},
  ): Observable<DetalleEsPagina> {
    const url = this.buildDetalleEsUrl(filtros, { q: opts.q });
    return this.analytics.getSafe(url).pipe(
      map((data) =>
        this.normalizarDetalleEsResponse(
          data,
          { limite: 0, offset: 0 },
          opts.q,
        ),
      ),
      catchError((err) => {
        console.error('Error loading /api/inventario/es/detalle (completo):', err);
        return of({
          items: [],
          total: 0,
          limite: 0,
          offset: 0,
          totales: {
            entradas: 0,
            valorEntradas: 0,
            salidas: 0,
            valorSalidas: 0,
          },
        });
      }),
    );
  }

  private mapearInventarioCritico(raw: {
    cards: any;
    porCategoria: any;
  }): Record<string, unknown> {
    const { cards, porCategoria } = raw;
    return {
      metricasInventario: {
        productosEnStock: cards?.productosEnStock ?? 0,
        promedioInvertido: cards?.promedioInvertido ?? 0,
        ventasEsperadas: cards?.ventasEsperadas ?? 0,
        utilidadEsperada: cards?.utilidadEsperada ?? 0,
      },
      stockPorCategoriaConfig: {
        type: 'bar',
        horizontal: true,
        showXAxisLabels: false,
        isCurrency: false,
        colors: ['#7CABFF'],
        labels: (porCategoria ?? []).filter((i: any) => i.stockUnidades > 0).map((i: any) => i.categoria),
        data: (porCategoria ?? []).filter((i: any) => i.stockUnidades > 0).map((i: any) => i.stockUnidades),
      },
    };
  }

  /**
   * Filas del grid «Detalle entradas y salidas por producto».
   * Ajusta claves si el JSON de Go difiere (snake_case, etc.).
   */
  private mapEsDetalleItem(i: any) {
    const mesNum =
      i.mes != null && !Number.isNaN(Number(i.mes)) ? Number(i.mes) : null;
    let mesLabel = '';
    if (i.nombreMes != null && String(i.nombreMes).trim() !== '') {
      mesLabel = String(i.nombreMes).trim();
    } else if (mesNum != null && mesNum >= 1 && mesNum <= 12) {
      mesLabel = InventarioDashboardDataService.MESES_ES[mesNum - 1];
    } else if (i.fecha != null && String(i.fecha).trim() !== '') {
      mesLabel = String(i.fecha).trim();
    }
    const anio = i.anio ?? i.ano ?? i.year;
    const fecha =
      mesLabel && anio != null && String(anio).trim() !== ''
        ? `${mesLabel} ${anio}`
        : mesLabel;

    return {
      fecha,
      mes: mesNum,
      producto: i.producto != null ? String(i.producto) : '',
      concepto: i.concepto != null ? String(i.concepto) : '',
      referencia:
        i.referencia != null
          ? String(i.referencia)
          : i.correlativo != null
            ? String(i.correlativo)
            : '',
      entradas: i.entradas ?? i.entradasUnidades ?? null,
      valorEntradas: i.valorEntradas ?? i.valor_entradas ?? null,
      salidas: i.salidas ?? i.salidasUnidades ?? null,
      valorSalidas: i.valorSalidas ?? i.valor_salidas ?? null,
    };
  }

  private mapearInventarioPesado(raw: {
    esCards: any;
    esPorMes: any;
    ajustesCards: any;
  }): Record<string, unknown> {
    const { esCards, esPorMes, ajustesCards } = raw;
    return {
      entradasSalidas: {
        productosEnStock: esCards?.productosEnStock ?? 0,
        entradas: esCards?.entradas ?? 0,
        salidas: esCards?.salidas ?? 0,
        utilidadEsperada: esCards?.utilidadEsperada ?? 0,
      },
      entradasSalidasPorMesConfig: this.buildEntradasSalidasPorMesChart(esPorMes),
      ajustes: {
        productosEnStock: ajustesCards?.productosEnStock ?? 0,
        unidadesPerdidas: ajustesCards?.unidadesPerdidas ?? 0,
        unidadesRecuperadas: ajustesCards?.unidadesRecuperadas ?? 0,
        montoTotalRecuperado: ajustesCards?.montoRecuperado ?? 0,
      },
    };
  }

  /**
   * Snapshot (cards, categoría, por-mes) + cards de movimientos/ajustes.
   * Detalles (productos, E/S, ajustes) se cargan aparte con paginación.
   */
  obtenerInventarioProgresivo(filtros: any = {}): Observable<any> {
    const api = this.analytics.baseUrl;
    const { pSnap, pMov } = this.buildQueryPaths(filtros);
    const safe = (path: string) => this.analytics.getSafe(`${api}${path}`);

    const cards$ = safe(`/api/inventario/cards?${pSnap}`).pipe(
      map(data => ({
        metricasInventario: {
          productosEnStock: data?.productosEnStock ?? 0,
          promedioInvertido: data?.promedioInvertido ?? 0,
          ventasEsperadas: data?.ventasEsperadas ?? 0,
          utilidadEsperada: data?.utilidadEsperada ?? 0,
        }
      })),
      catchError(err => {
        console.error('Error loading /api/inventario/cards:', err);
        return of({ metricasInventario: { productosEnStock: 0, promedioInvertido: 0, ventasEsperadas: 0, utilidadEsperada: 0 } });
      })
    );

    const porCategoria$ = safe(`/api/inventario/por-categoria?${pSnap}`).pipe(
      map(data => ({
        stockPorCategoriaConfig: {
          type: 'bar',
          horizontal: true,
          showXAxisLabels: false,
          isCurrency: false,
          colors: ['#7CABFF'],
          labels: (data ?? []).filter((i: any) => i.stockUnidades > 0).map((i: any) => i.categoria),
          data: (data ?? []).filter((i: any) => i.stockUnidades > 0).map((i: any) => i.stockUnidades),
        }
      })),
      catchError(err => {
        console.error('Error loading /api/inventario/por-categoria:', err);
        return of({ stockPorCategoriaConfig: { type: 'bar', horizontal: true, isCurrency: false, colors: ['#7CABFF'], labels: [], data: [] } });
      })
    );

    const esCards$ = safe(`/api/inventario/es/cards?${pMov}`).pipe(
      map(data => ({
        entradasSalidas: {
          productosEnStock: data?.productosEnStock ?? 0,
          entradas: data?.entradas ?? 0,
          salidas: data?.salidas ?? 0,
          utilidadEsperada: data?.utilidadEsperada ?? 0,
        }
      })),
      catchError(err => {
        console.error('Error loading /api/inventario/es/cards:', err);
        return of({ entradasSalidas: { productosEnStock: 0, entradas: 0, salidas: 0, utilidadEsperada: 0 } });
      })
    );

    const esPorMes$ = safe(`/api/inventario/es/por-mes?${pMov}`).pipe(
      map(data => ({
        entradasSalidasPorMesConfig: this.buildEntradasSalidasPorMesChart(data)
      })),
      catchError(err => {
        console.error('Error loading /api/inventario/es/por-mes:', err);
        return of({ entradasSalidasPorMesConfig: this.buildEntradasSalidasPorMesChart([]) });
      })
    );

    const ajustesCards$ = safe(`/api/inventario/ajustes/cards?${pMov}`).pipe(
      map(data => ({
        ajustes: {
          productosEnStock: data?.productosEnStock ?? 0,
          unidadesPerdidas: data?.unidadesPerdidas ?? 0,
          unidadesRecuperadas: data?.unidadesRecuperadas ?? 0,
          montoTotalRecuperado: data?.montoRecuperado ?? 0,
        }
      })),
      catchError(err => {
        console.error('Error loading /api/inventario/ajustes/cards:', err);
        return of({ ajustes: { productosEnStock: 0, unidadesPerdidas: 0, unidadesRecuperadas: 0, montoTotalRecuperado: 0 } });
      })
    );

    const deepMergeScan = (acc: any, curr: any) => {
      const result = { ...acc };
      for (const key of Object.keys(curr)) {
        if (curr[key] && typeof curr[key] === 'object' && !Array.isArray(curr[key])) {
          result[key] = { ...acc[key], ...curr[key] };
        } else {
          result[key] = curr[key];
        }
      }
      return result;
    };

    return merge(
      cards$,
      porCategoria$,
      esCards$,
      esPorMes$,
      ajustesCards$
    ).pipe(
      scan(deepMergeScan, {})
    );
  }

  obtenerInventario(filtros: any = {}): Observable<any> {
    const api = this.analytics.baseUrl;
    const { pSnap, pMov } = this.buildQueryPaths(filtros);
    const safe = (path: string) => this.analytics.getSafe(`${api}${path}`);

    return forkJoin({
      cards: safe(`/api/inventario/cards?${pSnap}`),
      porCategoria: safe(`/api/inventario/por-categoria?${pSnap}`),
      esCards: safe(`/api/inventario/es/cards?${pMov}`),
      esPorMes: safe(`/api/inventario/es/por-mes?${pMov}`),
      ajustesCards: safe(`/api/inventario/ajustes/cards?${pMov}`),
    }).pipe(
      map((all) => ({
        ...this.mapearInventarioCritico({
          cards: all.cards,
          porCategoria: all.porCategoria,
        }),
        ...this.mapearInventarioPesado({
          esCards: all.esCards,
          esPorMes: all.esPorMes,
          ajustesCards: all.ajustesCards,
        }),
      })),
    );
  }
}
