import { Injectable } from '@angular/core';
import { Observable, of } from 'rxjs';
import { delay } from 'rxjs/operators';
import { ApiService } from '@services/api.service';
import { DashboardData, ChartConfig, MetricCard, AccountItem, CashFlowData, CashFlowItem, Cuenta30Dias, BudgetMetric } from '../models/chart-config.model';

@Injectable({
  providedIn: 'root'
})
export class DashboardDataService {

  constructor(
    private apiService: ApiService
  ) { }

  obtenerDatos(): Observable<DashboardData> {
    // TODO: Reemplazar con llamada real a la API
    // return this.apiService.get('/dashboard/datos');
    
    // Datos de ejemplo
    const datos: DashboardData = {
      metrics: [
        {
          title: 'Total Revenue',
          value: 34152,
          icon: 'fa-chart-line',
          color: '#5470c6',
          trend: {
            value: 2.65,
            direction: 'up'
          }
        },
        {
          title: 'Orders',
          value: 5643,
          icon: 'fa-shopping-cart',
          color: '#91cc75',
          trend: {
            value: -0.82,
            direction: 'down'
          }
        },
        {
          title: 'Customers',
          value: 45254,
          icon: 'fa-users',
          color: '#4a90e2',
          trend: {
            value: -6.24,
            direction: 'down'
          }
        },
        {
          title: 'Growth',
          value: '+ 12.58%',
          icon: 'fa-chart-line',
          color: '#ff9800',
          trend: {
            value: 10.51,
            direction: 'up'
          }
        }
      ],
      lineChartConfig: {
        title: 'Tendencias de Ventas',
        data: [120, 132, 101, 134, 90, 230, 210],
        labels: ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul'],
        type: 'line',
        colors: ['#5470c6', '#91cc75']
      },
      barChartConfig: {
        title: 'Comparación Mensual',
        data: [120, 132, 101, 134, 90, 230, 210],
        labels: ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul'],
        type: 'bar',
        colors: ['#5470c6']
      },
      pieChartConfig: {
        title: 'Distribución por Categoría',
        data: [
          { name: 'Categoría A', value: 335 },
          { name: 'Categoría B', value: 310 },
          { name: 'Categoría C', value: 234 },
          { name: 'Categoría D', value: 135 }
        ],
        type: 'pie',
        colors: ['#5470c6', '#91cc75', '#fac858', '#ee6666']
      },
      ventasGastosConfig: {
        title: '',
        labels: ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'],
        data: [
          {
            name: 'Ventas con IVA',
            data: [2563.68, 3163.89, 3179.61, 5098.80, 2163.53, 5660.51, 7239.71, 6447.00, -592.58, 1714.22, 1416.33, 10627.96]
          },
          {
            name: 'Gastos con IVA',
            data: [3533.86, 4724.34, 350.24, 1852.60, 5319.36, 200.00, 602.99, 5957.26, 4727.35, 2593.52, 7954.89, 2151.53]
          }
        ],
        type: 'bar',
        colors: ['#5470c6', '#ff9800']
      },
      cuentasPorCobrar: [
        { name: 'Desarrollos Veterina...', amount: 169.50 },
        { name: 'Emerson Mauricio R...', amount: 169.50 },
        { name: 'María Teresa De Alas', amount: 169.50 },
        { name: 'Poc, S.A. De C.V.', amount: 169.50 },
        { name: 'Natura La', amount: 141.25 },
        { name: 'Rosembel Alexande...', amount: 141.25 },
        { name: 'Zelaya Romero, An...', amount: 141.25 },
        { name: 'Guerrero Contreras,...', amount: 135.60 },
        { name: 'Aquino Barriere, Est...', amount: 129.91 },
        { name: 'Edwin Evora', amount: 113.00 },
        { name: 'Esencial Express', amount: 113.00 },
        { name: 'Francisco Javier Per...', amount: 113.00 },
        { name: 'Jimenez Rivera, Jos...', amount: 113.00 },
        { name: 'Picaza, S.A De C.V', amount: 113.00 }
      ],
      cuentasPorPagar: [
        { name: 'VERSATIVE', amount: 395.50 },
        { name: 'Jennifer', amount: 300.00 },
        { name: 'CAESS', amount: 265.00 },
        { name: 'Melissa Benitez', amount: 200.00 },
        { name: 'ANDA', amount: 185.00 },
        { name: 'JAVIER', amount: 180.00 },
        { name: 'ELC', amount: 169.50 },
        { name: 'Ministerio de Hacie...', amount: 118.89 },
        { name: 'Gabriela De la Cuad...', amount: 100.00 },
        { name: 'Roberto Cuéllar', amount: 88.89 },
        { name: 'Google', amount: 66.00 },
        { name: 'HOSTINGER', amount: 24.99 },
        { name: 'AWS', amount: 18.89 },
        { name: 'ZOOM', amount: 14.99 }
      ],
      cashFlow: {
        ingresosPercibidos: 3501682.66,
        egresosRealizados: 69967.94,
        resultados: 3431714.72,
        minimoEfectivoRequerido: 69968,
        ventasDelMes: [
          { cliente: '', factura: '1', monto: 612.92 },
          { cliente: '', factura: '1', monto: 28.25 },
          { cliente: '', factura: '1', monto: 28.00 },
          { cliente: 'Natura La', factura: '2', monto: 20.85 },
          { cliente: 'Natura La', factura: '3', monto: 280.56 },
          { cliente: 'Natura La', factura: '4', monto: 666.78 },
          { cliente: '', factura: '5', monto: 3500000.00 }
        ],
        gastosDelMes: [
          { proveedor: 'AWS', factura: '', monto: 18.89 },
          { proveedor: 'Bodega', factura: '', monto: 16541.14 },
          { proveedor: 'CAESS', factura: '', monto: 250.00 },
          { proveedor: 'CALENDLY', factura: '', monto: 10.00 },
          { proveedor: 'CLARO', factura: '', monto: 50.00 },
          { proveedor: 'DALIA', factura: '', monto: 4157.62 },
          { proveedor: 'Google', factura: '', monto: 66.00 },
          { proveedor: 'HOSTINGER', factura: '', monto: 24.99 },
          { proveedor: 'ZOOM', factura: '', monto: 14.99 },
          { proveedor: 'Otros', factura: '', monto: 48830.31 }
        ]
      } as CashFlowData,
      cuentasPorCobrar30Dias: [
        { factura: '001', cliente: 'Cliente A', vence: '2024-12-15', diasVencimiento: 5 },
        { factura: '002', cliente: 'Cliente B', vence: '2024-12-20', diasVencimiento: 10 },
        { factura: '003', cliente: 'Cliente C', vence: '2024-12-25', diasVencimiento: 15 }
      ] as Cuenta30Dias[],
      cuentasPorPagar30Dias: [
        { factura: '001', proveedor: 'DALIA', vence: '2024-12-10', diasVencimiento: 0 },
        { factura: '002', proveedor: 'FREUND', vence: '2024-12-10', diasVencimiento: 0 }
      ] as Cuenta30Dias[],
      budgetMetrics: [
        {
          title: 'Ingresos obtenidos vs presupuesto',
          currentValue: 3501682.66,
          percentageChange: 765.00,
          target: 404821.00,
          targetMonth: 'Enero',
          color: 'green',
          chartData: [20, 25, 30, 35, 40, 45, 50, 55, 60, 65, 70, 75, 80, 85, 90, 95, 100]
        },
        {
          title: 'Gastos incurridos vs presupuesto',
          currentValue: 73393.49,
          percentageChange: 64,
          target: 44825.00,
          targetMonth: 'Enero',
          color: 'orange',
          chartData: [50, 48, 52, 49, 51, 50, 53, 52, 51, 50, 49, 51, 50, 52, 51, 50, 51]
        },
        {
          title: 'Utilidad obtenida vs presupuesto',
          currentValue: 3428289.17,
          percentageChange: 852,
          target: 359996.00,
          targetMonth: 'Enero',
          color: 'green',
          chartData: [10, 15, 20, 25, 30, 35, 40, 45, 50, 55, 60, 65, 70, 75, 80, 85, 90]
        }
      ] as BudgetMetric[],
      // Datos para la sección de Ventas
      metricasVentas: {
        ventasConIVA: 3501682.66,
        ventasSinIVA: 3098279.56,
        transacciones: 983,
        ticketPromedio: 3562.24
      },
      ventasPorMesConfig: {
        title: '',
        type: 'line',
        labels: ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'],
        data: [22563.68, 3163.89, 23179.61, 10098.80, 20163.53, 5660.51, 7239.71, -592.58, 6447.00, 1714.22, 3391416.33, 10627.96],
        colors: ['#5470c6']
      },
      ventasVsPresupuestoConfig: {
        title: '',
        type: 'bar',
        labels: ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'],
        data: [
          {
            name: 'Ventas totales',
            data: [22563.68, 3163.89, 23179.61, 10098.80, 20163.53, 5660.51, 7239.71, 6447.00, -592.58, 1714.22, 3391416.33, 10627.96]
          },
          {
            name: 'Presupuestado',
            data: [23000.00, 26000.00, 17321.00, 37000.00, 20300.00, 34800.00, 32800.00, 18300.00, 18300.00, 27000.00, 17000.00, 133000.00]
          }
        ],
        colors: ['#5470c6', '#d3d3d3']
      },
      ventasVsAnioAnteriorConfig: {
        title: '',
        type: 'bar',
        labels: ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'],
        data: [
          {
            name: 'Año actual',
            data: [22563.68, 3163.89, 23179.61, 10098.80, 20163.53, 5660.51, 7239.71, 6447.00, -592.58, 1714.22, 3391416.33, 10627.96]
          },
          {
            name: 'Año anterior',
            data: [18000.00, 2500.00, 20000.00, 8000.00, 15000.00, 4500.00, 6000.00, 5000.00, 4000.00, 1200.00, 50000.00, 8000.00]
          }
        ],
        colors: ['#5470c6', '#d3d3d3']
      },
      ventasPorCanal: [
        { name: 'Facebook', amount: 3438857.38 },
        { name: 'Tienda', amount: 35207.01 },
        { name: 'El Salvador', amount: 7859.15 },
        { name: 'Canal 1', amount: 6457.29 },
        { name: 'Fabiola', amount: 5043.19 },
        { name: 'Instagram', amount: 2482.93 },
        { name: 'Marketplace', amount: 1470.00 },
        { name: 'Twitter', amount: 1356.00 },
        { name: 'Partner 2', amount: 1226.84 },
        { name: 'Whatsapp', amount: 561.64 },
        { name: 'Bibanking', amount: 440.09 },
        { name: 'Azucena Perez', amount: 282.50 },
        { name: 'Pedidos Ya', amount: 174.36 }
      ],
      ventasPorVendedorChartConfig: {
        title: '',
        type: 'bar',
        labels: ['Gaby', 'Paula', 'Soporte', 'Jennifer', 'Gabriela', 'DANIELA', 'Ventas SmartPyme', 'CONTADOR', 'Paola Vasquez', 'Facturacion', 'Cliente Demo'],
        data: [3469166.35, 16319.29, 5429.65, 423.75, 104.80, 56.50, 51.90, 23.73, 15.26, 3.39, 0.01],
        colors: ['#5470c6'],
        rotateLabels: 45
      },
      ventasPorFormaPagoConfig: {
        title: '',
        type: 'bar',
        labels: ['Efectivo', 'Tarjeta', 'Transferencia', 'Cheque', 'Otros'],
        data: [3481952.17, 15234.50, 8456.23, 1234.56, 1205.20],
        colors: ['#5470c6']
      },
      ventasPorCategoria: [
        { name: 'Categoría 3', amount: 3002714.08 },
        { name: 'Adidas - Masculino', amount: 31860.00 },
        { name: 'Promoción', amount: 24820.03 },
        { name: 'Planes', amount: 15734.65 },
        { name: 'Implementaciones', amount: 8000.00 },
        { name: 'Categoría 1', amount: 5700.77 },
        { name: 'Promociones', amount: 3982.28 },
        { name: 'Organización', amount: 2969.03 },
        { name: 'Accesorios', amount: 2450.75 },
        { name: 'Patrocinio', amount: 1950.00 },
        { name: 'Construcción-Torre Alta', amount: 1415.92 },
        { name: 'Tenis Clase B', amount: 1265.00 },
        { name: 'Roadtrip', amount: 1225.00 },
        { name: 'Liquidacion', amount: 975.00 },
        { name: 'Adidas - Femenino', amount: 912.00 },
        { name: 'Juguetes Perros', amount: 863.54 },
        { name: 'Servicio', amount: 812.00 }
      ],
      topProductosVendidos: [
        { name: 'Producto C', amount: 3000677.08 },
        { name: 'AA2', amount: 24520.00 },
        { name: 'Prueba12', amount: 18660.00 },
        { name: 'Adidas Forum morados', amount: 12600.00 },
        { name: 'SERVICIO DE IMPLEM...', amount: 8000.00 },
        { name: 'Plan Avanzado - Smar...', amount: 5450.00 },
        { name: 'Plan Estándar', amount: 4371.00 },
        { name: 'Producto B', amount: 3981.50 },
        { name: 'Libro inglés', amount: 2969.03 },
        { name: 'IMPLEMENTACION S...', amount: 2812.85 },
        { name: 'Plan Avanzado: Inven...', amount: 2388.65 },
        { name: 'Servicio de importació...', amount: 2086.74 },
        { name: 'Producto A', amount: 2037.00 },
        { name: 'Patrocinio Evento Des...', amount: 1950.00 },
        { name: 'Producto X', amount: 1720.00 }
      ],
      ventasPorProducto: [
        {
          categoria: 'Planes',
          producto: 'Plan Avanzado: Inventario, Servicios, Ventas, Compras, Gastos, Finanzas, Citas, Cierre de Caja e Inteligencia de Negocios. Hasta 2 sucursales 5 usuarios incluidos',
          formaPago: 'Efectivo',
          cantidad: 140,
          precioUnitario: 50.00,
          descuento: 0.00,
          ventasSinIVA: 7000.00,
          costoTotal: 3500.00,
          utilidad: 3500.00
        },
        {
          categoria: 'Planes',
          producto: 'Plan Avanzado - SmartPyme',
          formaPago: 'Transferencia',
          cantidad: 146,
          precioUnitario: 100.00,
          descuento: 0.00,
          ventasSinIVA: 14600.00,
          costoTotal: 7300.00,
          utilidad: 7300.00
        },
        {
          categoria: 'Planes',
          producto: 'Plan Pro: Inventario, Servicios, Ventas, Compras, Gastos, Finanzas, Citas, Cierre de Caja e Inteligencia de Negocios. Facturación electrónica gratis. 10 usuarios y hasta 3 sucursales',
          formaPago: 'Efectivo',
          cantidad: 7,
          precioUnitario: 150.00,
          descuento: 0.00,
          ventasSinIVA: 1050.00,
          costoTotal: 525.00,
          utilidad: 525.00
        },
        {
          categoria: 'Planes',
          producto: 'Plan Estándar',
          formaPago: 'Transferencia',
          cantidad: 8,
          precioUnitario: 25.00,
          descuento: 0.00,
          ventasSinIVA: 200.00,
          costoTotal: 100.00,
          utilidad: 100.00
        },
        {
          categoria: 'Planes',
          producto: 'Plan Avanzado: Inventario, Servicios, Ventas, Compras, Gastos, Finanzas, Citas, Cierre de Caja e Inteligencia de Negocios. Hasta 2 sucursales 5 usuarios incluidos',
          formaPago: 'Efectivo',
          cantidad: 332,
          precioUnitario: 50.00,
          descuento: 0.00,
          ventasSinIVA: 16600.00,
          costoTotal: 8300.00,
          utilidad: 8300.00
        }
      ]
    };

    return of(datos).pipe(delay(500));
  }

  obtenerDatosPorFiltro(filtros: any): Observable<DashboardData> {
    // TODO: Reemplazar con llamada real a la API con filtros
    // return this.apiService.get('/dashboard/datos', { params: filtros });
    
    // Por ahora retorna los mismos datos de ejemplo
    // En producción, aquí se filtrarían los datos según filtros.seccion, filtros.anio, filtros.sucursal
    return this.obtenerDatos();
  }
}

