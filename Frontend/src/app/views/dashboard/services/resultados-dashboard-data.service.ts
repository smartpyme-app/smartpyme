import { Injectable } from '@angular/core';
import { Observable, forkJoin, merge, of } from 'rxjs';
import { map, catchError, switchMap, startWith, scan } from 'rxjs/operators';
import { DashboardAnalyticsApiService } from './dashboard-analytics-api.service';

@Injectable({
  providedIn: 'root',
})
export class ResultadosDashboardDataService {
  constructor(private analytics: DashboardAnalyticsApiService) { }

  private obtenerNombreMes(val: any): string {
    if (!val) return '';
    const meses = [
      'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
      'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'
    ];

    const valStr = String(val);
    if (valStr.includes('-')) {
      const parts = valStr.split('-');
      const mesNum = parseInt(parts[1], 10);
      if (mesNum >= 1 && mesNum <= 12) {
        return meses[mesNum - 1];
      }
    }

    const num = parseInt(val, 10);
    if (!isNaN(num) && num >= 1 && num <= 12) {
      return meses[num - 1];
    }

    return valStr;
  }

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
        {
          title: 'Cuentas por cobrar',
          value: cxcTotal,
          type: 'currency',
          icon: 'dollar-sign',
          color: '#28a745',
        },
        {
          title: 'Cuentas por pagar',
          value: cxpTotal,
          type: 'currency',
          icon: 'dollar-sign',
          color: '#dc3545',
        },
      ],
      ventasGastosConfig: {
        type: 'bar',
        labels: (porMes ?? []).map((f: any) => this.obtenerNombreMes(f.anioMes || f.mes)),
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
        colors: ['#7CABFF', '#F19447'],
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
    abonosCxc: any;
    abonosCxp: any;
  }): Record<string, unknown> {
    const { cashflow, cashflowVentas, cashflowGastos, abonosCxc, abonosCxp } = raw;
    return {
      cashflow: {
        ventas: cashflowVentas || [],
        gastos: cashflowGastos || [],
        ingresosTotales: cashflow?.ingresosPercibidos || 0,
        egresosTotales: cashflow?.egresosRealizados || 0,
      },
      abonos: {
        cxc: abonosCxc || [],
        cxp: abonosCxp || [],
      },
    };
  }

  obtenerResultadosProgresivo(filtros: any = {}): Observable<any> {
    const api = this.analytics.baseUrl;
    const p = this.analytics.params(filtros);
    const safe = (path: string) => this.analytics.getSafe(`${api}${path}`);

    const cards$ = safe(`/api/resultados/cards?${p}`).pipe(
      map(cards => {
        const ventasTotales = cards?.ventasTotalesConIVA ?? 0;
        const gastosTotales = cards?.gastosTotalesConIVA ?? 0;
        const resultados = cards?.resultados ?? 0;
        const margen = cards?.margen ?? 0;
        const cxcTotal = cards?.cuentasPorCobrar ?? 0;
        const cxpTotal = cards?.cuentasPorPagar ?? 0;
        return {
          metrics: [
            { title: 'Ventas totales', value: ventasTotales, type: 'currency', icon: 'trending-up', color: '#28a745' },
            { title: 'Gastos totales', value: gastosTotales, type: 'currency', icon: 'trending-down', color: '#dc3545' },
            { title: 'Resultados', value: resultados, type: 'currency', icon: 'dollar-sign', color: resultados >= 0 ? '#28a745' : '#dc3545' },
            { title: 'Margen', value: margen, type: 'percentage', icon: 'percent', color: '#007bff' },
            { title: 'Cuentas por cobrar', value: cxcTotal, type: 'currency', icon: 'dollar-sign', color: '#28a745' },
            { title: 'Cuentas por pagar', value: cxpTotal, type: 'currency', icon: 'dollar-sign', color: '#dc3545' },
          ],
          ventasTotalesConIVA: ventasTotales,
          gastosTotalesConIVA: gastosTotales,
          resultados: resultados,
          margen: margen,
          cuentasPorCobrarTotal: cxcTotal,
          cuentasPorPagarTotal: cxpTotal,
        };
      }),
      catchError(err => {
        console.error('Error loading /api/resultados/cards:', err);
        return of({
          metrics: [
            { title: 'Ventas totales', value: 0, type: 'currency', icon: 'trending-up', color: '#28a745' },
            { title: 'Gastos totales', value: 0, type: 'currency', icon: 'trending-down', color: '#dc3545' },
            { title: 'Resultados', value: 0, type: 'currency', icon: 'dollar-sign', color: '#28a745' },
            { title: 'Margen', value: 0, type: 'percentage', icon: 'percent', color: '#007bff' },
            { title: 'Cuentas por cobrar', value: 0, type: 'currency', icon: 'dollar-sign', color: '#28a745' },
            { title: 'Cuentas por pagar', value: 0, type: 'currency', icon: 'dollar-sign', color: '#dc3545' },
          ],
          ventasTotalesConIVA: 0,
          gastosTotalesConIVA: 0,
          resultados: 0,
          margen: 0,
          cuentasPorCobrarTotal: 0,
          cuentasPorPagarTotal: 0,
        });
      })
    );

    const porMes$ = safe(`/api/resultados/ventas-gastos-mes?${p}`).pipe(
      map(porMes => ({
        ventasGastosConfig: {
          type: 'bar',
          labels: (porMes ?? []).map((f: any) => this.obtenerNombreMes(f.anioMes || f.mes)),
          data: [
            { name: 'Ventas', data: (porMes ?? []).map((f: any) => f.ventasConIva || 0) },
            { name: 'Gastos', data: (porMes ?? []).map((f: any) => f.egresosConIva || 0) }
          ],
          colors: ['#7CABFF', '#F19447'],
        }
      })),
      catchError(err => {
        console.error('Error loading /api/resultados/ventas-gastos-mes:', err);
        return of({ ventasGastosConfig: { type: 'bar', labels: [], data: [], colors: ['#7CABFF', '#F19447'] } });
      })
    );

    const cxc$ = safe(`/api/resultados/cxc-clientes?${p}&limite=10`).pipe(
      map(cxc => ({
        cuentasPorCobrar: (cxc ?? []).map((i: any) => ({ name: i.name, amount: i.amount }))
      })),
      catchError(err => {
        console.error('Error loading /api/resultados/cxc-clientes:', err);
        return of({ cuentasPorCobrar: [] });
      })
    );

    const cxp$ = safe(`/api/resultados/cxp-proveedores?${p}&limite=10`).pipe(
      map(cxp => ({
        cuentasPorPagar: (cxp ?? []).map((i: any) => ({ name: i.name, amount: i.amount }))
      })),
      catchError(err => {
        console.error('Error loading /api/resultados/cxp-proveedores:', err);
        return of({ cuentasPorPagar: [] });
      })
    );

    const cashflow$ = safe(`/api/resultados/cashflow?${p}`).pipe(
      map(cashflow => ({
        cashflow: {
          ingresosTotales: cashflow?.ingresosPercibidos || 0,
          egresosTotales: cashflow?.egresosRealizados || 0,
        }
      })),
      catchError(err => {
        console.error('Error loading /api/resultados/cashflow:', err);
        return of({ cashflow: { ingresosTotales: 0, egresosTotales: 0 } });
      })
    );

    const cashflowVentas$ = safe(`/api/resultados/cashflow-ventas-detalle?${p}`).pipe(
      map(cashflowVentas => ({
        cashflow: {
          ventas: cashflowVentas || []
        }
      })),
      catchError(err => {
        console.error('Error loading /api/resultados/cashflow-ventas-detalle:', err);
        return of({ cashflow: { ventas: [] } });
      })
    );

    const cashflowGastos$ = safe(`/api/resultados/cashflow-gastos-detalle?${p}`).pipe(
      map(cashflowGastos => ({
        cashflow: {
          gastos: cashflowGastos || []
        }
      })),
      catchError(err => {
        console.error('Error loading /api/resultados/cashflow-gastos-detalle:', err);
        return of({ cashflow: { gastos: [] } });
      })
    );

    const abonosCxc$ = safe(`/api/resultados/abonos-cxc?${p}`).pipe(
      map(abonosCxc => ({
        abonos: {
          cxc: abonosCxc || []
        }
      })),
      catchError(err => {
        console.error('Error loading /api/resultados/abonos-cxc:', err);
        return of({ abonos: { cxc: [] } });
      })
    );

    const abonosCxp$ = safe(`/api/resultados/abonos-cxp?${p}`).pipe(
      map(abonosCxp => ({
        abonos: {
          cxp: abonosCxp || []
        }
      })),
      catchError(err => {
        console.error('Error loading /api/resultados/abonos-cxp:', err);
        return of({ abonos: { cxp: [] } });
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
      porMes$,
      cxc$,
      cxp$,
      cashflow$,
      cashflowVentas$,
      cashflowGastos$,
      abonosCxc$,
      abonosCxp$
    ).pipe(
      scan(deepMergeScan, {})
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
      abonosCxc: safe(`/api/resultados/abonos-cxc?${p}`),
      abonosCxp: safe(`/api/resultados/abonos-cxp?${p}`),
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
          abonosCxc: all.abonosCxc,
          abonosCxp: all.abonosCxp,
        }),
      })),
    );
  }
}
