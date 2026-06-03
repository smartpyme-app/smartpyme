import { Injectable } from '@angular/core';
import { Observable, forkJoin } from 'rxjs';
import { map, switchMap, startWith } from 'rxjs/operators';
import { DashboardAnalyticsApiService } from './dashboard-analytics-api.service';

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
        showArea: false,
        smooth: false,
        showYAxisLabels: false,
        showXAxisLine: false,
        labels: (porMes ?? []).map((f: any) => this.obtenerNombreMes(f.anioMes)),
        data: (porMes ?? []).map((f: any) => f.ventas),
        colors: ['#7CABFF'],
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
      },
      ventasVsAnioAnteriorConfig: {
        type: 'bar',
        labels: (vsAnioAnterior ?? []).map((f: any) => this.obtenerNombreMes(f.anioMes)),
        data: [
          {
            name: 'Año actual',
            data: (vsAnioAnterior ?? []).map((f: any) => f.anioActual || 0),
          },
          {
            name: 'Año anterior',
            data: (vsAnioAnterior ?? []).map((f: any) => f.anioAnterior || 0),
          }
        ],
        colors: ['#7CABFF', 'rgba(124, 171, 255, 0.4)'],
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
      ventasPorVendedor: (porVendedor ?? []).map((i: any) => ({
        name: i.name,
        amount: i.amount,
      })),
      ventasPorVendedorChartConfig: {
        type: 'bar',
        highlightMaxBar: true,
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
      ventasPorCliente: (detalleClientes ?? []).map((i: any) => ({
        cliente: i.cliente,
        ultimaVenta: i.ultimaVenta || i.ultimaVentaMes || '-',
        dias: i.dias || 0,
        transacciones: i.transacciones || 0,
        ventasSinIva: i.ventasSinIva || 0,
        ventasConIva: i.ventasConIva || 0,
        ventas: i.ventasConIva || 0,
      })),
    };
  }

  obtenerVentasProgresivo(filtros: any = {}): Observable<any> {
    const api = this.analytics.baseUrl;
    const p = this.analytics.params(filtros);
    const safe = (path: string) => this.analytics.getSafe(`${api}${path}`);

    const critico$ = forkJoin({
      cards: safe(`/api/ventas/cards?${p}`),
      porMes: safe(`/api/ventas/por-mes?${p}`),
      vsPresupuesto: safe(`/api/ventas/vs-presupuesto?${p}`),
      vsAnioAnterior: safe(`/api/ventas/vs-anio-anterior?${p}`),
    }).pipe(map((r) => this.mapearVentasCritico(r)));

    const pesado$ = forkJoin({
      porCanal: safe(`/api/ventas/por-canal?${p}`),
      porVendedor: safe(`/api/ventas/por-vendedor?${p}`),
      porFormaPago: safe(`/api/ventas/por-forma-pago?${p}`),
      porCategoria: safe(`/api/ventas/por-categoria?${p}`),
      topProductos: safe(`/api/ventas/top-productos?${p}&limite=15`),
      topClientes: safe(`/api/ventas/top-clientes?${p}&limite=25`),
      detalleClientes: safe(`/api/ventas/detalle-clientes?${p}`),
      detalleProductos: safe(`/api/ventas/detalle-productos?${p}`),
      ventasDetalladas: safe(`/api/ventas/detalladas?${p}`), // ✅ NUEVO
    }).pipe(
      map((r) => ({
        ...this.mapearVentasPesado(r),
        ventasPorProducto: (r.detalleProductos ?? []).map((i: any) => ({
          idProducto: i.idProducto ?? i.id_producto,
          categoria: i.categoria,
          producto: i.producto,
          formaPago: i.formaPago,
          cantidad: i.cantidad,
          precioUnitario: i.precioUnitario,
          descuento: i.descuento,
          ventasSinIVA: i.ventasConIva,
          costoTotal: i.costoTotal,
          utilidad: i.utilidad,
        })),
        ventasDetalladas: r.ventasDetalladas ?? [], // ✅ NUEVO
      })),
    );

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
      detalleClientes: safe(`/api/ventas/detalle-clientes?${p}`),
      detalleProductos: safe(`/api/ventas/detalle-productos?${p}`),
      ventasDetalladas: safe(`/api/ventas/detalladas?${p}`), // ✅ NUEVO
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
        ventasPorProducto: (all.detalleProductos ?? []).map((i: any) => ({
          idProducto: i.idProducto ?? i.id_producto,
          categoria: i.categoria,
          producto: i.producto,
          formaPago: i.formaPago,
          cantidad: i.cantidad,
          precioUnitario: i.precioUnitario,
          descuento: i.descuento,
          ventasSinIVA: i.ventasConIva,
          costoTotal: i.costoTotal,
          utilidad: i.utilidad,
        })),
        ventasDetalladas: all.ventasDetalladas ?? [],
      })),
    );
  }
}
