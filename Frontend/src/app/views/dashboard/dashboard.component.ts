import { Component, OnInit, OnDestroy, ChangeDetectorRef } from '@angular/core';
import { Subject, Subscription } from 'rxjs';
import { takeUntil } from 'rxjs/operators';
import { DashboardDataService } from './services/dashboard-data.service';
import { FiltrosConsultaVentasDashboard } from './models/filtros-consulta-ventas-dashboard.model';

@Component({
  selector: 'app-dashboard',
  templateUrl: './dashboard.component.html',
  styleUrls: ['./dashboard.component.css']
})
export class DashboardComponent implements OnInit, OnDestroy {
  private static readonly SECCION_GUARDADA_KEY = 'smartpyme.dashboard.seccionActiva';

  /** Pestañas con vista en `ngSwitch` y persistencia en localStorage. */
  private readonly seccionesConVista = new Set<string>([
    'Resultados',
    'Ventas',
    'Gastos',
    'Control de cuentas',
    'Inventario',
  ]);

  private destroy$ = new Subject<void>();
  private datosSubscription?: Subscription;

  private cancelarSuscripcionActiva(): void {
    if (this.datosSubscription) {
      this.datosSubscription.unsubscribe();
      this.datosSubscription = undefined;
    }
  }

  loading = false;
  datos: any = {};
  filtrosPorSeccion: { [seccion: string]: any } = {};

  // Finanzas: oculta hasta estar lista — añadir de nuevo `{ nombre: 'Finanzas', ... }` aquí y el *ngSwitchCase* en el HTML.
  secciones = [
    { nombre: 'Resultados', activo: true, componente: 'Resultados' },
    { nombre: 'Ventas', activo: false, componente: 'Ventas' },
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
    this.cancelarSuscripcionActiva();
    this.destroy$.next();
    this.destroy$.complete();
  }

  cambiarSeccion(seccion: any): void {
    this.secciones.forEach(s => s.activo = false);
    seccion.activo = true;
    this.persistirSeccionActivaSiAplica();
    // Cargar datos usando filtros guardados de la nueva sección activa (si existen)
    const filtrosGuardados = this.filtrosPorSeccion[this.seccionActiva] || {};
    this.cargarDatos(filtrosGuardados);
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
    this.cancelarSuscripcionActiva();
    this.loading = true;

    const filtros = {
      seccion: this.seccionActiva,
      ...filtrosAdicionales
    };

    this.datosSubscription = this.dashboardDataService.obtenerDatosPorFiltro(filtros).subscribe({
      next: (data) => {
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
    this.cancelarSuscripcionActiva();
    this.filtrosPorSeccion['Resultados'] = filtros;
    const filtrosCompletos = {
      seccion: 'Resultados',
      ...filtros,
    };

    this.datosSubscription = this.dashboardDataService
      .obtenerResultadosProgresivo(filtrosCompletos)
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
    this.cancelarSuscripcionActiva();
    this.filtrosPorSeccion['Gastos'] = filtros;
    const filtrosCompletos = {
      seccion: 'Gastos',
      ...filtros,
    };

    this.datosSubscription = this.dashboardDataService
      .obtenerGastosProgresivo(filtrosCompletos)
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

  onFiltrosVentasCambiados(filtros: FiltrosConsultaVentasDashboard): void {
    this.cancelarSuscripcionActiva();
    this.filtrosPorSeccion['Ventas'] = filtros;
    const filtrosCompletos = {
      seccion: 'Ventas' as const,
      ...filtros,
    };

    this.datosSubscription = this.dashboardDataService
      .obtenerVentasProgresivo(filtrosCompletos)
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
    this.cancelarSuscripcionActiva();
    this.filtrosPorSeccion['Control de cuentas'] = filtros;
    const filtrosCompletos = {
      seccion: 'Control de cuentas',
      ...filtros,
    };

    this.datosSubscription = this.dashboardDataService
      .obtenerCuentasProgresivo(filtrosCompletos)
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
    this.cancelarSuscripcionActiva();
    this.filtrosPorSeccion['Inventario'] = filtros;
    const filtrosCompletos = {
      seccion: 'Inventario',
      ...filtros,
    };

    this.datosSubscription = this.dashboardDataService
      .obtenerInventarioProgresivo(filtrosCompletos)
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

