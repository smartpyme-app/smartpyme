import { Injectable } from '@angular/core';
import { Observable, forkJoin, merge, of } from 'rxjs';
import { map, scan, catchError, switchMap } from 'rxjs/operators';
import { DashboardAnalyticsApiService } from './dashboard-analytics-api.service';

export interface DetalleVentasProductoTotales {
  cantidad: number;
  descuento: number;
  ventasSinIVA: number;
  costoTotal: number;
  utilidad: number;
}

export interface DetalleVentasProductoPagina {
  items: any[];
  total: number;
  limite: number;
  offset: number;
  totales: DetalleVentasProductoTotales;
}

export interface DetalleVentasProductoPageParams {
  limite: number;
  offset: number;
  q?: string;
}

export interface DetalleVentasClienteTotales {
  transacciones: number;
  ventasSinIva: number;
  ventasConIva: number;
  dias: number;
  ultimaVenta: string;
}

export interface DetalleVentasClientePagina {
  items: any[];
  total: number;
  limite: number;
  offset: number;
  totales: DetalleVentasClienteTotales;
}

export interface DetalleVentasClientePageParams {
  limite: number;
  offset: number;
  q?: string;
}

@Injectable({
  providedIn: 'root',
})
export class VentasDashboardDataService {
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

  private mapearVentasCritico(raw: {
    cards: any;
    porMes: any;
    vsPresupuesto: any;
    vsAnioAnterior: any;
    anio?: number;
  }): Record<string, unknown> {
    const { cards, porMes, vsPresupuesto, vsAnioAnterior, anio } = raw;
    // Si consultamos el año actual, eliminar meses que aún no han ocurrido.
    const hoy = new Date();
    const anioConsulta = anio ?? hoy.getFullYear();
    const vsAnioFiltrado = (anioConsulta === hoy.getFullYear())
      ? (vsAnioAnterior ?? []).filter((f: any) => {
          const mesNum = parseInt(String(f.anioMes ?? '').split('-')[1] ?? f.anioMes, 10);
          return mesNum <= hoy.getMonth() + 1;
        })
      : (vsAnioAnterior ?? []);
    return {
      metricasVentas: {
        ventasConIVA: cards?.ventasConIva ?? 0,
        ventasSinIVA: cards?.ventasSinIva ?? 0,
        transacciones: cards?.transacciones ?? 0,
        ticketPromedio: cards?.ticketPromedio ?? 0,
      },
      ventasPorMesConfig: {
        type: 'line',
        showArea: false,
        smooth: false,
        showYAxisLabels: false,
        showXAxisLine: false,
        labels: (porMes ?? []).map((f: any) => this.obtenerNombreMes(f.anioMes)),
        data: (porMes ?? []).map((f: any) => f.ventas),
        colors: ['#7CABFF'],
        barLabelExactUnder1000: true,
      },
      ventasVsPresupuestoConfig: {
        type: 'bar',
        labels: (vsPresupuesto ?? []).map((f: any) => this.obtenerNombreMes(f.anioMes)),
        data: [
          {
            name: 'Ventas',
            data: (vsPresupuesto ?? []).map((f: any) => f.ventas || 0),
          },
          {
            name: 'Presupuesto',
            data: (vsPresupuesto ?? []).map((f: any) => f.presupuesto || 0),
          }
        ],
        colors: ['#7CABFF', 'rgba(124, 171, 255, 0.4)'],
        dataExtra: (vsPresupuesto ?? []).map((f: any) => f.presupuesto || 0),
        barLabelExactUnder1000: true,
      },
      ventasVsAnioAnteriorConfig: {
        type: 'bar',
        labels: vsAnioFiltrado.map((f: any) => this.obtenerNombreMes(f.anioMes)),
        data: [
          {
            name: 'Año actual',
            data: vsAnioFiltrado.map((f: any) => f.anioActual || 0),
          },
          {
            name: 'Año anterior',
            data: vsAnioFiltrado.map((f: any) => f.anioAnterior || 0),
          }
        ],
        colors: ['#7CABFF', 'rgba(124, 171, 255, 0.4)'],
        dataExtra: vsAnioFiltrado.map((f: any) => f.anioAnterior || 0),
        barLabelExactUnder1000: true,
      },
    };
  }

