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

  // ponytail: deep comparison helper to ignore identical filter emissions
  private sonFiltrosIguales(f1: any, f2: any): boolean {
    if (!f1 || !f2) return f1 === f2;
    const keys1 = Object.keys(f1);
    const keys2 = Object.keys(f2);
    if (keys1.length !== keys2.length) return false;
    for (const key of keys1) {
      const v1 = f1[key];
      const v2 = f2[key];
      if (Array.isArray(v1) && Array.isArray(v2)) {
        if (v1.length !== v2.length) return false;
        const s1 = [...v1].sort();
        const s2 = [...v2].sort();
        if (s1.some((val, idx) => val !== s2[idx])) return false;
      } else if (v1 !== v2) {
        return false;
      }
    }
    return true;
  }

  loading = false;
  datos: any = {};
  datosResultadosCompletos = false;
  datosVentasCompletos = false;
  datosGastosCompletos = false;
  datosCuentasCompletos = false;
  datosInventarioCompletos = false;
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
    // ponytail: only load initial data if we have saved filters for the active section.
    // Otherwise, wait for the child component to mount and emit its default filters.
    const filtrosGuardados = this.filtrosPorSeccion[this.seccionActiva];
    if (filtrosGuardados) {
      this.cargarDatos(filtrosGuardados);
    } else {
      this.datos = {};
    }
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
    // ponytail: only load data on tab change if we have saved filters for the target section.
    // Otherwise, let the child component initialize and emit its defaults.
    const filtrosGuardados = this.filtrosPorSeccion[this.seccionActiva];
    if (filtrosGuardados) {
      this.cargarDatos(filtrosGuardados);
    } else {
      this.datos = {};
    }
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

    // ponytail: ensure we store the active filters
    this.filtrosPorSeccion[this.seccionActiva] = filtrosAdicionales;

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
    // Solo omitir si ya terminó la carga: si no, el hijo queda en “Filtrando datos…”
    // (emite al montar tras cambiar de tab y el padre hacía return sin tocar datosCompletos).
    if (
      this.sonFiltrosIguales(this.filtrosPorSeccion['Resultados'], filtros) &&
      this.datosResultadosCompletos
    ) {
      return;
    }
    this.cancelarSuscripcionActiva();
    this.datosResultadosCompletos = false;
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
          this.datosResultadosCompletos = true; // desbloquear aunque haya error
          this.cdr.markForCheck();
        },
        complete: () => {
          this.datosResultadosCompletos = true;
          this.cdr.markForCheck();
        },
      });
  }

  onFiltrosGastosCambiados(filtros: any): void {
    if (
      this.sonFiltrosIguales(this.filtrosPorSeccion['Gastos'], filtros) &&
      this.datosGastosCompletos
    ) {
      return;
    }
    this.cancelarSuscripcionActiva();
    this.datosGastosCompletos = false;
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
          this.datosGastosCompletos = true;
          this.cdr.markForCheck();
        },
        complete: () => {
          this.datosGastosCompletos = true;
          this.cdr.markForCheck();
        },
      });
  }


  onFiltrosVentasCambiados(filtros: FiltrosConsultaVentasDashboard): void {
    if (
      this.sonFiltrosIguales(this.filtrosPorSeccion['Ventas'], filtros) &&
      this.datosVentasCompletos
    ) {
      return;
    }
    this.cancelarSuscripcionActiva();
    this.datosVentasCompletos = false;
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
          this.datosVentasCompletos = true;
          this.cdr.markForCheck();
        },
        complete: () => {
          this.datosVentasCompletos = true;
          this.cdr.markForCheck();
        },
      });
  }

  onFiltrosControlCuentasCambiados(filtros: any): void {
    if (
      this.sonFiltrosIguales(this.filtrosPorSeccion['Control de cuentas'], filtros) &&
      this.datosCuentasCompletos
    ) {
      return;
    }
    this.cancelarSuscripcionActiva();
    this.datosCuentasCompletos = false;
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
          this.datosCuentasCompletos = true;
          this.cdr.markForCheck();
        },
        complete: () => {
          this.datosCuentasCompletos = true;
          this.cdr.markForCheck();
        },
      });
  }


  onFiltrosInventarioCambiados(filtros: any): void {
    if (
      this.sonFiltrosIguales(this.filtrosPorSeccion['Inventario'], filtros) &&
      this.datosInventarioCompletos
    ) {
      return;
    }
    this.cancelarSuscripcionActiva();
    this.datosInventarioCompletos = false;
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
          this.datosInventarioCompletos = true;
          this.cdr.markForCheck();
        },
        complete: () => {
          this.datosInventarioCompletos = true;
          this.cdr.markForCheck();
        },
      });
  }

}

