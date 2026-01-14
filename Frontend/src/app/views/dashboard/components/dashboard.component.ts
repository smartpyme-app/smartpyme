import { Component, OnInit } from '@angular/core';

@Component({
  selector: 'app-dashboard-old',
  templateUrl: './dashboard.component.html',
  styleUrls: ['./dashboard.component.css']
})
export class DashboardComponent implements OnInit {
  
  chartOption: any = {};
  loading = false;

  // Filtros
  secciones = [
    { nombre: 'Ventas', activo: false },
    { nombre: 'Finanzas', activo: false },
    { nombre: 'Gastos', activo: false },
    { nombre: 'Resultados', activo: true },
    { nombre: 'Control de cuentas', activo: false },
    { nombre: 'Inventario', activo: false }
  ];

  anios = [2024, 2025, 2026];
  anioSeleccionado = 2024;

  sucursales = [
    { id: 'todas', nombre: 'Todas' },
    { id: '1', nombre: 'Sucursal 1' },
    { id: '2', nombre: 'Sucursal 2' }
  ];
  sucursalSeleccionada = 'todas';

  get seccionActiva(): string {
    const seccion = this.secciones.find(s => s.activo);
    return seccion ? seccion.nombre : 'Resultados';
  }

  constructor() { }

  ngOnInit(): void {
    // Pequeño delay para asegurar que el DOM esté listo
    setTimeout(() => {
      this.initChart();
    }, 100);
  }

  cambiarSeccion(seccion: any): void {
    this.secciones.forEach(s => s.activo = false);
    seccion.activo = true;
    // Aquí puedes cargar datos según la sección seleccionada
    this.initChart();
  }

  cambiarAnio(anio: number): void {
    this.anioSeleccionado = anio;
    // Aquí puedes recargar datos según el año seleccionado
    this.initChart();
  }

  cambiarSucursal(sucursal: string): void {
    this.sucursalSeleccionada = sucursal;
    // Aquí puedes recargar datos según la sucursal seleccionada
    this.initChart();
  }

  initChart(): void {
    // Crear un nuevo objeto para forzar la detección de cambios
    this.chartOption = {
      title: {
        text: 'Dashboard',
        left: 'center',
        textStyle: {
          fontSize: 16
        }
      },
      tooltip: {
        trigger: 'axis',
        axisPointer: {
          type: 'cross'
        }
      },
      legend: {
        data: ['Ventas', 'Compras'],
        top: '10%'
      },
      grid: {
        left: '3%',
        right: '4%',
        bottom: '3%',
        containLabel: true
      },
      xAxis: {
        type: 'category',
        boundaryGap: false,
        data: ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun']
      },
      yAxis: {
        type: 'value'
      },
      series: [
        {
          name: 'Ventas',
          type: 'line',
          data: [120, 132, 101, 134, 90, 230],
          smooth: true,
          itemStyle: {
            color: '#5470c6'
          }
        },
        {
          name: 'Compras',
          type: 'line',
          data: [220, 182, 191, 234, 290, 330],
          smooth: true,
          itemStyle: {
            color: '#91cc75'
          }
        }
      ]
    };
  }
}
