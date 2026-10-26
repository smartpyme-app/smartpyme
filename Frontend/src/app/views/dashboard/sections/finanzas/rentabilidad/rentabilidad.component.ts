import { Component, Input, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ApiService } from '@services/api.service';
import {
  formatEmpresaCurrency,
  formatEmpresaCurrencyInt,
} from '@helpers/currency-format.helper';
import {
  RentabilidadDimensionId,
  RentabilidadHorizonteId,
  RentabilidadKpi,
  RentabilidadMontoPct,
  RentabilidadProductoFila,
  RentabilidadClienteDetalle,
  RentabilidadClienteFila,
  RentabilidadProductoDetalle,
  RentabilidadProductoTablaFila,
  RentabilidadProveedorDetalle,
  RentabilidadProveedorFila,
  RentabilidadProveedorResumenFila,
  RentabilidadProyectoBarTono,
  RentabilidadProyectoDetalle,
  RentabilidadProyectoFila,
  RentabilidadProyectoResultadoFila,
  RentabilidadSucursalFila,
} from './rentabilidad.model';
import { RENTABILIDAD_MOCK } from './rentabilidad.mock';
import {
  factorHorizonte,
  kpisDesdeCliente,
  kpisDesdeProducto,
  kpisDesdeProveedor,
  kpisDesdeProyecto,
  kpisDesdeSucursal,
  RentabilidadKpiContexto,
} from './rentabilidad-kpis.builder';
import { detalleClienteDesdeFila } from './rentabilidad-cliente-detalle.builder';
import { detalleProveedorDesdeFila } from './rentabilidad-proveedor-detalle.builder';
import { detalleProyectoDesdeFila } from './rentabilidad-proyecto-detalle.builder';
import { detalleProductoDesdeFila } from './rentabilidad-producto-detalle.builder';
import { MetricCard } from '../../../models/chart-config.model';
import { ChartCardComponent } from '../../../components/chart-card/chart-card.component';

export interface RentKpiChartItem {
  metric: MetricCard;
  linea?: string;
  sublinea?: string;
  accion?: string;
}

@Component({
  selector: 'app-rentabilidad',
  templateUrl: './rentabilidad.component.html',
  styleUrls: ['./rentabilidad.component.css'],
  standalone: true,
  imports: [CommonModule, ChartCardComponent],
})
export class RentabilidadComponent {
  private readonly api = inject(ApiService);

  @Input() periodoTitulo = 'septiembre 2026';
  /** En «Alertas y resúmenes» los KPIs superiores son fijos (alertas empresa). */
  @Input() kpiModo: 'dinamico' | 'alertas' = 'dinamico';

  readonly data = RENTABILIDAD_MOCK;

  readonly horizonteOpciones: { id: RentabilidadHorizonteId; label: string }[] =
    [
      { id: 'mes', label: 'Mes' },
      { id: 'trimestre', label: 'Trimestre' },
      { id: 'anio', label: 'Año' },
    ];

  readonly dimensionOpciones: {
    id: RentabilidadDimensionId;
    label: string;
  }[] = [
    { id: 'sucursal', label: 'Sucursal' },
    { id: 'producto', label: 'Producto' },
    { id: 'cliente', label: 'Cliente' },
    { id: 'proveedor', label: 'Proveedor' },
    { id: 'proyecto', label: 'Proyecto' },
  ];

  horizonte: RentabilidadHorizonteId = 'mes';
  dimension: RentabilidadDimensionId = 'sucursal';
  sucursalAbierta: RentabilidadSucursalFila | null =
    RENTABILIDAD_MOCK.filasSucursal.find((fila) => fila.nombre === 'San Miguel') ?? null;
  productoAbierto: RentabilidadProductoTablaFila | null =
    RENTABILIDAD_MOCK.filasProducto.find(
      (fila) => fila.nombre === 'Lámina galvanizada 12 ft',
    ) ?? null;
  clienteAbierto: RentabilidadClienteFila | null =
    RENTABILIDAD_MOCK.filasCliente.find(
      (fila) => fila.nombre === 'Constructora Meléndez',
    ) ?? null;
  proveedorAbierto: RentabilidadProveedorFila | null =
    RENTABILIDAD_MOCK.filasProveedor.find(
      (fila) => fila.nombre === 'Aceros de Centroamérica',
    ) ?? null;
  proyectoAbierto: RentabilidadProyectoFila | null =
    RENTABILIDAD_MOCK.filasProyecto.find(
      (fila) => fila.nombre === 'Residencial Los Almendros',
    ) ?? null;

  seleccionarHorizonte(id: RentabilidadHorizonteId): void {
    this.horizonte = id;
  }

