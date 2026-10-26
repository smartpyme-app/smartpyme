export type RentabilidadHorizonteId = 'mes' | 'trimestre' | 'anio';
export type RentabilidadDimensionId =
  | 'sucursal'
  | 'producto'
  | 'cliente'
  | 'proveedor'
  | 'proyecto';

export type RentabilidadKpiTono = 'rojo' | 'azul' | 'naranja';

export interface RentabilidadKpi {
  titulo: string;
  valor: string;
  tono: RentabilidadKpiTono;
  linea?: string;
  sublinea?: string;
  accion?: string;
}

/** KPIs fijos de toolbar; si falta, se calculan desde la fila activa. */
export type RentabilidadKpiOverride = RentabilidadKpi[];

export interface RentabilidadMontoPct {
  etiqueta: string;
  monto: number;
  pct: number;
}

export interface RentabilidadProductoFila {
  tipo: string;
  ventas: number;
  pctVentas: number;
  margenPct: number;
  margenEmpresaPct: number;
}

export type RentabilidadProductoTono = 'normal' | 'activa' | 'riesgo' | 'muerta';

export type RentabilidadProductoInventario =
  | { tipo: 'dias'; dias: number }
  | { tipo: 'agota'; dias: number }
  | { tipo: 'sinVenta'; dias: number; monto: number };

export interface RentabilidadProductoClienteFila {
  nombre: string;
  unidades: number;
  precio: number;
  descuentoPct: number | null;
  margenPct: number;
  margenBajo: boolean;
}

export interface RentabilidadProductoDetalle {
  proveedor: string;
  utilidadAnio: number;
  costoUnitario: number;
  costoUnitarioVsPct: number;
  precioUnitario: number;
  precioUnitarioVsPct: number;
  inventarioValor: number;
  precioCostoLabels: string[];
  precioSerie: number[];
  costoSerie: number[];
  sucursales: RentabilidadMontoPct[];
  clientes: RentabilidadProductoClienteFila[];
  vendidoConDescuentoPct: number;
  vendidoConDescuentoMonto: number;
}

export interface RentabilidadProductoTablaFila {
  nombre: string;
  categoria: string;
  puesto: number;
  unidades: number;
  ventas: number;
  ventasVsPct: number | null;
  utilidad: number;
  margenPct: number | null;
  margenBajo: boolean;
  margenAnioPct: number | null;
  tendenciaAlcista: boolean;
  tendencia: number[];
  inventario: RentabilidadProductoInventario;
  precioVsPct: number | null;
  costoVsPct: number | null;
  costoAlerta: boolean;
  tono: RentabilidadProductoTono;
  detalle?: RentabilidadProductoDetalle;
  kpisToolbar?: RentabilidadKpiOverride;
}

export interface RentabilidadSucursalDetalle {
  cxcVencido: number;
  cxpVencido: number;
  puente: RentabilidadMontoPct[];
  productos: RentabilidadProductoFila[];
  clientes: RentabilidadMontoPct[];
  devolucionesTotal: number;
  devoluciones: RentabilidadMontoPct[];
}

export interface RentabilidadSucursalFila {
  nombre: string;
  ventas: number;
  gastos: number;
  utilidad: number;
  margenPct: number;
  tendenciaAlcista: boolean;
  tendencia: number[];
  cxc: number;
  cxcAlerta: boolean;
  cxp: number;
  ventasVsAnioPct: number;
  detalle: RentabilidadSucursalDetalle;
  kpisToolbar?: RentabilidadKpiOverride;
}

export type RentabilidadClienteTono = 'normal' | 'activa' | 'alerta';

export interface RentabilidadClienteComparacion100 {
  mercaderia: number;
  esperarPago: number;
  leQueda: number;
}

export interface RentabilidadClienteFactura {
  codigo: string;
  fecha: string;
  monto: number;
  nota: string;
  notaAlerta: boolean;
}

export interface RentabilidadClienteProductoFila {
  producto: string;
  compro: number;
  deCada100: number;
  deCada100Bajo: boolean;
  normalDeCada100: number;
}

export interface RentabilidadClienteDetalle {
  obra: string;
  deCada100Promedio: number;
  alFinalQuedan: number;
  descuentoPct: number;
  descuentoMonto: number;
  cxcVencido: number;
  comparacionEste: RentabilidadClienteComparacion100;
  comparacionTodos: RentabilidadClienteComparacion100;
  puente: RentabilidadMontoPct[];
  facturas: RentabilidadClienteFactura[];
  productos: RentabilidadClienteProductoFila[];
  sucursales: RentabilidadMontoPct[];
}

export interface RentabilidadClienteFila {
  nombre: string;
  etiqueta?: string;
  etiquetaTono?: 'rojo' | 'naranja';
  compro: number;
  ganancia: number;
  deCada100: number;
  deCada100Bajo: boolean;
  tendenciaAlcista: boolean;
  tendencia: number[];
  alFinalQuedan: number;
  alFinalTono: 'normal' | 'naranja' | 'rojo';
  leDebe: number | null;
  pagoContado: boolean;
  pagoDias: number | null;
  pagoPactado: number | null;
  pagoLento: boolean;
  ultimaCompra: string;
  ultimaCompraAlerta: boolean;
  parteVentasPct: number;
  tono: RentabilidadClienteTono;
  detalle?: RentabilidadClienteDetalle;
  kpisToolbar?: RentabilidadKpiOverride;
}

