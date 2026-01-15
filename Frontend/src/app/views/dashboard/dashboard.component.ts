import { Component, OnInit } from '@angular/core';
import { DashboardDataService } from './services/dashboard-data.service';

@Component({
  selector: 'app-dashboard',
  templateUrl: './dashboard.component.html',
  styleUrls: ['./dashboard.component.css']
})
export class DashboardComponent implements OnInit {
  
  loading = false;
  datos: any = {};

  // Configuración de secciones - fácil de extender
  secciones = [
    { nombre: 'Resultados', activo: true, componente: 'Resultados' },
    { nombre: 'Ventas', activo: false, componente: 'Ventas' },
    { nombre: 'Finanzas', activo: false, componente: 'Finanzas' },
    { nombre: 'Gastos', activo: false, componente: 'Gastos' },
    { nombre: 'Control de cuentas', activo: false, componente: 'Control de cuentas' },
    { nombre: 'Inventario', activo: false, componente: 'Inventario' }
  ];

  anios = [2024, 2025, 2026];
  anioSeleccionado = 2024;

  sucursales = [
    { id: 'todas', nombre: 'Todas' },
    { id: '1', nombre: 'Sucursal 1' },
    { id: '2', nombre: 'Sucursal 2' }
  ];
  sucursalSeleccionada = 'todas';

  presupuestos = [
    { id: 'todas', nombre: 'Todas' },
    { id: '2024', nombre: '2024' },
    { id: '2025', nombre: '2025' }
  ];
  presupuestoSeleccionado = 'todas';

  constructor(
    private dashboardDataService: DashboardDataService
  ) { }

  ngOnInit(): void {
    this.cargarDatos();
  }

  cambiarSeccion(seccion: any): void {
    this.secciones.forEach(s => s.activo = false);
    seccion.activo = true;
    // Recargar datos según la sección seleccionada
    this.cargarDatos();
  }

  cambiarAnio(anio: number): void {
    this.anioSeleccionado = anio;
    // Recargar datos según el año seleccionado
    this.cargarDatos();
  }

  cambiarSucursal(): void {
    // Recargar datos según la sucursal seleccionada
    this.cargarDatos();
  }

  get seccionActiva(): string {
    const seccion = this.secciones.find(s => s.activo);
    return seccion ? seccion.nombre : 'Resultados';
  }

  cargarDatos(): void {
    this.loading = true;
    const filtros = {
      seccion: this.seccionActiva,
      anio: this.anioSeleccionado,
      sucursal: this.sucursalSeleccionada
    };
    
    this.dashboardDataService.obtenerDatosPorFiltro(filtros).subscribe({
      next: (data) => {
        this.datos = data;
        this.loading = false;
      },
      error: (error) => {
        console.error('Error al cargar datos del dashboard:', error);
        this.loading = false;
      }
    });
  }

  actualizarDatos(): void {
    this.cargarDatos();
  }
}