  kpiChartItems(): RentKpiChartItem[] {
    return this.kpisActivos().map((kpi) => ({
      metric: { title: kpi.titulo, value: kpi.valor },
      linea: kpi.linea,
      sublinea: kpi.sublinea,
      accion: kpi.accion,
    }));
  }

  kpisActivos(): RentabilidadKpi[] {
    if (this.kpiModo === 'alertas') {
      return this.data.kpis;
    }

    const ctx: RentabilidadKpiContexto = {
      horizonte: this.horizonte,
      formatMonto: (value) => this.formatMonto(value),
      formatPrecio: (value) => this.formatPrecio(value),
      formatPct: (value, digits) => this.formatPct(value, digits),
      textoInventario: (fila) => this.textoInventario(fila),
    };

    switch (this.dimension) {
      case 'sucursal':
        if (this.sucursalAbierta?.kpisToolbar) {
          return this.sucursalAbierta.kpisToolbar;
        }
        if (this.sucursalAbierta) {
          return kpisDesdeSucursal(this.sucursalAbierta, ctx);
        }
        break;
      case 'producto':
        if (this.productoAbierto?.kpisToolbar) {
          return this.productoAbierto.kpisToolbar;
        }
        if (this.productoAbierto) {
          return kpisDesdeProducto(this.productoAbierto, ctx);
        }
        break;
      case 'cliente':
        if (this.clienteAbierto?.kpisToolbar) {
          return this.clienteAbierto.kpisToolbar;
        }
        if (this.clienteAbierto) {
          return kpisDesdeCliente(this.clienteAbierto, ctx);
        }
        break;
      case 'proveedor':
        if (this.proveedorAbierto?.kpisToolbar) {
          return this.proveedorAbierto.kpisToolbar;
        }
        if (this.proveedorAbierto) {
          return kpisDesdeProveedor(this.proveedorAbierto, ctx);
        }
        break;
      case 'proyecto':
        if (this.proyectoAbierto?.kpisToolbar) {
          return this.proyectoAbierto.kpisToolbar;
        }
        if (this.proyectoAbierto) {
          return kpisDesdeProyecto(this.proyectoAbierto, ctx);
        }
        break;
    }

    return this.data.kpis;
  }

  seleccionarDimension(id: RentabilidadDimensionId): void {
    this.dimension = id;
    if (id === 'sucursal' && !this.sucursalAbierta) {
      this.sucursalAbierta =
        this.data.filasSucursal.find((fila) => fila.nombre === 'San Miguel') ??
        this.data.filasSucursal[0] ??
        null;
    }
    if (id === 'producto' && !this.productoAbierto) {
      this.productoAbierto =
        this.data.filasProducto.find(
          (fila) => fila.nombre === 'Lámina galvanizada 12 ft',
        ) ??
        this.data.filasProducto.find((fila) => fila.detalle) ??
        null;
    }
    if (id === 'cliente' && !this.clienteAbierto) {
      this.clienteAbierto =
        this.data.filasCliente.find(
          (fila) => fila.nombre === 'Constructora Meléndez',
        ) ??
        this.data.filasCliente.find((fila) => fila.detalle) ??
        null;
    }
    if (id === 'proveedor' && !this.proveedorAbierto) {
      this.proveedorAbierto =
        this.data.filasProveedor.find(
          (fila) => fila.nombre === 'Aceros de Centroamérica',
        ) ??
        this.data.filasProveedor.find((fila) => fila.detalle) ??
        null;
    }
    if (id === 'proyecto' && !this.proyectoAbierto) {
      this.proyectoAbierto =
        this.data.filasProyecto.find(
          (fila) => fila.nombre === 'Residencial Los Almendros',
        ) ??
        this.data.filasProyecto[0] ??
        null;
    }
  }

  abrirSucursal(fila: RentabilidadSucursalFila): void {
    this.sucursalAbierta = fila;
  }

  abrirProducto(fila: RentabilidadProductoTablaFila): void {
    this.productoAbierto = fila;
  }

  montoHorizonte(value: number): number {
    return Math.round(value * factorHorizonte(this.horizonte));
  }

  unidadesHorizonte(value: number): number {
    return Math.round(value * factorHorizonte(this.horizonte));
  }

