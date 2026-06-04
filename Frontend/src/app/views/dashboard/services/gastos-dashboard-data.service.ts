import { Injectable } from '@angular/core';
import { Observable, forkJoin } from 'rxjs';
import { map, switchMap, startWith } from 'rxjs/operators';
import { DashboardAnalyticsApiService } from './dashboard-analytics-api.service';

@Injectable({
  providedIn: 'root',
})
export class GastosDashboardDataService {
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

  private filtrosExtraQuery(filtros: any): string {
    const pTipo = filtros?.tipoGasto ? `&tipo_gasto=${filtros.tipoGasto}` : '';
    const pEstado = filtros?.estadoGasto
      ? `&estado_gasto=${filtros.estadoGasto}`
      : '';
    const pProveedor = filtros?.proveedor
      ? `&proveedor=${filtros.proveedor}`
      : '';
    return `${pTipo}${pEstado}${pProveedor}`;
  }

  private mapearGastosCritico(raw: {
    cards: any;
    porMes: any;
    vsPresupuesto: any;
    vsAnioAnterior: any;
  }): Record<string, unknown> {
    const { cards, porMes, vsPresupuesto, vsAnioAnterior } = raw;
    const c = cards ?? {};
    return {
      metricasGastos: {
        gastosConIVA: Number(
          c.gastosTotales ?? c.gastos_totales ?? c.total ?? 0,
        ),
        gastosMesActual: Number(
          c.gastosMesActual ??
          c.gastos_mes_actual ??
          c.gastosSinIva ??
          c.gastos_sin_iva ??
          0,
        ),
        gastosMesAnterior: Number(
          c.gastosMesAnterior ?? c.gastos_mes_anterior ?? 0,
        ),
        variacionGastos: Number(c.variacion ?? c.variacion_gastos ?? 0),
        aumentoCostosPorcentaje: Number(
          c.variacionPct ?? c.variacion_pct ?? 0,
        ),
      },
      gastosPorMesConfig: {
        type: 'line',
        showArea: false,
        smooth: false,
        showYAxisLabels: false,
        showXAxisLine: false,
        labels: (porMes ?? []).map((f: any) => this.obtenerNombreMes(f.anioMes)),
        data: (porMes ?? []).map((f: any) => f.gastosConIva),
        colors: ['#F19447'],
      },
      gastosVsPresupuestoConfig: {
        type: 'bar',
        labels: (vsPresupuesto ?? []).map((f: any) => this.obtenerNombreMes(f.anioMes)),
        data: [
          {
            name: 'Gastos totales',
            data: (vsPresupuesto ?? []).map((f: any) => f.gastosConIva || 0)
          },
          {
            name: 'Presupuestado',
            data: (vsPresupuesto ?? []).map((f: any) => f.presupuesto || 0)
          }
        ],
        dataExtra: (vsPresupuesto ?? []).map((f: any) => f.presupuesto),
        colors: ['#F19447', '#d3d3d3']
      },
      gastosVsAnioAnteriorConfig: {
        type: 'bar',
        labels: (vsAnioAnterior ?? []).map((f: any) => this.obtenerNombreMes(f.anioMes)),
        data: [
          {
            name: 'Año actual',
            data: (vsAnioAnterior ?? []).map((f: any) => f.anioActual || 0)
          },
          {
            name: 'Año anterior',
            data: (vsAnioAnterior ?? []).map((f: any) => f.anioAnterior || 0)
          }
        ],
        dataExtra: (vsAnioAnterior ?? []).map((f: any) => f.anioAnterior),
        colors: ['#F19447', '#d3d3d3']
      },
    };
  }

