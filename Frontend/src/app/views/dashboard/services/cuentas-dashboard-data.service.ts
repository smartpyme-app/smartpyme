import { Injectable } from '@angular/core';
import { Observable, forkJoin, merge, of } from 'rxjs';
import { map, scan, catchError } from 'rxjs/operators';
import { DashboardAnalyticsApiService } from './dashboard-analytics-api.service';

export interface DetalleCxcTotales {
  ventasConIVA: number;
  montoAbonado: number;
  saldoPendiente: number;
}

export interface DetalleCxcPagina {
  items: any[];
  total: number;
  limite: number;
  offset: number;
  totales: DetalleCxcTotales;
}

export interface DetalleCxcPageParams {
  limite: number;
  offset: number;
  q?: string;
}

export interface DetalleCxpTotales {
  gastosTotalesConIVA: number;
  totalAbonado: number;
  saldoPendiente: number;
}

export interface DetalleCxpPagina {
  items: any[];
  total: number;
  limite: number;
  offset: number;
  totales: DetalleCxpTotales;
}

export interface DetalleCxpPageParams {
  limite: number;
  offset: number;
  q?: string;
}

/** Control de cuentas (CXC / CXP). */
@Injectable({
  providedIn: 'root',
})
export class CuentasDashboardDataService {
  constructor(private analytics: DashboardAnalyticsApiService) {}

  private static num(v: unknown): number {
    if (v == null || v === '') {
      return 0;
    }
    const n = Number(v);
    return Number.isFinite(n) ? n : 0;
  }

  /**
   * Cards CXC: la API puede devolver `cuentasPorCobrar` + `cxc0a30`… (legacy) o
   * buckets `cxcCorriente`, `cxc1a30`, `cxc31a60`, `cxc61a90`, `cxcMas90`.
   */
  private totalCxcCards(c: any): number {
    if (c == null) {
      return 0;
    }
    if (c.cuentasPorCobrar != null) {
      return CuentasDashboardDataService.num(c.cuentasPorCobrar);
    }
    return (
      CuentasDashboardDataService.num(c.cxcCorriente) +
      CuentasDashboardDataService.num(c.cxc1a30) +
      CuentasDashboardDataService.num(c.cxc31a60) +
      CuentasDashboardDataService.num(c.cxc61a90) +
      CuentasDashboardDataService.num(c.cxcMas90)
    );
  }

  /**
   * `analytics.params(filtros)` ya incluye `cliente`, `categoria`, `sucursal`, etc.
   * Solo añadimos aquí lo que no va en ese helper (proveedor CXP, estado_vigencia).
   */
  private querySuffix(filtros: any): {
    p: string;
    pCXP: string;
    pEst: string;
  } {
    const p = this.analytics.params(filtros);
    const pCXP = filtros?.proveedor ? `&proveedor=${filtros.proveedor}` : '';
    const estadoVig =
      filtros?.estadoVigencia ??
      filtros?.estado ??
      filtros?.estadoPagar;
    const pEst =
      estadoVig != null && String(estadoVig).trim() !== ''
        ? `&estado_vigencia=${estadoVig}`
        : '';
    return { p, pCXP, pEst };
  }

  private mapDetalleCxcItem(i: any) {
    return {
      cliente: i.cliente,
      factura: i.numFactura ?? i.factura,
      fechaVenta: i.fechaVenta,
      fechaPago: i.fechaPago,
      diasVencimiento: i.diasVencimiento,
      estado: i.estadoVigencia ?? i.estado,
      ventasConIVA: i.ventasConIva ?? i.ventasConIVA,
      montoAbonado: i.montoAbonado,
      diasAbono: i.diasAbono,
      saldoPendiente: i.saldoPendiente,
    };
  }

  private sumarTotalesCxc(items: any[]): DetalleCxcTotales {
    return (items ?? []).reduce(
      (acc, i) => ({
        ventasConIVA: acc.ventasConIVA + (Number(i.ventasConIVA) || 0),
        montoAbonado: acc.montoAbonado + (Number(i.montoAbonado) || 0),
        saldoPendiente: acc.saldoPendiente + (Number(i.saldoPendiente) || 0),
      }),
      { ventasConIVA: 0, montoAbonado: 0, saldoPendiente: 0 },
    );
  }

