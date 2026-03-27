import { Injectable } from '@angular/core';
import { Observable, forkJoin, of } from 'rxjs';
import { map, catchError, switchMap, startWith } from 'rxjs/operators';
import { ApiService } from '@services/api.service';
import { HttpClient, HttpHeaders } from '@angular/common/http';
import { environment } from '../../../../environments/environment';

@Injectable({
  providedIn: 'root',
})
export class DashboardDataService {
  constructor(
    private apiService: ApiService,
    private http: HttpClient,
  ) {}

  // ─────────────────────────────────────────────
  // Helpers
  // ─────────────────────────────────────────────

  private get idEmpresa(): number {
    return (
      this.apiService.auth_user()?.id_empresa ??
      this.apiService.auth_user()?.empresa?.id ??
      0
    );
  }

  private get headers(): HttpHeaders {
    return new HttpHeaders({
      Authorization: `Bearer ${environment.goApiSecret}`,
    });
  }

  private get api(): string {
    return environment.goApiUrl;
  }

  private get(url: string): Observable<any> {
    return this.http
      .get<any>(url, {
        headers: this.headers,
        params: { saltarJWT: 'true' }, // se salta el JwtInterceptor
      })
      .pipe(
        catchError((err) => {
          console.error('Analytics API error:', url, err);
          return of(null);
        }),
      );
  }

  /** Normaliza sucursal: 'todas'/vacío → no param; string → tal cual; array → "37,44". */
  private sucursalQueryParam(sucursal: any): string | null {
    if (sucursal == null || sucursal === '' || sucursal === 'todas') {
      return null;
    }
    if (Array.isArray(sucursal)) {
      const parts = sucursal.map((s: any) => String(s).trim()).filter(Boolean);
      return parts.length ? parts.join(',') : null;
    }
    const s = String(sucursal).trim();
    return s || null;
  }

  private params(filtros: any, extras: string[] = []): string {
    const empresa = this.idEmpresa;
    const anio = filtros?.anio ?? new Date().getFullYear();
    let p = `empresa=${empresa}&anio=${anio}`;
    if (filtros?.mes) p += `&mes=${filtros.mes}`;
    const sv = this.sucursalQueryParam(filtros?.sucursal);
    if (sv) p += `&sucursal=${sv}`;
    extras.forEach((e) => {
      if (e) p += `&${e}`;
    });
    return p;
  }

  private paramsAnual(filtros: any, extras: string[] = []): string {
    const empresa = this.idEmpresa;
    const anio = filtros?.anio ?? new Date().getFullYear();
    let p = `empresa=${empresa}&anio=${anio}`;
    // NO incluye mes
    const sv = this.sucursalQueryParam(filtros?.sucursal);
    if (sv) p += `&sucursal=${sv}`;
    extras.forEach((e) => {
      if (e) p += `&${e}`;
    });
    return p;
  }

  // ─────────────────────────────────────────────
  // DASHBOARD 1 — RESULTADOS
  // ─────────────────────────────────────────────

