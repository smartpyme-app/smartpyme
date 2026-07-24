import { Injectable } from '@angular/core';
import { Observable, forkJoin, merge, of } from 'rxjs';
import { map, scan, catchError } from 'rxjs/operators';
import { DashboardAnalyticsApiService } from './dashboard-analytics-api.service';

export interface DetalleGastosTotales {
  gastosConIVA: number;
}

export interface DetalleGastosPagina {
  items: any[];
  total: number;
  limite: number;
  offset: number;
  totales: DetalleGastosTotales;
}

export interface DetalleGastosPageParams {
  limite: number;
  offset: number;
  q?: string;
}

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
        barLabelExactUnder1000: true,
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
        colors: ['#F19447', '#d3d3d3'],
        barLabelExactUnder1000: true,
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
        colors: ['#F19447', '#d3d3d3'],
        barLabelExactUnder1000: true,
      },
    };
  }

  private mapDetalleGastosItem(i: any) {
    return {
      fecha: i.fecha,
      proveedor: i.proveedor,
      concepto: i.concepto,
      documento: i.doc ?? i.documento,
      correlativo: i.correlativo,
      gastosConIVA: i.gastosConIva ?? i.gastosConIVA,
    };
  }

  private sumarTotalesDetalleGastos(items: any[]): DetalleGastosTotales {
    return (items ?? []).reduce(
      (acc, i) => ({
        gastosConIVA: acc.gastosConIVA + (Number(i.gastosConIVA) || 0),
      }),
      { gastosConIVA: 0 },
    );
  }

  normalizarDetalleGastosResponse(
    data: any,
    page: { limite: number; offset: number },
    q?: string,
  ): DetalleGastosPagina {
    const qNorm = q != null ? String(q).trim().toLowerCase() : '';

    if (Array.isArray(data)) {
      let all = data.map((i) => this.mapDetalleGastosItem(i));
      if (qNorm) {
        all = all.filter(
          (i) =>
            String(i.proveedor ?? '')
              .toLowerCase()
              .includes(qNorm) ||
            String(i.concepto ?? '')
              .toLowerCase()
              .includes(qNorm) ||
            String(i.documento ?? '')
              .toLowerCase()
              .includes(qNorm) ||
            String(i.correlativo ?? '')
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
        totales: this.sumarTotalesDetalleGastos(all),
      };
    }

    const rawItems = data?.items ?? data?.data ?? [];
    const items = (Array.isArray(rawItems) ? rawItems : []).map((i: any) =>
      this.mapDetalleGastosItem(i),
    );
    const totalesApi = data?.totales;
    const totales =
      totalesApi && typeof totalesApi === 'object'
        ? {
            gastosConIVA:
              Number(totalesApi.gastosConIVA ?? totalesApi.gastosConIva) || 0,
          }
        : this.sumarTotalesDetalleGastos(items);

    return {
      items,
      total: Number(data?.total) >= 0 ? Number(data.total) : items.length,
      limite: Number(data?.limite) >= 0 ? Number(data.limite) : page.limite,
      offset: Number(data?.offset) >= 0 ? Number(data.offset) : page.offset,
      totales,
    };
  }

  private buildDetalleGastosUrl(
    filtros: any,
    opts?: { limite?: number; offset?: number; q?: string },
  ): string {
    const api = this.analytics.baseUrl;
    const p = this.analytics.params(filtros);
    const pExtra = this.filtrosExtraQuery(filtros);
    let url = `${api}/api/gastos/detalle?${p}${pExtra}`;
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

  obtenerDetalleGastosPagina(
    filtros: any = {},
    page: DetalleGastosPageParams,
  ): Observable<DetalleGastosPagina> {
    const limite = page.limite > 0 ? page.limite : 50;
    const offset = page.offset >= 0 ? page.offset : 0;
    const url = this.buildDetalleGastosUrl(filtros, {
      limite,
      offset,
      q: page.q,
    });
    return this.analytics.getSafe(url).pipe(
      map((data) =>
        this.normalizarDetalleGastosResponse(data, { limite, offset }, page.q),
      ),
      catchError((err) => {
        console.error('Error loading /api/gastos/detalle (página):', err);
        return of({
          items: [],
          total: 0,
          limite,
          offset,
          totales: { gastosConIVA: 0 },
        });
      }),
    );
  }

  obtenerDetalleGastosCompleto(
    filtros: any = {},
    opts: { q?: string } = {},
  ): Observable<DetalleGastosPagina> {
    const url = this.buildDetalleGastosUrl(filtros, { q: opts.q });
    return this.analytics.getSafe(url).pipe(
      map((data) =>
        this.normalizarDetalleGastosResponse(
          data,
          { limite: 0, offset: 0 },
          opts.q,
        ),
      ),
      catchError((err) => {
        console.error('Error loading /api/gastos/detalle (completo):', err);
        return of({
          items: [],
          total: 0,
          limite: 0,
          offset: 0,
          totales: { gastosConIVA: 0 },
        });
      }),
    );
  }

  private mapearGastosPesado(raw: {
    porCategoria: any;
    porConcepto: any;
    porFormaPago: any;
    porProveedor: any;
    gastosDetallados?: any;
  }): Record<string, unknown> {
    const { porCategoria, porConcepto, porFormaPago, porProveedor, gastosDetallados } =
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
        barLabelExactUnder1000: true,
      },
      gastosPorConceptoConfig: {
        type: 'bar',
        collapseExcessBars: true,
        initialVisibleBars: 5,
        labels: (porConcepto ?? []).map((i: any) => i.name),
        data: (porConcepto ?? []).map((i: any) => i.amount),
        colors: ['#F19447'],
        graduatedOpacity: true,
        barLabelExactUnder1000: true,
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
    const safe = (path: string) => this.analytics.getSafe(`${api}${path}`);

    const cards$ = safe(`/api/gastos/cards?${p}${pExtra}`).pipe(
      map(c => ({
        metricasGastos: {
          gastosConIVA: Number(c?.gastosTotales ?? c?.gastos_totales ?? c?.total ?? 0),
          gastosMesActual: Number(c?.gastosMesActual ?? c?.gastos_mes_actual ?? c?.gastosSinIva ?? c?.gastos_sin_iva ?? 0),
          gastosMesAnterior: Number(c?.gastosMesAnterior ?? c?.gastos_mes_anterior ?? 0),
          variacionGastos: Number(c?.variacion ?? c?.variacion_gastos ?? 0),
          aumentoCostosPorcentaje: Number(c?.variacionPct ?? c?.variacion_pct ?? 0),
        }
      })),
      catchError(err => {
        console.error('Error loading /api/gastos/cards:', err);
        return of({ metricasGastos: { gastosConIVA: 0, gastosMesActual: 0, gastosMesAnterior: 0, variacionGastos: 0, aumentoCostosPorcentaje: 0 } });
      })
    );

    const porMes$ = safe(`/api/gastos/por-mes?${p}${pExtra}`).pipe(
      map(porMes => ({
        gastosPorMesConfig: {
          type: 'line',
          showArea: false,
          smooth: false,
          showYAxisLabels: false,
          showXAxisLine: false,
          labels: (porMes ?? []).map((f: any) => this.obtenerNombreMes(f.anioMes)),
          data: (porMes ?? []).map((f: any) => f.gastosConIva),
          colors: ['#F19447'],
          barLabelExactUnder1000: true,
        }
      })),
      catchError(err => {
        console.error('Error loading /api/gastos/por-mes:', err);
        return of({ gastosPorMesConfig: { type: 'line', labels: [], data: [], colors: ['#F19447'] } });
      })
    );

    const vsPresupuesto$ = safe(`/api/gastos/vs-presupuesto?${p}${pExtra}`).pipe(
      map(vsPresupuesto => ({
        gastosVsPresupuestoConfig: {
          type: 'bar',
          labels: (vsPresupuesto ?? []).map((f: any) => this.obtenerNombreMes(f.anioMes)),
          data: [
            { name: 'Gastos totales', data: (vsPresupuesto ?? []).map((f: any) => f.gastosConIva || 0) },
            { name: 'Presupuestado', data: (vsPresupuesto ?? []).map((f: any) => f.presupuesto || 0) }
          ],
          dataExtra: (vsPresupuesto ?? []).map((f: any) => f.presupuesto),
          colors: ['#F19447', '#d3d3d3'],
          barLabelExactUnder1000: true,
        }
      })),
      catchError(err => {
        console.error('Error loading /api/gastos/vs-presupuesto:', err);
        return of({ gastosVsPresupuestoConfig: { type: 'bar', labels: [], data: [], colors: ['#F19447', '#d3d3d3'] } });
      })
    );

    const vsAnioAnterior$ = safe(`/api/gastos/vs-anio-anterior?${p}${pExtra}`).pipe(
      map(vsAnioAnterior => ({
        gastosVsAnioAnteriorConfig: {
          type: 'bar',
          labels: (vsAnioAnterior ?? []).map((f: any) => this.obtenerNombreMes(f.anioMes)),
          data: [
            { name: 'Año actual', data: (vsAnioAnterior ?? []).map((f: any) => f.anioActual || 0) },
            { name: 'Año anterior', data: (vsAnioAnterior ?? []).map((f: any) => f.anioAnterior || 0) }
          ],
          dataExtra: (vsAnioAnterior ?? []).map((f: any) => f.anioAnterior),
          colors: ['#F19447', '#d3d3d3'],
          barLabelExactUnder1000: true,
        }
      })),
      catchError(err => {
        console.error('Error loading /api/gastos/vs-anio-anterior:', err);
        return of({ gastosVsAnioAnteriorConfig: { type: 'bar', labels: [], data: [], colors: ['#F19447', '#d3d3d3'] } });
      })
    );

    const porCategoria$ = safe(`/api/gastos/por-categoria?${p}${pExtra}`).pipe(
      map(porCategoria => ({
        gastosPorCategoriaConfig: {
          type: 'bar',
          horizontal: true,
          labels: (porCategoria ?? []).map((i: any) => i.name),
          data: (porCategoria ?? []).map((i: any) => i.amount),
          colors: ['#F19447'],
          showXAxisLabels: false,
          graduatedOpacity: true,
          barLabelExactUnder1000: true,
        }
      })),
      catchError(err => {
        console.error('Error loading /api/gastos/por-categoria:', err);
        return of({ gastosPorCategoriaConfig: { type: 'bar', horizontal: true, labels: [], data: [], colors: ['#F19447'], showXAxisLabels: false } });
      })
    );

    const porConcepto$ = safe(`/api/gastos/por-concepto?${p}${pExtra}`).pipe(
      map(porConcepto => ({
        gastosPorConceptoConfig: {
          type: 'bar',
          collapseExcessBars: true,
          initialVisibleBars: 5,
          labels: (porConcepto ?? []).map((i: any) => i.name),
          data: (porConcepto ?? []).map((i: any) => i.amount),
          colors: ['#F19447'],
          graduatedOpacity: true,
          barLabelExactUnder1000: true,
        }
      })),
      catchError(err => {
        console.error('Error loading /api/gastos/por-concepto:', err);
        return of({
          gastosPorConceptoConfig: {
            type: 'bar',
            collapseExcessBars: true,
            initialVisibleBars: 5,
            labels: [],
            data: [],
            colors: ['#F19447'],
          },
        });
      })
    );

    const porFormaPago$ = safe(`/api/gastos/por-forma-pago?${p}${pExtra}`).pipe(
      map(porFormaPago => ({
        gastosPorFormaPagoConfig: {
          type: 'doughnut',
          labels: (porFormaPago ?? []).map((i: any) => i.name),
          data: (porFormaPago ?? []).map((i: any) => i.amount),
        }
      })),
      catchError(err => {
        console.error('Error loading /api/gastos/por-forma-pago:', err);
        return of({ gastosPorFormaPagoConfig: { type: 'doughnut', labels: [], data: [] } });
      })
    );

    const porProveedor$ = safe(`/api/gastos/por-proveedor?${p}${pExtra}&limite=10`).pipe(
      map(porProveedor => ({
        gastosPorProveedor: (porProveedor ?? []).map((i: any) => ({ name: i.name, amount: i.amount }))
      })),
      catchError(err => {
        console.error('Error loading /api/gastos/por-proveedor:', err);
        return of({ gastosPorProveedor: [] });
      })
    );

    const gastosDetallados$ = safe(`/api/gastos/detalladas?${p}${pExtra}`).pipe(
      map(gastosDetallados => ({
        gastosDetallados: gastosDetallados ?? []
      })),
      catchError(err => {
        console.error('Error loading /api/gastos/detalladas:', err);
        return of({ gastosDetallados: [] });
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
      vsPresupuesto$,
      vsAnioAnterior$,
      porCategoria$,
      porConcepto$,
      porFormaPago$,
      porProveedor$,
      gastosDetallados$
    ).pipe(
      scan(deepMergeScan, {})
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
          gastosDetallados: all.gastosDetallados,
        }),
      })),
    );
  }
}
