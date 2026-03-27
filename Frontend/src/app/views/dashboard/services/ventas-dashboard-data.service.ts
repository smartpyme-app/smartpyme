import { Injectable } from '@angular/core';
import { Observable, forkJoin } from 'rxjs';
import { map, switchMap, startWith } from 'rxjs/operators';
import { DashboardAnalyticsApiService } from './dashboard-analytics-api.service';

@Injectable({
  providedIn: 'root',
})
export class VentasDashboardDataService {
  constructor(private analytics: DashboardAnalyticsApiService) {}

  private mapearVentasCritico(raw: {
    cards: any;
    porMes: any;
    vsPresupuesto: any;
    vsAnioAnterior: any;
  }): Record<string, unknown> {
    const { cards, porMes, vsPresupuesto, vsAnioAnterior } = raw;
    return {
      metricasVentas: {
        ventasConIVA: cards?.ventasConIva ?? 0,
        ventasSinIVA: cards?.ventasSinIva ?? 0,
        transacciones: cards?.transacciones ?? 0,
        ticketPromedio: cards?.ticketPromedio ?? 0,
      },
      ventasPorMesConfig: {
        type: 'line',
        labels: (porMes ?? []).map((f: any) => f.anioMes),
        data: (porMes ?? []).map((f: any) => f.ventas),
      },
      ventasVsPresupuestoConfig: {
        type: 'bar',
        labels: (vsPresupuesto ?? []).map((f: any) => f.anioMes),
        data: (vsPresupuesto ?? []).map((f: any) => f.ventas),
        dataExtra: (vsPresupuesto ?? []).map((f: any) => f.presupuesto),
      },
      ventasVsAnioAnteriorConfig: {
        type: 'bar',
        labels: (vsAnioAnterior ?? []).map((f: any) => f.anioMes),
        data: (vsAnioAnterior ?? []).map((f: any) => f.anioActual),
        dataExtra: (vsAnioAnterior ?? []).map((f: any) => f.anioAnterior),
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
    detalleClientes: any;
  }): Record<string, unknown> {
    const {
      porCanal,
      porVendedor,
      porFormaPago,
      porCategoria,
      topProductos,
      topClientes,
      detalleClientes,
    } = raw;
    return {
      ventasPorCanal: (porCanal ?? []).map((i: any) => ({
        name: i.name,
        amount: i.amount,
      })),
      ventasPorVendedorChartConfig: {
        type: 'bar',
        labels: (porVendedor ?? []).map((i: any) => i.name),
        data: (porVendedor ?? []).map((i: any) => i.amount),
      },
      ventasPorFormaPagoConfig: {
        type: 'doughnut',
        labels: (porFormaPago ?? []).map((i: any) => i.formaPago),
        data: (porFormaPago ?? []).map((i: any) => i.ventas),
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
      ventasPorCliente: (detalleClientes ?? []).map((i: any) => ({
        cliente: i.cliente,
        ultimaVenta: i.ultimaVentaMes,
        dias: 0,
        transacciones: i.transacciones,
        ventas: i.ventasConIva,
      })),
    };
  }

  obtenerVentasProgresivo(filtros: any = {}): Observable<any> {
    const api = this.analytics.baseUrl;
    const p = this.analytics.params(filtros);
    const pVendedor = filtros?.vendedor ? `&vendedor=${filtros.vendedor}` : '';
    const safe = (path: string) => this.analytics.getSafe(`${api}${path}`);

    const critico$ = forkJoin({
      cards: safe(`/api/ventas/cards?${p}`),
      porMes: safe(`/api/ventas/por-mes?${p}`),
      vsPresupuesto: safe(`/api/ventas/vs-presupuesto?${p}`),
      vsAnioAnterior: safe(`/api/ventas/vs-anio-anterior?${p}`),
    }).pipe(map((r) => this.mapearVentasCritico(r)));

    const pesado$ = forkJoin({
      porCanal: safe(`/api/ventas/por-canal?${p}`),
      porVendedor: safe(`/api/ventas/por-vendedor?${p}${pVendedor}`),
      porFormaPago: safe(`/api/ventas/por-forma-pago?${p}`),
      porCategoria: safe(`/api/ventas/por-categoria?${p}`),
      topProductos: safe(`/api/ventas/top-productos?${p}&limite=15`),
      topClientes: safe(`/api/ventas/top-clientes?${p}&limite=25`),
      detalleClientes: safe(`/api/ventas/detalle-clientes?${p}`),
    }).pipe(map((r) => this.mapearVentasPesado(r)));

    return critico$.pipe(
      switchMap((c) =>
        pesado$.pipe(
          map((p) => ({ ...c, ...p })),
          startWith(c),
        ),
      ),
    );
  }

  obtenerVentas(filtros: any = {}): Observable<any> {
    const api = this.analytics.baseUrl;
    const p = this.analytics.params(filtros);
    const pVendedor = filtros?.vendedor ? `&vendedor=${filtros.vendedor}` : '';
    const safe = (path: string) => this.analytics.getSafe(`${api}${path}`);

    return forkJoin({
      cards: safe(`/api/ventas/cards?${p}`),
      porMes: safe(`/api/ventas/por-mes?${p}`),
      vsPresupuesto: safe(`/api/ventas/vs-presupuesto?${p}`),
      vsAnioAnterior: safe(`/api/ventas/vs-anio-anterior?${p}`),
      porCanal: safe(`/api/ventas/por-canal?${p}`),
      porVendedor: safe(`/api/ventas/por-vendedor?${p}${pVendedor}`),
      porFormaPago: safe(`/api/ventas/por-forma-pago?${p}`),
      porCategoria: safe(`/api/ventas/por-categoria?${p}`),
      topProductos: safe(`/api/ventas/top-productos?${p}&limite=15`),
      topClientes: safe(`/api/ventas/top-clientes?${p}&limite=25`),
      detalleClientes: safe(`/api/ventas/detalle-clientes?${p}`),
    }).pipe(
      map((all) => ({
        ...this.mapearVentasCritico({
          cards: all.cards,
          porMes: all.porMes,
          vsPresupuesto: all.vsPresupuesto,
          vsAnioAnterior: all.vsAnioAnterior,
        }),
        ...this.mapearVentasPesado({
          porCanal: all.porCanal,
          porVendedor: all.porVendedor,
          porFormaPago: all.porFormaPago,
          porCategoria: all.porCategoria,
          topProductos: all.topProductos,
          topClientes: all.topClientes,
          detalleClientes: all.detalleClientes,
        }),
      })),
    );
  }
}
