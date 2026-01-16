export interface ChartConfig {
    title?: string;
    data: any[];
    labels?: string[];
    options?: any;
    colors?: string[];
    type?: 'line' | 'bar' | 'pie' | 'doughnut';
    rotateLabels?: number; // Ángulo de rotación para labels del eje X (0 = horizontal, 45 = diagonal)
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
}