export type RentabilidadProveedorTono = 'normal' | 'activa' | 'alerta';
export type RentabilidadProveedorValorTono = 'normal' | 'naranja' | 'verde' | 'rojo';

export interface RentabilidadProveedorResumenFila {
  concepto: string;
  anual: number;
  promedio: number;
  mes: number;
  formato: 'monto' | 'pct';
  anualTono?: RentabilidadProveedorValorTono;
  mesTono?: RentabilidadProveedorValorTono;
}

export interface RentabilidadProveedorClienteFila {
  nombre: string;
  ventas: number;
  margenPct: number;
  margenBajo: boolean;
}

export interface RentabilidadProveedorDetalle {
  ganaPorDolar6m: number;
  costoLamina: number;
  costoLaminaVs6mPct: number;
  precioVentaVs6mPct: number;
  diasPagoPromedioOtros: number;
  otraCotizacionMonto: number;
  otraCotizacionFuente: string;
  otraCotizacionVsPct: number;
  resumen: RentabilidadProveedorResumenFila[];
  chartLabels: string[];
  chartCompras: number[];
  chartVentas: number[];
  chartMargen: number[];
  productoNombre: string;
  productoCompras: number;
  productoCostoHoy: number;
  productoCosto6m: number;
  productoMargenPct: number;
  porPagar: { fecha: string; monto: number }[];
  clientes: RentabilidadProveedorClienteFila[];
}

export type RentabilidadProyectoTono = 'normal' | 'activa' | 'alerta';
export type RentabilidadProyectoBarTono = 'azul' | 'verde' | 'rojo';

export interface RentabilidadProyectoAvance {
  etiqueta: string;
  pct: number;
  barTono: RentabilidadProyectoBarTono;
}

export interface RentabilidadProyectoResultadoFila {
  concepto: string;
  planeado: number;
  fecha: number;
  formato: 'monto' | 'pct';
  fechaBajo?: boolean;
}

export interface RentabilidadProyectoCategoriaGasto {
  nombre: string;
  pctGasto: number;
  monto: number;
}

export interface RentabilidadProyectoPresupuestoFila {
  categoria: string;
  presupuesto: number;
  gastado: number;
  disponible: number;
  usadoPct: number;
  usadoBarTono: 'azul' | 'naranja';
}

export interface RentabilidadProyectoMaterialFila {
  material: string;
  cotizadoTexto: string | null;
  compradoTexto: string;
  cantPct: number | null;
  cantPctAlto?: boolean;
  difPrecio: number | null;
}

export interface RentabilidadProyectoDetalle {
  porFacturar: number;
  facturado: number;
  cobrado: number;
  gastado: number;
  pagadoObra: number;
  utilidadFecha: number;
  contratoCobradoPct: number;
  avances: RentabilidadProyectoAvance[];
  resultado: RentabilidadProyectoResultadoFila[];
  costosCategoria: RentabilidadProyectoCategoriaGasto[];
  presupuesto: RentabilidadProyectoPresupuestoFila[];
  presupuestoTotal: {
    presupuesto: number;
    gastado: number;
    disponible: number;
    usadoPct: number;
  };
  materiales: RentabilidadProyectoMaterialFila[];
  chartLabels: string[];
  chartGastado: number[];
  chartFacturado: number[];
  chartCobrado: number[];
}

export interface RentabilidadProyectoFila {
  nombre: string;
  cliente: string;
  contrato: number;
  facturadoPct: number;
  presupuestoGastadoPct: number;
  presupuestoBarTono: RentabilidadProyectoBarTono;
  margenPlaneadoPct: number;
  margenFechaPct: number;
  margenFechaBajo: boolean;
  porCobrar: number;
  dineroEnObra: number | null;
  dineroEnObraAlerta: boolean;
  tono: RentabilidadProyectoTono;
  detalle?: RentabilidadProyectoDetalle;
  kpisToolbar?: RentabilidadKpiOverride;
}

export interface RentabilidadProveedorFila {
  nombre: string;
  compro: number;
  parteComprasPct: number;
  dependenciaAlta: boolean;
  ganoAlVender: number;
  ganaPorDolar: number;
  ganaPorDolarTono: RentabilidadProveedorValorTono;
  precio6mPct: number;
  precio6mTono: RentabilidadProveedorValorTono;
  diasPago: number;
  diasPagoTono: RentabilidadProveedorValorTono;
  tono: RentabilidadProveedorTono;
  detalle?: RentabilidadProveedorDetalle;
  kpisToolbar?: RentabilidadKpiOverride;
}

export interface RentabilidadMock {
  kpis: RentabilidadKpi[];
  filasSucursal: RentabilidadSucursalFila[];
  filasProducto: RentabilidadProductoTablaFila[];
  filasCliente: RentabilidadClienteFila[];
  filasProveedor: RentabilidadProveedorFila[];
  filasProyecto: RentabilidadProyectoFila[];
}
