import { Component, OnInit, ChangeDetectorRef } from '@angular/core';
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


  constructor(
    private dashboardDataService: DashboardDataService,
    private cdr: ChangeDetectorRef
  ) { }

  ngOnInit(): void {
    this.cargarDatos();
  }

  cambiarSeccion(seccion: any): void {
    this.secciones.forEach(s => s.activo = false);
    seccion.activo = true;
    // Cargar datos iniciales para la nueva sección
    // Cada sección manejará sus propios filtros después
    this.cargarDatos();
  }

  get seccionActiva(): string {
    const seccion = this.secciones.find(s => s.activo);
    return seccion ? seccion.nombre : 'Resultados';
  }

  cargarDatos(filtrosAdicionales: any = {}): void {
    this.loading = true;
    
    const filtros = {
      seccion: this.seccionActiva,
      ...filtrosAdicionales
    };
    
    this.dashboardDataService.obtenerDatosPorFiltro(filtros).subscribe({
      next: (data) => {
        this.datos = data || {};
        this.loading = false;
        // Usar setTimeout para evitar ExpressionChangedAfterItHasBeenCheckedError
        setTimeout(() => {
          this.cdr.detectChanges();
        }, 0);
      },
      error: (error) => {
        console.error('Error al cargar datos del dashboard:', error);
        this.datos = {};
        this.loading = false;
        setTimeout(() => {
          this.cdr.detectChanges();
        }, 0);
      }
    });
  }

  actualizarDatos(): void {
    this.cargarDatos();
  }

  onFiltrosResultadosCambiados(filtros: any): void {
    // Recargar datos con los filtros específicos de resultados
    this.loading = true;
    
    const filtrosCompletos = {
      seccion: 'Resultados',
      ...filtros // Filtros específicos de resultados (anio, sucursal, presupuesto)
    };
    
    this.dashboardDataService.obtenerDatosPorFiltro(filtrosCompletos).subscribe({
      next: (data) => {
        this.datos = data || {};
        this.loading = false;
        setTimeout(() => {
          this.cdr.detectChanges();
        }, 0);
      },
      error: (error) => {
        console.error('Error al cargar datos de resultados:', error);
        this.datos = {};
        this.loading = false;
        setTimeout(() => {
          this.cdr.detectChanges();
        }, 0);
      }
    });
  }

  onFiltrosVentasCambiados(filtros: any): void {
    // Recargar datos con los filtros específicos de ventas
    this.loading = true;
    
    const filtrosCompletos = {
      seccion: 'Ventas',
      ...filtros // Filtros específicos de ventas (fechaInicio, fechaFin, vendedor, etc.)
    };
    
    this.dashboardDataService.obtenerDatosPorFiltro(filtrosCompletos).subscribe({
      next: (data) => {
        this.datos = data || {};
        this.loading = false;
        setTimeout(() => {
          this.cdr.detectChanges();
        }, 0);
      },
      error: (error) => {
        console.error('Error al cargar datos de ventas:', error);
        this.datos = {};
        this.loading = false;
        setTimeout(() => {
          this.cdr.detectChanges();
        }, 0);
      }
    });
  }
}

