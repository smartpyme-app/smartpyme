import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import {
  ResultadosDashboardDataService,
  CashflowVentasPagina,
  CashflowVentasPageParams,
  CashflowGastosPagina,
  CashflowGastosPageParams,
  AbonosCxcPagina,
  AbonosCxcPageParams,
  AbonosCxpPagina,
  AbonosCxpPageParams,
} from './resultados-dashboard-data.service';
import {
  VentasDashboardDataService,
  DetalleVentasProductoPagina,
  DetalleVentasProductoPageParams,
  DetalleVentasClientePagina,
  DetalleVentasClientePageParams,
} from './ventas-dashboard-data.service';
import {
  GastosDashboardDataService,
  DetalleGastosPagina,
  DetalleGastosPageParams,
} from './gastos-dashboard-data.service';
import {
  CuentasDashboardDataService,
  DetalleCxcPagina,
  DetalleCxcPageParams,
  DetalleCxpPagina,
  DetalleCxpPageParams,
} from './cuentas-dashboard-data.service';
import {
  InventarioDashboardDataService,
  DetalleProductosPagina,
  DetalleProductosPageParams,
  DetalleAjustesPagina,
  DetalleAjustesPageParams,
  DetalleEsPagina,
  DetalleEsPageParams,
} from './inventario-dashboard-data.service';

/**
 * Punto de entrada único para el dashboard: delega en servicios por sección.
 * La lógica HTTP y mapeos viven en `*-dashboard-data.service` y `DashboardAnalyticsApiService`.
 */
@Injectable({
  providedIn: 'root',
})
export class DashboardDataService {
  constructor(
    private resultadosData: ResultadosDashboardDataService,
    private ventasData: VentasDashboardDataService,
    private gastosData: GastosDashboardDataService,
    private cuentasData: CuentasDashboardDataService,
    private inventarioData: InventarioDashboardDataService,
  ) {}

  obtenerDatosPorFiltro(filtros: any): Observable<any> {
    const seccion = filtros?.seccion ?? 'Resultados';
    switch (seccion) {
      case 'Ventas':
        return this.ventasData.obtenerVentasProgresivo(filtros);
      case 'Gastos':
        return this.gastosData.obtenerGastosProgresivo(filtros);
      case 'Control de cuentas':
        return this.cuentasData.obtenerCuentasProgresivo(filtros);
      case 'Inventario':
        return this.inventarioData.obtenerInventarioProgresivo(filtros);
      default:
        return this.resultadosData.obtenerResultadosProgresivo(filtros);
    }
  }

  obtenerResultadosProgresivo(filtros: any): Observable<any> {
    return this.resultadosData.obtenerResultadosProgresivo(filtros);
  }

  obtenerResultados(filtros: any): Observable<any> {
    return this.resultadosData.obtenerResultados(filtros);
  }

  obtenerCashflowVentasPagina(
    filtros: any,
    page: CashflowVentasPageParams,
  ): Observable<CashflowVentasPagina> {
    return this.resultadosData.obtenerCashflowVentasPagina(filtros, page);
  }

  obtenerCashflowVentasCompleto(
    filtros: any,
    opts: { q?: string } = {},
  ): Observable<CashflowVentasPagina> {
    return this.resultadosData.obtenerCashflowVentasCompleto(filtros, opts);
  }

  obtenerCashflowGastosPagina(
    filtros: any,
    page: CashflowGastosPageParams,
  ): Observable<CashflowGastosPagina> {
    return this.resultadosData.obtenerCashflowGastosPagina(filtros, page);
  }

  obtenerCashflowGastosCompleto(
    filtros: any,
    opts: { q?: string } = {},
  ): Observable<CashflowGastosPagina> {
    return this.resultadosData.obtenerCashflowGastosCompleto(filtros, opts);
  }

  obtenerAbonosCxcPagina(
    filtros: any,
    page: AbonosCxcPageParams,
  ): Observable<AbonosCxcPagina> {
    return this.resultadosData.obtenerAbonosCxcPagina(filtros, page);
  }

  obtenerAbonosCxcCompleto(
    filtros: any,
    opts: { q?: string } = {},
  ): Observable<AbonosCxcPagina> {
    return this.resultadosData.obtenerAbonosCxcCompleto(filtros, opts);
  }

  obtenerAbonosCxpPagina(
    filtros: any,
    page: AbonosCxpPageParams,
  ): Observable<AbonosCxpPagina> {
    return this.resultadosData.obtenerAbonosCxpPagina(filtros, page);
  }

  obtenerAbonosCxpCompleto(
    filtros: any,
    opts: { q?: string } = {},
  ): Observable<AbonosCxpPagina> {
    return this.resultadosData.obtenerAbonosCxpCompleto(filtros, opts);
  }

  obtenerVentasProgresivo(filtros: any): Observable<any> {
    return this.ventasData.obtenerVentasProgresivo(filtros);
  }

  obtenerVentas(filtros: any): Observable<any> {
    return this.ventasData.obtenerVentas(filtros);
  }

