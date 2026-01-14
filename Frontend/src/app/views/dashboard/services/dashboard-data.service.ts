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
        title: 'Ventas y gastos totales por mes',
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
      ] as BudgetMetric[]
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

