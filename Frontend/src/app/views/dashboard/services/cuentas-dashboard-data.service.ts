import { Injectable } from '@angular/core';
import { Observable, forkJoin } from 'rxjs';
import { map, switchMap, startWith } from 'rxjs/operators';
import { DashboardAnalyticsApiService } from './dashboard-analytics-api.service';

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

  private mapearCuentasCritico(raw: {
    cxcCards: any;
    cxcVigencia: any;
    cxcClientes: any;
    cxcDetalle: any;
  }): Record<string, unknown> {
    const { cxcCards, cxcVigencia, cxcClientes, cxcDetalle } = raw;
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
      detalleCuentasPorCobrar: (cxcDetalle ?? []).map((i: any) => ({
        cliente: i.cliente,
        factura: i.numFactura,
        fechaVenta: i.fechaVenta,
        fechaPago: i.fechaPago,
        diasVencimiento: i.diasVencimiento,
        estado: i.estadoVigencia,
        ventasConIVA: i.ventasConIva,
        montoAbonado: i.montoAbonado,
        diasAbono: i.diasAbono,
        saldoPendiente: i.saldoPendiente,
      })),
    };
  }

  private mapearCuentasPesado(raw: {
    cxpCards: any;
    cxpVigencia: any;
    cxpProveedores: any;
    cxpCategorias: any;
    cxpDetalle: any;
  }): Record<string, unknown> {
    const {
      cxpCards,
      cxpVigencia,
      cxpProveedores,
      cxpDetalle,
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
      resumenCuentasPorPagar: (cxpDetalle ?? []).map((i: any) => ({
        proveedor: i.proveedor != null ? String(i.proveedor).trim() : '',
        correlativo: i.correlativo != null ? String(i.correlativo).trim() : '',
        fechaCompra: i.fechaDocumento,
        vencimiento: i.fechaVencimiento,
        diasVencimiento: i.diasVencimiento,
        estado: i.estado,
        gastosTotalesConIVA: i.gastosConIva,
        totalAbonado: i.totalAbonado,
        ultimoAbono: i.ultimoAbono,
        saldoPendiente: i.saldoPendiente,
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

    const critico$ = forkJoin({
      cxcCards: safe(`/api/cuentas/cxc/cards?${p}${pEst}`),
      cxcVigencia: safe(`/api/cuentas/cxc/vigencia?${p}${pEst}`),
      cxcClientes: safe(`/api/cuentas/cxc/clientes?${p}&limite=10`),
      cxcDetalle: safe(`/api/cuentas/cxc/detalle?${p}${pEst}`),
    }).pipe(map((r) => this.mapearCuentasCritico(r)));

    const pesado$ = forkJoin({
      cxpCards: safe(`/api/cuentas/cxp/cards?${p}${pCXP}${pEst}`),
      cxpVigencia: safe(`/api/cuentas/cxp/vigencia?${p}${pCXP}${pEst}`),
      cxpProveedores: safe(
        `/api/cuentas/cxp/proveedores?${p}${pCXP}&limite=10`,
      ),
      cxpCategorias: safe(`/api/cuentas/cxp/categorias?${p}${pCXP}`),
      cxpDetalle: safe(`/api/cuentas/cxp/detalle?${p}${pCXP}${pEst}`),
    }).pipe(map((r) => this.mapearCuentasPesado(r)));

    return critico$.pipe(
      switchMap((c) =>
        pesado$.pipe(
          map((heavy) => this.mergeCuentasPayload(c, heavy)),
          startWith(c),
        ),
      ),
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
      cxcDetalle: safe(`/api/cuentas/cxc/detalle?${p}${pEst}`),
      cxpCards: safe(`/api/cuentas/cxp/cards?${p}${pCXP}${pEst}`),
      cxpVigencia: safe(`/api/cuentas/cxp/vigencia?${p}${pCXP}${pEst}`),
      cxpProveedores: safe(
        `/api/cuentas/cxp/proveedores?${p}${pCXP}&limite=10`,
      ),
      cxpCategorias: safe(`/api/cuentas/cxp/categorias?${p}${pCXP}`),
      cxpDetalle: safe(`/api/cuentas/cxp/detalle?${p}${pCXP}${pEst}`),
    }).pipe(
      map((all) =>
        this.mergeCuentasPayload(
          this.mapearCuentasCritico({
            cxcCards: all.cxcCards,
            cxcVigencia: all.cxcVigencia,
            cxcClientes: all.cxcClientes,
            cxcDetalle: all.cxcDetalle,
          }) as Record<string, unknown>,
          this.mapearCuentasPesado({
            cxpCards: all.cxpCards,
            cxpVigencia: all.cxpVigencia,
            cxpProveedores: all.cxpProveedores,
            cxpCategorias: all.cxpCategorias,
            cxpDetalle: all.cxpDetalle,
          }) as Record<string, unknown>,
        ),
      ),
    );
  }
}