  normalizarDetalleCxcResponse(
    data: any,
    page: { limite: number; offset: number },
    q?: string,
  ): DetalleCxcPagina {
    const qNorm = q != null ? String(q).trim().toLowerCase() : '';

    if (Array.isArray(data)) {
      let all = data.map((i) => this.mapDetalleCxcItem(i));
      if (qNorm) {
        all = all.filter(
          (i) =>
            String(i.cliente ?? '')
              .toLowerCase()
              .includes(qNorm) ||
            String(i.factura ?? '')
              .toLowerCase()
              .includes(qNorm) ||
            String(i.estado ?? '')
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
        totales: this.sumarTotalesCxc(all),
      };
    }

    const rawItems = data?.items ?? data?.data ?? [];
    const items = (Array.isArray(rawItems) ? rawItems : []).map((i: any) =>
      this.mapDetalleCxcItem(i),
    );
    const totalesApi = data?.totales;
    const totales =
      totalesApi && typeof totalesApi === 'object'
        ? {
            ventasConIVA:
              Number(totalesApi.ventasConIVA ?? totalesApi.ventasConIva) || 0,
            montoAbonado: Number(totalesApi.montoAbonado) || 0,
            saldoPendiente: Number(totalesApi.saldoPendiente) || 0,
          }
        : this.sumarTotalesCxc(items);

    return {
      items,
      total: Number(data?.total) >= 0 ? Number(data.total) : items.length,
      limite: Number(data?.limite) >= 0 ? Number(data.limite) : page.limite,
      offset: Number(data?.offset) >= 0 ? Number(data.offset) : page.offset,
      totales,
    };
  }

  private buildDetalleCxcUrl(
    filtros: any,
    opts?: { limite?: number; offset?: number; q?: string },
  ): string {
    const api = this.analytics.baseUrl;
    const { p, pEst } = this.querySuffix(filtros);
    let url = `${api}/api/cuentas/cxc/detalle?${p}${pEst}`;
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

  obtenerDetalleCxcPagina(
    filtros: any = {},
    page: DetalleCxcPageParams,
  ): Observable<DetalleCxcPagina> {
    const limite = page.limite > 0 ? page.limite : 50;
    const offset = page.offset >= 0 ? page.offset : 0;
    const url = this.buildDetalleCxcUrl(filtros, {
      limite,
      offset,
      q: page.q,
    });
    return this.analytics.getSafe(url).pipe(
      map((data) =>
        this.normalizarDetalleCxcResponse(data, { limite, offset }, page.q),
      ),
      catchError((err) => {
        console.error('Error loading /api/cuentas/cxc/detalle (página):', err);
        return of({
          items: [],
          total: 0,
          limite,
          offset,
          totales: { ventasConIVA: 0, montoAbonado: 0, saldoPendiente: 0 },
        });
      }),
    );
  }

  obtenerDetalleCxcCompleto(
    filtros: any = {},
    opts: { q?: string } = {},
  ): Observable<DetalleCxcPagina> {
    const url = this.buildDetalleCxcUrl(filtros, { q: opts.q });
    return this.analytics.getSafe(url).pipe(
      map((data) =>
        this.normalizarDetalleCxcResponse(
          data,
          { limite: 0, offset: 0 },
          opts.q,
        ),
      ),
      catchError((err) => {
        console.error('Error loading /api/cuentas/cxc/detalle (completo):', err);
        return of({
          items: [],
          total: 0,
          limite: 0,
          offset: 0,
          totales: { ventasConIVA: 0, montoAbonado: 0, saldoPendiente: 0 },
        });
      }),
    );
  }

  private mapDetalleCxpItem(i: any) {
    return {
      proveedor: i.proveedor != null ? String(i.proveedor).trim() : '',
      correlativo: i.correlativo != null ? String(i.correlativo).trim() : '',
      fechaCompra: i.fechaDocumento ?? i.fechaCompra,
      vencimiento: i.fechaVencimiento ?? i.vencimiento,
      diasVencimiento: i.diasVencimiento,
      estado: i.estadoVigencia ?? i.estado,
      gastosTotalesConIVA: i.gastosConIva ?? i.gastosTotalesConIVA,
      totalAbonado: i.totalAbonado,
      ultimoAbono: i.ultimoAbono,
      saldoPendiente: i.saldoPendiente,
    };
  }

  private sumarTotalesCxp(items: any[]): DetalleCxpTotales {
    return (items ?? []).reduce(
      (acc, i) => ({
        gastosTotalesConIVA:
          acc.gastosTotalesConIVA + (Number(i.gastosTotalesConIVA) || 0),
        totalAbonado: acc.totalAbonado + (Number(i.totalAbonado) || 0),
        saldoPendiente: acc.saldoPendiente + (Number(i.saldoPendiente) || 0),
      }),
      { gastosTotalesConIVA: 0, totalAbonado: 0, saldoPendiente: 0 },
    );
  }

  normalizarDetalleCxpResponse(
    data: any,
    page: { limite: number; offset: number },
    q?: string,
  ): DetalleCxpPagina {
    const qNorm = q != null ? String(q).trim().toLowerCase() : '';

    if (Array.isArray(data)) {
      let all = data.map((i) => this.mapDetalleCxpItem(i));
      if (qNorm) {
        all = all.filter(
          (i) =>
            String(i.proveedor ?? '')
              .toLowerCase()
              .includes(qNorm) ||
            String(i.correlativo ?? '')
              .toLowerCase()
              .includes(qNorm) ||
            String(i.estado ?? '')
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
        totales: this.sumarTotalesCxp(all),
      };
    }

    const rawItems = data?.items ?? data?.data ?? [];
    const items = (Array.isArray(rawItems) ? rawItems : []).map((i: any) =>
      this.mapDetalleCxpItem(i),
    );
    const totalesApi = data?.totales;
    const totales =
      totalesApi && typeof totalesApi === 'object'
        ? {
            gastosTotalesConIVA:
              Number(
                totalesApi.gastosTotalesConIVA ?? totalesApi.gastosConIva,
              ) || 0,
            totalAbonado: Number(totalesApi.totalAbonado) || 0,
            saldoPendiente: Number(totalesApi.saldoPendiente) || 0,
          }
        : this.sumarTotalesCxp(items);

    return {
      items,
      total: Number(data?.total) >= 0 ? Number(data.total) : items.length,
      limite: Number(data?.limite) >= 0 ? Number(data.limite) : page.limite,
      offset: Number(data?.offset) >= 0 ? Number(data.offset) : page.offset,
      totales,
    };
  }

  private buildDetalleCxpUrl(
    filtros: any,
    opts?: { limite?: number; offset?: number; q?: string },
  ): string {
    const api = this.analytics.baseUrl;
    const { p, pCXP, pEst } = this.querySuffix(filtros);
    let url = `${api}/api/cuentas/cxp/detalle?${p}${pCXP}${pEst}`;
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

  obtenerDetalleCxpPagina(
    filtros: any = {},
    page: DetalleCxpPageParams,
  ): Observable<DetalleCxpPagina> {
    const limite = page.limite > 0 ? page.limite : 50;
    const offset = page.offset >= 0 ? page.offset : 0;
    const url = this.buildDetalleCxpUrl(filtros, {
      limite,
      offset,
      q: page.q,
    });
    return this.analytics.getSafe(url).pipe(
      map((data) =>
        this.normalizarDetalleCxpResponse(data, { limite, offset }, page.q),
      ),
      catchError((err) => {
        console.error('Error loading /api/cuentas/cxp/detalle (página):', err);
        return of({
          items: [],
          total: 0,
          limite,
          offset,
          totales: {
            gastosTotalesConIVA: 0,
            totalAbonado: 0,
            saldoPendiente: 0,
          },
        });
      }),
    );
  }

  obtenerDetalleCxpCompleto(
    filtros: any = {},
    opts: { q?: string } = {},
  ): Observable<DetalleCxpPagina> {
    const url = this.buildDetalleCxpUrl(filtros, { q: opts.q });
    return this.analytics.getSafe(url).pipe(
      map((data) =>
        this.normalizarDetalleCxpResponse(
          data,
          { limite: 0, offset: 0 },
          opts.q,
        ),
      ),
      catchError((err) => {
        console.error('Error loading /api/cuentas/cxp/detalle (completo):', err);
        return of({
          items: [],
          total: 0,
          limite: 0,
          offset: 0,
          totales: {
            gastosTotalesConIVA: 0,
            totalAbonado: 0,
            saldoPendiente: 0,
          },
        });
      }),
    );
  }

  private mapearCuentasCritico(raw: {
    cxcCards: any;
    cxcVigencia: any;
    cxcClientes: any;
  }): Record<string, unknown> {
    const { cxcCards, cxcVigencia, cxcClientes } = raw;
    return {
      metricasCuentas: {
        cuentasPorCobrarTotal: this.totalCxcCards(cxcCards),
        cuentasPorCobrar30Dias: CuentasDashboardDataService.num(
          cxcCards?.cxc1a30 ?? cxcCards?.cxc0a30,
        ),
        cuentasPorCobrar60Dias: CuentasDashboardDataService.num(
          cxcCards?.cxc31a60,
        ),
        cuentasPorCobrar90Dias:
          CuentasDashboardDataService.num(cxcCards?.cxc61a90) +
          CuentasDashboardDataService.num(cxcCards?.cxcMas90),
        cuentasPorPagarTotal: 0,
        cuentasPorPagar30Dias: 0,
        cuentasPorPagar60Dias: 0,
        cuentasPorPagar90Dias: 0,
      },
      cuentasPorVigenciaConfig: {
        type: 'doughnut',
        labels: (cxcVigencia ?? []).map((i: any) => i.estadoVigencia),
        data: (cxcVigencia ?? []).map((i: any) => i.total),
      },
      cuentasPorCobrarClientes: (cxcClientes ?? []).map((i: any) => ({
        name: i.name,
        amount: i.amount,
      })),
    };
  }

  private mapearCuentasPesado(raw: {
    cxpCards: any;
    cxpVigencia: any;
    cxpProveedores: any;
    cxpCategorias: any;
  }): Record<string, unknown> {
    const {
      cxpCards,
      cxpVigencia,
      cxpProveedores,
      cxpCategorias: _cxpCategorias,
    } = raw;
    void _cxpCategorias;
    return {
      metricasCuentas: {
        cuentasPorPagarTotal: cxpCards?.cuentasPorPagar ?? 0,
        cuentasPorPagar30Dias: cxpCards?.cxp0a30 ?? 0,
        cuentasPorPagar60Dias: cxpCards?.cxp31a60 ?? 0,
        cuentasPorPagar90Dias: cxpCards?.cxp61a90 ?? 0,
      },
      cuentasPorPagarVigenciaConfig: {
        type: 'doughnut',
        labels: (cxpVigencia ?? []).map((i: any) => i.estadoVigencia),
        data: (cxpVigencia ?? []).map((i: any) => i.total),
      },
      cuentasPorPagarProveedores: (cxpProveedores ?? []).map((i: any) => ({
        name: i.name,
        amount: i.amount,
      })),
    };
  }

  /** Une métricas CXC (crítico) con CXP (pesado) sin pisar campos. */
  private mergeCuentasPayload(
    critico: Record<string, unknown>,
    pesado: Record<string, unknown>,
  ): any {
    const mcC = (critico as any).metricasCuentas || {};
    const mcP = (pesado as any).metricasCuentas || {};
    return {
      ...critico,
      ...pesado,
      metricasCuentas: { ...mcC, ...mcP },
    };
  }

  obtenerCuentasProgresivo(filtros: any = {}): Observable<any> {
    const api = this.analytics.baseUrl;
    const { p, pCXP, pEst } = this.querySuffix(filtros);
    const safe = (path: string) => this.analytics.getSafe(`${api}${path}`);

    const cxcCards$ = safe(`/api/cuentas/cxc/cards?${p}${pEst}`).pipe(
      map(cxcCards => ({
        metricasCuentas: {
          cuentasPorCobrarTotal: this.totalCxcCards(cxcCards),
          cuentasPorCobrar30Dias: CuentasDashboardDataService.num(cxcCards?.cxc1a30 ?? cxcCards?.cxc0a30),
          cuentasPorCobrar60Dias: CuentasDashboardDataService.num(cxcCards?.cxc31a60),
          cuentasPorCobrar90Dias: CuentasDashboardDataService.num(cxcCards?.cxc61a90) + CuentasDashboardDataService.num(cxcCards?.cxcMas90),
        }
      })),
      catchError(err => {
        console.error('Error loading /api/cuentas/cxc/cards:', err);
        return of({
          metricasCuentas: {
            cuentasPorCobrarTotal: 0,
            cuentasPorCobrar30Dias: 0,
            cuentasPorCobrar60Dias: 0,
            cuentasPorCobrar90Dias: 0,
          }
        });
      })
    );

    const cxcVigencia$ = safe(`/api/cuentas/cxc/vigencia?${p}${pEst}`).pipe(
      map(cxcVigencia => ({
        cuentasPorVigenciaConfig: {
          type: 'doughnut',
          labels: (cxcVigencia ?? []).map((i: any) => i.estadoVigencia),
          data: (cxcVigencia ?? []).map((i: any) => i.total),
        }
      })),
      catchError(err => {
        console.error('Error loading /api/cuentas/cxc/vigencia:', err);
        return of({ cuentasPorVigenciaConfig: { type: 'doughnut', labels: [], data: [] } });
      })
    );

    const cxcClientes$ = safe(`/api/cuentas/cxc/clientes?${p}&limite=10`).pipe(
      map(cxcClientes => ({
        cuentasPorCobrarClientes: (cxcClientes ?? []).map((i: any) => ({ name: i.name, amount: i.amount }))
      })),
      catchError(err => {
        console.error('Error loading /api/cuentas/cxc/clientes:', err);
        return of({ cuentasPorCobrarClientes: [] });
      })
    );

    const cxpCards$ = safe(`/api/cuentas/cxp/cards?${p}${pCXP}${pEst}`).pipe(
      map(cxpCards => ({
        metricasCuentas: {
          cuentasPorPagarTotal: cxpCards?.cuentasPorPagar ?? 0,
          cuentasPorPagar30Dias: cxpCards?.cxp0a30 ?? 0,
          cuentasPorPagar60Dias: cxpCards?.cxp31a60 ?? 0,
          cuentasPorPagar90Dias: cxpCards?.cxp61a90 ?? 0,
        }
      })),
      catchError(err => {
        console.error('Error loading /api/cuentas/cxp/cards:', err);
        return of({
          metricasCuentas: {
            cuentasPorPagarTotal: 0,
            cuentasPorPagar30Dias: 0,
            cuentasPorPagar60Dias: 0,
            cuentasPorPagar90Dias: 0,
          }
        });
      })
    );

    const cxpVigencia$ = safe(`/api/cuentas/cxp/vigencia?${p}${pCXP}${pEst}`).pipe(
      map(cxpVigencia => ({
        cuentasPorPagarVigenciaConfig: {
          type: 'doughnut',
          labels: (cxpVigencia ?? []).map((i: any) => i.estadoVigencia),
          data: (cxpVigencia ?? []).map((i: any) => i.total),
        }
      })),
      catchError(err => {
        console.error('Error loading /api/cuentas/cxp/vigencia:', err);
        return of({ cuentasPorPagarVigenciaConfig: { type: 'doughnut', labels: [], data: [] } });
      })
    );

    const cxpProveedores$ = safe(`/api/cuentas/cxp/proveedores?${p}${pCXP}&limite=10`).pipe(
      map(cxpProveedores => ({
        cuentasPorPagarProveedores: (cxpProveedores ?? []).map((i: any) => ({ name: i.name, amount: i.amount }))
      })),
      catchError(err => {
        console.error('Error loading /api/cuentas/cxp/proveedores:', err);
        return of({ cuentasPorPagarProveedores: [] });
      })
    );

    const cxpCategorias$ = safe(`/api/cuentas/cxp/categorias?${p}${pCXP}`).pipe(
      map(cxpCategorias => ({
        cxpCategorias: cxpCategorias || []
      })),
      catchError(err => {
        console.error('Error loading /api/cuentas/cxp/categorias:', err);
        return of({ cxpCategorias: [] });
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
      cxcCards$,
      cxcVigencia$,
      cxcClientes$,
      cxpCards$,
      cxpVigencia$,
      cxpProveedores$,
      cxpCategorias$
    ).pipe(
      scan(deepMergeScan, {})
    );
  }

  obtenerCuentas(filtros: any = {}): Observable<any> {
    const api = this.analytics.baseUrl;
    const { p, pCXP, pEst } = this.querySuffix(filtros);
    const safe = (path: string) => this.analytics.getSafe(`${api}${path}`);

    return forkJoin({
      cxcCards: safe(`/api/cuentas/cxc/cards?${p}${pEst}`),
      cxcVigencia: safe(`/api/cuentas/cxc/vigencia?${p}${pEst}`),
      cxcClientes: safe(`/api/cuentas/cxc/clientes?${p}&limite=10`),
      cxpCards: safe(`/api/cuentas/cxp/cards?${p}${pCXP}${pEst}`),
      cxpVigencia: safe(`/api/cuentas/cxp/vigencia?${p}${pCXP}${pEst}`),
      cxpProveedores: safe(
        `/api/cuentas/cxp/proveedores?${p}${pCXP}&limite=10`,
      ),
      cxpCategorias: safe(`/api/cuentas/cxp/categorias?${p}${pCXP}`),
    }).pipe(
      map((all) =>
        this.mergeCuentasPayload(
          this.mapearCuentasCritico({
            cxcCards: all.cxcCards,
            cxcVigencia: all.cxcVigencia,
            cxcClientes: all.cxcClientes,
          }) as Record<string, unknown>,
          this.mapearCuentasPesado({
            cxpCards: all.cxpCards,
            cxpVigencia: all.cxpVigencia,
            cxpProveedores: all.cxpProveedores,
            cxpCategorias: all.cxpCategorias,
          }) as Record<string, unknown>,
        ),
      ),
    );
  }
}