  private mapearVentasPesado(raw: {
    porCanal: any;
    porVendedor: any;
    porFormaPago: any;
    porCategoria: any;
    topProductos: any;
    topClientes: any;
  }): Record<string, unknown> {
    const {
      porCanal,
      porVendedor,
      porFormaPago,
      porCategoria,
      topProductos,
      topClientes,
    } = raw;
    return {
      ventasPorCanal: (porCanal ?? []).map((i: any) => ({
        name: i.name,
        amount: i.amount,
      })),
      ventasPorVendedor: (porVendedor ?? []).map((i: any) => ({
        name: i.name,
        amount: i.amount,
      })),
      ventasPorVendedorChartConfig: {
        type: 'bar',
        highlightMaxBar: true,
        collapseExcessBars: true,
        initialVisibleBars: 5,
        colors: ['#7CABFF'],
        labels: (porVendedor ?? []).map((i: any) => i.name),
        data: (porVendedor ?? []).map((i: any) => i.amount),
      },
      ventasPorFormaPagoConfig: {
        type: 'treemap',
        colors: ['#012B67', '#96BCFF', '#5E80BF'],
        labels: (porFormaPago ?? []).map(
          (i: any) => i.formaPago ?? i.name ?? ''
        ),
        data: (porFormaPago ?? []).map((i: any) => ({
          name: i.formaPago ?? i.name ?? '',
          value: Number(i.ventas ?? i.amount ?? 0),
        })),
        porcentajes: (porFormaPago ?? []).map((i: any) => {
          const p = i.porcentaje;
          if (p == null || p === '') return Number.NaN;
          const n = Number(p);
          return Number.isFinite(n) ? n : Number.NaN;
        }),
      },
      ventasPorCategoria: (porCategoria ?? []).map((i: any) => ({
        name: i.categoria,
        amount: i.ventasConIva,
      })),
      topProductosVendidos: (topProductos ?? []).map((i: any) => ({
        name: i.producto,
        amount: i.ventasConIva,
      })),
      topClientes: (topClientes ?? []).map((i: any) => ({
        name: i.name,
        amount: i.amount,
      })),
    };
  }

  private mapDetalleVentasProductoItem(i: any) {
    const ventasSinIVA = i.ventasConIva ?? i.ventasSinIVA ?? 0;
    const descuento = i.descuento ?? 0;
    const costoTotal = i.costoTotal ?? 0;
    const utilidadApi = i.utilidad;
    return {
      idProducto: i.idProducto ?? i.id_producto,
      categoria: i.categoria,
      producto: i.producto,
      formaPago: i.formaPago,
      cantidad: i.cantidad,
      precioUnitario: i.precioUnitario,
      descuento,
      ventasSinIVA,
      costoTotal,
      utilidad:
        utilidadApi != null
          ? utilidadApi
          : Number(ventasSinIVA) - Number(descuento) - Number(costoTotal),
    };
  }

  private sumarTotalesDetalleVentasProducto(
    items: any[],
  ): DetalleVentasProductoTotales {
    const base = (items ?? []).reduce(
      (acc, i) => ({
        cantidad: acc.cantidad + (Number(i.cantidad) || 0),
        descuento: acc.descuento + (Number(i.descuento) || 0),
        ventasSinIVA: acc.ventasSinIVA + (Number(i.ventasSinIVA) || 0),
        costoTotal: acc.costoTotal + (Number(i.costoTotal) || 0),
        utilidad: 0,
      }),
      { cantidad: 0, descuento: 0, ventasSinIVA: 0, costoTotal: 0, utilidad: 0 },
    );
    return {
      ...base,
      utilidad: base.ventasSinIVA - base.descuento - base.costoTotal,
    };
  }

