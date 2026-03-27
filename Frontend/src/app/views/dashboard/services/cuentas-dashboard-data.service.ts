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

  private querySuffix(filtros: any): {
    p: string;
    pCXC: string;
    pCXP: string;
    pEst: string;
    pCat: string;
  } {
    const p = this.analytics.params(filtros);
    const pCXC = filtros?.cliente ? `&cliente=${filtros.cliente}` : '';
    const pCXP = filtros?.proveedor ? `&proveedor=${filtros.proveedor}` : '';
    const pEst = filtros?.estadoVigencia
      ? `&estado_vigencia=${filtros.estadoVigencia}`
      : '';
    const pCat = filtros?.categoria ? `&categoria=${filtros.categoria}` : '';
    return { p, pCXC, pCXP, pEst, pCat };
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
        cuentasPorCobrarTotal: cxcCards?.cuentasPorCobrar ?? 0,
        cuentasPorCobrar30Dias: cxcCards?.cxc0a30 ?? 0,
        cuentasPorCobrar60Dias: cxcCards?.cxc31a60 ?? 0,
        cuentasPorCobrar90Dias: cxcCards?.cxc61a90 ?? 0,
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
    const { p, pCXC, pCXP, pEst, pCat } = this.querySuffix(filtros);
    const safe = (path: string) => this.analytics.getSafe(`${api}${path}`);

    const critico$ = forkJoin({
      cxcCards: safe(`/api/cuentas/cxc/cards?${p}${pCXC}${pEst}`),
      cxcVigencia: safe(`/api/cuentas/cxc/vigencia?${p}${pCXC}${pEst}`),
      cxcClientes: safe(`/api/cuentas/cxc/clientes?${p}${pCXC}&limite=10`),
      cxcDetalle: safe(`/api/cuentas/cxc/detalle?${p}${pCXC}${pEst}`),
    }).pipe(map((r) => this.mapearCuentasCritico(r)));

    const pesado$ = forkJoin({
      cxpCards: safe(`/api/cuentas/cxp/cards?${p}${pCXP}${pEst}${pCat}`),
      cxpVigencia: safe(`/api/cuentas/cxp/vigencia?${p}${pCXP}${pEst}${pCat}`),
      cxpProveedores: safe(
        `/api/cuentas/cxp/proveedores?${p}${pCXP}&limite=10`,
      ),
      cxpCategorias: safe(`/api/cuentas/cxp/categorias?${p}${pCXP}${pCat}`),
      cxpDetalle: safe(`/api/cuentas/cxp/detalle?${p}${pCXP}${pEst}${pCat}`),
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
    const { p, pCXC, pCXP, pEst, pCat } = this.querySuffix(filtros);
    const safe = (path: string) => this.analytics.getSafe(`${api}${path}`);

    return forkJoin({
      cxcCards: safe(`/api/cuentas/cxc/cards?${p}${pCXC}${pEst}`),
      cxcVigencia: safe(`/api/cuentas/cxc/vigencia?${p}${pCXC}${pEst}`),
      cxcClientes: safe(`/api/cuentas/cxc/clientes?${p}${pCXC}&limite=10`),
      cxcDetalle: safe(`/api/cuentas/cxc/detalle?${p}${pCXC}${pEst}`),
      cxpCards: safe(`/api/cuentas/cxp/cards?${p}${pCXP}${pEst}${pCat}`),
      cxpVigencia: safe(`/api/cuentas/cxp/vigencia?${p}${pCXP}${pEst}${pCat}`),
      cxpProveedores: safe(
        `/api/cuentas/cxp/proveedores?${p}${pCXP}&limite=10`,
      ),
      cxpCategorias: safe(`/api/cuentas/cxp/categorias?${p}${pCXP}${pCat}`),
      cxpDetalle: safe(`/api/cuentas/cxp/detalle?${p}${pCXP}${pEst}${pCat}`),
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
