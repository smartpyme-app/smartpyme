import { Injectable } from '@angular/core';
import { Observable, forkJoin, merge, of } from 'rxjs';
import { map, switchMap, startWith, scan, catchError } from 'rxjs/operators';
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
        }
      })),
      catchError(err => {
        console.error('Error loading /api/ventas/vs-presupuesto:', err);
        return of({ ventasVsPresupuestoConfig: { type: 'bar', labels: [], data: [], colors: ['#7CABFF', 'rgba(124, 171, 255, 0.4)'] } });
      })
    );

    const vsAnioAnterior$ = safe(`/api/ventas/vs-anio-anterior?${p}`).pipe(
      map(data => ({
        ventasVsAnioAnteriorConfig: {
          type: 'bar',
          labels: (data ?? []).map((f: any) => this.obtenerNombreMes(f.anioMes)),
          data: [
            { name: 'Año actual', data: (data ?? []).map((f: any) => f.anioActual || 0) },
            { name: 'Año anterior', data: (data ?? []).map((f: any) => f.anioAnterior || 0) }
          ],
          colors: ['#7CABFF', 'rgba(124, 171, 255, 0.4)'],
        }
      })),
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
          colors: ['#7CABFF'],
          labels: (data ?? []).map((i: any) => i.name),
          data: (data ?? []).map((i: any) => i.amount),
        }
      })),
      catchError(err => {
        console.error('Error loading /api/ventas/por-vendedor:', err);
        return of({ ventasPorVendedor: [], ventasPorVendedorChartConfig: { type: 'bar', labels: [], data: [], colors: ['#7CABFF'] } });
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

    const detalleClientes$ = safe(`/api/ventas/detalle-clientes?${p}`).pipe(
      map(data => ({
        ventasPorCliente: (data ?? []).map((i: any) => ({
          cliente: i.cliente,
          ultimaVenta: i.ultimaVenta || i.ultimaVentaMes || '-',
          dias: i.dias || 0,
          transacciones: i.transacciones || 0,
          ventasSinIva: i.ventasSinIva || 0,
          ventasConIva: i.ventasConIva || 0,
          ventas: i.ventasConIva || 0,
        }))
      })),
      catchError(err => {
        console.error('Error loading /api/ventas/detalle-clientes:', err);
        return of({ ventasPorCliente: [] });
      })
    );

    const detalleProductos$ = safe(`/api/ventas/detalle-productos?${p}`).pipe(
      map(data => ({
        ventasPorProducto: (data ?? []).map((i: any) => ({
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
        }))
      })),
      catchError(err => {
        console.error('Error loading /api/ventas/detalle-productos:', err);
        return of({ ventasPorProducto: [] });
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
      detalleClientes$,
      detalleProductos$,
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