  normalizarDetalleVentasProductoResponse(
    data: any,
    page: { limite: number; offset: number },
    q?: string,
  ): DetalleVentasProductoPagina {
    const qNorm = q != null ? String(q).trim().toLowerCase() : '';

    if (Array.isArray(data)) {
      let all = data.map((i) => this.mapDetalleVentasProductoItem(i));
      if (qNorm) {
        all = all.filter(
          (i) =>
            String(i.producto ?? '')
              .toLowerCase()
              .includes(qNorm) ||
            String(i.categoria ?? '')
              .toLowerCase()
              .includes(qNorm) ||
            String(i.formaPago ?? '')
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
        totales: this.sumarTotalesDetalleVentasProducto(all),
      };
    }

    const rawItems = data?.items ?? data?.data ?? [];
    const items = (Array.isArray(rawItems) ? rawItems : []).map((i: any) =>
      this.mapDetalleVentasProductoItem(i),
    );
    const totalesApi = data?.totales;
    const totales =
      totalesApi && typeof totalesApi === 'object'
        ? {
            cantidad: Number(totalesApi.cantidad) || 0,
            descuento: Number(totalesApi.descuento) || 0,
            ventasSinIVA:
              Number(
                totalesApi.ventasSinIVA ??
                  totalesApi.ventasConIva ??
                  totalesApi.ventasConIVA,
              ) || 0,
            costoTotal: Number(totalesApi.costoTotal) || 0,
            utilidad:
              totalesApi.utilidad != null
                ? Number(totalesApi.utilidad) || 0
                : (Number(
                    totalesApi.ventasSinIVA ??
                      totalesApi.ventasConIva ??
                      totalesApi.ventasConIVA,
                  ) || 0) -
                  (Number(totalesApi.descuento) || 0) -
                  (Number(totalesApi.costoTotal) || 0),
          }
        : this.sumarTotalesDetalleVentasProducto(items);

    return {
      items,
      total: Number(data?.total) >= 0 ? Number(data.total) : items.length,
      limite: Number(data?.limite) >= 0 ? Number(data.limite) : page.limite,
      offset: Number(data?.offset) >= 0 ? Number(data.offset) : page.offset,
      totales,
    };
  }

  private buildDetalleVentasProductoUrl(
    filtros: any,
    opts?: { limite?: number; offset?: number; q?: string },
  ): string {
    const api = this.analytics.baseUrl;
    const p = this.analytics.params(filtros);
    let url = `${api}/api/ventas/detalle-productos?${p}`;
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

  obtenerDetalleVentasProductoPagina(
    filtros: any = {},
    page: DetalleVentasProductoPageParams,
  ): Observable<DetalleVentasProductoPagina> {
    const limite = page.limite > 0 ? page.limite : 50;
    const offset = page.offset >= 0 ? page.offset : 0;
    const url = this.buildDetalleVentasProductoUrl(filtros, {
      limite,
      offset,
      q: page.q,
    });
    return this.analytics.getSafe(url).pipe(
      map((data) =>
        this.normalizarDetalleVentasProductoResponse(
          data,
          { limite, offset },
          page.q,
        ),
      ),
      catchError((err) => {
        console.error(
          'Error loading /api/ventas/detalle-productos (página):',
          err,
        );
        return of({
          items: [],
          total: 0,
          limite,
          offset,
          totales: {
            cantidad: 0,
            descuento: 0,
            ventasSinIVA: 0,
            costoTotal: 0,
            utilidad: 0,
          },
        });
      }),
    );
  }

  obtenerDetalleVentasProductoCompleto(
    filtros: any = {},
    opts: { q?: string } = {},
  ): Observable<DetalleVentasProductoPagina> {
    const url = this.buildDetalleVentasProductoUrl(filtros, { q: opts.q });
    return this.analytics.getSafe(url).pipe(
      map((data) =>
        this.normalizarDetalleVentasProductoResponse(
          data,
          { limite: 0, offset: 0 },
          opts.q,
        ),
      ),
      catchError((err) => {
        console.error(
          'Error loading /api/ventas/detalle-productos (completo):',
          err,
        );
        return of({
          items: [],
          total: 0,
          limite: 0,
          offset: 0,
          totales: {
            cantidad: 0,
            descuento: 0,
            ventasSinIVA: 0,
            costoTotal: 0,
            utilidad: 0,
          },
        });
      }),
    );
  }

  private mapDetalleVentasClienteItem(i: any) {
    return {
      cliente: i.cliente,
      ultimaVenta: i.ultimaVenta || i.ultimaVentaMes || '-',
      dias: i.dias || 0,
      transacciones: i.transacciones || 0,
      ventasSinIva: i.ventasSinIva || 0,
      ventasConIva: i.ventasConIva || 0,
      ventas: i.ventasConIva || i.ventas || 0,
    };
  }

  private pickUltimaVentaMasReciente(fechas: string[]): string {
    const valid = (fechas ?? []).filter((f) => f && f !== '-');
    if (valid.length === 0) return '';
    valid.sort((a, b) => {
      const dateA = new Date(a.split('/').reverse().join('-'));
      const dateB = new Date(b.split('/').reverse().join('-'));
      return dateB.getTime() - dateA.getTime();
    });
    return valid[0];
  }

  private sumarTotalesDetalleVentasCliente(
    items: any[],
  ): DetalleVentasClienteTotales {
    const base = (items ?? []).reduce(
      (acc, i) => ({
        transacciones: acc.transacciones + (Number(i.transacciones) || 0),
        ventasSinIva: acc.ventasSinIva + (Number(i.ventasSinIva) || 0),
        ventasConIva: acc.ventasConIva + (Number(i.ventasConIva) || 0),
        dias: Math.max(acc.dias, Number(i.dias) || 0),
        ultimaVenta: '',
      }),
      {
        transacciones: 0,
        ventasSinIva: 0,
        ventasConIva: 0,
        dias: 0,
        ultimaVenta: '',
      },
    );
    return {
      ...base,
      ultimaVenta: this.pickUltimaVentaMasReciente(
        (items ?? []).map((i) => String(i.ultimaVenta ?? '')),
      ),
    };
  }

  private extractDetalleVentasClienteItems(data: any): any[] {
    if (Array.isArray(data)) return data;
    if (data == null || typeof data !== 'object') return [];
    const candidates = [
      data.items,
      data.detalleClientes,
      data.data,
      data.results,
      data.rows,
      data.clientes,
      data.ventasPorCliente,
    ];
    for (const c of candidates) {
      if (Array.isArray(c)) return c;
    }
    return [];
  }

  private paginarDetalleVentasClienteLocal(
    rawAll: any[],
    page: { limite: number; offset: number },
    q?: string,
  ): DetalleVentasClientePagina {
    const qNorm = q != null ? String(q).trim().toLowerCase() : '';
    let all = rawAll.map((i) => this.mapDetalleVentasClienteItem(i));
    if (qNorm) {
      all = all.filter((i) =>
        String(i.cliente ?? '')
          .toLowerCase()
          .includes(qNorm),
      );
    }
    const limite = page.limite > 0 ? page.limite : all.length;
    const offset = page.offset >= 0 ? page.offset : 0;
    const items = page.limite > 0 ? all.slice(offset, offset + limite) : all;
    return {
      items,
      total: all.length,
      limite,
      offset,
      totales: this.sumarTotalesDetalleVentasCliente(all),
    };
  }

  normalizarDetalleVentasClienteResponse(
    data: any,
    page: { limite: number; offset: number },
    q?: string,
  ): DetalleVentasClientePagina {
    if (data == null) {
      return {
        items: [],
        total: 0,
        limite: page.limite,
        offset: page.offset,
        totales: {
          transacciones: 0,
          ventasSinIva: 0,
          ventasConIva: 0,
          dias: 0,
          ultimaVenta: '',
        },
      };
    }

    // Array plano (compat / export / fallback): filtrar q y paginar en cliente.
    if (Array.isArray(data)) {
      return this.paginarDetalleVentasClienteLocal(data, page, q);
    }

    const rawItems = this.extractDetalleVentasClienteItems(data);
    const hasServerPageMeta =
      data.total != null ||
      data.totales != null ||
      data.limite != null ||
      data.meta?.total != null;

    // Envelope sin meta de página → tratar como lista completa.
    if (!hasServerPageMeta) {
      return this.paginarDetalleVentasClienteLocal(rawItems, page, q);
    }

    const items = rawItems.map((i: any) => this.mapDetalleVentasClienteItem(i));
    const totalesApi = data?.totales;
    const totales =
      totalesApi && typeof totalesApi === 'object'
        ? {
            transacciones: Number(totalesApi.transacciones) || 0,
            ventasSinIva: Number(totalesApi.ventasSinIva) || 0,
            ventasConIva:
              Number(totalesApi.ventasConIva ?? totalesApi.ventas) || 0,
            dias: Number(totalesApi.dias) || 0,
            ultimaVenta: String(totalesApi.ultimaVenta ?? ''),
          }
        : this.sumarTotalesDetalleVentasCliente(items);

    const totalMeta = data?.total ?? data?.meta?.total;
    return {
      items,
      total: Number(totalMeta) >= 0 ? Number(totalMeta) : items.length,
      limite: Number(data?.limite) >= 0 ? Number(data.limite) : page.limite,
      offset: Number(data?.offset) >= 0 ? Number(data.offset) : page.offset,
      totales,
    };
  }

  private buildDetalleVentasClienteUrl(
    filtros: any,
    opts?: { limite?: number; offset?: number; q?: string },
  ): string {
    const api = this.analytics.baseUrl;
    const p = this.analytics.params(filtros);
    let url = `${api}/api/ventas/detalle-clientes?${p}`;
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

  obtenerDetalleVentasClientePagina(
    filtros: any = {},
    page: DetalleVentasClientePageParams,
  ): Observable<DetalleVentasClientePagina> {
    const limite = page.limite > 0 ? page.limite : 50;
    const offset = page.offset >= 0 ? page.offset : 0;
    const empty: DetalleVentasClientePagina = {
      items: [],
      total: 0,
      limite,
      offset,
      totales: {
        transacciones: 0,
        ventasSinIva: 0,
        ventasConIva: 0,
        dias: 0,
        ultimaVenta: '',
      },
    };
    const urlPaged = this.buildDetalleVentasClienteUrl(filtros, {
      limite,
      offset,
      q: page.q,
    });
    // Compat: si el backend aún no soporta limite/offset (getSafe → null), reintenta sin ellos.
    const urlFull = this.buildDetalleVentasClienteUrl(filtros, { q: page.q });

    return this.analytics.getSafe(urlPaged).pipe(
      switchMap((data) => {
        if (data != null) {
          return of(
            this.normalizarDetalleVentasClienteResponse(
              data,
              { limite, offset },
              page.q,
            ),
          );
        }
        return this.analytics.getSafe(urlFull).pipe(
          map((data2) =>
            data2 == null
              ? empty
              : this.normalizarDetalleVentasClienteResponse(
                  data2,
                  { limite, offset },
                  page.q,
                ),
          ),
        );
      }),
      catchError((err) => {
        console.error(
          'Error loading /api/ventas/detalle-clientes (página):',
          err,
        );
        return of(empty);
      }),
    );
  }

  obtenerDetalleVentasClienteCompleto(
    filtros: any = {},
    opts: { q?: string } = {},
  ): Observable<DetalleVentasClientePagina> {
    const url = this.buildDetalleVentasClienteUrl(filtros, { q: opts.q });
    return this.analytics.getSafe(url).pipe(
      map((data) =>
        this.normalizarDetalleVentasClienteResponse(
          data,
          { limite: 0, offset: 0 },
          opts.q,
        ),
      ),
      catchError((err) => {
        console.error(
          'Error loading /api/ventas/detalle-clientes (completo):',
          err,
        );
        return of({
          items: [],
          total: 0,
          limite: 0,
          offset: 0,
          totales: {
            transacciones: 0,
            ventasSinIva: 0,
            ventasConIva: 0,
            dias: 0,
            ultimaVenta: '',
          },
        });
      }),
    );
  }

  obtenerVentasProgresivo(filtros: any = {}): Observable<any> {
    const api = this.analytics.baseUrl;
    const p = this.analytics.params(filtros);
    const safe = (path: string) => this.analytics.getSafe(`${api}${path}`);

    const cards$ = safe(`/api/ventas/cards?${p}`).pipe(
      map(data => ({
        metricasVentas: {
          ventasConIVA: data?.ventasConIva ?? 0,
          ventasSinIVA: data?.ventasSinIva ?? 0,
          transacciones: data?.transacciones ?? 0,
          ticketPromedio: data?.ticketPromedio ?? 0,
        }
      })),
      catchError(err => {
        console.error('Error loading /api/ventas/cards:', err);
        return of({ metricasVentas: { ventasConIVA: 0, ventasSinIVA: 0, transacciones: 0, ticketPromedio: 0 } });
      })
    );

    const porMes$ = safe(`/api/ventas/por-mes?${p}`).pipe(
      map(data => ({
        ventasPorMesConfig: {
          type: 'line',
          showArea: false,
          smooth: false,
          showYAxisLabels: false,
          showXAxisLine: false,
          labels: (data ?? []).map((f: any) => this.obtenerNombreMes(f.anioMes)),
          data: (data ?? []).map((f: any) => f.ventas),
          colors: ['#7CABFF'],
          barLabelExactUnder1000: true,
        }
      })),
      catchError(err => {
        console.error('Error loading /api/ventas/por-mes:', err);
        return of({ ventasPorMesConfig: { type: 'line', labels: [], data: [], colors: ['#7CABFF'] } });
      })
    );

    const vsPresupuesto$ = safe(`/api/ventas/vs-presupuesto?${p}`).pipe(
      map(data => ({
        ventasVsPresupuestoConfig: {
          type: 'bar',
          labels: (data ?? []).map((f: any) => this.obtenerNombreMes(f.anioMes)),
          data: [
            { name: 'Ventas', data: (data ?? []).map((f: any) => f.ventas || 0) },
            { name: 'Presupuesto', data: (data ?? []).map((f: any) => f.presupuesto || 0) }
          ],
          colors: ['#7CABFF', 'rgba(124, 171, 255, 0.4)'],
          dataExtra: (data ?? []).map((f: any) => f.presupuesto || 0),
          barLabelExactUnder1000: true,
        }
      })),
      catchError(err => {
        console.error('Error loading /api/ventas/vs-presupuesto:', err);
        return of({ ventasVsPresupuestoConfig: { type: 'bar', labels: [], data: [], colors: ['#7CABFF', 'rgba(124, 171, 255, 0.4)'] } });
      })
    );

    const vsAnioAnterior$ = safe(`/api/ventas/vs-anio-anterior?${p}`).pipe(
      map(data => {
        // Si consultamos el año actual, eliminar meses que aún no han ocurrido.
        const hoy = new Date();
        const anioConsulta = Number(filtros?.anio ?? hoy.getFullYear());
        const dateFiltrada = (anioConsulta === hoy.getFullYear())
          ? (data ?? []).filter((f: any) => {
              const mesNum = parseInt(String(f.anioMes ?? '').split('-')[1] ?? f.anioMes, 10);
              return mesNum <= hoy.getMonth() + 1;
            })
          : (data ?? []);
        return {
          ventasVsAnioAnteriorConfig: {
            type: 'bar',
            labels: dateFiltrada.map((f: any) => this.obtenerNombreMes(f.anioMes)),
            data: [
              { name: 'Año actual', data: dateFiltrada.map((f: any) => f.anioActual || 0) },
              { name: 'Año anterior', data: dateFiltrada.map((f: any) => f.anioAnterior || 0) }
            ],
            colors: ['#7CABFF', 'rgba(124, 171, 255, 0.4)'],
            dataExtra: dateFiltrada.map((f: any) => f.anioAnterior || 0),
            barLabelExactUnder1000: true,
          }
        };
      }),
      catchError(err => {
        console.error('Error loading /api/ventas/vs-anio-anterior:', err);
        return of({ ventasVsAnioAnteriorConfig: { type: 'bar', labels: [], data: [], colors: ['#7CABFF', 'rgba(124, 171, 255, 0.4)'] } });
      })
    );

    const porCanal$ = safe(`/api/ventas/por-canal?${p}`).pipe(
      map(data => ({
        ventasPorCanal: (data ?? []).map((i: any) => ({ name: i.name, amount: i.amount }))
      })),
      catchError(err => {
        console.error('Error loading /api/ventas/por-canal:', err);
        return of({ ventasPorCanal: [] });
      })
    );

    const porVendedor$ = safe(`/api/ventas/por-vendedor?${p}`).pipe(
      map(data => ({
        ventasPorVendedor: (data ?? []).map((i: any) => ({ name: i.name, amount: i.amount })),
        ventasPorVendedorChartConfig: {
          type: 'bar',
          highlightMaxBar: true,
          collapseExcessBars: true,
          initialVisibleBars: 5,
          colors: ['#7CABFF'],
          labels: (data ?? []).map((i: any) => i.name),
          data: (data ?? []).map((i: any) => i.amount),
        }
      })),
      catchError(err => {
        console.error('Error loading /api/ventas/por-vendedor:', err);
        return of({
          ventasPorVendedor: [],
          ventasPorVendedorChartConfig: {
            type: 'bar',
            collapseExcessBars: true,
            initialVisibleBars: 5,
            labels: [],
            data: [],
            colors: ['#7CABFF'],
          },
        });
      })
    );

    const porFormaPago$ = safe(`/api/ventas/por-forma-pago?${p}`).pipe(
      map(data => ({
        ventasPorFormaPagoConfig: {
          type: 'treemap',
          colors: ['#012B67', '#96BCFF', '#5E80BF'],
          labels: (data ?? []).map((i: any) => i.formaPago ?? i.name ?? ''),
          data: (data ?? []).map((i: any) => ({
            name: i.formaPago ?? i.name ?? '',
            value: Number(i.ventas ?? i.amount ?? 0),
          })),
          porcentajes: (data ?? []).map((i: any) => {
            const pVal = i.porcentaje;
            if (pVal == null || pVal === '') return Number.NaN;
            const n = Number(pVal);
            return Number.isFinite(n) ? n : Number.NaN;
          }),
        }
      })),
      catchError(err => {
        console.error('Error loading /api/ventas/por-forma-pago:', err);
        return of({ ventasPorFormaPagoConfig: { type: 'treemap', labels: [], data: [], porcentajes: [], colors: ['#012B67', '#96BCFF', '#5E80BF'] } });
      })
    );

    const porCategoria$ = safe(`/api/ventas/por-categoria?${p}`).pipe(
      map(data => ({
        ventasPorCategoria: (data ?? []).map((i: any) => ({ name: i.categoria, amount: i.ventasConIva }))
      })),
      catchError(err => {
        console.error('Error loading /api/ventas/por-categoria:', err);
        return of({ ventasPorCategoria: [] });
      })
    );

    const topProductos$ = safe(`/api/ventas/top-productos?${p}&limite=15`).pipe(
      map(data => ({
        topProductosVendidos: (data ?? []).map((i: any) => ({ name: i.producto, amount: i.ventasConIva }))
      })),
      catchError(err => {
        console.error('Error loading /api/ventas/top-productos:', err);
        return of({ topProductosVendidos: [] });
      })
    );

    const topClientes$ = safe(`/api/ventas/top-clientes?${p}&limite=25`).pipe(
      map(data => ({
        topClientes: (data ?? []).map((i: any) => ({ name: i.name, amount: i.amount }))
      })),
      catchError(err => {
        console.error('Error loading /api/ventas/top-clientes:', err);
        return of({ topClientes: [] });
      })
    );

    const ventasDetalladas$ = safe(`/api/ventas/detalladas?${p}`).pipe(
      map(data => ({
        ventasDetalladas: data ?? []
      })),
      catchError(err => {
        console.error('Error loading /api/ventas/detalladas:', err);
        return of({ ventasDetalladas: [] });
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
      porCanal$,
      porVendedor$,
      porFormaPago$,
      porCategoria$,
      topProductos$,
      topClientes$,
      ventasDetalladas$
    ).pipe(
      scan(deepMergeScan, {})
    );
  }

  obtenerVentas(filtros: any = {}): Observable<any> {
    const api = this.analytics.baseUrl;
    const p = this.analytics.params(filtros);
    const safe = (path: string) => this.analytics.getSafe(`${api}${path}`);

    return forkJoin({
      cards: safe(`/api/ventas/cards?${p}`),
      porMes: safe(`/api/ventas/por-mes?${p}`),
      vsPresupuesto: safe(`/api/ventas/vs-presupuesto?${p}`),
      vsAnioAnterior: safe(`/api/ventas/vs-anio-anterior?${p}`),
      porCanal: safe(`/api/ventas/por-canal?${p}`),
      porVendedor: safe(`/api/ventas/por-vendedor?${p}`),
      porFormaPago: safe(`/api/ventas/por-forma-pago?${p}`),
      porCategoria: safe(`/api/ventas/por-categoria?${p}`),
      topProductos: safe(`/api/ventas/top-productos?${p}&limite=15`),
      topClientes: safe(`/api/ventas/top-clientes?${p}&limite=25`),
      ventasDetalladas: safe(`/api/ventas/detalladas?${p}`),
    }).pipe(
      map((all) => ({
        ...this.mapearVentasCritico({
          cards: all.cards,
          porMes: all.porMes,
          vsPresupuesto: all.vsPresupuesto,
          vsAnioAnterior: all.vsAnioAnterior,
          anio: filtros?.anio ? Number(filtros.anio) : undefined,
        }),
        ...this.mapearVentasPesado({
          porCanal: all.porCanal,
          porVendedor: all.porVendedor,
          porFormaPago: all.porFormaPago,
          porCategoria: all.porCategoria,
          topProductos: all.topProductos,
          topClientes: all.topClientes,
        }),
        ventasDetalladas: all.ventasDetalladas ?? [],
      })),
    );
  }
}
