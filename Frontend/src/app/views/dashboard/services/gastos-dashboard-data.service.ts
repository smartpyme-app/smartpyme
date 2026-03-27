import { Injectable } from '@angular/core';
import { Observable, forkJoin } from 'rxjs';
import { map } from 'rxjs/operators';
import { DashboardAnalyticsApiService } from './dashboard-analytics-api.service';

@Injectable({
  providedIn: 'root',
})
export class GastosDashboardDataService {
  constructor(private analytics: DashboardAnalyticsApiService) {}

  obtenerGastos(filtros: any = {}): Observable<any> {
    const api = this.analytics.baseUrl;
    const p = this.analytics.params(filtros);
    const pTipo = filtros?.tipoGasto ? `&tipo_gasto=${filtros.tipoGasto}` : '';
    const pEstado = filtros?.estadoGasto
      ? `&estado_gasto=${filtros.estadoGasto}`
      : '';
    const pProveedor = filtros?.proveedor
      ? `&proveedor=${filtros.proveedor}`
      : '';
    const pExtra = `${pTipo}${pEstado}${pProveedor}`;
    const g = (path: string) =>
      this.analytics.get(`${api}${path}`);

    return forkJoin({
      cards: g(`/api/gastos/cards?${p}${pExtra}`),
      porMes: g(`/api/gastos/por-mes?${p}${pExtra}`),
      vsPresupuesto: g(`/api/gastos/vs-presupuesto?${p}${pExtra}`),
      vsAnioAnterior: g(`/api/gastos/vs-anio-anterior?${p}${pExtra}`),
      porCategoria: g(`/api/gastos/por-categoria?${p}${pExtra}`),
      porConcepto: g(`/api/gastos/por-concepto?${p}${pExtra}`),
      porFormaPago: g(`/api/gastos/por-forma-pago?${p}${pExtra}`),
      porProveedor: g(`/api/gastos/por-proveedor?${p}${pExtra}&limite=10`),
      detalle: g(`/api/gastos/detalle?${p}${pExtra}`),
    }).pipe(
      map(
        ({
          cards,
          porMes,
          vsPresupuesto,
          vsAnioAnterior,
          porCategoria,
          porConcepto,
          porFormaPago,
          porProveedor,
          detalle,
        }) => ({
          metricasGastos: {
            gastosConIVA: cards?.gastosTotales ?? 0,
            gastosSinIVA: 0,
            gastosMesAnterior: cards?.gastosMesAnterior ?? 0,
            variacionGastos: cards?.variacion ?? 0,
            aumentoCostosPorcentaje: cards?.variacionPct ?? 0,
          },
          gastosPorMesConfig: {
            type: 'line',
            labels: (porMes ?? []).map((f: any) => f.anioMes),
            data: (porMes ?? []).map((f: any) => f.gastosConIva),
          },
          gastosVsPresupuestoConfig: {
            type: 'bar',
            labels: (vsPresupuesto ?? []).map((f: any) => f.anioMes),
            data: (vsPresupuesto ?? []).map((f: any) => f.gastosConIva),
            dataExtra: (vsPresupuesto ?? []).map((f: any) => f.presupuesto),
          },
          gastosVsAnioAnteriorConfig: {
            type: 'bar',
            labels: (vsAnioAnterior ?? []).map((f: any) => f.anioMes),
            data: (vsAnioAnterior ?? []).map((f: any) => f.anioActual),
            dataExtra: (vsAnioAnterior ?? []).map((f: any) => f.anioAnterior),
          },
          gastosPorCategoriaConfig: {
            type: 'bar',
            horizontal: true,
            labels: (porCategoria ?? []).map((i: any) => i.name),
            data: (porCategoria ?? []).map((i: any) => i.amount),
          },
          gastosPorConceptoConfig: {
            type: 'bar',
            labels: (porConcepto ?? []).map((i: any) => i.name),
            data: (porConcepto ?? []).map((i: any) => i.amount),
          },
          gastosPorFormaPagoConfig: {
            type: 'doughnut',
            labels: (porFormaPago ?? []).map((i: any) => i.name),
            data: (porFormaPago ?? []).map((i: any) => i.amount),
          },
          gastosPorProveedor: (porProveedor ?? []).map((i: any) => ({
            name: i.name,
            amount: i.amount,
          })),
          detalleGastos: (detalle ?? []).map((i: any) => ({
            fecha: i.fecha,
            proveedor: i.proveedor,
            concepto: i.concepto,
            documento: i.doc,
            correlativo: i.correlativo,
            gastosConIVA: i.gastosConIva,
          })),
        }),
      ),
    );
  }
}
