import { Injectable } from '@angular/core';
import { Observable, forkJoin } from 'rxjs';
import { map } from 'rxjs/operators';
import { DashboardAnalyticsApiService } from './dashboard-analytics-api.service';

@Injectable({
  providedIn: 'root',
})
export class InventarioDashboardDataService {
  constructor(private analytics: DashboardAnalyticsApiService) {}

  obtenerInventario(filtros: any = {}): Observable<any> {
    const api = this.analytics.baseUrl;
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
    const g = (path: string) => this.analytics.get(`${api}${path}`);

    return forkJoin({
      cards: g(`/api/inventario/cards?${pSnap}`),
      porCategoria: g(`/api/inventario/por-categoria?${pSnap}`),
      detalleProductos: g(`/api/inventario/detalle-productos?${pSnap}`),
      esCards: g(`/api/inventario/es/cards?${pMov}`),
      esPorMes: g(`/api/inventario/es/por-mes?${pMov}`),
      ajustesCards: g(`/api/inventario/ajustes/cards?${pMov}`),
      ajustesDetalle: g(`/api/inventario/ajustes/detalle?${pMov}`),
    }).pipe(
      map(
        ({
          cards,
          porCategoria,
          detalleProductos,
          esCards,
          esPorMes,
          ajustesCards,
          ajustesDetalle,
        }) => ({
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
        }),
      ),
    );
  }
}