  proyectoDetalle(proy: RentabilidadProyectoFila): RentabilidadProyectoDetalle {
    const base = detalleProyectoDesdeFila(proy);
    const f = factorHorizonte(this.horizonte);
    if (f === 1) {
      return base;
    }
    const escalaM = (n: number) => Math.round(n * f);
    return {
      ...base,
      porFacturar: escalaM(base.porFacturar),
      facturado: escalaM(base.facturado),
      cobrado: escalaM(base.cobrado),
      gastado: escalaM(base.gastado),
      pagadoObra: escalaM(base.pagadoObra),
      utilidadFecha: escalaM(base.utilidadFecha),
      resultado: base.resultado.map((row) =>
        row.formato === 'monto'
          ? { ...row, fecha: escalaM(row.fecha) }
          : row,
      ),
      costosCategoria: base.costosCategoria.map((c) => ({
        ...c,
        monto: escalaM(c.monto),
      })),
      presupuesto: base.presupuesto.map((p) => ({
        ...p,
        gastado: escalaM(p.gastado),
        disponible: escalaM(p.disponible),
      })),
      presupuestoTotal: {
        ...base.presupuestoTotal,
        gastado: escalaM(base.presupuestoTotal.gastado),
        disponible: escalaM(base.presupuestoTotal.disponible),
      },
      materiales: base.materiales.map((m) => ({
        ...m,
        difPrecio: m.difPrecio === null ? null : escalaM(m.difPrecio),
      })),
      chartGastado: base.chartGastado.map(escalaM),
      chartFacturado: base.chartFacturado.map(escalaM),
      chartCobrado: base.chartCobrado.map(escalaM),
    };
  }

  proveedorDetalle(prov: RentabilidadProveedorFila): RentabilidadProveedorDetalle {
    const base = detalleProveedorDesdeFila(prov);
    const f = factorHorizonte(this.horizonte);
    if (f === 1) {
      return base;
    }
    const escalaResumen = (filas: RentabilidadProveedorResumenFila[]) =>
      filas.map((fila) =>
        fila.formato === 'monto'
          ? {
              ...fila,
              anual: Math.round(fila.anual * f),
              promedio: Math.round(fila.promedio * f),
              mes: Math.round(fila.mes * f),
            }
          : fila,
      );
    return {
      ...base,
      resumen: escalaResumen(base.resumen),
      chartCompras: base.chartCompras.map((v) => Math.round(v * f)),
      chartVentas: base.chartVentas.map((v) => Math.round(v * f)),
      productoCompras: Math.round(base.productoCompras * f),
      porPagar: base.porPagar.map((p) => ({ ...p, monto: Math.round(p.monto * f) })),
      clientes: base.clientes.map((c) => ({
        ...c,
        ventas: Math.round(c.ventas * f),
      })),
    };
  }

  clienteDetalle(cli: RentabilidadClienteFila): RentabilidadClienteDetalle {
    const base = detalleClienteDesdeFila(cli);
    const f = factorHorizonte(this.horizonte);
    if (f === 1) {
      return base;
    }
    return {
      ...base,
      descuentoMonto: Math.round(base.descuentoMonto * f),
      cxcVencido: Math.round(base.cxcVencido * f),
      puente: base.puente.map((paso) => ({
        ...paso,
        monto: Math.round(paso.monto * f),
      })),
      facturas: base.facturas.map((fac) => ({
        ...fac,
        monto: Math.round(fac.monto * f),
      })),
      productos: base.productos.map((prod) => ({
        ...prod,
        compro: Math.round(prod.compro * f),
      })),
      sucursales: base.sucursales.map((suc) => ({
        ...suc,
        monto: Math.round(suc.monto * f),
      })),
    };
  }

  productoDetalle(prod: RentabilidadProductoTablaFila): RentabilidadProductoDetalle {
    const base = detalleProductoDesdeFila(prod);
    const f = factorHorizonte(this.horizonte);
    if (f === 1) {
      return base;
    }
    return {
      ...base,
      sucursales: base.sucursales.map((s) => ({
        ...s,
        monto: Math.round(s.monto * f),
      })),
      vendidoConDescuentoMonto: Math.round(base.vendidoConDescuentoMonto * f),
      clientes: base.clientes.map((c) => ({
        ...c,
        unidades: Math.round(c.unidades * f),
      })),
    };
  }

  abrirCliente(fila: RentabilidadClienteFila): void {
    this.clienteAbierto = fila;
  }

  abrirProveedor(fila: RentabilidadProveedorFila): void {
    this.proveedorAbierto = fila;
  }

  abrirProyecto(fila: RentabilidadProyectoFila): void {
    this.proyectoAbierto = fila;
  }

  claseBarraProyecto(tono: RentabilidadProyectoBarTono): string {
    if (tono === 'rojo') {
      return 'rent-barra-fill--rojo';
    }
    if (tono === 'verde') {
      return 'rent-barra-fill--verde';
    }
    return 'rent-barra-fill--azul';
  }

