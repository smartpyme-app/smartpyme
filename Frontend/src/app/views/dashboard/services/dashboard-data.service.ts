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
      ],
      topClientes: [
        { name: '(En blanco)', amount: 3469374.25 },
        { name: 'Consumidor Final', amount: 18037.17 },
        { name: 'Manufacturas Cavalier Sa D...', amount: 9096.50 },
        { name: 'Cliente Ejemplo 1', amount: 8500.00 },
        { name: 'Cliente Ejemplo 2', amount: 7200.00 },
        { name: 'Cliente Ejemplo 3', amount: 6500.00 },
        { name: 'Cliente Ejemplo 4', amount: 5800.00 },
        { name: 'Cliente Ejemplo 5', amount: 5200.00 },
        { name: 'Cliente Ejemplo 6', amount: 4800.00 },
        { name: 'Cliente Ejemplo 7', amount: 4500.00 },
        { name: 'Cliente Ejemplo 8', amount: 4200.00 },
        { name: 'Cliente Ejemplo 9', amount: 3900.00 },
        { name: 'Cliente Ejemplo 10', amount: 3600.00 },
        { name: 'Cliente Ejemplo 11', amount: 3300.00 },
        { name: 'Cliente Ejemplo 12', amount: 3000.00 },
        { name: 'Cliente Ejemplo 13', amount: 2800.00 },
        { name: 'Cliente Ejemplo 14', amount: 2600.00 },
        { name: 'Cliente Ejemplo 15', amount: 2400.00 },
        { name: 'Cliente Ejemplo 16', amount: 2200.00 },
        { name: 'Cliente Ejemplo 17', amount: 2000.00 },
        { name: 'Cliente Ejemplo 18', amount: 1800.00 },
        { name: 'Cliente Ejemplo 19', amount: 1600.00 },
        { name: 'Cliente Ejemplo 20', amount: 1400.00 },
        { name: 'Cliente Ejemplo 21', amount: 1200.00 },
        { name: 'Facturacion@Airboxsv.Com', amount: -10758.73 }
      ],
      // Datos detallados de ventas con relaciones para filtros interactivos
      ventasDetalladas: this.generarVentasDetalladasDummy(),
      ventasPorCliente: [
        { cliente: '(En blanco)', ultimaVenta: '13/06/24', dias: 581, transacciones: 1, ventas: 3469374.25 },
        { cliente: 'Consumidor Final', ultimaVenta: '01/03/24', dias: 685, transacciones: 2, ventas: 18037.17 },
        { cliente: 'Manufacturas Cavalier Sa D...', ultimaVenta: '15/05/24', dias: 610, transacciones: 3, ventas: 9096.50 },
        { cliente: 'Cliente Ejemplo 1', ultimaVenta: '20/04/24', dias: 635, transacciones: 1, ventas: 8500.00 },
        { cliente: 'Cliente Ejemplo 2', ultimaVenta: '10/06/24', dias: 584, transacciones: 2, ventas: 7200.00 },
        { cliente: 'Cliente Ejemplo 3', ultimaVenta: '05/07/24', dias: 559, transacciones: 1, ventas: 6500.00 },
        { cliente: 'Cliente Ejemplo 4', ultimaVenta: '18/08/24', dias: 515, transacciones: 2, ventas: 5800.00 },
        { cliente: 'Cliente Ejemplo 5', ultimaVenta: '22/09/24', dias: 480, transacciones: 1, ventas: 5200.00 },
        { cliente: 'Cliente Ejemplo 6', ultimaVenta: '30/10/24', dias: 432, transacciones: 3, ventas: 4800.00 },
        { cliente: 'Cliente Ejemplo 7', ultimaVenta: '12/11/24', dias: 419, transacciones: 1, ventas: 4500.00 },
        { cliente: 'Cliente Ejemplo 8', ultimaVenta: '25/12/24', dias: 381, transacciones: 2, ventas: 4200.00 },
        { cliente: 'Cliente Ejemplo 9', ultimaVenta: '30/12/24', dias: 381, transacciones: 1, ventas: 3900.00 },
        { cliente: 'Cliente Ejemplo 10', ultimaVenta: '30/12/24', dias: 381, transacciones: 1, ventas: 3600.00 },
        { cliente: 'Cliente Ejemplo 11', ultimaVenta: '30/12/24', dias: 381, transacciones: 1, ventas: 3300.00 },
        { cliente: 'Cliente Ejemplo 12', ultimaVenta: '30/12/24', dias: 381, transacciones: 1, ventas: 3000.00 },
        { cliente: 'Cliente Ejemplo 13', ultimaVenta: '30/12/24', dias: 381, transacciones: 1, ventas: 2800.00 },
        { cliente: 'Cliente Ejemplo 14', ultimaVenta: '30/12/24', dias: 381, transacciones: 1, ventas: 2600.00 },
        { cliente: 'Cliente Ejemplo 15', ultimaVenta: '30/12/24', dias: 381, transacciones: 1, ventas: 2400.00 },
        { cliente: 'Cliente Ejemplo 16', ultimaVenta: '30/12/24', dias: 381, transacciones: 1, ventas: 2200.00 },
        { cliente: 'Cliente Ejemplo 17', ultimaVenta: '30/12/24', dias: 381, transacciones: 1, ventas: 2000.00 },
        { cliente: 'Cliente Ejemplo 18', ultimaVenta: '30/12/24', dias: 381, transacciones: 1, ventas: 1800.00 },
        { cliente: 'Cliente Ejemplo 19', ultimaVenta: '30/12/24', dias: 381, transacciones: 1, ventas: 1600.00 },
        { cliente: 'Cliente Ejemplo 20', ultimaVenta: '30/12/24', dias: 381, transacciones: 1, ventas: 1400.00 },
        { cliente: 'Cliente Ejemplo 21', ultimaVenta: '30/12/24', dias: 381, transacciones: 1, ventas: 1200.00 },
        { cliente: 'Facturacion@Airboxsv.Com', ultimaVenta: '30/12/24', dias: 381, transacciones: 1, ventas: -10758.73 }
      ],
      // Datos para la sección de Control de Cuentas
      metricasCuentas: {
        // Cuentas por cobrar
        cuentasPorCobrarTotal: 64789.74,
        cuentasPorCobrar30Dias: 2587.57,
        cuentasPorCobrar60Dias: 118.65,
        cuentasPorCobrar90Dias: 0,
        tooltipCuentasPorCobrar: 'Monico Rusconi, Maria Alicia',
        // Cuentas por pagar
        cuentasPorPagarTotal: 39761.81,
        cuentasPorPagar30Dias: 4465.61,
        cuentasPorPagar60Dias: 0,
        cuentasPorPagar90Dias: 0
      },
      cuentasPorVigenciaConfig: {
        title: '',
        type: 'pie',
        data: [
          { name: 'Vencido', value: 81892.51 },
          { name: 'Vigente', value: 3035.81 },
          { name: 'Pendiente', value: 0 }
        ],
        colors: ['#3366cc', '#e0e0e0', '#e0e9f6']
      },
      cuentasPorCobrarClientes: [
        { name: 'José Benitez', amount: 2919.13 },
        { name: 'José Benitez', amount: 2021.55 },
        { name: 'Dra Aguila', amount: 908.52 },
        { name: 'Monico Rusconi, Maria Alicia', amount: 612.11 },
        { name: 'Eugenia Galvez', amount: 565.00 },
        { name: 'Facturacion@Airboxsv.Com', amount: 565.00 },
        { name: 'Alas Argueta, Rene Guillermo', amount: 542.40 },
        { name: 'Velo Group, S.A. De C.V.', amount: 395.50 },
        { name: 'Victor Mejia', amount: 379.68 },
        { name: 'Sonia', amount: 355.95 },
        { name: 'Nathalia Torres', amount: 339.00 },
        { name: 'Cond Palo Alto', amount: 282.50 },
        { name: 'Desarrollos Veterinarios', amount: 282.50 },
        { name: 'Edwin Evora', amount: 282.50 }
      ],
      detalleCuentasPorCobrar: [
        {
          cliente: 'Monico Rusconi, Maria Alicia',
          factura: 55,
          fechaVenta: '14/01/26',
          fechaPago: '16/6/26',
          diasVencimiento: 149,
          estado: 'Vigente',
          ventasConIVA: 395.50,
          montoAbonado: 65.91,
          diasAbono: 4,
          saldoPendiente: 329.59
        },
        {
          cliente: 'Claudia Del Carmen Mojica Rivera',
          factura: 30,
          fechaVenta: '09/01/26',
          fechaPago: '9/3/26',
          diasVencimiento: 50,
          estado: 'Vigente',
          ventasConIVA: 118.65,
          montoAbonado: 0.00,
          saldoPendiente: 118.65
        },
        {
          cliente: 'Julio Funes',
          factura: 81,
          fechaVenta: '19/12/25',
          fechaPago: '15/2/26',
          diasVencimiento: 28,
          estado: 'Vigente',
          ventasConIVA: 271.20,
          montoAbonado: 0.00,
          saldoPendiente: 271.20
        },
        {
          cliente: 'Karla Garcia',
          factura: 127,
          fechaVenta: '15/01/26',
          fechaPago: '29/1/26',
          diasVencimiento: 11,
          estado: 'Vigente',
          ventasConIVA: 39.55,
          montoAbonado: 25.00,
          diasAbono: 11,
          saldoPendiente: 14.55
        },
        {
          cliente: 'José Benitez',
          factura: 10,
          fechaVenta: '06/01/26',
          fechaPago: '',
          diasVencimiento: 0,
          estado: 'Vigente',
          ventasConIVA: 113.00,
          montoAbonado: 0.00,
          saldoPendiente: 113.00
        }
      ],
      cuentasPorPagarVigenciaConfig: {
        title: '',
        type: 'pie',
        data: [
          { name: 'Vencido', value: 35296.20 },
          { name: 'Pendiente', value: 4465.61 }
        ],
        colors: ['#F19447', '#fef0e6']
      },
      cuentasPorPagarProveedores: [
        { name: 'DALIA', amount: 12333.25 },
        { name: '(En blanco)', amount: 10905.25 },
        { name: 'ANDA', amount: 4849.22 },
        { name: 'Bodega', amount: 4809.90 },
        { name: 'FREUND', amount: 1667.00 },
        { name: 'JESÚS ALVARADO', amount: 800.00 },
        { name: 'APRIL', amount: 565.00 },
        { name: 'CAESS', amount: 541.00 },
        { name: 'Edgardo', amount: 463.00 },
        { name: 'Gabriela', amount: 416.95 },
        { name: 'CARLOS ARNULFO', amount: 406.24 },
        { name: 'Jennifer', amount: 401.70 },
        { name: 'VERSATIVE', amount: 395.50 },
        { name: 'Melissa Benitez', amount: 200.00 }
      ],
      resumenCuentasPorPagar: [
        {
          fechaCompra: '2/4/2025',
          vencimiento: '',
          diasVencimiento: 0,
          estado: 'Pendiente',
          gastosTotalesConIVA: 37.00,
          totalAbonado: 0.00,
          ultimoAbono: '',
          saldoPendiente: 37.00
        },
        {
          fechaCompra: '6/10/2025',
          vencimiento: '',
          diasVencimiento: 0,
          estado: 'Pendiente',
          gastosTotalesConIVA: 252.14,
          totalAbonado: 0.00,
          ultimoAbono: '',
          saldoPendiente: 252.14
        },
        {
          fechaCompra: '1/22/2025',
          vencimiento: '',
          diasVencimiento: 0,
          estado: 'Pendiente',
          gastosTotalesConIVA: 1214.75,
          totalAbonado: 0.00,
          ultimoAbono: '',
          saldoPendiente: 1214.75
        },
        {
          fechaCompra: '2/4/2025',
          vencimiento: '',
          diasVencimiento: 0,
          estado: 'Pendiente',
          gastosTotalesConIVA: 37.00,
          totalAbonado: 0.00,
          ultimoAbono: '',
          saldoPendiente: 37.00
        },
        {
          fechaCompra: '6/10/2025',
          vencimiento: '',
          diasVencimiento: 0,
          estado: 'Pendiente',
          gastosTotalesConIVA: 252.14,
          totalAbonado: 0.00,
          ultimoAbono: '',
          saldoPendiente: 252.14
        }
      ],
      // Datos para la sección de Gastos
      metricasGastos: {
        gastosConIVA: 73393.49,
        gastosSinIVA: 12773.03,
        gastosMesAnterior: 7954.89,
        variacionGastos: 4818.14,
        aumentoCostosPorcentaje: 61
      },
      gastosPorMesConfig: {
        title: '',
        type: 'line',
        labels: ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'],
        data: [3533.86, 4724.34, 350.24, 1852.60, 15319.36, 200.00, 602.99, 6974.26, 5548.15, 13559.77, 7954.89, 12773.03],
        colors: ['#F19447']
      },
      gastosVsPresupuestoConfig: {
        title: '',
        type: 'bar',
        labels: ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'],
        data: [
          {
            name: 'Gastos totales',
            data: [3533.86, 4724.34, 350.24, 1852.60, 15319.36, 200.00, 602.99, 6974.26, 5548.15, 13559.77, 7954.89, 12773.03]
          },
          {
            name: 'Presupuestado',
            data: [5000.00, 5000.00, 5000.00, 5000.00, 5000.00, 5000.00, 5000.00, 5000.00, 5000.00, 5000.00, 5000.00, 5000.00]
          }
        ],
        colors: ['#F19447', '#d3d3d3']
      },
      gastosVsAnioAnteriorConfig: {
        title: '',
        type: 'bar',
        labels: ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'],
        data: [
          {
            name: 'Año actual',
            data: [3533.86, 4724.34, 350.24, 1852.60, 15319.36, 200.00, 602.99, 6974.26, 5548.15, 13559.77, 7954.89, 12773.03]
          },
          {
            name: 'Año anterior',
            data: [2800.00, 3200.00, 250.00, 1500.00, 12000.00, 150.00, 500.00, 5500.00, 4500.00, 11000.00, 6500.00, 10000.00]
          }
        ],
        colors: ['#F19447', '#d3d3d3']
      },
      gastosPresupuesto: [5000.00, 5000.00, 5000.00, 5000.00, 5000.00, 5000.00, 5000.00, 5000.00, 5000.00, 5000.00, 5000.00, 5000.00],
      gastosAnioAnterior: [2800.00, 3200.00, 250.00, 1500.00, 12000.00, 150.00, 500.00, 5500.00, 4500.00, 11000.00, 6500.00, 10000.00],
      gastosPorCategoriaConfig: {
        title: '',
        type: 'bar',
        labels: ['Compras', 'G. operativos', 'G. administrativos', 'G. financieros', 'G. comerciales', 'Costo de ventas'],
        data: [47175.21, 17588.96, 5048.37, 3355.95, 200.00, 25.00],
        colors: ['#F19447'],
        horizontal: true
      },
      gastosPorConceptoConfig: {
        title: '',
        type: 'bar',
        labels: ['Costo artículo', 'Materia Prima', 'Gastos varios', 'Planilla', 'Alquiler', 'Impuestos', 'Servicios', 'Insumos', 'Combustible', 'Mantenimiento', 'Publicidad', 'Compras', 'Costo de venta'],
        data: [47124.36, 8013.90, 4184.25, 3488.00, 1345.37, 763.00, 733.42, 215.00, 200.00, 50.85, 25.00, 15.00, 25.00],
        colors: ['#F19447'],
        rotateLabels: 45
      },
      gastosPorFormaPagoConfig: {
        title: '',
        type: 'bar',
        data: [
          {
            name: 'Efectivo',
            value: 57532.12,
            children: []
          },
          {
            name: 'Transferencia',
            value: 14231.37,
            children: [
              { name: 'Transferencia', value: 8000.00 },
              { name: 'Tarjeta de crédito', value: 6231.37 }
            ]
          }
        ],
        colors: ['#F19447', '#C9732F', '#A0521F']
      },
      detalleGastos: [
        { fecha: '2024-12-05', proveedor: 'Edgardo', concepto: 'Diciembre- Planilla', documento: 'Factura', correlativo: '283', gastosConIVA: 350.00 },
        { fecha: '2024-12-03', proveedor: 'DALIA', concepto: 'Proyecto-Materia prima', documento: 'Factura', correlativo: '3748', gastosConIVA: 39.00 },
        { fecha: '2024-11-29', proveedor: 'FREUND', concepto: 'pasante', documento: 'Factura', correlativo: '2849', gastosConIVA: 800.00 },
        { fecha: '2024-11-28', proveedor: 'Bodega', concepto: 'Diciembre alquiler', documento: 'Factura', correlativo: '', gastosConIVA: 180.00 },
        { fecha: '2024-11-27', proveedor: 'CAESS', concepto: 'FREUND MATERIALES', documento: 'Factura', correlativo: '', gastosConIVA: 200.00 },
        { fecha: '2024-11-26', proveedor: 'Gabriela Avilés', concepto: 'Hoja en blanco 500', documento: 'Factura', correlativo: '', gastosConIVA: 500.00 },
        { fecha: '2024-11-25', proveedor: 'ANDA', concepto: 'Luz- Nov', documento: 'Factura', correlativo: '', gastosConIVA: -114.00 },
        { fecha: '2024-11-24', proveedor: 'JAVIER', concepto: 'Agua-Noviembre', documento: 'Factura', correlativo: '', gastosConIVA: 500.00 },
        { fecha: '2024-11-23', proveedor: 'Jennifer', concepto: 'Noviembre-Luz', documento: 'Factura', correlativo: '', gastosConIVA: 150.00 },
        { fecha: '2024-11-22', proveedor: 'Ministerio de', concepto: 'Noviembre-Electricidad', documento: 'Factura', correlativo: '', gastosConIVA: 30.00 },
        { fecha: '2024-11-21', proveedor: 'CAESS', concepto: 'Diciembre-Electricidad', documento: 'Factura', correlativo: '', gastosConIVA: 100.00 },
        { fecha: '2024-11-20', proveedor: 'ANDA', concepto: 'IVA noviembre 2024', documento: 'Factura', correlativo: '', gastosConIVA: 100.00 },
        { fecha: '2024-11-19', proveedor: 'FREUND', concepto: 'Maquinaria limpieza', documento: 'Factura', correlativo: '', gastosConIVA: 100.00 },
        { fecha: '2024-11-18', proveedor: 'DALIA', concepto: 'PAC novimebre 2024', documento: 'Crédito', correlativo: '', gastosConIVA: 13.00 },
        { fecha: '2024-11-17', proveedor: 'Bodega', concepto: 'Materia prima diciembre', documento: 'Factura', correlativo: '', gastosConIVA: 395.00 },
        { fecha: '2024-11-16', proveedor: 'Edgardo', concepto: 'Servicios diciembre', documento: 'Factura', correlativo: '', gastosConIVA: 3955.00 },
        { fecha: '2024-11-15', proveedor: 'FREUND', concepto: 'Insumos varios', documento: 'Factura', correlativo: '', gastosConIVA: 383.00 },
        { fecha: '2024-11-14', proveedor: 'ANDA', concepto: 'Mantenimiento equipo', documento: 'Factura', correlativo: '', gastosConIVA: 250.00 },
        { fecha: '2024-11-13', proveedor: 'CAESS', concepto: 'Publicidad diciembre', documento: 'Factura', correlativo: '', gastosConIVA: 368.00 },
        { fecha: '2024-11-12', proveedor: 'Bodega', concepto: 'Compras varias', documento: 'Factura', correlativo: '', gastosConIVA: 1200.00 },
        { fecha: '2024-11-11', proveedor: 'DALIA', concepto: 'Materiales construcción', documento: 'Factura', correlativo: '', gastosConIVA: 2500.00 },
        { fecha: '2024-11-10', proveedor: 'FREUND', concepto: 'Equipos oficina', documento: 'Factura', correlativo: '', gastosConIVA: 1500.00 },
        { fecha: '2024-11-09', proveedor: 'ANDA', concepto: 'Servicios públicos', documento: 'Factura', correlativo: '', gastosConIVA: 800.00 },
        { fecha: '2024-11-08', proveedor: 'Bodega', concepto: 'Inventario diciembre', documento: 'Factura', correlativo: '', gastosConIVA: 5000.00 },
        { fecha: '2024-11-07', proveedor: 'Edgardo', concepto: 'Planilla noviembre', documento: 'Factura', correlativo: '', gastosConIVA: 3200.00 },
        { fecha: '2024-11-06', proveedor: 'CAESS', concepto: 'Energía eléctrica', documento: 'Factura', correlativo: '', gastosConIVA: 450.00 },
        { fecha: '2024-11-05', proveedor: 'DALIA', concepto: 'Materiales varios', documento: 'Factura', correlativo: '', gastosConIVA: 1800.00 },
        { fecha: '2024-11-04', proveedor: 'FREUND', concepto: 'Herramientas', documento: 'Factura', correlativo: '', gastosConIVA: 950.00 },
        { fecha: '2024-11-03', proveedor: 'Bodega', concepto: 'Almacén diciembre', documento: 'Factura', correlativo: '', gastosConIVA: 3200.00 },
        { fecha: '2024-11-02', proveedor: 'ANDA', concepto: 'Agua potable', documento: 'Factura', correlativo: '', gastosConIVA: 280.00 },
        { fecha: '2024-11-01', proveedor: 'Edgardo', concepto: 'Servicios generales', documento: 'Factura', correlativo: '', gastosConIVA: 1200.00 },
        { fecha: '2024-10-30', proveedor: 'CAESS', concepto: 'Luz octubre', documento: 'Factura', correlativo: '', gastosConIVA: 380.00 },
        { fecha: '2024-10-29', proveedor: 'DALIA', concepto: 'Materiales octubre', documento: 'Factura', correlativo: '', gastosConIVA: 1500.00 },
        { fecha: '2024-10-28', proveedor: 'FREUND', concepto: 'Equipos varios', documento: 'Factura', correlativo: '', gastosConIVA: 2200.00 },
        { fecha: '2024-10-27', proveedor: 'Bodega', concepto: 'Inventario octubre', documento: 'Factura', correlativo: '', gastosConIVA: 4500.00 },
        { fecha: '2024-10-26', proveedor: 'ANDA', concepto: 'Servicios octubre', documento: 'Factura', correlativo: '', gastosConIVA: 600.00 },
        { fecha: '2024-10-25', proveedor: 'Edgardo', concepto: 'Planilla octubre', documento: 'Factura', correlativo: '', gastosConIVA: 3100.00 },
        { fecha: '2024-10-24', proveedor: 'CAESS', concepto: 'Energía octubre', documento: 'Factura', correlativo: '', gastosConIVA: 420.00 },
        { fecha: '2024-10-23', proveedor: 'DALIA', concepto: 'Compras octubre', documento: 'Factura', correlativo: '', gastosConIVA: 2000.00 },
        { fecha: '2024-10-22', proveedor: 'FREUND', concepto: 'Materiales construcción', documento: 'Factura', correlativo: '', gastosConIVA: 1800.00 },
        { fecha: '2024-10-21', proveedor: 'Bodega', concepto: 'Almacén octubre', documento: 'Factura', correlativo: '', gastosConIVA: 2800.00 },
        { fecha: '2024-10-20', proveedor: 'ANDA', concepto: 'Agua octubre', documento: 'Factura', correlativo: '', gastosConIVA: 250.00 },
        { fecha: '2024-10-19', proveedor: 'Edgardo', concepto: 'Servicios varios', documento: 'Factura', correlativo: '', gastosConIVA: 1100.00 },
        { fecha: '2024-10-18', proveedor: 'CAESS', concepto: 'Luz septiembre', documento: 'Factura', correlativo: '', gastosConIVA: 400.00 },
        { fecha: '2024-10-17', proveedor: 'DALIA', concepto: 'Materiales septiembre', documento: 'Factura', correlativo: '', gastosConIVA: 1400.00 },
        { fecha: '2024-10-16', proveedor: 'FREUND', concepto: 'Equipos septiembre', documento: 'Factura', correlativo: '', gastosConIVA: 1900.00 },
        { fecha: '2024-10-15', proveedor: 'Bodega', concepto: 'Inventario septiembre', documento: 'Factura', correlativo: '', gastosConIVA: 4100.00 },
        { fecha: '2024-10-14', proveedor: 'ANDA', concepto: 'Servicios septiembre', documento: 'Factura', correlativo: '', gastosConIVA: 550.00 },
        { fecha: '2024-10-13', proveedor: 'Edgardo', concepto: 'Planilla septiembre', documento: 'Factura', correlativo: '', gastosConIVA: 3000.00 },
        { fecha: '2024-10-12', proveedor: 'CAESS', concepto: 'Energía septiembre', documento: 'Factura', correlativo: '', gastosConIVA: 410.00 },
        { fecha: '2024-10-11', proveedor: 'DALIA', concepto: 'Compras septiembre', documento: 'Factura', correlativo: '', gastosConIVA: 1900.00 },
        { fecha: '2024-10-10', proveedor: 'FREUND', concepto: 'Materiales varios', documento: 'Factura', correlativo: '', gastosConIVA: 1600.00 },
        { fecha: '2024-10-09', proveedor: 'Bodega', concepto: 'Almacén septiembre', documento: 'Factura', correlativo: '', gastosConIVA: 2600.00 },
        { fecha: '2024-10-08', proveedor: 'ANDA', concepto: 'Agua septiembre', documento: 'Factura', correlativo: '', gastosConIVA: 240.00 },
        { fecha: '2024-10-07', proveedor: 'Edgardo', concepto: 'Servicios generales', documento: 'Factura', correlativo: '', gastosConIVA: 1000.00 }
      ],
      gastosPorProveedor: [
        { name: 'Bodega', amount: 25324.62 },
        { name: 'DALIA', amount: 16049.27 },
        { name: 'ANDA', amount: 8875.50 },
        { name: 'FREUND', amount: 3894.50 },
        { name: 'Ministerio de', amount: 3305.95 },
        { name: 'Edgardo', amount: 2466.30 },
        { name: 'Lightfire', amount: 2000.00 },
        { name: 'APRIL', amount: 1655.68 },
        { name: 'Gabriela', amount: 1416.95 },
        { name: 'CAESS', amount: 950.00 },
        { name: 'JESÚS ALVARA...', amount: 800.00 },
        { name: 'Organika, SA d...', amount: 718.03 },
        { name: 'Jennifer', amount: 695.50 },
        { name: 'Don local', amount: 681.28 },
        { name: 'DELIVERY', amount: 617.00 },
        { name: 'VERSATIVE', amount: 395.50 },
        { name: '01Hawb3110', amount: 367.24 },
        { name: 'Gustavo', amount: 300.00 },
        { name: 'Secretaria', amount: 300.00 },
        { name: 'MOTOMAS', amount: 272.95 },
        { name: 'asdadasd', amount: 242.50 },
        { name: 'Jesus Alfonso', amount: 208.00 },
        { name: 'Alele', amount: 200.85 },
        { name: 'Melissa Benitez', amount: 200.00 },
        { name: 'JAVIER', amount: 193.84 }
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

  // Generar datos dummy de ventas detalladas con relaciones
  private generarVentasDetalladasDummy(): any[] {
    const canales = ['Facebook', 'Tienda', 'Instagram', 'Marketplace', 'Whatsapp', 'El Salvador'];
    const vendedores = ['Gaby', 'Paula', 'Soporte', 'Jennifer', 'Gabriela', 'DANIELA'];
    const formasPago = ['Efectivo', 'Tarjeta', 'Transferencia', 'Cheque', 'Otros'];
    const categorias = ['Categoría 3', 'Adidas - Masculino', 'Promoción', 'Planes', 'Implementaciones', 'Categoría 1'];
    const productos = ['Producto C', 'AA2', 'Prueba12', 'Adidas Forum morados', 'SERVICIO DE IMPLEM...', 'Plan Avanzado - Smar...'];
    const clientes = ['Consumidor Final', 'Manufacturas Cavalier Sa D...', 'Cliente Ejemplo 1', 'Cliente Ejemplo 2', 'Cliente Ejemplo 3', '(En blanco)'];
    
    const meses = [
      { nombre: 'enero', dias: 31 },
      { nombre: 'febrero', dias: 28 },
      { nombre: 'marzo', dias: 31 },
      { nombre: 'abril', dias: 30 },
      { nombre: 'mayo', dias: 31 },
      { nombre: 'junio', dias: 30 },
      { nombre: 'julio', dias: 31 },
      { nombre: 'agosto', dias: 31 },
      { nombre: 'septiembre', dias: 30 },
      { nombre: 'octubre', dias: 31 },
      { nombre: 'noviembre', dias: 30 },
      { nombre: 'diciembre', dias: 31 }
    ];

    const ventas: any[] = [];
    let facturaId = 1;

    // Generar ventas distribuidas a lo largo del año con relaciones consistentes
    meses.forEach((mes, mesIndex) => {
      const año = 2024;
      const diasEnMes = mes.dias;
      
      // Generar entre 15-30 ventas por mes
      const numVentas = Math.floor(Math.random() * 16) + 15;
      
      for (let i = 0; i < numVentas; i++) {
        const dia = Math.floor(Math.random() * diasEnMes) + 1;
        const fecha = new Date(año, mesIndex, dia);
        
        // Seleccionar valores relacionados (algunos canales tienen más probabilidad con ciertos vendedores)
        const canalIndex = Math.floor(Math.random() * canales.length);
        const canal = canales[canalIndex];
        
        // Relacionar vendedores con canales (ej: Gaby vende más por Facebook)
        let vendedorIndex;
        if (canal === 'Facebook' && Math.random() > 0.3) {
          vendedorIndex = 0; // Gaby
        } else if (canal === 'Tienda' && Math.random() > 0.4) {
          vendedorIndex = 1; // Paula
        } else {
          vendedorIndex = Math.floor(Math.random() * vendedores.length);
        }
        const vendedor = vendedores[vendedorIndex];
        
        // Relacionar forma de pago con canal (ej: Tienda usa más Efectivo)
        let formaPagoIndex;
        if (canal === 'Tienda' && Math.random() > 0.3) {
          formaPagoIndex = 0; // Efectivo
        } else if (canal === 'Facebook' && Math.random() > 0.4) {
          formaPagoIndex = 2; // Transferencia
        } else {
          formaPagoIndex = Math.floor(Math.random() * formasPago.length);
        }
        const formaPago = formasPago[formaPagoIndex];
        
        // Relacionar categoría con producto
        const categoriaIndex = Math.floor(Math.random() * categorias.length);
        const categoria = categorias[categoriaIndex];
        const productoIndex = Math.min(categoriaIndex, productos.length - 1);
        const producto = productos[productoIndex];
        
        // Seleccionar cliente
        const clienteIndex = Math.floor(Math.random() * clientes.length);
        const cliente = clientes[clienteIndex];
        
        // Generar montos realistas
        const montoBase = Math.random() * 5000 + 100; // Entre 100 y 5100
        const monto = Math.round(montoBase * 100) / 100;
        const cantidad = Math.floor(Math.random() * 5) + 1;
        const precioUnitario = monto / cantidad;
        const descuento = Math.random() > 0.7 ? Math.round(monto * 0.1 * 100) / 100 : 0;
        const ventasSinIVA = monto / 1.12;
        const costoTotal = ventasSinIVA * 0.5; // 50% de costo
        const utilidad = ventasSinIVA - costoTotal;
        
        ventas.push({
          fecha: fecha.toISOString().split('T')[0],
          cliente: cliente,
          factura: `FAC-${facturaId++}`,
          productos: cantidad,
          monto: monto,
          estado: Math.random() > 0.1 ? 'completada' : 'pendiente',
          // Campos para filtros interactivos
          canal: canal,
          vendedor: vendedor,
          formaPago: formaPago,
          categoria: categoria,
          producto: producto,
          cantidad: cantidad,
          precioUnitario: precioUnitario,
          descuento: descuento,
          ventasSinIVA: ventasSinIVA,
          costoTotal: costoTotal,
          utilidad: utilidad
        });
      }
    });

    // Agregar algunas ventas grandes para que coincidan con los totales
    ventas.push({
      fecha: '2024-06-13',
      cliente: '(En blanco)',
      factura: 'FAC-ESPECIAL-1',
      productos: 1,
      monto: 3469374.25,
      estado: 'completada',
      canal: 'Facebook',
      vendedor: 'Gaby',
      formaPago: 'Efectivo',
      categoria: 'Categoría 3',
      producto: 'Producto C',
      cantidad: 1,
      precioUnitario: 3469374.25,
      descuento: 0,
      ventasSinIVA: 3098279.56,
      costoTotal: 1549139.78,
      utilidad: 1549139.78
    });

    return ventas.sort((a, b) => new Date(a.fecha).getTime() - new Date(b.fecha).getTime());
  }
}

