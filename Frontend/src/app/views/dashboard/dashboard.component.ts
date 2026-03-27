import { Component, OnInit, OnDestroy, ChangeDetectorRef } from '@angular/core';
import { Subject } from 'rxjs';
import { takeUntil } from 'rxjs/operators';
import { DashboardDataService } from './services/dashboard-data.service';

@Component({
  selector: 'app-dashboard',
  templateUrl: './dashboard.component.html',
  styleUrls: ['./dashboard.component.css']
})
export class DashboardComponent implements OnInit, OnDestroy {
  private destroy$ = new Subject<void>();
  
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

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
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
        console.log('Dashboard - Datos recibidos:', {
          seccion: filtros.seccion,
          tieneDatos: !!data,
          keys: data ? Object.keys(data) : [],
          tieneDetalleGastos: !!(data && (data as any).detalleGastos),
          cantidadGastos: data && (data as any).detalleGastos ? (data as any).detalleGastos.length : 0
        });
        // Crear nueva referencia para que OnPush detecte cambios
        this.datos = { ...(data || {}) };
        this.loading = false;
        this.cdr.markForCheck();
      },
      error: (error) => {
        console.error('Error al cargar datos del dashboard:', error);
        this.datos = {};
        this.loading = false;
        this.cdr.markForCheck();
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
        // Crear una nueva referencia del objeto para que OnPush detecte el cambio
        this.datos = { ...(data || {}) };
        console.log('Dashboard - Datos de Resultados actualizados:', this.datos);
        this.loading = false;
        this.cdr.markForCheck();
      },
      error: (error) => {
        console.error('Error al cargar datos de resultados:', error);
        this.datos = {};
        this.loading = false;
        this.cdr.markForCheck();
      }
    });
  }

  onFiltrosGastosCambiados(filtros: any): void {
    this.loading = true;

    const filtrosCompletos = {
      seccion: 'Gastos',
      ...filtros
    };

    this.dashboardDataService.obtenerDatosPorFiltro(filtrosCompletos).subscribe({
      next: (data) => {
        // Crear nueva referencia para que OnPush detecte cambios
        this.datos = { ...(data || {}) };
        this.loading = false;
        this.cdr.markForCheck();
      },
      error: (error) => {
        console.error('Error al cargar datos de gastos:', error);
        this.datos = {};
        this.loading = false;
        this.cdr.markForCheck();
      }
    });
  }

  onFiltrosVentasCambiados(filtros: any): void {
    const filtrosCompletos = {
      seccion: 'Ventas',
      ...filtros,
    };

    this.dashboardDataService
      .obtenerVentasProgresivo(filtrosCompletos)
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: (data) => {
          this.datos = { ...data };
          this.cdr.markForCheck();
        },
        error: (error) => {
          console.error('Error al cargar datos de ventas:', error);
          this.datos = {};
          this.cdr.markForCheck();
        },
      });
  }

  onFiltrosControlCuentasCambiados(filtros: any): void {
    // Recargar datos con los filtros específicos de control de cuentas
    this.loading = true;

    const filtrosCompletos = {
      seccion: 'Control de cuentas',
      ...filtros // Filtros específicos de control de cuentas (fechaInicio, fechaFin, tipoCuenta, etc.)
    };

    this.dashboardDataService.obtenerDatosPorFiltro(filtrosCompletos).subscribe({
      next: (data) => {
        // Crear nueva referencia para que OnPush detecte cambios
        this.datos = { ...(data || {}) };
        this.loading = false;
        this.cdr.markForCheck();
      },
      error: (error) => {
        console.error('Error al cargar datos de control de cuentas:', error);
        this.datos = {};
        this.loading = false;
        this.cdr.markForCheck();
      }
    });
  }

  onFiltrosInventarioCambiados(filtros: any): void {
    // Recargar datos con los filtros específicos de inventario
    this.loading = true;

    const filtrosCompletos = {
      seccion: 'Inventario',
      ...filtros // Filtros específicos de inventario (fechaInicio, fechaFin, sucursal, etc.)
    };

    this.dashboardDataService.obtenerDatosPorFiltro(filtrosCompletos).subscribe({
      next: (data) => {
        // Crear nueva referencia para que OnPush detecte cambios
        this.datos = { ...(data || {}) };
        this.loading = false;
        this.cdr.markForCheck();
      },
      error: (error) => {
        console.error('Error al cargar datos de inventario:', error);
        this.datos = {};
        this.loading = false;
        this.cdr.markForCheck();
      }
    });
  }
}