  claseBarraPresupuestoUsado(tono: 'azul' | 'naranja'): string {
    return tono === 'naranja' ? 'rent-barra-fill--naranja' : 'rent-barra-fill--azul';
  }

  formatResultadoProyecto(
    fila: RentabilidadProyectoResultadoFila,
    columna: 'planeado' | 'fecha',
  ): string {
    const value = fila[columna];
    return fila.formato === 'pct' ? this.formatPct(value) : this.formatMonto(value);
  }

  formatDifPrecio(value: number): string {
    const abs = this.formatMonto(value).replace(/^-/, '');
    return value > 0 ? `+${abs}` : `-${abs}`;
  }

  maxProyectoAcumulado(detalle: RentabilidadProyectoDetalle): number {
    return Math.max(
      ...detalle.chartGastado,
      ...detalle.chartFacturado,
      ...detalle.chartCobrado,
      1,
    );
  }

  proyectoAcumuladoLinea(
    valores: number[],
    detalle: RentabilidadProyectoDetalle,
    w = 300,
    h = 88,
  ): string {
    const max = this.maxProyectoAcumulado(detalle);
    return this.seriePath(valores, w, h, 0, max);
  }

  proyectoAcumuladoArea(
    valores: number[],
    detalle: RentabilidadProyectoDetalle,
    w = 300,
    h = 88,
  ): string {
    const linea = this.proyectoAcumuladoLinea(valores, detalle, w, h);
    if (!linea) {
      return '';
    }
    return `${linea} L ${w},${h} L 0,${h} Z`;
  }

  anchoRelativoCategoriaGasto(
    pct: number,
    filas: { pctGasto: number }[],
  ): number {
    const max = Math.max(...filas.map((f) => f.pctGasto), 1);
    return (pct / max) * 100;
  }

  formatResumenValor(
    fila: RentabilidadProveedorResumenFila,
    columna: 'anual' | 'promedio' | 'mes',
  ): string {
    const value = fila[columna];
    if (fila.formato === 'pct' && columna === 'promedio' && value === 0) {
      return '—';
    }
    if (!value) {
      return '';
    }
    return fila.formato === 'pct' ? this.formatPct(value) : this.formatMonto(value);
  }

  maxProveedorChart(detalle: RentabilidadProveedorDetalle): number {
    return Math.max(...detalle.chartCompras, ...detalle.chartVentas, 1);
  }

  alturaProveedorBarra(valor: number, max: number): number {
    return Math.round((valor / max) * 100);
  }

  margenProveedorPath(detalle: RentabilidadProveedorDetalle): string {
    return this.seriePath(detalle.chartMargen, 280, 56);
  }

  tonoPuenteCliente(etiqueta: string): string {
    if (etiqueta === 'Le queda') {
      return 'verde';
    }
    if (etiqueta === 'Ganancia') {
      return 'azul';
    }
    if (etiqueta.startsWith('Descuentos') || etiqueta === 'Esperar su pago') {
      return 'rosa';
    }
    if (etiqueta === 'Ventas a precio de lista' || etiqueta === 'Ventas netas') {
      return 'neutral';
    }
    return 'naranja';
  }

  tituloTabla(): string {
    if (this.dimension === 'cliente') {
      return `¿Cuánto le deja cada cliente? — ${this.periodoTitulo}`;
    }
    if (this.dimension === 'proveedor') {
      return `¿Cuánto le dejan sus proveedores? — ${this.periodoTitulo}`;
    }
    const dim = this.dimensionOpciones.find((d) => d.id === this.dimension);
    const nombre = dim?.label?.toLowerCase() ?? 'sucursal';
    return `Rentabilidad por ${nombre} — ${this.periodoTitulo}`;
  }

  formatDeCada100(value: number): string {
    return `$${value}`;
  }

  formatGanaPorDolar(value: number): string {
    return this.formatPrecio(value);
  }

  formatPrecio6m(value: number): string {
    const sign = value > 0 ? '+' : '';
    return `${sign}${value.toFixed(1)}%`;
  }

  claseValorTono(tono: 'normal' | 'naranja' | 'verde' | 'rojo'): string {
    if (tono === 'verde') {
      return 'rent-text-pos';
    }
    if (tono === 'rojo' || tono === 'naranja') {
      return tono === 'rojo' ? 'rent-text-neg' : 'rent-text-warn';
    }
    return '';
  }

  textoPago(fila: RentabilidadClienteFila): string {
    if (fila.pagoContado) {
      return 'contado';
    }
    return `${fila.pagoDias} (pactó ${fila.pagoPactado})`;
  }

