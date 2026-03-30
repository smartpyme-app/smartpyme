import { Injectable } from '@angular/core';
import { Observable, forkJoin } from 'rxjs';
import { map, switchMap, startWith } from 'rxjs/operators';
import { DashboardAnalyticsApiService } from './dashboard-analytics-api.service';

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

  private mapearInventarioCritico(raw: {
    cards: any;
    porCategoria: any;
    detalleProductos: any;
  }): Record<string, unknown> {
    const { cards, porCategoria, detalleProductos } = raw;
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
        labels: (porCategoria ?? []).map((i: any) => i.categoria),
        data: (porCategoria ?? []).map((i: any) => i.stockUnidades),
      },
      detalleInventario: (detalleProductos ?? []).map((i: any) => ({
        producto: i.producto,
        stock: i.stock,
        costo: i.costo,
        inversionPromedio: i.inversionPromedio,
        precio: i.precio,
        ventasEsperadas: i.ventasEsperadas,
        utilidadEsperada: i.utilidadEsperada,
      })),
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
    esDetalle: any;
    ajustesCards: any;
    ajustesDetalle: any;
  }): Record<string, unknown> {
    const { esCards, esPorMes, esDetalle, ajustesCards, ajustesDetalle } = raw;
    const listaDetalle = Array.isArray(esDetalle)
      ? esDetalle
      : esDetalle?.items ?? esDetalle?.data ?? [];
    return {
      entradasSalidas: {
        productosEnStock: esCards?.productosEnStock ?? 0,
        entradas: esCards?.entradas ?? 0,
        salidas: esCards?.salidas ?? 0,
        utilidadEsperada: esCards?.utilidadEsperada ?? 0,
      },
      entradasSalidasPorMesConfig: this.buildEntradasSalidasPorMesChart(esPorMes),
      detalleEntradasSalidas: (listaDetalle ?? []).map((row: any) =>
        this.mapEsDetalleItem(row),
      ),
      ajustes: {
        productosEnStock: ajustesCards?.productosEnStock ?? 0,
        unidadesPerdidas: ajustesCards?.unidadesPerdidas ?? 0,
        unidadesRecuperadas: ajustesCards?.unidadesRecuperadas ?? 0,
        montoTotalRecuperado: ajustesCards?.montoRecuperado ?? 0,
      },
      detalleAjustes: (ajustesDetalle ?? []).map((i: any) => ({
        mes: i.mes,
        nombreMes: i.nombreMes,
        producto: i.producto,
        concepto: i.concepto,
        stockInicial: i.stockInicial,
        stockReal: i.stockReal,
        ajuste: i.ajuste,
        costoTotal: i.costoTotal,
        unidadesPerdidas: i.unidadesPerdidas,
        unidadesRecuperadas: i.unidadesRecuperadas,
      })),
    };
  }

  /**
   * Snapshot de stock (cards, categoría, detalle) primero; movimientos y ajustes después.
   */
  obtenerInventarioProgresivo(filtros: any = {}): Observable<any> {
    const api = this.analytics.baseUrl;
    const { pSnap, pMov } = this.buildQueryPaths(filtros);
    const safe = (path: string) => this.analytics.getSafe(`${api}${path}`);

    const critico$ = forkJoin({
      cards: safe(`/api/inventario/cards?${pSnap}`),
      porCategoria: safe(`/api/inventario/por-categoria?${pSnap}`),
      detalleProductos: safe(`/api/inventario/detalle-productos?${pSnap}`),
    }).pipe(map((r) => this.mapearInventarioCritico(r)));

    const pesado$ = forkJoin({
      esCards: safe(`/api/inventario/es/cards?${pMov}`),
      esPorMes: safe(`/api/inventario/es/por-mes?${pMov}`),
      esDetalle: safe(`/api/inventario/es/detalle?${pMov}`),
      ajustesCards: safe(`/api/inventario/ajustes/cards?${pMov}`),
      ajustesDetalle: safe(`/api/inventario/ajustes/detalle?${pMov}`),
    }).pipe(map((r) => this.mapearInventarioPesado(r)));

    return critico$.pipe(
      switchMap((c) =>
        pesado$.pipe(
          map((heavy) => ({ ...c, ...heavy })),
          startWith(c),
        ),
      ),
    );
  }

  obtenerInventario(filtros: any = {}): Observable<any> {
    const api = this.analytics.baseUrl;
    const { pSnap, pMov } = this.buildQueryPaths(filtros);
    const safe = (path: string) => this.analytics.getSafe(`${api}${path}`);

    return forkJoin({
      cards: safe(`/api/inventario/cards?${pSnap}`),
      porCategoria: safe(`/api/inventario/por-categoria?${pSnap}`),
      detalleProductos: safe(`/api/inventario/detalle-productos?${pSnap}`),
      esCards: safe(`/api/inventario/es/cards?${pMov}`),
      esPorMes: safe(`/api/inventario/es/por-mes?${pMov}`),
      esDetalle: safe(`/api/inventario/es/detalle?${pMov}`),
      ajustesCards: safe(`/api/inventario/ajustes/cards?${pMov}`),
      ajustesDetalle: safe(`/api/inventario/ajustes/detalle?${pMov}`),
    }).pipe(
      map((all) => ({
        ...this.mapearInventarioCritico({
          cards: all.cards,
          porCategoria: all.porCategoria,
          detalleProductos: all.detalleProductos,
        }),
        ...this.mapearInventarioPesado({
          esCards: all.esCards,
          esPorMes: all.esPorMes,
          esDetalle: all.esDetalle,
          ajustesCards: all.ajustesCards,
          ajustesDetalle: all.ajustesDetalle,
        }),
      })),
    );
  }
}
