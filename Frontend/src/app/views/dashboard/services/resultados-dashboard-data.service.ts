import { Injectable } from '@angular/core';
import { Observable, forkJoin } from 'rxjs';
import { map, catchError, switchMap, startWith } from 'rxjs/operators';
import { DashboardAnalyticsApiService } from './dashboard-analytics-api.service';

@Injectable({
  providedIn: 'root',
})
export class ResultadosDashboardDataService {
  constructor(private analytics: DashboardAnalyticsApiService) {}

  private mapearResultadosCritico(raw: {
    cards: any;
    porMes: any;
    cxc: any;
    cxp: any;
  }): Record<string, unknown> {
    const { cards, porMes, cxc, cxp } = raw;
    const ventasTotales = cards?.ventasTotalesConIVA ?? 0;
    const gastosTotales = cards?.gastosTotalesConIVA ?? 0;
    const resultados = cards?.resultados ?? 0;
    const margen = cards?.margen ?? 0;
    const cxcTotal = cards?.cuentasPorCobrar ?? 0;
    const cxpTotal = cards?.cuentasPorPagar ?? 0;

    return {
      metrics: [
        {
          title: 'Ventas totales',
          value: ventasTotales,
          type: 'currency',
          icon: 'trending-up',
          color: '#28a745',
        },
        {
          title: 'Gastos totales',
          value: gastosTotales,
          type: 'currency',
          icon: 'trending-down',
          color: '#dc3545',
        },
        {
          title: 'Resultados',
          value: resultados,
          type: 'currency',
          icon: 'dollar-sign',
          color: resultados >= 0 ? '#28a745' : '#dc3545',
        },
        {
          title: 'Margen',
          value: margen,
          type: 'percentage',
          icon: 'percent',
          color: '#007bff',
        },
      ],
      ventasGastosConfig: {
        type: 'bar',
        labels: (porMes ?? []).map((f: any) => f.anioMes || f.mes),
        data: [
          {
            name: 'Ventas',
            data: (porMes ?? []).map((f: any) => f.ventasConIva || 0),
          },
          {
            name: 'Gastos',
            data: (porMes ?? []).map((f: any) => f.egresosConIva || 0),
          },
        ],
      },
      cuentasPorCobrar: (cxc ?? []).map((i: any) => ({
        name: i.name,
        amount: i.amount,
      })),
      cuentasPorPagar: (cxp ?? []).map((i: any) => ({
        name: i.name,
        amount: i.amount,
      })),
      ventasTotalesConIVA: ventasTotales,
      gastosTotalesConIVA: gastosTotales,
      resultados: resultados,
      margen: margen,
      cuentasPorCobrarTotal: cxcTotal,
      cuentasPorPagarTotal: cxpTotal,
    };
  }

  private mapearResultadosPesado(raw: {
    cashflow: any;
    cashflowVentas: any;
    cashflowGastos: any;
    cxc30: any;
    cxp30: any;
  }): Record<string, unknown> {
    const { cashflow, cashflowVentas, cashflowGastos, cxc30, cxp30 } = raw;
    return {
      cashflow: {
        ventas: cashflowVentas || [],
        gastos: cashflowGastos || [],
        ingresosTotales: cashflow?.ingresosPercibidos || 0,
        egresosTotales: cashflow?.egresosRealizados || 0,
      },
      cuentas30: {
        cobrar: cxc30 || [],
        pagar: cxp30 || [],
      },
    };
  }

  obtenerResultadosProgresivo(filtros: any = {}): Observable<any> {
    const api = this.analytics.baseUrl;
    const p = this.analytics.params(filtros);
    const pAnual = this.analytics.paramsAnual(filtros);
    const safe = (path: string) => this.analytics.getSafe(`${api}${path}`);

    const critico$ = forkJoin({
      cards: safe(`/api/resultados/cards?${pAnual}`),
      porMes: safe(`/api/resultados/ventas-gastos-mes?${p}`),
      cxc: safe(`/api/resultados/cxc-clientes?${pAnual}&limite=10`),
      cxp: safe(`/api/resultados/cxp-proveedores?${pAnual}&limite=10`),
    }).pipe(map((r) => this.mapearResultadosCritico(r)));

    const pesado$ = forkJoin({
      cashflow: safe(`/api/resultados/cashflow?${p}`),
      cashflowVentas: safe(`/api/resultados/cashflow-ventas-detalle?${p}`),
      cashflowGastos: safe(`/api/resultados/cashflow-gastos-detalle?${p}`),
      cxc30: safe(`/api/resultados/cxc-30dias?${pAnual}`),
      cxp30: safe(`/api/resultados/cxp-30dias?${pAnual}`),
    }).pipe(map((r) => this.mapearResultadosPesado(r)));

    return critico$.pipe(
      switchMap((c) =>
        pesado$.pipe(
          map((p) => ({ ...c, ...p })),
          startWith(c),
        ),
      ),
    );
  }

  obtenerResultados(filtros: any = {}): Observable<any> {
    const api = this.analytics.baseUrl;
    const p = this.analytics.params(filtros);
    const pAnual = this.analytics.paramsAnual(filtros);
    const safe = (path: string) => this.analytics.getSafe(`${api}${path}`);

    return forkJoin({
      cards: safe(`/api/resultados/cards?${pAnual}`),
      porMes: safe(`/api/resultados/ventas-gastos-mes?${p}`),
      cxc: safe(`/api/resultados/cxc-clientes?${pAnual}&limite=10`),
      cxp: safe(`/api/resultados/cxp-proveedores?${pAnual}&limite=10`),
      cashflow: safe(`/api/resultados/cashflow?${p}`),
      cashflowVentas: safe(`/api/resultados/cashflow-ventas-detalle?${p}`),
      cashflowGastos: safe(`/api/resultados/cashflow-gastos-detalle?${p}`),
      cxc30: safe(`/api/resultados/cxc-30dias?${pAnual}`),
      cxp30: safe(`/api/resultados/cxp-30dias?${pAnual}`),
    }).pipe(
      map((all) => ({
        ...this.mapearResultadosCritico({
          cards: all.cards,
          porMes: all.porMes,
          cxc: all.cxc,
          cxp: all.cxp,
        }),
        ...this.mapearResultadosPesado({
          cashflow: all.cashflow,
          cashflowVentas: all.cashflowVentas,
          cashflowGastos: all.cashflowGastos,
          cxc30: all.cxc30,
          cxp30: all.cxp30,
        }),
      })),
    );
  }
}