  tonoPago(fila: RentabilidadClienteFila): 'pos' | 'warn' | 'neg' | 'normal' {
    if (fila.pagoContado) {
      return 'pos';
    }
    const dias = fila.pagoDias ?? 0;
    if (dias <= 35) {
      return 'pos';
    }
    if (dias <= 50) {
      return 'warn';
    }
    return 'neg';
  }

  etiquetaComparacion(): string {
    const partes = this.periodoTitulo.trim().split(/\s+/);
    const mes = (partes[0] || 'sep').slice(0, 3).toUpperCase();
    const anio = Number(partes[1]);
    const previo = Number.isFinite(anio) ? anio - 1 : new Date().getFullYear() - 1;
    return `${mes} ${previo}`;
  }

  formatMonto(value: number): string {
    const empresa = this.api.auth_user()?.empresa;
    const abs = formatEmpresaCurrencyInt(Math.abs(value), empresa);
    return value < 0 ? `-${abs}` : abs;
  }

  formatPrecio(value: number): string {
    return formatEmpresaCurrency(value, this.api.auth_user()?.empresa);
  }

  etiquetaComparacionCorta(): string {
    return this.etiquetaComparacion().toLowerCase();
  }

  formatPct(value: number, digits = 1): string {
    return `${value.toFixed(digits)}%`;
  }

  formatVs(value: number): string {
    const n = value.toFixed(1);
    return value > 0 ? `+${n}%` : `${n}%`;
  }

  gastosPctVentas(fila: RentabilidadSucursalFila): string {
    if (!fila.ventas) {
      return '0%';
    }
    return this.formatPct((fila.gastos / fila.ventas) * 100);
  }

  formatUnidades(value: number): string {
    return new Intl.NumberFormat('en-US').format(value);
  }

  textoInventario(fila: RentabilidadProductoTablaFila): string {
    const inv = fila.inventario;
    if (inv.tipo === 'dias') {
      return `${inv.dias} días`;
    }
    if (inv.tipo === 'agota') {
      return `se agota en ~${inv.dias} días`;
    }
    return `${inv.dias} días sin venta · ${this.formatMonto(inv.monto)}`;
  }

  seriePath(values: number[], w: number, h: number, min?: number, max?: number): string {
    if (!values.length) {
      return '';
    }
    const lo = min ?? Math.min(...values);
    const hi = max ?? Math.max(...values);
    const range = hi - lo || 1;
    const coords = values.map((v, i) => {
      const x = values.length === 1 ? 0 : (i / (values.length - 1)) * w;
      const y = h - 4 - ((v - lo) / range) * (h - 10);
      return `${x.toFixed(1)},${y.toFixed(1)}`;
    });
    return `M ${coords.join(' L ')}`;
  }

  precioCostoPaths(detalle: NonNullable<RentabilidadProductoTablaFila['detalle']>): {
    precio: string;
    costo: string;
  } {
    const w = 280;
    const h = 120;
    const all = [...detalle.precioSerie, ...detalle.costoSerie];
    const min = Math.min(...all) - 0.5;
    const max = Math.max(...all) + 0.5;
    return {
      precio: this.seriePath(detalle.precioSerie, w, h, min, max),
      costo: this.seriePath(detalle.costoSerie, w, h, min, max),
    };
  }

  sparklinePath(values: number[]): string {
    const w = 56;
    const h = 26;
    if (!values.length) {
      return '';
    }
    const min = Math.min(...values);
    const max = Math.max(...values);
    const range = max - min || 1;
    const coords = values.map((v, i) => {
      const x = values.length === 1 ? 0 : (i / (values.length - 1)) * w;
      const y = h - 2 - ((v - min) / range) * (h - 6);
      return `${x.toFixed(1)},${y.toFixed(1)}`;
    });
    return `M ${coords.join(' L ')}`;
  }

  anchoBarra(pct: number): number {
    return Math.min(100, Math.abs(pct));
  }

  anchoRelativo(pct: number, filas: RentabilidadMontoPct[]): number {
    const max = Math.max(...filas.map((f) => f.pct), 1);
    return (pct / max) * 100;
  }

  anchoRelativoMonto(monto: number, filas: { ventas: number }[]): number {
    const max = Math.max(...filas.map((f) => f.ventas), 1);
    return (monto / max) * 100;
  }

  tonoPuente(fila: RentabilidadMontoPct, index: number, total: number): string {
    if (index === 0) {
      return 'azul';
    }
    if (index === total - 1) {
      return fila.monto < 0 ? 'rojo' : 'verde';
    }
    return 'naranja';
  }

  margenBajoEmpresa(fila: RentabilidadProductoFila): boolean {
    return fila.margenPct < fila.margenEmpresaPct;
  }
}
