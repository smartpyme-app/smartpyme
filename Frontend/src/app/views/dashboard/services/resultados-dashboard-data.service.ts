import { Injectable } from '@angular/core';
import { Observable, forkJoin, merge, of } from 'rxjs';
import { map, catchError, scan } from 'rxjs/operators';
import { DashboardAnalyticsApiService } from './dashboard-analytics-api.service';

export interface CashflowVentasTotales {
  monto: number;
}

export interface CashflowVentasPagina {
  items: any[];
  total: number;
  limite: number;
  offset: number;
  totales: CashflowVentasTotales;
}

export interface CashflowVentasPageParams {
  limite: number;
  offset: number;
  q?: string;
}

export interface CashflowGastosTotales {
  monto: number;
}

export interface CashflowGastosPagina {
  items: any[];
  total: number;
  limite: number;
  offset: number;
  totales: CashflowGastosTotales;
}

export interface CashflowGastosPageParams {
  limite: number;
  offset: number;
  q?: string;
}

export interface AbonosCxcTotales {
  monto: number;
}

export interface AbonosCxcPagina {
  items: any[];
  total: number;
  limite: number;
  offset: number;
  totales: AbonosCxcTotales;
}

export interface AbonosCxcPageParams {
  limite: number;
  offset: number;
  q?: string;
}

export interface AbonosCxpTotales {
  monto: number;
}

export interface AbonosCxpPagina {
  items: any[];
  total: number;
  limite: number;
  offset: number;
  totales: AbonosCxpTotales;
}

