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
  private static readonly SECCION_GUARDADA_KEY = 'smartpyme.dashboard.seccionActiva';

  /** Pestañas que tienen vista en el `ngSwitch` (Finanzas u otras sin componente no entran). */
  private readonly seccionesConVista = new Set<string>([
    'Resultados',
    'Ventas',
    'Gastos',
    'Control de cuentas',
    'Inventario',
  ]);

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
    this.restaurarSeccionDesdeAlmacenamiento();
    this.cargarDatos();
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }

  cambiarSeccion(seccion: any): void {
    this.secciones.forEach(s => s.activo = false);
    seccion.activo = true;
    this.persistirSeccionActivaSiAplica();
    // Cargar datos iniciales para la nueva sección
    // Cada sección manejará sus propios filtros después
    this.cargarDatos();
  }

  private restaurarSeccionDesdeAlmacenamiento(): void {
    try {
      const guardada = localStorage.getItem(
        DashboardComponent.SECCION_GUARDADA_KEY
      )?.trim();
      if (!guardada || !this.seccionesConVista.has(guardada)) {
        return;
      }
      const destino = this.secciones.find((s) => s.nombre === guardada);
      if (!destino) {
        return;
      }
      this.secciones.forEach((s) => (s.activo = false));
      destino.activo = true;
    } catch {
      /* sin localStorage o quota */
    }
  }

  private persistirSeccionActivaSiAplica(): void {
    const nombre = this.seccionActiva;
    try {
      if (this.seccionesConVista.has(nombre)) {
        localStorage.setItem(
          DashboardComponent.SECCION_GUARDADA_KEY,
          nombre
        );
      }
    } catch {
      /* sin localStorage o quota */
    }
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
    const filtrosCompletos = {
      seccion: 'Resultados',
      ...filtros,
    };

    this.dashboardDataService
      .obtenerResultadosProgresivo(filtrosCompletos)
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: (data) => {
          this.datos = { ...data };
          this.cdr.markForCheck();
        },
        error: (error) => {
          console.error('Error al cargar datos de resultados:', error);
          this.datos = {};
          this.cdr.markForCheck();
        },
      });
  }

  onFiltrosGastosCambiados(filtros: any): void {
    const filtrosCompletos = {
      seccion: 'Gastos',
      ...filtros,
    };

    this.dashboardDataService
      .obtenerGastosProgresivo(filtrosCompletos)
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: (data) => {
          this.datos = { ...data };
          this.cdr.markForCheck();
        },
        error: (error) => {
          console.error('Error al cargar datos de gastos:', error);
          this.datos = {};
          this.cdr.markForCheck();
        },
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
    const filtrosCompletos = {
      seccion: 'Control de cuentas',
      ...filtros,
    };

    this.dashboardDataService
      .obtenerCuentasProgresivo(filtrosCompletos)
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: (data) => {
          this.datos = { ...data };
          this.cdr.markForCheck();
        },
        error: (error) => {
          console.error('Error al cargar datos de control de cuentas:', error);
          this.datos = {};
          this.cdr.markForCheck();
        },
      });
  }

  onFiltrosInventarioCambiados(filtros: any): void {
    const filtrosCompletos = {
      seccion: 'Inventario',
      ...filtros,
    };

    this.dashboardDataService
      .obtenerInventarioProgresivo(filtrosCompletos)
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: (data) => {
          this.datos = { ...data };
          this.cdr.markForCheck();
        },
        error: (error) => {
          console.error('Error al cargar datos de inventario:', error);
          this.datos = {};
          this.cdr.markForCheck();
        },
      });
  }
}

