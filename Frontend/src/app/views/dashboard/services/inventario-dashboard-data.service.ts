import { Injectable } from '@angular/core';
import { Observable, forkJoin } from 'rxjs';
import { map, switchMap, startWith } from 'rxjs/operators';
import { DashboardAnalyticsApiService } from './dashboard-analytics-api.service';

@Injectable({
  providedIn: 'root',
})
export class InventarioDashboardDataService {
  constructor(private analytics: DashboardAnalyticsApiService) {}

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

  private mapearInventarioPesado(raw: {
    esCards: any;
    esPorMes: any;
    ajustesCards: any;
    ajustesDetalle: any;
  }): Record<string, unknown> {
    const { esCards, esPorMes, ajustesCards, ajustesDetalle } = raw;
    return {
      entradasSalidas: {
        productosEnStock: esCards?.productosEnStock ?? 0,
        entradas: esCards?.entradas ?? 0,
        salidas: esCards?.salidas ?? 0,
        utilidadEsperada: esCards?.utilidadEsperada ?? 0,
      },
      entradasSalidasPorMesConfig: {
        type: 'bar',
        labels: (esPorMes ?? []).map((i: any) => i.nombreMes),
        data: (esPorMes ?? []).map((i: any) => i.entradasUnidades),
        dataExtra: (esPorMes ?? []).map((i: any) => i.salidasUnidades),
      },
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
          ajustesCards: all.ajustesCards,
          ajustesDetalle: all.ajustesDetalle,
        }),
      })),
    );
  }
}