  obtenerDetalleVentasProductoPagina(
    filtros: any,
    page: DetalleVentasProductoPageParams,
  ): Observable<DetalleVentasProductoPagina> {
    return this.ventasData.obtenerDetalleVentasProductoPagina(filtros, page);
  }

  obtenerDetalleVentasProductoCompleto(
    filtros: any,
    opts: { q?: string } = {},
  ): Observable<DetalleVentasProductoPagina> {
    return this.ventasData.obtenerDetalleVentasProductoCompleto(filtros, opts);
  }

  obtenerDetalleVentasClientePagina(
    filtros: any,
    page: DetalleVentasClientePageParams,
  ): Observable<DetalleVentasClientePagina> {
    return this.ventasData.obtenerDetalleVentasClientePagina(filtros, page);
  }

  obtenerDetalleVentasClienteCompleto(
    filtros: any,
    opts: { q?: string } = {},
  ): Observable<DetalleVentasClientePagina> {
    return this.ventasData.obtenerDetalleVentasClienteCompleto(filtros, opts);
  }

  obtenerGastosProgresivo(filtros: any): Observable<any> {
    return this.gastosData.obtenerGastosProgresivo(filtros);
  }

  obtenerGastos(filtros: any): Observable<any> {
    return this.gastosData.obtenerGastos(filtros);
  }

  obtenerDetalleGastosPagina(
    filtros: any,
    page: DetalleGastosPageParams,
  ): Observable<DetalleGastosPagina> {
    return this.gastosData.obtenerDetalleGastosPagina(filtros, page);
  }

  obtenerDetalleGastosCompleto(
    filtros: any,
    opts: { q?: string } = {},
  ): Observable<DetalleGastosPagina> {
    return this.gastosData.obtenerDetalleGastosCompleto(filtros, opts);
  }

  obtenerCuentasProgresivo(filtros: any): Observable<any> {
    return this.cuentasData.obtenerCuentasProgresivo(filtros);
  }

  obtenerCuentas(filtros: any): Observable<any> {
    return this.cuentasData.obtenerCuentas(filtros);
  }

  obtenerDetalleCxcPagina(
    filtros: any,
    page: DetalleCxcPageParams,
  ): Observable<DetalleCxcPagina> {
    return this.cuentasData.obtenerDetalleCxcPagina(filtros, page);
  }

  obtenerDetalleCxcCompleto(
    filtros: any,
    opts: { q?: string } = {},
  ): Observable<DetalleCxcPagina> {
    return this.cuentasData.obtenerDetalleCxcCompleto(filtros, opts);
  }

  obtenerDetalleCxpPagina(
    filtros: any,
    page: DetalleCxpPageParams,
  ): Observable<DetalleCxpPagina> {
    return this.cuentasData.obtenerDetalleCxpPagina(filtros, page);
  }

  obtenerDetalleCxpCompleto(
    filtros: any,
    opts: { q?: string } = {},
  ): Observable<DetalleCxpPagina> {
    return this.cuentasData.obtenerDetalleCxpCompleto(filtros, opts);
  }

  obtenerInventarioProgresivo(filtros: any): Observable<any> {
    return this.inventarioData.obtenerInventarioProgresivo(filtros);
  }

  obtenerInventario(filtros: any): Observable<any> {
    return this.inventarioData.obtenerInventario(filtros);
  }

  obtenerDetalleProductosPagina(
    filtros: any,
    page: DetalleProductosPageParams,
  ): Observable<DetalleProductosPagina> {
    return this.inventarioData.obtenerDetalleProductosPagina(filtros, page);
  }

  obtenerDetalleProductosCompleto(
    filtros: any,
    opts: { q?: string } = {},
  ): Observable<DetalleProductosPagina> {
    return this.inventarioData.obtenerDetalleProductosCompleto(filtros, opts);
  }

  obtenerDetalleAjustesPagina(
    filtros: any,
    page: DetalleAjustesPageParams,
  ): Observable<DetalleAjustesPagina> {
    return this.inventarioData.obtenerDetalleAjustesPagina(filtros, page);
  }

  obtenerDetalleAjustesCompleto(
    filtros: any,
    opts: { q?: string } = {},
  ): Observable<DetalleAjustesPagina> {
    return this.inventarioData.obtenerDetalleAjustesCompleto(filtros, opts);
  }

  obtenerDetalleEsPagina(
    filtros: any,
    page: DetalleEsPageParams,
  ): Observable<DetalleEsPagina> {
    return this.inventarioData.obtenerDetalleEsPagina(filtros, page);
  }

  obtenerDetalleEsCompleto(
    filtros: any,
    opts: { q?: string } = {},
  ): Observable<DetalleEsPagina> {
    return this.inventarioData.obtenerDetalleEsCompleto(filtros, opts);
  }

  private filtrosUI: { [seccion: string]: any } = {};

  guardarFiltrosUI(seccion: string, filtros: any): void {
    this.filtrosUI[seccion] = filtros;
  }

  obtenerFiltrosUI(seccion: string): any {
    return this.filtrosUI[seccion];
  }
}
