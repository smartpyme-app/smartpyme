export interface ChartConfig {
    title?: string;
    data: any[];
    labels?: string[];
    /** Opcional: porcentaje por segmento (p. ej. API ventas por forma de pago). Si falta, el gráfico usa el % calculado por ECharts. */
    porcentajes?: number[];
    options?: any;
    colors?: string[];
    type?: 'line' | 'bar' | 'pie' | 'doughnut';
    rotateLabels?: number; // Ángulo de rotación para labels del eje X (0 = horizontal, 45 = diagonal)
    horizontal?: boolean; // Si es true, las barras serán horizontales
    conditionalColors?: boolean; // Si es true, los colores serán condicionales (verde para positivos, rojo para negativos)
}

export interface MetricCard {
    title: string;
    value: number | string;
    icon?: string;
    color?: string;
    trend?: {
        value: number;
        direction: 'up' | 'down' | 'neutral';
    };
}

export interface AccountItem {
    name: string;
    amount: number;
}

export interface CashFlowItem {
    cliente?: string;
    proveedor?: string;
    factura: string;
    monto: number;
}

export interface CashFlowData {
    ingresosPercibidos: number;
    egresosRealizados: number;
    resultados: number;
    minimoEfectivoRequerido: number;
    ventasDelMes: CashFlowItem[];
    gastosDelMes: CashFlowItem[];
}

export interface Cuenta30Dias {
    factura: string;
    cliente?: string;
    proveedor?: string;
    vence: string;
    diasVencimiento: number;
    monto?: number;
}

export interface BudgetMetric {
    title: string;
    currentValue: number;
    percentageChange: number;
    target: number;
    targetMonth: string;
    color: 'green' | 'orange' | 'gray';
    chartData: number[];
}

export interface DashboardData {
    metrics?: MetricCard[];
    lineChartConfig?: ChartConfig;
    barChartConfig?: ChartConfig;
    pieChartConfig?: ChartConfig;
    ventasGastosConfig?: ChartConfig;
    cuentasPorCobrar?: AccountItem[];
    cuentasPorPagar?: AccountItem[];
    cashFlow?: CashFlowData;
    cuentasPorCobrar30Dias?: Cuenta30Dias[];
    cuentasPorPagar30Dias?: Cuenta30Dias[];
    budgetMetrics?: BudgetMetric[];
    // Datos para la sección de Ventas
    metricasVentas?: {
        ventasConIVA: number;
        ventasSinIVA: number;
        transacciones: number;
        ticketPromedio: number;
    };
    ventasPorMesConfig?: ChartConfig;
    ventasVsPresupuestoConfig?: ChartConfig;
    ventasVsAnioAnteriorConfig?: ChartConfig;
    ventasPorCanal?: AccountItem[];
    ventasPorVendedorChartConfig?: ChartConfig;
    ventasPorFormaPagoConfig?: ChartConfig;
    ventasPorCategoria?: AccountItem[];
    topProductosVendidos?: AccountItem[];
    ventasPorProducto?: Array<{
      categoria: string;
      producto: string;
      formaPago: string;
      cantidad: number;
      precioUnitario: number;
      descuento: number;
      ventasSinIVA: number;
      costoTotal: number;
      utilidad: number;
    }>;
    topClientes?: AccountItem[];
    ventasPorCliente?: Array<{
      cliente: string;
      ultimaVenta: string;
      dias: number;
      transacciones: number;
      ventas: number;
    }>;
    ventasDetalladas?: Array<{
      fecha: string;
      cliente: string;
      factura: string;
      productos: number;
      monto: number;
      estado: string;
      canal?: string;
      vendedor?: string;
      formaPago?: string;
      categoria?: string;
      producto?: string;
      cantidad?: number;
      precioUnitario?: number;
      descuento?: number;
      ventasSinIVA?: number;
      costoTotal?: number;
      utilidad?: number;
    }>;
    // Datos para la sección de Control de Cuentas
    metricasCuentas?: {
      // Cuentas por cobrar
      cuentasPorCobrarTotal: number;
      cuentasPorCobrar30Dias: number;
      cuentasPorCobrar60Dias: number;
      cuentasPorCobrar90Dias: number;
      tooltipCuentasPorCobrar?: string;
      // Cuentas por pagar
      cuentasPorPagarTotal: number;
      cuentasPorPagar30Dias: number;
      cuentasPorPagar60Dias: number;
      cuentasPorPagar90Dias: number;
      tooltipCuentasPorPagar?: string;
    };
    cuentasPorVigenciaConfig?: ChartConfig;
    cuentasPorCobrarClientes?: AccountItem[];
    detalleCuentasPorCobrar?: Array<{
      cliente: string;
      factura: string | number;
      fechaVenta: string;
      fechaPago: string;
      diasVencimiento: number;
      estado: string;
      ventasConIVA: number;
      montoAbonado: number;
      diasAbono?: number;
      saldoPendiente: number;
    }>;
    cuentasPorPagarVigenciaConfig?: ChartConfig;
    cuentasPorPagarProveedores?: AccountItem[];
    resumenCuentasPorPagar?: Array<{
      fechaCompra: string;
      vencimiento: string;
      diasVencimiento: number;
      estado: string;
      gastosTotalesConIVA: number;
      totalAbonado: number;
      ultimoAbono: string;
      saldoPendiente: number;
    }>;
    // Datos para la sección de Gastos
    metricasGastos?: {
      gastosConIVA: number;
      gastosSinIVA: number;
      gastosMesAnterior: number;
      variacionGastos: number;
      aumentoCostosPorcentaje: number;
    };
    gastosPorMesConfig?: ChartConfig;
    gastosVsPresupuestoConfig?: ChartConfig;
    gastosVsAnioAnteriorConfig?: ChartConfig;
    gastosPorCategoriaConfig?: ChartConfig;
    gastosPorConceptoConfig?: ChartConfig;
    gastosPorFormaPagoConfig?: ChartConfig;
    gastosPresupuesto?: number[];
    gastosAnioAnterior?: number[];
    detalleGastos?: Array<{
      fecha: string;
      proveedor: string;
      concepto: string;
      documento: string;
      correlativo: string;
      gastosConIVA: number;
    }>;
    gastosPorProveedor?: AccountItem[];
    // Datos para la sección de Finanzas
    ventasTotalesConIVA?: number;
    gastosTotalesConIVA?: number;
    resultados?: number;
    margen?: number;
    detalleVentas?: Array<{
      fecha: string;
      cliente: string;
      factura: string;
      totalConIVA: number;
      totalSinIVA: number;
      sucursalId?: string;
    }>;
    estadoResultados?: Array<{
      nombre: string;
      nivel: number;
      expandido: boolean;
      tieneHijos: boolean;
      hijos: any[];
      valores: { [key: string]: number };
      esUtilidadNeta?: boolean;
    }>;
    analisisVertical?: Array<{
      concepto: string;
      valor: number;
      porcentaje: number;
    }>;
    analisisHorizontal?: Array<{
      concepto: string;
      mesAnterior: number;
      mesActual: number;
      variacion: number;
      variacionPorcentaje: number;
    }>;
    flujoEfectivo?: Array<{
      concepto: string;
      esEfectivoDisponible: boolean;
      valores: { [key: string]: number };
    }>;
}

