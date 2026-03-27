import { Injectable } from '@angular/core';
import { Observable, forkJoin } from 'rxjs';
import { map } from 'rxjs/operators';
import { DashboardAnalyticsApiService } from './dashboard-analytics-api.service';

/** Control de cuentas (CXC / CXP). */
@Injectable({
  providedIn: 'root',
})
export class CuentasDashboardDataService {
  constructor(private analytics: DashboardAnalyticsApiService) {}

  obtenerCuentas(filtros: any = {}): Observable<any> {
    const api = this.analytics.baseUrl;
    const p = this.analytics.params(filtros);
    const pCXC = filtros?.cliente ? `&cliente=${filtros.cliente}` : '';
    const pCXP = filtros?.proveedor ? `&proveedor=${filtros.proveedor}` : '';
    const pEst = filtros?.estadoVigencia
      ? `&estado_vigencia=${filtros.estadoVigencia}`
      : '';
    const pCat = filtros?.categoria ? `&categoria=${filtros.categoria}` : '';
    const g = (path: string) => this.analytics.get(`${api}${path}`);

    return forkJoin({
      cxcCards: g(`/api/cuentas/cxc/cards?${p}${pCXC}${pEst}`),
      cxcVigencia: g(`/api/cuentas/cxc/vigencia?${p}${pCXC}${pEst}`),
      cxcClientes: g(`/api/cuentas/cxc/clientes?${p}${pCXC}&limite=10`),
      cxcDetalle: g(`/api/cuentas/cxc/detalle?${p}${pCXC}${pEst}`),
      cxpCards: g(`/api/cuentas/cxp/cards?${p}${pCXP}${pEst}${pCat}`),
      cxpVigencia: g(`/api/cuentas/cxp/vigencia?${p}${pCXP}${pEst}${pCat}`),
      cxpProveedores: g(
        `/api/cuentas/cxp/proveedores?${p}${pCXP}&limite=10`,
      ),
      cxpCategorias: g(`/api/cuentas/cxp/categorias?${p}${pCXP}${pCat}`),
      cxpDetalle: g(`/api/cuentas/cxp/detalle?${p}${pCXP}${pEst}${pCat}`),
    }).pipe(
      map(
        ({
          cxcCards,
          cxcVigencia,
          cxcClientes,
          cxcDetalle,
          cxpCards,
          cxpVigencia,
          cxpProveedores,
          cxpCategorias: _cxpCategorias,
          cxpDetalle,
        }) => ({
          metricasCuentas: {
            cuentasPorCobrarTotal: cxcCards?.cuentasPorCobrar ?? 0,
            cuentasPorCobrar30Dias: cxcCards?.cxc0a30 ?? 0,
            cuentasPorCobrar60Dias: cxcCards?.cxc31a60 ?? 0,
            cuentasPorCobrar90Dias: cxcCards?.cxc61a90 ?? 0,
            cuentasPorPagarTotal: cxpCards?.cuentasPorPagar ?? 0,
            cuentasPorPagar30Dias: cxpCards?.cxp0a30 ?? 0,
            cuentasPorPagar60Dias: cxpCards?.cxp31a60 ?? 0,
            cuentasPorPagar90Dias: cxpCards?.cxp61a90 ?? 0,
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
        }),
      ),
    );
  }
}