export interface AbonosCxpPageParams {
  limite: number;
  offset: number;
  q?: string;
}

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
  }): Record<string, unknown> {
    const { cashflow } = raw;
    return {
      cashflow: {
        // ventas/gastos detalle: lazy
        ventas: [],
        gastos: [],
        ingresosTotales: cashflow?.ingresosPercibidos || 0,
        egresosTotales: cashflow?.egresosRealizados || 0,
      },
      abonos: {
        // cxc/cxp: lazy
        cxc: [],
        cxp: [],
      },
    };
  }

  private mapCashflowVentaItem(i: any) {
    return {
      cliente: i.cliente ?? '-',
      factura: i.factura ?? '',
      monto: Number(i.monto) || 0,
    };
  }

  private sumarTotalesCashflowVentas(items: any[]): CashflowVentasTotales {
    return {
      monto: (items ?? []).reduce(
        (acc, i) => acc + (Number(i.monto) || 0),
        0,
      ),
    };
  }

  normalizarCashflowVentasResponse(
    data: any,
    page: { limite: number; offset: number },
    q?: string,
  ): CashflowVentasPagina {
    const qNorm = q != null ? String(q).trim().toLowerCase() : '';

    if (Array.isArray(data)) {
      let all = data.map((i) => this.mapCashflowVentaItem(i));
      if (qNorm) {
        all = all.filter(
          (i) =>
            String(i.cliente ?? '')
              .toLowerCase()
              .includes(qNorm) ||
            String(i.factura ?? '')
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
        totales: this.sumarTotalesCashflowVentas(all),
      };
    }

    const rawItems = data?.items ?? data?.data ?? [];
    const items = (Array.isArray(rawItems) ? rawItems : []).map((i: any) =>
      this.mapCashflowVentaItem(i),
    );
    const totalesApi = data?.totales;
    const totales =
      totalesApi && typeof totalesApi === 'object'
        ? { monto: Number(totalesApi.monto) || 0 }
        : this.sumarTotalesCashflowVentas(items);

    return {
      items,
      total: Number(data?.total) >= 0 ? Number(data.total) : items.length,
      limite: Number(data?.limite) >= 0 ? Number(data.limite) : page.limite,
      offset: Number(data?.offset) >= 0 ? Number(data.offset) : page.offset,
      totales,
    };
  }

  private buildCashflowVentasUrl(
    filtros: any,
    opts?: { limite?: number; offset?: number; q?: string },
  ): string {
    const api = this.analytics.baseUrl;
    const p = this.analytics.params(filtros);
    let url = `${api}/api/resultados/cashflow-ventas-detalle?${p}`;
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

  obtenerCashflowVentasPagina(
    filtros: any = {},
    page: CashflowVentasPageParams,
  ): Observable<CashflowVentasPagina> {
    const limite = page.limite > 0 ? page.limite : 50;
    const offset = page.offset >= 0 ? page.offset : 0;
    const url = this.buildCashflowVentasUrl(filtros, {
      limite,
      offset,
      q: page.q,
    });
    return this.analytics.getSafe(url).pipe(
      map((data) =>
        this.normalizarCashflowVentasResponse(
          data,
          { limite, offset },
          page.q,
        ),
      ),
      catchError((err) => {
        console.error(
          'Error loading /api/resultados/cashflow-ventas-detalle (página):',
          err,
        );
        return of({
          items: [],
          total: 0,
          limite,
          offset,
          totales: { monto: 0 },
        });
      }),
    );
  }

  obtenerCashflowVentasCompleto(
    filtros: any = {},
    opts: { q?: string } = {},
  ): Observable<CashflowVentasPagina> {
    const url = this.buildCashflowVentasUrl(filtros, { q: opts.q });
    return this.analytics.getSafe(url).pipe(
      map((data) =>
        this.normalizarCashflowVentasResponse(
          data,
          { limite: 0, offset: 0 },
          opts.q,
        ),
      ),
      catchError((err) => {
        console.error(
          'Error loading /api/resultados/cashflow-ventas-detalle (completo):',
          err,
        );
        return of({
          items: [],
          total: 0,
          limite: 0,
          offset: 0,
          totales: { monto: 0 },
        });
      }),
    );
  }

  private mapCashflowGastoItem(i: any) {
    return {
      proveedor: i.proveedor ?? '-',
      factura: i.factura ?? '',
      monto: Number(i.monto) || 0,
    };
  }

  private sumarTotalesCashflowGastos(items: any[]): CashflowGastosTotales {
    return {
      monto: (items ?? []).reduce(
        (acc, i) => acc + (Number(i.monto) || 0),
        0,
      ),
    };
  }

  normalizarCashflowGastosResponse(
    data: any,
    page: { limite: number; offset: number },
    q?: string,
  ): CashflowGastosPagina {
    const qNorm = q != null ? String(q).trim().toLowerCase() : '';

    if (Array.isArray(data)) {
      let all = data.map((i) => this.mapCashflowGastoItem(i));
      if (qNorm) {
        all = all.filter(
          (i) =>
            String(i.proveedor ?? '')
              .toLowerCase()
              .includes(qNorm) ||
            String(i.factura ?? '')
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
        totales: this.sumarTotalesCashflowGastos(all),
      };
    }

    const rawItems = data?.items ?? data?.data ?? [];
    const items = (Array.isArray(rawItems) ? rawItems : []).map((i: any) =>
      this.mapCashflowGastoItem(i),
    );
    const totalesApi = data?.totales;
    const totales =
      totalesApi && typeof totalesApi === 'object'
        ? { monto: Number(totalesApi.monto) || 0 }
        : this.sumarTotalesCashflowGastos(items);

    return {
      items,
      total: Number(data?.total) >= 0 ? Number(data.total) : items.length,
      limite: Number(data?.limite) >= 0 ? Number(data.limite) : page.limite,
      offset: Number(data?.offset) >= 0 ? Number(data.offset) : page.offset,
      totales,
    };
  }

  private buildCashflowGastosUrl(
    filtros: any,
    opts?: { limite?: number; offset?: number; q?: string },
  ): string {
    const api = this.analytics.baseUrl;
    const p = this.analytics.params(filtros);
    let url = `${api}/api/resultados/cashflow-gastos-detalle?${p}`;
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

  obtenerCashflowGastosPagina(
    filtros: any = {},
    page: CashflowGastosPageParams,
  ): Observable<CashflowGastosPagina> {
    const limite = page.limite > 0 ? page.limite : 50;
    const offset = page.offset >= 0 ? page.offset : 0;
    const url = this.buildCashflowGastosUrl(filtros, {
      limite,
      offset,
      q: page.q,
    });
    return this.analytics.getSafe(url).pipe(
      map((data) =>
        this.normalizarCashflowGastosResponse(
          data,
          { limite, offset },
          page.q,
        ),
      ),
      catchError((err) => {
        console.error(
          'Error loading /api/resultados/cashflow-gastos-detalle (página):',
          err,
        );
        return of({
          items: [],
          total: 0,
          limite,
          offset,
          totales: { monto: 0 },
        });
      }),
    );
  }

  obtenerCashflowGastosCompleto(
    filtros: any = {},
    opts: { q?: string } = {},
  ): Observable<CashflowGastosPagina> {
    const url = this.buildCashflowGastosUrl(filtros, { q: opts.q });
    return this.analytics.getSafe(url).pipe(
      map((data) =>
        this.normalizarCashflowGastosResponse(
          data,
          { limite: 0, offset: 0 },
          opts.q,
        ),
      ),
      catchError((err) => {
        console.error(
          'Error loading /api/resultados/cashflow-gastos-detalle (completo):',
          err,
        );
        return of({
          items: [],
          total: 0,
          limite: 0,
          offset: 0,
          totales: { monto: 0 },
        });
      }),
    );
  }

  private mapAbonosCxcItem(i: any) {
    return {
      factura: i.factura ?? '',
      cliente: i.cliente ?? '-',
      vence: i.vence ?? '',
      diasVencimiento: Number(i.diasVencimiento) || 0,
      monto: Number(i.monto) || 0,
    };
  }

  private sumarTotalesAbonosCxc(items: any[]): AbonosCxcTotales {
    return {
      monto: (items ?? []).reduce(
        (acc, i) => acc + (Number(i.monto) || 0),
        0,
      ),
    };
  }

  normalizarAbonosCxcResponse(
    data: any,
    page: { limite: number; offset: number },
    q?: string,
  ): AbonosCxcPagina {
    const qNorm = q != null ? String(q).trim().toLowerCase() : '';

    if (Array.isArray(data)) {
      let all = data.map((i) => this.mapAbonosCxcItem(i));
      if (qNorm) {
        all = all.filter(
          (i) =>
            String(i.cliente ?? '')
              .toLowerCase()
              .includes(qNorm) ||
            String(i.factura ?? '')
              .toLowerCase()
              .includes(qNorm) ||
            String(i.vence ?? '')
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
        totales: this.sumarTotalesAbonosCxc(all),
      };
    }

    const rawItems = data?.items ?? data?.data ?? [];
    const items = (Array.isArray(rawItems) ? rawItems : []).map((i: any) =>
      this.mapAbonosCxcItem(i),
    );
    const totalesApi = data?.totales;
    const totales =
      totalesApi && typeof totalesApi === 'object'
        ? { monto: Number(totalesApi.monto) || 0 }
        : this.sumarTotalesAbonosCxc(items);

    return {
      items,
      total: Number(data?.total) >= 0 ? Number(data.total) : items.length,
      limite: Number(data?.limite) >= 0 ? Number(data.limite) : page.limite,
      offset: Number(data?.offset) >= 0 ? Number(data.offset) : page.offset,
      totales,
    };
  }

  private buildAbonosCxcUrl(
    filtros: any,
    opts?: { limite?: number; offset?: number; q?: string },
  ): string {
    const api = this.analytics.baseUrl;
    const p = this.analytics.params(filtros);
    let url = `${api}/api/resultados/abonos-cxc?${p}`;
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

  obtenerAbonosCxcPagina(
    filtros: any = {},
    page: AbonosCxcPageParams,
  ): Observable<AbonosCxcPagina> {
    const limite = page.limite > 0 ? page.limite : 50;
    const offset = page.offset >= 0 ? page.offset : 0;
    const url = this.buildAbonosCxcUrl(filtros, {
      limite,
      offset,
      q: page.q,
    });
    return this.analytics.getSafe(url).pipe(
      map((data) =>
        this.normalizarAbonosCxcResponse(data, { limite, offset }, page.q),
      ),
      catchError((err) => {
        console.error('Error loading /api/resultados/abonos-cxc (página):', err);
        return of({
          items: [],
          total: 0,
          limite,
          offset,
          totales: { monto: 0 },
        });
      }),
    );
  }

  obtenerAbonosCxcCompleto(
    filtros: any = {},
    opts: { q?: string } = {},
  ): Observable<AbonosCxcPagina> {
    const url = this.buildAbonosCxcUrl(filtros, { q: opts.q });
    return this.analytics.getSafe(url).pipe(
      map((data) =>
        this.normalizarAbonosCxcResponse(
          data,
          { limite: 0, offset: 0 },
          opts.q,
        ),
      ),
      catchError((err) => {
        console.error(
          'Error loading /api/resultados/abonos-cxc (completo):',
          err,
        );
        return of({
          items: [],
          total: 0,
          limite: 0,
          offset: 0,
          totales: { monto: 0 },
        });
      }),
    );
  }

  private mapAbonosCxpItem(i: any) {
    return {
      factura: i.factura ?? '',
      proveedor: i.proveedor ?? '-',
      vence: i.vence ?? '',
      diasVencimiento: Number(i.diasVencimiento) || 0,
      monto: Number(i.monto) || 0,
    };
  }

  private sumarTotalesAbonosCxp(items: any[]): AbonosCxpTotales {
    return {
      monto: (items ?? []).reduce(
        (acc, i) => acc + (Number(i.monto) || 0),
        0,
      ),
    };
  }

  normalizarAbonosCxpResponse(
    data: any,
    page: { limite: number; offset: number },
    q?: string,
  ): AbonosCxpPagina {
    const qNorm = q != null ? String(q).trim().toLowerCase() : '';

    if (Array.isArray(data)) {
      let all = data.map((i) => this.mapAbonosCxpItem(i));
      if (qNorm) {
        all = all.filter(
          (i) =>
            String(i.proveedor ?? '')
              .toLowerCase()
              .includes(qNorm) ||
            String(i.factura ?? '')
              .toLowerCase()
              .includes(qNorm) ||
            String(i.vence ?? '')
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
        totales: this.sumarTotalesAbonosCxp(all),
      };
    }

    const rawItems = data?.items ?? data?.data ?? [];
    const items = (Array.isArray(rawItems) ? rawItems : []).map((i: any) =>
      this.mapAbonosCxpItem(i),
    );
    const totalesApi = data?.totales;
    const totales =
      totalesApi && typeof totalesApi === 'object'
        ? { monto: Number(totalesApi.monto) || 0 }
        : this.sumarTotalesAbonosCxp(items);

    return {
      items,
      total: Number(data?.total) >= 0 ? Number(data.total) : items.length,
      limite: Number(data?.limite) >= 0 ? Number(data.limite) : page.limite,
      offset: Number(data?.offset) >= 0 ? Number(data.offset) : page.offset,
      totales,
    };
  }

  private buildAbonosCxpUrl(
    filtros: any,
    opts?: { limite?: number; offset?: number; q?: string },
  ): string {
    const api = this.analytics.baseUrl;
    const p = this.analytics.params(filtros);
    let url = `${api}/api/resultados/abonos-cxp?${p}`;
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

  obtenerAbonosCxpPagina(
    filtros: any = {},
    page: AbonosCxpPageParams,
  ): Observable<AbonosCxpPagina> {
    const limite = page.limite > 0 ? page.limite : 50;
    const offset = page.offset >= 0 ? page.offset : 0;
    const url = this.buildAbonosCxpUrl(filtros, {
      limite,
      offset,
      q: page.q,
    });
    return this.analytics.getSafe(url).pipe(
      map((data) =>
        this.normalizarAbonosCxpResponse(data, { limite, offset }, page.q),
      ),
      catchError((err) => {
        console.error('Error loading /api/resultados/abonos-cxp (página):', err);
        return of({
          items: [],
          total: 0,
          limite,
          offset,
          totales: { monto: 0 },
        });
      }),
    );
  }

  obtenerAbonosCxpCompleto(
    filtros: any = {},
    opts: { q?: string } = {},
  ): Observable<AbonosCxpPagina> {
    const url = this.buildAbonosCxpUrl(filtros, { q: opts.q });
    return this.analytics.getSafe(url).pipe(
      map((data) =>
        this.normalizarAbonosCxpResponse(
          data,
          { limite: 0, offset: 0 },
          opts.q,
        ),
      ),
      catchError((err) => {
        console.error(
          'Error loading /api/resultados/abonos-cxp (completo):',
          err,
        );
        return of({
          items: [],
          total: 0,
          limite: 0,
          offset: 0,
          totales: { monto: 0 },
        });
      }),
    );
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
        },
        // Shell abonos (cxc/cxp lazy).
        abonos: { cxc: [], cxp: [] },
      })),
      catchError(err => {
        console.error('Error loading /api/resultados/cashflow:', err);
        return of({
          cashflow: { ingresosTotales: 0, egresosTotales: 0 },
          abonos: { cxc: [], cxp: [] },
        });
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
      cashflow$
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
        }),
      })),
    );
  }
}
