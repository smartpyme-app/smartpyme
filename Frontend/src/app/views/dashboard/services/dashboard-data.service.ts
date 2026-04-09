import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { ResultadosDashboardDataService } from './resultados-dashboard-data.service';
import { VentasDashboardDataService } from './ventas-dashboard-data.service';
import { GastosDashboardDataService } from './gastos-dashboard-data.service';
import { CuentasDashboardDataService } from './cuentas-dashboard-data.service';
import { InventarioDashboardDataService } from './inventario-dashboard-data.service';

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

  obtenerVentasProgresivo(filtros: any): Observable<any> {
    return this.ventasData.obtenerVentasProgresivo(filtros);
  }

  obtenerVentas(filtros: any): Observable<any> {
    return this.ventasData.obtenerVentas(filtros);
  }

  obtenerGastosProgresivo(filtros: any): Observable<any> {
    return this.gastosData.obtenerGastosProgresivo(filtros);
  }

  obtenerGastos(filtros: any): Observable<any> {
    return this.gastosData.obtenerGastos(filtros);
  }

  obtenerCuentasProgresivo(filtros: any): Observable<any> {
    return this.cuentasData.obtenerCuentasProgresivo(filtros);
  }

  obtenerCuentas(filtros: any): Observable<any> {
    return this.cuentasData.obtenerCuentas(filtros);
  }

  obtenerInventarioProgresivo(filtros: any): Observable<any> {
    return this.inventarioData.obtenerInventarioProgresivo(filtros);
  }

  obtenerInventario(filtros: any): Observable<any> {
    return this.inventarioData.obtenerInventario(filtros);
  }
}