  private mapearGastosPesado(raw: {
    porCategoria: any;
    porConcepto: any;
    porFormaPago: any;
    porProveedor: any;
    detalle: any;
    gastosDetallados?: any;
  }): Record<string, unknown> {
    const { porCategoria, porConcepto, porFormaPago, porProveedor, detalle, gastosDetallados } =
      raw;
    return {
      gastosPorCategoriaConfig: {
        type: 'bar',
        horizontal: true,
        labels: (porCategoria ?? []).map((i: any) => i.name),
        data: (porCategoria ?? []).map((i: any) => i.amount),
        colors: ['#F19447'],
        showXAxisLabels: false,
        graduatedOpacity: true,
      },
      gastosPorConceptoConfig: {
        type: 'bar',
        labels: (porConcepto ?? []).map((i: any) => i.name),
        data: (porConcepto ?? []).map((i: any) => i.amount),
        colors: ['#F19447'],
        graduatedOpacity: true,
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
      gastosDetallados: gastosDetallados ?? [],
    };
  }

  /**
   * Cards y gráficos principales primero; categorías, detalle y proveedores después.
   */
  obtenerGastosProgresivo(filtros: any = {}): Observable<any> {
    const api = this.analytics.baseUrl;
    const p = this.analytics.params(filtros);
    const pExtra = this.filtrosExtraQuery(filtros);
    const safe = (path: string) =>
      this.analytics.getSafe(`${api}${path}`);

    const critico$ = forkJoin({
      cards: safe(`/api/gastos/cards?${p}${pExtra}`),
      porMes: safe(`/api/gastos/por-mes?${p}${pExtra}`),
      vsPresupuesto: safe(`/api/gastos/vs-presupuesto?${p}${pExtra}`),
      vsAnioAnterior: safe(`/api/gastos/vs-anio-anterior?${p}${pExtra}`),
    }).pipe(map((r) => this.mapearGastosCritico(r)));

    const pesado$ = forkJoin({
      porCategoria: safe(`/api/gastos/por-categoria?${p}${pExtra}`),
      porConcepto: safe(`/api/gastos/por-concepto?${p}${pExtra}`),
      porFormaPago: safe(`/api/gastos/por-forma-pago?${p}${pExtra}`),
      porProveedor: safe(
        `/api/gastos/por-proveedor?${p}${pExtra}&limite=10`,
      ),
      detalle: safe(`/api/gastos/detalle?${p}${pExtra}`),
      gastosDetallados: safe(`/api/gastos/detalladas?${p}${pExtra}`),
    }).pipe(map((r) => this.mapearGastosPesado(r)));

    return critico$.pipe(
      switchMap((c) =>
        pesado$.pipe(
          map((heavy) => ({ ...c, ...heavy })),
          startWith(c),
        ),
      ),
    );
  }

  obtenerGastos(filtros: any = {}): Observable<any> {
    const api = this.analytics.baseUrl;
    const p = this.analytics.params(filtros);
    const pExtra = this.filtrosExtraQuery(filtros);
    const safe = (path: string) =>
      this.analytics.getSafe(`${api}${path}`);

    return forkJoin({
      cards: safe(`/api/gastos/cards?${p}${pExtra}`),
      porMes: safe(`/api/gastos/por-mes?${p}${pExtra}`),
      vsPresupuesto: safe(`/api/gastos/vs-presupuesto?${p}${pExtra}`),
      vsAnioAnterior: safe(`/api/gastos/vs-anio-anterior?${p}${pExtra}`),
      porCategoria: safe(`/api/gastos/por-categoria?${p}${pExtra}`),
      porConcepto: safe(`/api/gastos/por-concepto?${p}${pExtra}`),
      porFormaPago: safe(`/api/gastos/por-forma-pago?${p}${pExtra}`),
      porProveedor: safe(
        `/api/gastos/por-proveedor?${p}${pExtra}&limite=10`,
      ),
      detalle: safe(`/api/gastos/detalle?${p}${pExtra}`),
      gastosDetallados: safe(`/api/gastos/detalladas?${p}${pExtra}`),
    }).pipe(
      map((all) => ({
        ...this.mapearGastosCritico({
          cards: all.cards,
          porMes: all.porMes,
          vsPresupuesto: all.vsPresupuesto,
          vsAnioAnterior: all.vsAnioAnterior,
        }),
        ...this.mapearGastosPesado({
          porCategoria: all.porCategoria,
          porConcepto: all.porConcepto,
          porFormaPago: all.porFormaPago,
          porProveedor: all.porProveedor,
          detalle: all.detalle,
          gastosDetallados: all.gastosDetallados,
        }),
      })),
    );
  }
}