  /** Petición con fallback para forkJoin (compartido con Ventas y otros dashboards). */
  private apiGetSafe(url: string): Observable<any> {
    return this.get(url).pipe(catchError(() => of(null)));
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
      ],
      ventasGastosConfig: {
        type: 'bar',
        labels: (porMes ?? []).map((f: any) => f.anioMes || f.mes),
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
    cashflowVentas: any;
    cashflowGastos: any;
    cxc30: any;
    cxp30: any;
  }): Record<string, unknown> {
    const { cashflow, cashflowVentas, cashflowGastos, cxc30, cxp30 } = raw;
    return {
      cashflow: {
        ventas: cashflowVentas || [],
        gastos: cashflowGastos || [],
        ingresosTotales: cashflow?.ingresosPercibidos || 0,
        egresosTotales: cashflow?.egresosRealizados || 0,
      },
      cuentas30: {
        cobrar: cxc30 || [],
        pagar: cxp30 || [],
      },
    };
  }

  /**
   * Métricas, gráfico por mes y tops CXC/CXP primero; flujo de efectivo y +30 días después.
   */
  obtenerResultadosProgresivo(filtros: any = {}): Observable<any> {
    const p = this.params(filtros);
    const pAnual = this.paramsAnual(filtros);

    const critico$ = forkJoin({
      cards: this.apiGetSafe(`${this.api}/api/resultados/cards?${pAnual}`),
      porMes: this.apiGetSafe(
        `${this.api}/api/resultados/ventas-gastos-mes?${p}`,
      ),
      cxc: this.apiGetSafe(
        `${this.api}/api/resultados/cxc-clientes?${pAnual}&limite=10`,
      ),
      cxp: this.apiGetSafe(
        `${this.api}/api/resultados/cxp-proveedores?${pAnual}&limite=10`,
      ),
    }).pipe(map((r) => this.mapearResultadosCritico(r)));

    const pesado$ = forkJoin({
      cashflow: this.apiGetSafe(`${this.api}/api/resultados/cashflow?${p}`),
      cashflowVentas: this.apiGetSafe(
        `${this.api}/api/resultados/cashflow-ventas-detalle?${p}`,
      ),
      cashflowGastos: this.apiGetSafe(
        `${this.api}/api/resultados/cashflow-gastos-detalle?${p}`,
      ),
      cxc30: this.apiGetSafe(
        `${this.api}/api/resultados/cxc-30dias?${pAnual}`,
      ),
      cxp30: this.apiGetSafe(
        `${this.api}/api/resultados/cxp-30dias?${pAnual}`,
      ),
    }).pipe(map((r) => this.mapearResultadosPesado(r)));

    return critico$.pipe(
      switchMap((c) =>
        pesado$.pipe(
          map((p) => ({ ...c, ...p })),
          startWith(c),
        ),
      ),
    );
  }

  obtenerResultados(filtros: any = {}): Observable<any> {
    const p = this.params(filtros);
    const pAnual = this.paramsAnual(filtros);

    return forkJoin({
      cards: this.apiGetSafe(`${this.api}/api/resultados/cards?${pAnual}`),
      porMes: this.apiGetSafe(
        `${this.api}/api/resultados/ventas-gastos-mes?${p}`,
      ),
      cxc: this.apiGetSafe(
        `${this.api}/api/resultados/cxc-clientes?${pAnual}&limite=10`,
      ),
      cxp: this.apiGetSafe(
        `${this.api}/api/resultados/cxp-proveedores?${pAnual}&limite=10`,
      ),
      cashflow: this.apiGetSafe(`${this.api}/api/resultados/cashflow?${p}`),
      cashflowVentas: this.apiGetSafe(
        `${this.api}/api/resultados/cashflow-ventas-detalle?${p}`,
      ),
      cashflowGastos: this.apiGetSafe(
        `${this.api}/api/resultados/cashflow-gastos-detalle?${p}`,
      ),
      cxc30: this.apiGetSafe(
        `${this.api}/api/resultados/cxc-30dias?${pAnual}`,
      ),
      cxp30: this.apiGetSafe(
        `${this.api}/api/resultados/cxp-30dias?${pAnual}`,
      ),
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
          cashflowVentas: all.cashflowVentas,
          cashflowGastos: all.cashflowGastos,
          cxc30: all.cxc30,
          cxp30: all.cxp30,
        }),
      })),
    );
  }

  // ─────────────────────────────────────────────
  // DASHBOARD 2 — VENTAS
  // ─────────────────────────────────────────────

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

  /**
   * Carga en dos fases: métricas y gráficos principales primero; listas y detalle después.
   * Cada emisión es un objeto nuevo (spread) para OnPush.
   */
  obtenerVentasProgresivo(filtros: any = {}): Observable<any> {
    const p = this.params(filtros);
    const pVendedor = filtros?.vendedor ? `&vendedor=${filtros.vendedor}` : '';

    const critico$ = forkJoin({
      cards: this.apiGetSafe(`${this.api}/api/ventas/cards?${p}`),
      porMes: this.apiGetSafe(`${this.api}/api/ventas/por-mes?${p}`),
      vsPresupuesto: this.apiGetSafe(
        `${this.api}/api/ventas/vs-presupuesto?${p}`,
      ),
      vsAnioAnterior: this.apiGetSafe(
        `${this.api}/api/ventas/vs-anio-anterior?${p}`,
      ),
    }).pipe(map((r) => this.mapearVentasCritico(r)));

    const pesado$ = forkJoin({
      porCanal: this.apiGetSafe(`${this.api}/api/ventas/por-canal?${p}`),
      porVendedor: this.apiGetSafe(
        `${this.api}/api/ventas/por-vendedor?${p}${pVendedor}`,
      ),
      porFormaPago: this.apiGetSafe(
        `${this.api}/api/ventas/por-forma-pago?${p}`,
      ),
      porCategoria: this.apiGetSafe(
        `${this.api}/api/ventas/por-categoria?${p}`,
      ),
      topProductos: this.apiGetSafe(
        `${this.api}/api/ventas/top-productos?${p}&limite=15`,
      ),
      topClientes: this.apiGetSafe(
        `${this.api}/api/ventas/top-clientes?${p}&limite=25`,
      ),
      detalleClientes: this.apiGetSafe(
        `${this.api}/api/ventas/detalle-clientes?${p}`,
      ),
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
    const p = this.params(filtros);
    const pVendedor = filtros?.vendedor ? `&vendedor=${filtros.vendedor}` : '';

    return forkJoin({
      cards: this.apiGetSafe(`${this.api}/api/ventas/cards?${p}`),
      porMes: this.apiGetSafe(`${this.api}/api/ventas/por-mes?${p}`),
      vsPresupuesto: this.apiGetSafe(
        `${this.api}/api/ventas/vs-presupuesto?${p}`,
      ),
      vsAnioAnterior: this.apiGetSafe(
        `${this.api}/api/ventas/vs-anio-anterior?${p}`,
      ),
      porCanal: this.apiGetSafe(`${this.api}/api/ventas/por-canal?${p}`),
      porVendedor: this.apiGetSafe(
        `${this.api}/api/ventas/por-vendedor?${p}${pVendedor}`,
      ),
      porFormaPago: this.apiGetSafe(
        `${this.api}/api/ventas/por-forma-pago?${p}`,
      ),
      porCategoria: this.apiGetSafe(
        `${this.api}/api/ventas/por-categoria?${p}`,
      ),
      topProductos: this.apiGetSafe(
        `${this.api}/api/ventas/top-productos?${p}&limite=15`,
      ),
      topClientes: this.apiGetSafe(
        `${this.api}/api/ventas/top-clientes?${p}&limite=25`,
      ),
      detalleClientes: this.apiGetSafe(
        `${this.api}/api/ventas/detalle-clientes?${p}`,
      ),
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

  // ─────────────────────────────────────────────
  // DASHBOARD 4 — GASTOS
  // ─────────────────────────────────────────────

  obtenerGastos(filtros: any = {}): Observable<any> {
    const p = this.params(filtros);
    const pTipo = filtros?.tipoGasto ? `&tipo_gasto=${filtros.tipoGasto}` : '';
    const pEstado = filtros?.estadoGasto
      ? `&estado_gasto=${filtros.estadoGasto}`
      : '';
    const pProveedor = filtros?.proveedor
      ? `&proveedor=${filtros.proveedor}`
      : '';
    const pExtra = `${pTipo}${pEstado}${pProveedor}`;

    return forkJoin({
      cards: this.get(`${this.api}/api/gastos/cards?${p}${pExtra}`),
      porMes: this.get(`${this.api}/api/gastos/por-mes?${p}${pExtra}`),
      vsPresupuesto: this.get(
        `${this.api}/api/gastos/vs-presupuesto?${p}${pExtra}`,
      ),
      vsAnioAnterior: this.get(
        `${this.api}/api/gastos/vs-anio-anterior?${p}${pExtra}`,
      ),
      porCategoria: this.get(
        `${this.api}/api/gastos/por-categoria?${p}${pExtra}`,
      ),
      porConcepto: this.get(
        `${this.api}/api/gastos/por-concepto?${p}${pExtra}`,
      ),
      porFormaPago: this.get(
        `${this.api}/api/gastos/por-forma-pago?${p}${pExtra}`,
      ),
      porProveedor: this.get(
        `${this.api}/api/gastos/por-proveedor?${p}${pExtra}&limite=10`,
      ),
      detalle: this.get(`${this.api}/api/gastos/detalle?${p}${pExtra}`),
    }).pipe(
      map(
        ({
          cards,
          porMes,
          vsPresupuesto,
          vsAnioAnterior,
          porCategoria,
          porConcepto,
          porFormaPago,
          porProveedor,
          detalle,
        }) => ({
          // Cards
          metricasGastos: {
            gastosConIVA: cards?.gastosTotales ?? 0,
            gastosSinIVA: 0,
            gastosMesAnterior: cards?.gastosMesAnterior ?? 0,
            variacionGastos: cards?.variacion ?? 0,
            aumentoCostosPorcentaje: cards?.variacionPct ?? 0,
          },

          // Gráfico por mes
          gastosPorMesConfig: {
            type: 'line',
            labels: (porMes ?? []).map((f: any) => f.anioMes),
            data: (porMes ?? []).map((f: any) => f.gastosConIva),
          },

          // Vs presupuesto
          gastosVsPresupuestoConfig: {
            type: 'bar',
            labels: (vsPresupuesto ?? []).map((f: any) => f.anioMes),
            data: (vsPresupuesto ?? []).map((f: any) => f.gastosConIva),
            dataExtra: (vsPresupuesto ?? []).map((f: any) => f.presupuesto),
          },

          // Vs año anterior
          gastosVsAnioAnteriorConfig: {
            type: 'bar',
            labels: (vsAnioAnterior ?? []).map((f: any) => f.anioMes),
            data: (vsAnioAnterior ?? []).map((f: any) => f.anioActual),
            dataExtra: (vsAnioAnterior ?? []).map((f: any) => f.anioAnterior),
          },

          // Por categoría (barras horizontal)
          gastosPorCategoriaConfig: {
            type: 'bar',
            horizontal: true,
            labels: (porCategoria ?? []).map((i: any) => i.name),
            data: (porCategoria ?? []).map((i: any) => i.amount),
          },

          // Por concepto (barras vertical)
          gastosPorConceptoConfig: {
            type: 'bar',
            labels: (porConcepto ?? []).map((i: any) => i.name),
            data: (porConcepto ?? []).map((i: any) => i.amount),
          },

          // Por forma de pago (treemap / doughnut)
          gastosPorFormaPagoConfig: {
            type: 'doughnut',
            labels: (porFormaPago ?? []).map((i: any) => i.name),
            data: (porFormaPago ?? []).map((i: any) => i.amount),
          },

          // Por proveedor
          gastosPorProveedor: (porProveedor ?? []).map((i: any) => ({
            name: i.name,
            amount: i.amount,
          })),

          // Detalle
          detalleGastos: (detalle ?? []).map((i: any) => ({
            fecha: i.fecha,
            proveedor: i.proveedor,
            concepto: i.concepto,
            documento: i.doc,
            correlativo: i.correlativo,
            gastosConIVA: i.gastosConIva,
          })),
        }),
      ),
    );
  }

  // ─────────────────────────────────────────────
  // DASHBOARD 5 — CUENTAS
  // ─────────────────────────────────────────────

  obtenerCuentas(filtros: any = {}): Observable<any> {
    const p = this.params(filtros);
    const pCXC = filtros?.cliente ? `&cliente=${filtros.cliente}` : '';
    const pCXP = filtros?.proveedor ? `&proveedor=${filtros.proveedor}` : '';
    const pEst = filtros?.estadoVigencia
      ? `&estado_vigencia=${filtros.estadoVigencia}`
      : '';
    const pCat = filtros?.categoria ? `&categoria=${filtros.categoria}` : '';

    return forkJoin({
      cxcCards: this.get(
        `${this.api}/api/cuentas/cxc/cards?${p}${pCXC}${pEst}`,
      ),
      cxcVigencia: this.get(
        `${this.api}/api/cuentas/cxc/vigencia?${p}${pCXC}${pEst}`,
      ),
      cxcClientes: this.get(
        `${this.api}/api/cuentas/cxc/clientes?${p}${pCXC}&limite=10`,
      ),
      cxcDetalle: this.get(
        `${this.api}/api/cuentas/cxc/detalle?${p}${pCXC}${pEst}`,
      ),
      cxpCards: this.get(
        `${this.api}/api/cuentas/cxp/cards?${p}${pCXP}${pEst}${pCat}`,
      ),
      cxpVigencia: this.get(
        `${this.api}/api/cuentas/cxp/vigencia?${p}${pCXP}${pEst}${pCat}`,
      ),
      cxpProveedores: this.get(
        `${this.api}/api/cuentas/cxp/proveedores?${p}${pCXP}&limite=10`,
      ),
      cxpCategorias: this.get(
        `${this.api}/api/cuentas/cxp/categorias?${p}${pCXP}${pCat}`,
      ),
      cxpDetalle: this.get(
        `${this.api}/api/cuentas/cxp/detalle?${p}${pCXP}${pEst}${pCat}`,
      ),
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
          cxpCategorias,
          cxpDetalle,
        }) => ({
          // Cards CXC
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

          // Donut CXC vigencia
          cuentasPorVigenciaConfig: {
            type: 'doughnut',
            labels: (cxcVigencia ?? []).map((i: any) => i.estadoVigencia),
            data: (cxcVigencia ?? []).map((i: any) => i.total),
          },

          // Top clientes CXC
          cuentasPorCobrarClientes: (cxcClientes ?? []).map((i: any) => ({
            name: i.name,
            amount: i.amount,
          })),

          // Detalle CXC
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

          // Donut CXP vigencia
          cuentasPorPagarVigenciaConfig: {
            type: 'doughnut',
            labels: (cxpVigencia ?? []).map((i: any) => i.estadoVigencia),
            data: (cxpVigencia ?? []).map((i: any) => i.total),
          },

          // Top proveedores CXP
          cuentasPorPagarProveedores: (cxpProveedores ?? []).map((i: any) => ({
            name: i.name,
            amount: i.amount,
          })),

          // Detalle CXP
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

  // ─────────────────────────────────────────────
  // DASHBOARD 6 — INVENTARIO
  // ─────────────────────────────────────────────

  obtenerInventario(filtros: any = {}): Observable<any> {
    const empresa = this.idEmpresa;
    const pBase = `empresa=${empresa}`;
    const pAnio = filtros?.anio ? `&anio=${filtros.anio}` : '';
    const pMes = filtros?.mes ? `&mes=${filtros.mes}` : '';
    const pSuc =
      filtros?.sucursal && filtros.sucursal !== 'todas'
        ? `&sucursal=${filtros.sucursal}`
        : '';
    const pCat = filtros?.categoria ? `&categoria=${filtros.categoria}` : '';
    const pProd = filtros?.producto ? `&producto=${filtros.producto}` : '';
    const pProv = filtros?.proveedor ? `&proveedor=${filtros.proveedor}` : '';
    const pSnap = `${pBase}${pSuc}${pCat}${pProd}${pProv}`;
    const pMov = `${pBase}${pAnio}${pMes}${pSuc}${pCat}${pProd}${pProv}`;

    return forkJoin({
      cards: this.get(`${this.api}/api/inventario/cards?${pSnap}`),
      porCategoria: this.get(
        `${this.api}/api/inventario/por-categoria?${pSnap}`,
      ),
      detalleProductos: this.get(
        `${this.api}/api/inventario/detalle-productos?${pSnap}`,
      ),
      esCards: this.get(`${this.api}/api/inventario/es/cards?${pMov}`),
      esPorMes: this.get(`${this.api}/api/inventario/es/por-mes?${pMov}`),
      ajustesCards: this.get(
        `${this.api}/api/inventario/ajustes/cards?${pMov}`,
      ),
      ajustesDetalle: this.get(
        `${this.api}/api/inventario/ajustes/detalle?${pMov}`,
      ),
    }).pipe(
      map(
        ({
          cards,
          porCategoria,
          detalleProductos,
          esCards,
          esPorMes,
          ajustesCards,
          ajustesDetalle,
        }) => ({
          // Cards sección 1
          metricasInventario: {
            productosEnStock: cards?.productosEnStock ?? 0,
            promedioInvertido: cards?.promedioInvertido ?? 0,
            ventasEsperadas: cards?.ventasEsperadas ?? 0,
            utilidadEsperada: cards?.utilidadEsperada ?? 0,
          },

          // Stock por categoría (barras horizontal)
          stockPorCategoriaConfig: {
            type: 'bar',
            horizontal: true,
            labels: (porCategoria ?? []).map((i: any) => i.categoria),
            data: (porCategoria ?? []).map((i: any) => i.stockUnidades),
          },

          // Detalle de productos
          detalleInventario: (detalleProductos ?? []).map((i: any) => ({
            producto: i.producto,
            stock: i.stock,
            costo: i.costo,
            inversionPromedio: i.inversionPromedio,
            precio: i.precio,
            ventasEsperadas: i.ventasEsperadas,
            utilidadEsperada: i.utilidadEsperada,
          })),

          // Cards sección 2 — Entradas y Salidas
          entradasSalidas: {
            productosEnStock: esCards?.productosEnStock ?? 0,
            entradas: esCards?.entradas ?? 0,
            salidas: esCards?.salidas ?? 0,
            utilidadEsperada: esCards?.utilidadEsperada ?? 0,
          },

          // Gráfico entradas y salidas por mes
          entradasSalidasPorMesConfig: {
            type: 'bar',
            labels: (esPorMes ?? []).map((i: any) => i.nombreMes),
            data: (esPorMes ?? []).map((i: any) => i.entradasUnidades),
            dataExtra: (esPorMes ?? []).map((i: any) => i.salidasUnidades),
          },

          // Cards sección 3 — Ajustes
          ajustes: {
            productosEnStock: ajustesCards?.productosEnStock ?? 0,
            unidadesPerdidas: ajustesCards?.unidadesPerdidas ?? 0,
            unidadesRecuperadas: ajustesCards?.unidadesRecuperadas ?? 0,
            montoTotalRecuperado: ajustesCards?.montoRecuperado ?? 0,
          },

          // Detalle ajustes
          detalleAjustes: (ajustesDetalle ?? []).map((i: any) => ({
            mes: i.mes,
            nombreMes: i.nombreMes,
            producto: i.producto,
            concepto: i.concepto,
            stockInicial: i.stockInicial,
            stockReal: i.stockReal,
            ajuste: i.ajuste,
            costoTotal: i.costoTotal,
            unidadesPerdidas: i.unidadesPerdidas,
            unidadesRecuperadas: i.unidadesRecuperadas,
          })),
        }),
      ),
    );
  }

  // ─────────────────────────────────────────────
  // Método legacy — mantiene compatibilidad
  // con el DashboardComponent actual
  // ─────────────────────────────────────────────

  obtenerDatosPorFiltro(filtros: any): Observable<any> {
    const seccion = filtros?.seccion ?? 'Resultados';
    switch (seccion) {
      case 'Ventas':
        return this.obtenerVentasProgresivo(filtros);
      case 'Gastos':
        return this.obtenerGastos(filtros);
      case 'Control de cuentas':
        return this.obtenerCuentas(filtros);
      case 'Inventario':
        return this.obtenerInventario(filtros);
      default:
        return this.obtenerResultadosProgresivo(filtros);
    }
  }
}
