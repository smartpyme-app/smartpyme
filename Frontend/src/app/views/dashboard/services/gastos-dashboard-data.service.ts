import { Injectable } from '@angular/core';
import { Observable, forkJoin } from 'rxjs';
import { map, switchMap, startWith } from 'rxjs/operators';
import { DashboardAnalyticsApiService } from './dashboard-analytics-api.service';

@Injectable({
  providedIn: 'root',
})
export class GastosDashboardDataService {
  constructor(private analytics: DashboardAnalyticsApiService) {}

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
    return {
      metricasGastos: {
        gastosConIVA: cards?.gastosTotales ?? 0,
        gastosSinIVA: 0,
        gastosMesAnterior: cards?.gastosMesAnterior ?? 0,
        variacionGastos: cards?.variacion ?? 0,
        aumentoCostosPorcentaje: cards?.variacionPct ?? 0,
      },
      gastosPorMesConfig: {
        type: 'line',
        labels: (porMes ?? []).map((f: any) => f.anioMes),
        data: (porMes ?? []).map((f: any) => f.gastosConIva),
      },
      gastosVsPresupuestoConfig: {
        type: 'bar',
        labels: (vsPresupuesto ?? []).map((f: any) => f.anioMes),
        data: (vsPresupuesto ?? []).map((f: any) => f.gastosConIva),
        dataExtra: (vsPresupuesto ?? []).map((f: any) => f.presupuesto),
      },
      gastosVsAnioAnteriorConfig: {
        type: 'bar',
        labels: (vsAnioAnterior ?? []).map((f: any) => f.anioMes),
        data: (vsAnioAnterior ?? []).map((f: any) => f.anioActual),
        dataExtra: (vsAnioAnterior ?? []).map((f: any) => f.anioAnterior),
      },
    };
  }

  private mapearGastosPesado(raw: {
    porCategoria: any;
    porConcepto: any;
    porFormaPago: any;
    porProveedor: any;
    detalle: any;
  }): Record<string, unknown> {
    const { porCategoria, porConcepto, porFormaPago, porProveedor, detalle } =
      raw;
    return {
      gastosPorCategoriaConfig: {
        type: 'bar',
        horizontal: true,
        labels: (porCategoria ?? []).map((i: any) => i.name),
        data: (porCategoria ?? []).map((i: any) => i.amount),
      },
      gastosPorConceptoConfig: {
        type: 'bar',
        labels: (porConcepto ?? []).map((i: any) => i.name),
        data: (porConcepto ?? []).map((i: any) => i.amount),
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
      detalleGastos: (detalle ?? []).map((i: any) => ({
        fecha: i.fecha,
        proveedor: i.proveedor,
        concepto: i.concepto,
        documento: i.doc,
        correlativo: i.correlativo,
        gastosConIVA: i.gastosConIva,
      })),
    };
  }

  /**
   * Cards y gráficos principales primero; categorías, detalle y proveedores después.
   */
  obtenerGastosProgresivo(filtros: any = {}): Observable<any> {
    const api = this.analytics.baseUrl;
    const p = this.analytics.params(filtros);
    const pExtra = this.filtrosExtraQuery(filtros);
    const safe = (path: string) =>
      this.analytics.getSafe(`${api}${path}`);

    const critico$ = forkJoin({
      cards: safe(`/api/gastos/cards?${p}${pExtra}`),
      porMes: safe(`/api/gastos/por-mes?${p}${pExtra}`),
      vsPresupuesto: safe(`/api/gastos/vs-presupuesto?${p}${pExtra}`),
      vsAnioAnterior: safe(`/api/gastos/vs-anio-anterior?${p}${pExtra}`),
    }).pipe(map((r) => this.mapearGastosCritico(r)));

    const pesado$ = forkJoin({
      porCategoria: safe(`/api/gastos/por-categoria?${p}${pExtra}`),
      porConcepto: safe(`/api/gastos/por-concepto?${p}${pExtra}`),
      porFormaPago: safe(`/api/gastos/por-forma-pago?${p}${pExtra}`),
      porProveedor: safe(
        `/api/gastos/por-proveedor?${p}${pExtra}&limite=10`,
      ),
      detalle: safe(`/api/gastos/detalle?${p}${pExtra}`),
    }).pipe(map((r) => this.mapearGastosPesado(r)));

    return critico$.pipe(
      switchMap((c) =>
        pesado$.pipe(
          map((heavy) => ({ ...c, ...heavy })),
          startWith(c),
        ),
      ),
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
      detalle: safe(`/api/gastos/detalle?${p}${pExtra}`),
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
          detalle: all.detalle,
        }),
      })),
    );
  }
}
