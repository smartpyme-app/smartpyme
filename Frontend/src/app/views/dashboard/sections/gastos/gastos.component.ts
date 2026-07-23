import { Component, EventEmitter, Input, OnInit, OnChanges, SimpleChanges, Output, ViewChild, ChangeDetectorRef, ChangeDetectionStrategy, OnDestroy } from '@angular/core';
import { Subject } from 'rxjs';
import { debounceTime, distinctUntilChanged, switchMap, takeUntil } from 'rxjs/operators';
import { DashboardDataService } from '../../services/dashboard-data.service';
import { ColDef, GridOptions, GridApi } from 'ag-grid-community';
import { AgGridAngular } from 'ag-grid-angular';
import { ApiService } from '@services/api.service';
import {
  DashboardFiltrosCatalogoService,
  DashboardFiltroCatalogoItem,
} from '../../services/dashboard-filtros-catalogo.service';
import {
  DropdownMultiFiltroItem,
  DropdownMultiFiltroSelection,
} from '../../components/dropdown-multi-filtro/dropdown-multi-filtro.component';
import { formatEmpresaCurrency } from '@helpers/currency-format.helper';
import { MetricCard } from '../../models/chart-config.model';
import { DetalleGastosTotales } from '../../services/gastos-dashboard-data.service';

@Component({
  selector: 'app-gastos',
  templateUrl: './gastos.component.html',
  styleUrls: ['./gastos.component.css'],
  changeDetection: ChangeDetectionStrategy.OnPush
})
export class GastosComponent implements OnInit, OnChanges, OnDestroy {
  @Input() datos: any = {};
  @Input() datosCompletos = false;
  @Output() filtrosCambiados = new EventEmitter<any>();

  get metricasCards(): MetricCard[] {
    const m = this.datos?.metricasGastos || {};
    return [
      {
        title: 'Gastos totales',
        value: m.gastosConIVA || 0,
        type: 'currency'
      },
      {
        title: 'Gastos del mes',
        value: m.gastosMesActual || 0,
        type: 'currency'
      },
      {
        title: 'Gastos mes anterior',
        value: m.gastosMesAnterior || 0,
        type: 'currency'
      },
      {
        title: 'Variación en gastos',
        value: m.variacionGastos || 0,
        type: 'currency-int'
      },
      {
        title: (m.aumentoCostosPorcentaje || 0) >= 0 ? 'Aumento de costos' : 'Disminución de costos',
        value: m.aumentoCostosPorcentaje || 0,
        type: 'percentage-int'
      }
    ];
  }

  // Datos originales (sin filtrar)
  datosOriginales: any = {};

  // Datos filtrados (se muestran en la vista)
  datosFiltrados: any = {};

  // Fila de total pinned para AG Grid (se recalcula al filtrar)
  pinnedBottomRowDataGastos: any[] = [];

  // Propiedades cacheadas para evitar recálculos
  private _detalleGastosRowsCache: any[] = [];
  private _totalDetalleGastosCache: string = '';
  private _lastDatosHash: string = '';

  public inicializado: boolean = false;
  private filtrosListosParaEmitir = false;

  /** true mientras se espera la respuesta del servidor tras cambiar un filtro */
  filtrosLocked = false;
  private _filtrosLockTimeout: any = null;

  @ViewChild('detalleGastosGrid') detalleGastosGrid!: AgGridAngular;

  // AG Grid API
  private detalleGastosGridApi!: GridApi;

  // Quick filter text
  quickFilterTextGastos: string = '';

  // AG Grid options — detalle paginado lazy
  detalleGastosGridOptions: GridOptions = {};
  gastosListo = false;
  gastosLoading = false;
  gastosExporting = false;
  readonly gastosPageSize = 50;
  gastosOffset = 0;
  gastosTotal = 0;
  gastosTotales: DetalleGastosTotales = { gastosConIVA: 0 };
  private readonly destroy$ = new Subject<void>();
  private readonly gastosPage$ = new Subject<{
    offset: number;
    q: string;
    append: boolean;
  }>();
  private readonly quickFilterGastos$ = new Subject<string>();
  private gastosAppendPending = false;

  anio: string = new Date().getFullYear().toString();
  mes: string = '';
  mostrarFiltrosAdicionales: boolean = false;

  /** Otros filtros (multi-select como en Ventas). */
  filtroGastoSucTodasImplicitas = true;
  filtroGastoSucSeleccionadas: string[] = [];
  filtroGastoProvTodasImplicitas = true;
  filtroGastoProvSeleccionadas: string[] = [];
  filtroGastoTipoTodasImplicitas = true;
  filtroGastoTipoSeleccionadas: string[] = [];
  filtroGastoEstTodasImplicitas = true;
  filtroGastoEstSeleccionadas: string[] = [];

  /** Copia de otros filtros ya enviados al padre (año/mes usan siempre el control actual). */
  filtroGastoSucTodasImplicitasAplicado = true;
  filtroGastoSucSeleccionadasAplicado: string[] = [];
  filtroGastoProvTodasImplicitasAplicado = true;
  filtroGastoProvSeleccionadasAplicado: string[] = [];
  filtroGastoTipoTodasImplicitasAplicado = true;
  filtroGastoTipoSeleccionadasAplicado: string[] = [];
  filtroGastoEstTodasImplicitasAplicado = true;
  filtroGastoEstSeleccionadasAplicado: string[] = [];

  // Vista de métricas
  vistaMetricas: string = 'mes';

  // Filtros interactivos (se aplican localmente sin recargar)
  filtrosInteractivos: {
    proveedor?: string;
    categoria?: string;
    concepto?: string;
    formaPago?: string;
    mes?: string;
  } = {};

  /** Catálogos vía `DashboardFiltrosCatalogoService` (mismo origen que Ventas). */
  sucursales: DashboardFiltroCatalogoItem[] = [];
  proveedores: DashboardFiltroCatalogoItem[] = [];
  tiposGasto: DashboardFiltroCatalogoItem[] = [];
  estadosGasto: DashboardFiltroCatalogoItem[] = [];

  /** Si la API aún no expone `estados-gasto`, se usan estas opciones (compat. query `estado_gasto`). */
  private static readonly ESTADOS_GASTO_FALLBACK: DashboardFiltroCatalogoItem[] = [
    { id: 'pagada', nombre: 'Pagada' },
    { id: 'pendiente', nombre: 'Pendiente' },
    { id: 'vencida', nombre: 'Vencida' },
    { id: 'cancelada', nombre: 'Cancelada' },
  ];

  // Columnas para la tabla de detalle de gastos (AG Grid)
  detalleGastosColumnDefs: ColDef[] = [
    {
      field: 'fecha',
      headerName: 'Fecha',
      width: 120,
      minWidth: 120,
      sortable: true,
      filter: true
    },
    {
      field: 'proveedor',
      headerName: 'Proveedor',
      width: 200,
      minWidth: 200,
      sortable: true,
      filter: true
    },
    {
      field: 'concepto',
      headerName: 'Concepto',
      width: 260,
      minWidth: 260,
      sortable: true,
      filter: true
    },
    {
      field: 'documento',
      headerName: 'Doc.',
      width: 100,
      minWidth: 100,
      sortable: true,
      filter: true
    },
    {
      field: 'correlativo',
      headerName: 'Corr.',
      width: 110,
      minWidth: 110,
      sortable: true,
      filter: true
    },
    {
      field: 'gastosConIVA',
      headerName: 'Gastos con IVA',
      width: 160,
      minWidth: 160,
      sortable: true,
      filter: true,
      valueFormatter: (params: any) => {
        if (params.value !== undefined && params.value !== null) {
          return this.formatCurrency(params.value);
        }
        return '';
      },
      cellStyle: { textAlign: 'right' },
      type: 'numericColumn'
    }
  ];

  constructor(
    private cdr: ChangeDetectorRef,
    private apiService: ApiService,
    private filtrosCatalogo: DashboardFiltrosCatalogoService,
    private dashboardDataService: DashboardDataService
  ) { }

  /**
   * Método eficiente de clonación profunda
   */
  private clonarDatos(obj: any): any {
    if (obj === null || typeof obj !== 'object') return obj;
    if (obj instanceof Date) return new Date(obj.getTime());
    if (Array.isArray(obj)) return obj.map(item => this.clonarDatos(item));

    const clonado: any = {};
    for (const key in obj) {
      if (obj.hasOwnProperty(key)) {
        clonado[key] = this.clonarDatos(obj[key]);
      }
    }
    return clonado;
  }

  /**
   * Genera un hash simple de los datos para detectar cambios
   */
  private generarHashDatos(datos: any): string {
    if (!datos) return '';
    try {
      const str = JSON.stringify(datos);
      let hash = 0;
      for (let i = 0; i < str.length; i++) {
        const char = str.charCodeAt(i);
        hash = ((hash << 5) - hash) + char;
        hash = hash & hash;
      }
      return hash.toString();
    } catch {
      return Math.random().toString();
    }
  }

  /**
   * Formateador de moneda con caché
   */

  ngOnInit(): void {
    const savedState = this.dashboardDataService.obtenerFiltrosUI('Gastos');
    const tieneEstadoGuardado = !!savedState;
    if (savedState) {
      Object.assign(this, savedState);
      // Object.assign copia referencias de arrays; clonar para que borrador y aplicado
      // no compartan el mismo array (rompería la comparación mismoEstadoFiltroMultiGastos).
      this.filtroGastoSucSeleccionadas = [...(this.filtroGastoSucSeleccionadas ?? [])];
      this.filtroGastoProvSeleccionadas = [...(this.filtroGastoProvSeleccionadas ?? [])];
      this.filtroGastoTipoSeleccionadas = [...(this.filtroGastoTipoSeleccionadas ?? [])];
      this.filtroGastoEstSeleccionadas = [...(this.filtroGastoEstSeleccionadas ?? [])];
      this.filtroGastoSucSeleccionadasAplicado = [...(this.filtroGastoSucSeleccionadasAplicado ?? [])];
      this.filtroGastoProvSeleccionadasAplicado = [...(this.filtroGastoProvSeleccionadasAplicado ?? [])];
      this.filtroGastoTipoSeleccionadasAplicado = [...(this.filtroGastoTipoSeleccionadasAplicado ?? [])];
      this.filtroGastoEstSeleccionadasAplicado = [...(this.filtroGastoEstSeleccionadasAplicado ?? [])];
    }

    // Configurar AG Grid
    this.configurarAGGrid();
    this.wireDetalleGastosStreams();
    this.cargarOpcionesFiltros();

    // Intentar inicializar si ya hay datos
    if (this.datos && Object.keys(this.datos).length > 0) {
      this.inicializarDatos();
    }

    // Siempre emitir al padre tras restaurar UI; si no, al reentrar al dashboard
    // el padre queda sin datos y la vista se queda en loaders.
    setTimeout(() => {
      this.filtrosListosParaEmitir = true;
      this.aplicarFiltros();
      this.cdr.markForCheck();
    }, 100);
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    this.dashboardDataService.guardarFiltrosUI('Gastos', {
      anio: this.anio,
      mes: this.mes,
      filtroGastoSucTodasImplicitas: this.filtroGastoSucTodasImplicitas,
      filtroGastoSucSeleccionadas: this.filtroGastoSucSeleccionadas,
      filtroGastoProvTodasImplicitas: this.filtroGastoProvTodasImplicitas,
      filtroGastoProvSeleccionadas: this.filtroGastoProvSeleccionadas,
      filtroGastoTipoTodasImplicitas: this.filtroGastoTipoTodasImplicitas,
      filtroGastoTipoSeleccionadas: this.filtroGastoTipoSeleccionadas,
      filtroGastoEstTodasImplicitas: this.filtroGastoEstTodasImplicitas,
      filtroGastoEstSeleccionadas: this.filtroGastoEstSeleccionadas,
      filtroGastoSucTodasImplicitasAplicado: this.filtroGastoSucTodasImplicitasAplicado,
      filtroGastoSucSeleccionadasAplicado: this.filtroGastoSucSeleccionadasAplicado,
      filtroGastoProvTodasImplicitasAplicado: this.filtroGastoProvTodasImplicitasAplicado,
      filtroGastoProvSeleccionadasAplicado: this.filtroGastoProvSeleccionadasAplicado,
      filtroGastoTipoTodasImplicitasAplicado: this.filtroGastoTipoTodasImplicitasAplicado,
      filtroGastoTipoSeleccionadasAplicado: this.filtroGastoTipoSeleccionadasAplicado,
      filtroGastoEstTodasImplicitasAplicado: this.filtroGastoEstTodasImplicitasAplicado,
      filtroGastoEstSeleccionadasAplicado: this.filtroGastoEstSeleccionadasAplicado,
      filtrosInteractivos: this.filtrosInteractivos,
      vistaMetricas: this.vistaMetricas
    });
  }

  /**
   * Sucursales (Laravel); proveedores, tipos y estados de gasto (Go dimensiones).
   */
  cargarOpcionesFiltros(): void {
    const tieneEstadoGuardado = !!this.dashboardDataService.obtenerFiltrosUI('Gastos');
    this.filtrosCatalogo.sucursalesParaFiltro().subscribe({
      next: (items) => {
        this.sucursales = items;
        if (!tieneEstadoGuardado) {
          this.aplicarRestriccionSucursalGastosPorRol();
          this.copiarFiltrosAdicionalesGastosBorradorAAplicado();
          setTimeout(() => {
            if (this.filtrosListosParaEmitir) {
              this.aplicarFiltros();
            }
          }, 150);
        }
        this.cdr.markForCheck();
      },
    });

    this.filtrosCatalogo.proveedoresParaFiltro().subscribe({
      next: (items) => {
        this.proveedores = items;
        this.cdr.markForCheck();
      },
    });

    this.filtrosCatalogo.tiposGastoParaFiltro().subscribe({
      next: (items) => {
        this.tiposGasto = items;
        this.cdr.markForCheck();
      },
    });

    this.filtrosCatalogo.estadosGastoParaFiltro().subscribe({
      next: (items) => {
        this.estadosGasto =
          items.length > 0 ? items : GastosComponent.ESTADOS_GASTO_FALLBACK;
        this.cdr.markForCheck();
      },
    });
  }

  /** Usuario con una sola sucursal no puede cambiar el filtro (como Ventas/Resultados). */
  get filtroSucursalGastosDisabled(): boolean {
    const user = this.apiService.auth_user();
    return user?.tipo !== 'Administrador' && this.sucursales.length <= 1;
  }

  private aplicarRestriccionSucursalGastosPorRol(): void {
    const user = this.apiService.auth_user();
    const items = this.sucursales;
    if (items.length === 0) {
      this.filtroGastoSucTodasImplicitas = true;
      this.filtroGastoSucSeleccionadas = [];
      return;
    }
    if (user?.tipo !== 'Administrador' && user?.id_sucursal != null) {
      this.filtroGastoSucTodasImplicitas = false;
      this.filtroGastoSucSeleccionadas = [String(user.id_sucursal)];
    } else {
      this.filtroGastoSucTodasImplicitas = true;
      this.filtroGastoSucSeleccionadas = [];
    }
  }

  private idsDeListaFiltro(items: DashboardFiltroCatalogoItem[]): string[] {
    return (items || []).map((x) => String(x.id));
  }

  /**
   * Convierte el estado del filtro multi-selección en un string para la API.
   * Retorna:
   *   ''    → no enviar el parámetro (todas implícitas o equivalente a todas)
   *   null  → "ninguno seleccionado" (estado activo pero vacío — no se deben traer datos)
   *   'a,b' → IDs seleccionados
   * ponytail: null indica "filtro activo pero vacío"; el caller decide cómo manejarlo.
   */
  private filtroGastoMultiAString(
    todasImplicitas: boolean,
    seleccionados: string[],
    todosIds: string[],
  ): string | null {
    // Todas implícitas → sin filtro
    if (todasImplicitas) {
      return '';
    }
    // Ninguno seleccionado → filtro activo pero vacío (no traer nada)
    if (seleccionados.length === 0) {
      return null;
    }
    // Si el usuario eligió exactamente todos los IDs conocidos → equivale a "todas"
    if (
      todosIds.length > 0 &&
      seleccionados.length === todosIds.length &&
      todosIds.every((id) => seleccionados.includes(id))
    ) {
      return '';
    }
    return seleccionados.join(',');
  }

  private filtroGastoSucursalParaApiDesde(
    todasImplicitas: boolean,
    seleccionados: string[],
  ): string | string[] {
    const todosIds = this.idsDeListaFiltro(this.sucursales);
    const sel = seleccionados;
    if (todasImplicitas || sel.length === 0) {
      return '';
    }
    if (
      todosIds.length > 0 &&
      sel.length === todosIds.length &&
      todosIds.every((id) => sel.includes(id))
    ) {
      return '';
    }
    return sel.length === 1 ? sel[0] : [...sel];
  }

  private filtroGastoSucursalParaApiAplicado(): string | string[] {
    return this.filtroGastoSucursalParaApiDesde(
      this.filtroGastoSucTodasImplicitasAplicado,
      this.filtroGastoSucSeleccionadasAplicado,
    );
  }

  private copiarFiltrosAdicionalesGastosAplicadoABorrador(): void {
    this.filtroGastoSucTodasImplicitas = this.filtroGastoSucTodasImplicitasAplicado;
    this.filtroGastoSucSeleccionadas = [...this.filtroGastoSucSeleccionadasAplicado];
    this.filtroGastoProvTodasImplicitas = this.filtroGastoProvTodasImplicitasAplicado;
    this.filtroGastoProvSeleccionadas = [...this.filtroGastoProvSeleccionadasAplicado];
    this.filtroGastoTipoTodasImplicitas = this.filtroGastoTipoTodasImplicitasAplicado;
    this.filtroGastoTipoSeleccionadas = [...this.filtroGastoTipoSeleccionadasAplicado];
    this.filtroGastoEstTodasImplicitas = this.filtroGastoEstTodasImplicitasAplicado;
    this.filtroGastoEstSeleccionadas = [...this.filtroGastoEstSeleccionadasAplicado];
  }

  private copiarFiltrosAdicionalesGastosBorradorAAplicado(): void {
    this.filtroGastoSucTodasImplicitasAplicado = this.filtroGastoSucTodasImplicitas;
    this.filtroGastoSucSeleccionadasAplicado = [...this.filtroGastoSucSeleccionadas];
    this.filtroGastoProvTodasImplicitasAplicado = this.filtroGastoProvTodasImplicitas;
    this.filtroGastoProvSeleccionadasAplicado = [...this.filtroGastoProvSeleccionadas];
    this.filtroGastoTipoTodasImplicitasAplicado = this.filtroGastoTipoTodasImplicitas;
    this.filtroGastoTipoSeleccionadasAplicado = [...this.filtroGastoTipoSeleccionadas];
    this.filtroGastoEstTodasImplicitasAplicado = this.filtroGastoEstTodasImplicitas;
    this.filtroGastoEstSeleccionadasAplicado = [...this.filtroGastoEstSeleccionadas];
  }

  private arraysMismoContenidoGastos(a: string[], b: string[]): boolean {
    if (a.length !== b.length) return false;
    const sa = [...a].map(String).sort();
    const sb = [...b].map(String).sort();
    return sa.every((v, i) => v === sb[i]);
  }

  private mismoEstadoFiltroMultiGastos(
    todasA: boolean,
    selA: string[],
    todasB: boolean,
    selB: string[],
  ): boolean {
    return todasA === todasB && this.arraysMismoContenidoGastos(selA, selB);
  }

  /** Hay criterio explícito en sucursal / proveedor / tipo / estado (borrador o ya aplicado). */
  private filtroGastoAdicionalEstaActivo(
    todasImplicitas: boolean,
    seleccionados: string[],
  ): boolean {
    // Solo consideramos activo si no son todas implícitas Y hay al menos uno seleccionado.
    // todasImplicitas=false + seleccionados=[] es un estado inválido/vacío, no "activo útil".
    return !todasImplicitas && seleccionados.length > 0;
  }

  /** Botón «Limpiar filtros»: solo si hay mes, año distinto, otros filtros o filtros del gráfico. */
  get puedeLimpiarFiltrosGastos(): boolean {
    const anioActual = new Date().getFullYear().toString();
    const hayEnBorrador =
      this.filtroGastoAdicionalEstaActivo(
        this.filtroGastoSucTodasImplicitas,
        this.filtroGastoSucSeleccionadas,
      ) ||
      this.filtroGastoAdicionalEstaActivo(
        this.filtroGastoProvTodasImplicitas,
        this.filtroGastoProvSeleccionadas,
      ) ||
      this.filtroGastoAdicionalEstaActivo(
        this.filtroGastoTipoTodasImplicitas,
        this.filtroGastoTipoSeleccionadas,
      ) ||
      this.filtroGastoAdicionalEstaActivo(
        this.filtroGastoEstTodasImplicitas,
        this.filtroGastoEstSeleccionadas,
      );
    const hayAplicados =
      this.filtroGastoAdicionalEstaActivo(
        this.filtroGastoSucTodasImplicitasAplicado,
        this.filtroGastoSucSeleccionadasAplicado,
      ) ||
      this.filtroGastoAdicionalEstaActivo(
        this.filtroGastoProvTodasImplicitasAplicado,
        this.filtroGastoProvSeleccionadasAplicado,
      ) ||
      this.filtroGastoAdicionalEstaActivo(
        this.filtroGastoTipoTodasImplicitasAplicado,
        this.filtroGastoTipoSeleccionadasAplicado,
      ) ||
      this.filtroGastoAdicionalEstaActivo(
        this.filtroGastoEstTodasImplicitasAplicado,
        this.filtroGastoEstSeleccionadasAplicado,
      );
    return (
      !!this.mes ||
      this.anio !== anioActual ||
      hayEnBorrador ||
      hayAplicados ||
      this.tieneFiltrosInteractivos()
    );
  }

  /** Muestra «Aplicar» solo si el borrador difiere de lo ya emitido al padre. */
  get mostrarBotonAplicarOtrosFiltrosGastos(): boolean {
    if (!this.mostrarFiltrosAdicionales) return false;
    return !(
      this.mismoEstadoFiltroMultiGastos(
        this.filtroGastoSucTodasImplicitas,
        this.filtroGastoSucSeleccionadas,
        this.filtroGastoSucTodasImplicitasAplicado,
        this.filtroGastoSucSeleccionadasAplicado,
      ) &&
      this.mismoEstadoFiltroMultiGastos(
        this.filtroGastoProvTodasImplicitas,
        this.filtroGastoProvSeleccionadas,
        this.filtroGastoProvTodasImplicitasAplicado,
        this.filtroGastoProvSeleccionadasAplicado,
      ) &&
      this.mismoEstadoFiltroMultiGastos(
        this.filtroGastoTipoTodasImplicitas,
        this.filtroGastoTipoSeleccionadas,
        this.filtroGastoTipoTodasImplicitasAplicado,
        this.filtroGastoTipoSeleccionadasAplicado,
      ) &&
      this.mismoEstadoFiltroMultiGastos(
        this.filtroGastoEstTodasImplicitas,
        this.filtroGastoEstSeleccionadas,
        this.filtroGastoEstTodasImplicitasAplicado,
        this.filtroGastoEstSeleccionadasAplicado,
      )
    );
  }

  get filtroGastoSucursalesItems(): DropdownMultiFiltroItem[] {
    return (this.sucursales || []).map((s) => ({
      id: String(s.id),
      nombre: s.nombre ?? '',
    }));
  }

  get filtroGastoProveedoresItems(): DropdownMultiFiltroItem[] {
    return (this.proveedores || []).map((x) => ({
      id: String(x.id),
      nombre: x.nombre ?? '',
    }));
  }

  get filtroGastoTiposItems(): DropdownMultiFiltroItem[] {
    return (this.tiposGasto || []).map((x) => ({
      id: String(x.id),
      nombre: x.nombre ?? '',
    }));
  }

  get filtroGastoEstadosItems(): DropdownMultiFiltroItem[] {
    return (this.estadosGasto || []).map((e) => ({
      id: String(e.id),
      nombre: e.nombre ?? '',
    }));
  }

  onFiltroGastoSucursalChange(ev: DropdownMultiFiltroSelection): void {
    this.filtroGastoSucTodasImplicitas = ev.todasImplicitas;
    this.filtroGastoSucSeleccionadas = [...ev.seleccionados];
    this.cdr.markForCheck();
  }

  onFiltroGastoProveedorChange(ev: DropdownMultiFiltroSelection): void {
    this.filtroGastoProvTodasImplicitas = ev.todasImplicitas;
    this.filtroGastoProvSeleccionadas = [...ev.seleccionados];
    this.cdr.markForCheck();
  }

  onFiltroGastoTipoChange(ev: DropdownMultiFiltroSelection): void {
    this.filtroGastoTipoTodasImplicitas = ev.todasImplicitas;
    this.filtroGastoTipoSeleccionadas = [...ev.seleccionados];
    this.cdr.markForCheck();
  }

  onFiltroGastoEstadoChange(ev: DropdownMultiFiltroSelection): void {
    this.filtroGastoEstTodasImplicitas = ev.todasImplicitas;
    this.filtroGastoEstSeleccionadas = [...ev.seleccionados];
    this.cdr.markForCheck();
  }

  configurarAGGrid(): void {
    this.detalleGastosGridOptions = {
      pagination: true,
      paginationPageSize: 25,
      defaultColDef: {
        resizable: true,
        sortable: true,
        filter: true,
        suppressSizeToFit: true,
      },
      getRowClass: (params: any) => {
        if (params.node.rowPinned === 'bottom') {
          return 'ag-row-total';
        }
        return '';
      },
      enableCellTextSelection: true,
      ensureDomOrder: true,
      suppressExcelExport: false,
      suppressCsvExport: false,
      suppressHorizontalScroll: false,
      onGridReady: (params: any) => {
        this.detalleGastosGridApi = params.api;
        this.recalcularTotalesGastos();
        this.cdr.markForCheck();
      },
      onPaginationChanged: () => {
        this.maybeLoadMoreGastosFromGrid();
      },
    };
  }

  private wireDetalleGastosStreams(): void {
    this.gastosPage$
      .pipe(
        switchMap(({ offset, q, append }) => {
          this.gastosAppendPending = append;
          this.gastosLoading = true;
          this.cdr.markForCheck();
          return this.dashboardDataService.obtenerDetalleGastosPagina(
            this.getFiltrosDetalleGastos(),
            { limite: this.gastosPageSize, offset, q: q || undefined },
          );
        }),
        takeUntil(this.destroy$),
      )
      .subscribe({
        next: (page) => {
          this.gastosOffset = page.offset;
          this.gastosTotal = page.total;
          this.gastosTotales = page.totales;
          const append =
            this.gastosAppendPending && (page.items?.length ?? 0) > 0;
          this.applyGastosPageRows(page.items, page.totales, append);
          this.gastosAppendPending = false;
          this.gastosListo = true;
          this.gastosLoading = false;
          this.cdr.markForCheck();
        },
        error: () => {
          this.gastosAppendPending = false;
          this.gastosLoading = false;
          this.gastosListo = true;
          this.cdr.markForCheck();
        },
      });

    this.quickFilterGastos$
      .pipe(
        debounceTime(300),
        distinctUntilChanged(),
        takeUntil(this.destroy$),
      )
      .subscribe(() => {
        this.cargarDetalleGastosPagina(0);
      });
  }

  /** Filtros panel + overrides interactivos (categoría / mes del chart). */
  private getFiltrosDetalleGastos(): any {
    const filtros: any = {
      anio: this.anio || new Date().getFullYear().toString(),
    };
    if (this.mes) filtros.mes = this.mes;

    const suc = this.filtroGastoSucursalParaApiAplicado();
    if (suc !== '' && suc != null) filtros.sucursal = suc;

    const prov = this.filtroGastoMultiAString(
      this.filtroGastoProvTodasImplicitasAplicado,
      this.filtroGastoProvSeleccionadasAplicado,
      this.idsDeListaFiltro(this.proveedores),
    );
    if (prov) filtros.proveedor = prov;

    const tipo = this.filtroGastoMultiAString(
      this.filtroGastoTipoTodasImplicitasAplicado,
      this.filtroGastoTipoSeleccionadasAplicado,
      this.idsDeListaFiltro(this.tiposGasto),
    );
    if (tipo) filtros.tipoGasto = tipo;

    const est = this.filtroGastoMultiAString(
      this.filtroGastoEstTodasImplicitasAplicado,
      this.filtroGastoEstSeleccionadasAplicado,
      this.idsDeListaFiltro(this.estadosGasto),
    );
    if (est) filtros.estadoGasto = est;

    if (this.filtrosInteractivos.categoria) {
      filtros.categoria = this.filtrosInteractivos.categoria;
    }
    if (this.filtrosInteractivos.mes) {
      const meses = [
        'Enero',
        'Febrero',
        'Marzo',
        'Abril',
        'Mayo',
        'Junio',
        'Julio',
        'Agosto',
        'Septiembre',
        'Octubre',
        'Noviembre',
        'Diciembre',
      ];
      const idx = meses.findIndex(
        (m) =>
          m.toLowerCase() ===
          String(this.filtrosInteractivos.mes).toLowerCase(),
      );
      if (idx >= 0) filtros.mes = String(idx + 1);
    }
    return filtros;
  }

  private getGastosSearchQ(): string {
    const typed = this.quickFilterTextGastos.trim();
    if (typed) return typed;
    return (
      this.filtrosInteractivos.proveedor ||
      this.filtrosInteractivos.concepto ||
      this.filtrosInteractivos.formaPago ||
      ''
    ).trim();
  }

  cargarDetalleGastosPagina(offset: number, append = false): void {
    this.gastosPage$.next({
      offset: Math.max(0, offset),
      q: this.getGastosSearchQ(),
      append,
    });
  }

  private maybeLoadMoreGastosFromGrid(): void {
    if (!this.detalleGastosGridApi || this.gastosLoading || !this.gastosListo) {
      return;
    }
    const loaded = this._detalleGastosRowsCache.length;
    if (loaded >= this.gastosTotal) return;
    const pageSize = this.detalleGastosGridApi.paginationGetPageSize() || 25;
    const currentPage = this.detalleGastosGridApi.paginationGetCurrentPage();
    // Solo al llegar a la última página del buffer (evita prefetch en page 0 con chunk=50).
    const lastLoadedPage = Math.max(0, Math.ceil(loaded / pageSize) - 1);
    if (currentPage >= lastLoadedPage) {
      this.cargarDetalleGastosPagina(loaded, true);
    }
  }

  private mapGastosRows(items: any[]): any[] {
    return (items ?? []).map((gasto: any) => ({
      fecha: gasto.fecha || '-',
      proveedor: gasto.proveedor || '-',
      concepto: gasto.concepto || '-',
      documento: gasto.documento || '-',
      correlativo: gasto.correlativo || '-',
      gastosConIVA: gasto.gastosConIVA || 0,
      isTotal: false,
    }));
  }

  private applyGastosPageRows(
    items: any[],
    totales: DetalleGastosTotales,
    append = false,
  ): void {
    const mapped = this.mapGastosRows(items);
    if (append && this._detalleGastosRowsCache.length > 0) {
      this._detalleGastosRowsCache = [
        ...this._detalleGastosRowsCache,
        ...mapped,
      ];
    } else {
      this._detalleGastosRowsCache = mapped;
      if (this.detalleGastosGridApi) {
        this.detalleGastosGridApi.paginationGoToFirstPage();
      }
    }
    this._totalDetalleGastosCache = this.formatCurrency(
      totales.gastosConIVA || 0,
    );
    this.aplicarPinnedTotalesGastos(totales);
  }

  private aplicarPinnedTotalesGastos(totales: DetalleGastosTotales): void {
    if (totales.gastosConIVA !== 0) {
      this.pinnedBottomRowDataGastos = [
        {
          fecha: 'Total',
          proveedor: '',
          concepto: '',
          documento: '',
          correlativo: '',
          gastosConIVA: totales.gastosConIVA,
        },
      ];
    } else {
      this.pinnedBottomRowDataGastos = [];
    }
  }

  private csvEscape(value: string): string {
    if (/[",\n\r]/.test(value)) {
      return `"${value.replace(/"/g, '""')}"`;
    }
    return value;
  }

  onGridReadyGastos(params: any): void {
    this.detalleGastosGridApi = params.api;
    this.recalcularTotalesGastos();
  }

  /** Totales pinned desde API (`totales`); no sumar solo la página cargada. */
  recalcularTotalesGastos(): void {
    this.aplicarPinnedTotalesGastos(this.gastosTotales);
  }

  onFilterChangedGastos(): void {
    this.recalcularTotalesGastos();
    this.cdr.markForCheck();
  }

  onQuickFilterChangeGastos(): void {
    this.quickFilterGastos$.next(this.quickFilterTextGastos.trim());
  }

  exportarCSVGastos(): void {
    this.exportarGastosCompletoCsv();
  }

  exportarExcelGastos(): void {
    this.exportarGastosCompletoCsv();
  }

  private exportarGastosCompletoCsv(): void {
    if (this.gastosExporting) return;
    this.gastosExporting = true;
    this.cdr.markForCheck();
    this.dashboardDataService
      .obtenerDetalleGastosCompleto(this.getFiltrosDetalleGastos(), {
        q: this.getGastosSearchQ() || undefined,
      })
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: (page) => {
          const fecha = new Date().toISOString().split('T')[0];
          const cols = this.detalleGastosColumnDefs.filter((c) => c.field);
          const header = cols
            .map((c) => this.csvEscape(String(c.headerName || c.field)))
            .join(',');
          const lines = (page.items ?? []).map((item: any) =>
            cols
              .map((c) => {
                const field = c.field || '';
                const raw = item[field];
                return this.csvEscape(
                  raw === null || raw === undefined ? '' : String(raw),
                );
              })
              .join(','),
          );
          const csv = [header, ...lines].join('\n');
          const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
          const url = URL.createObjectURL(blob);
          const a = document.createElement('a');
          a.href = url;
          a.download = `detalle-gastos-${fecha}.csv`;
          a.click();
          URL.revokeObjectURL(url);
          this.gastosExporting = false;
          this.cdr.markForCheck();
        },
        error: () => {
          this.gastosExporting = false;
          this.cdr.markForCheck();
        },
      });
  }

  limpiarFiltrosGastos(): void {
    if (this.detalleGastosGridApi) {
      this.detalleGastosGridApi.setFilterModel(null);
    }
    this.quickFilterTextGastos = '';
    this.cargarDetalleGastosPagina(0);
  }

  ngOnChanges(changes: SimpleChanges): void {
    // console.log('GastosComponent - ngOnChanges llamado', {
    //   hasChanges: !!changes['datos'],
    //   firstChange: changes['datos']?.firstChange,
    //   currentValue: changes['datos']?.currentValue ? Object.keys(changes['datos'].currentValue) : [],
    //   tieneDetalleGastos: !!(changes['datos']?.currentValue && changes['datos'].currentValue.detalleGastos)
    // });

    if (changes['datos']) {
      const datosActuales = changes['datos'].currentValue;
      if (datosActuales && Object.keys(datosActuales).length > 0) {
        // Datos llegaron (ya sea en el primer cambio o después)
        this.inicializarDatos();
      }
    }

    // datosCompletos=true: todas las APIs terminaron → desbloquear filtros
    if (changes['datosCompletos'] && this.datosCompletos) {
      this._desbloquearFiltros();
    }
  }

  inicializarDatos(): void {
    // console.log('GastosComponent - inicializarDatos llamado', {
    //   tieneDatos: !!this.datos,
    //   keysDatos: this.datos ? Object.keys(this.datos) : [],
    //   tieneDetalleGastos: !!(this.datos && this.datos.detalleGastos),
    //   detalleGastos: this.datos?.detalleGastos,
    //   datosCompletos: this.datos
    // });

    if (this.datos && Object.keys(this.datos).length > 0) {
      // Nuevo lote desde el padre (otro año, etc.): no reutilizar hash ni filas cacheadas.
      this._lastDatosHash = '';
      // Guardar datos originales
      this.datosOriginales = this.clonarDatos(this.datos);
      // Inicializar datos filtrados
      this.datosFiltrados = this.clonarDatos(this.datos);
      this.datos = this.datosFiltrados;
      this.inicializado = true;
      this.recalcularRowsCache();

      // Detalle grid: lazy (cargarDetalleGastosPagina). Gráficos/métricas desde merge.
      this.recalcularMetricas();
      this.recalcularGastosPorMes();
      this.recalcularGastosVsPresupuesto();
      this.recalcularGastosVsAnioAnterior();
      this.recalcularGastosPorCategoria();
      this.recalcularGastosPorConcepto();
      this.recalcularGastosPorProveedor();
      this.recalcularGastosPorFormaPago();

      this.cdr.markForCheck();
    } else {
      // console.warn('GastosComponent - No hay datos para inicializar');
    }
  }

  cambiarVistaMetricas(vista: string): void {
    this.vistaMetricas = vista;
    // Recalcular los gráficos según la vista seleccionada
    if (vista === 'presupuesto') {
      this.recalcularGastosVsPresupuesto();
    } else if (vista === 'anio') {
      this.recalcularGastosVsAnioAnterior();
    } else {
      this.recalcularGastosPorMes();
    }
    this.cdr.markForCheck();
  }

  getTituloGraficoGastos(): string {
    switch (this.vistaMetricas) {
      case 'presupuesto':
        return 'Gastos totales vs presupuesto mensual';
      case 'anio':
        return 'Gastos totales año actual vs año anterior';
      default:
        return 'Gastos por mes';
    }
  }

  formatCurrency(value: number): string {
    if (value === null || value === undefined) {
      value = 0;
    }
    return formatEmpresaCurrency(value, this.apiService.auth_user()?.empresa);
  }

  tieneFiltrosInteractivos(): boolean {
    return Object.keys(this.filtrosInteractivos).length > 0;
  }

  getFiltrosInteractivosTexto(): string {
    const filtros: string[] = [];
    if (this.filtrosInteractivos.proveedor) filtros.push(`Proveedor: ${this.filtrosInteractivos.proveedor}`);
    if (this.filtrosInteractivos.categoria) filtros.push(`Categoría: ${this.filtrosInteractivos.categoria}`);
    if (this.filtrosInteractivos.mes) filtros.push(`Mes: ${this.filtrosInteractivos.mes}`);
    return filtros.join(', ');
  }

  aplicarFiltros(): void {
    if (!this.filtrosListosParaEmitir) {
      return;
    }
    
    // Bloquear filtros mientras se espera la respuesta
    this._bloquearFiltros();

    if (!this.anio) {
      this.anio = new Date().getFullYear().toString();
    }
    const filtros: any = {
      anio: this.anio,
    };
    const suc = this.filtroGastoSucursalParaApiAplicado();
    if (suc !== '' && suc != null) {
      filtros.sucursal = suc;
    }
    const prov = this.filtroGastoMultiAString(
      this.filtroGastoProvTodasImplicitasAplicado,
      this.filtroGastoProvSeleccionadasAplicado,
      this.idsDeListaFiltro(this.proveedores),
    );
    // null = ninguno seleccionado → no tiene sentido pedir datos, salir temprano
    if (prov === null) {
      this._desbloquearFiltros();
      return;
    }
    if (prov) {
      filtros.proveedor = prov;
    }
    const tipo = this.filtroGastoMultiAString(
      this.filtroGastoTipoTodasImplicitasAplicado,
      this.filtroGastoTipoSeleccionadasAplicado,
      this.idsDeListaFiltro(this.tiposGasto),
    );
    if (tipo === null) {
      this._desbloquearFiltros();
      return;
    }
    if (tipo) {
      filtros.tipoGasto = tipo;
    }
    const est = this.filtroGastoMultiAString(
      this.filtroGastoEstTodasImplicitasAplicado,
      this.filtroGastoEstSeleccionadasAplicado,
      this.idsDeListaFiltro(this.estadosGasto),
    );
    if (est === null) {
      this._desbloquearFiltros();
      return;
    }
    if (est) {
      filtros.estadoGasto = est;
    }
    if (this.mes) {
      filtros.mes = this.mes;
    }
    this.filtrosCambiados.emit(filtros);
    this._desbloquearSiPadreOmiteRecarga();
    this.cargarDetalleGastosPagina(0);
  }

  private _bloquearFiltros(): void {
    this.filtrosLocked = true;
    this.cdr.markForCheck();
    if (this._filtrosLockTimeout) clearTimeout(this._filtrosLockTimeout);
    this._filtrosLockTimeout = setTimeout(() => this._desbloquearFiltros(), 8000);
  }

  private _desbloquearFiltros(): void {
    if (this._filtrosLockTimeout) {
      clearTimeout(this._filtrosLockTimeout);
      this._filtrosLockTimeout = null;
    }
    this.filtrosLocked = false;
    this.cdr.markForCheck();
  }

  /** Si el padre omite la recarga (mismos filtros), datosCompletos no cambia y el overlay se queda. */
  private _desbloquearSiPadreOmiteRecarga(): void {
    setTimeout(() => {
      if (this.datosCompletos) {
        this._desbloquearFiltros();
      }
    }, 0);
  }


  limpiarFiltros(): void {
    this.anio = new Date().getFullYear().toString();
    this.mes = '';
    this.filtroGastoProvTodasImplicitas = true;
    this.filtroGastoProvSeleccionadas = [];
    this.filtroGastoTipoTodasImplicitas = true;
    this.filtroGastoTipoSeleccionadas = [];
    this.filtroGastoEstTodasImplicitas = true;
    this.filtroGastoEstSeleccionadas = [];
    this.aplicarRestriccionSucursalGastosPorRol();
    this.copiarFiltrosAdicionalesGastosBorradorAAplicado();
    this.limpiarFiltrosInteractivos();
    this.aplicarFiltros();
  }

  /** Confirma el borrador de «otros filtros» y emite al padre. */
  confirmarOtrosFiltrosGastos(): void {
    this.copiarFiltrosAdicionalesGastosBorradorAAplicado();
    this.aplicarFiltros();
    this.cdr.markForCheck();
  }

  limpiarFiltrosInteractivos(): void {
    this.filtrosInteractivos = {};
    this.quickFilterTextGastos = '';
    // Restaurar datos originales
    if (Object.keys(this.datosOriginales).length > 0) {
      this.datosFiltrados = this.clonarDatos(this.datosOriginales);

      // Recalcular todos los gráficos con datos originales
      this.recalcularTodosLosGraficos();
      this.recalcularMetricas();

      // Actualizar referencia - crear nuevo objeto para forzar detección de cambios
      this.datos = this.clonarDatos(this.datosFiltrados);
      this.recalcularRowsCache();
      if (this.inicializado) {
        this.cargarDetalleGastosPagina(0);
      }
      this.cdr.markForCheck();
    }
  }

  toggleFiltrosAdicionales(): void {
    this.mostrarFiltrosAdicionales = !this.mostrarFiltrosAdicionales;
    this.copiarFiltrosAdicionalesGastosAplicadoABorrador();
    this.cdr.markForCheck();
  }

  limpiarFiltroIndividual(key: string): void {
    delete (this.filtrosInteractivos as any)[key];
    if (key === 'proveedor' || key === 'concepto' || key === 'formaPago') {
      this.quickFilterTextGastos = '';
    }
    this.aplicarFiltrosInteractivos();
    this.cargarDetalleGastosPagina(0);
  }

  onMesClick(event: any): void {
    if (event && event.name) {
      if (this.filtrosInteractivos.mes === event.name) {
        delete this.filtrosInteractivos.mes;
      } else {
        this.filtrosInteractivos.mes = event.name;
      }
      this.aplicarFiltrosInteractivos();
      this.cargarDetalleGastosPagina(0);
    }
  }

  onCategoriaClick(event: any): void {
    if (event && event.name) {
      if (this.filtrosInteractivos.categoria === event.name) {
        delete this.filtrosInteractivos.categoria;
      } else {
        this.filtrosInteractivos.categoria = event.name;
      }
      this.aplicarFiltrosInteractivos();
      this.cargarDetalleGastosPagina(0);
    }
  }

  onConceptoClick(event: any): void {
    if (event && event.name) {
      if (this.filtrosInteractivos.concepto === event.name) {
        delete this.filtrosInteractivos.concepto;
        this.quickFilterTextGastos = '';
      } else {
        this.filtrosInteractivos.concepto = event.name;
        this.quickFilterTextGastos = event.name;
      }
      this.aplicarFiltrosInteractivos();
      this.cargarDetalleGastosPagina(0);
    }
  }

  onFormaPagoClick(event: any): void {
    if (event && event.name) {
      if (this.filtrosInteractivos.formaPago === event.name) {
        delete this.filtrosInteractivos.formaPago;
        this.quickFilterTextGastos = '';
      } else {
        this.filtrosInteractivos.formaPago = event.name;
        this.quickFilterTextGastos = event.name;
      }
      this.aplicarFiltrosInteractivos();
      this.cargarDetalleGastosPagina(0);
    }
  }

  onProveedorClick(event: any): void {
    if (event && event.name) {
      if (this.filtrosInteractivos.proveedor === event.name) {
        delete this.filtrosInteractivos.proveedor;
        this.quickFilterTextGastos = '';
      } else {
        this.filtrosInteractivos.proveedor = event.name;
        this.quickFilterTextGastos = event.name;
      }
      this.aplicarFiltrosInteractivos();
      this.cargarDetalleGastosPagina(0);
    }
  }

  filtrarGastosDetallados(): void {
    if (!this.datosFiltrados.gastosDetallados) return;
    
    let gastosFiltrados = [...this.datosFiltrados.gastosDetallados];
    
    if (this.filtrosInteractivos.proveedor) {
      gastosFiltrados = gastosFiltrados.filter(g => g.proveedor === this.filtrosInteractivos.proveedor);
    }
    if (this.filtrosInteractivos.categoria) {
      gastosFiltrados = gastosFiltrados.filter(g => g.categoria === this.filtrosInteractivos.categoria);
    }
    if (this.filtrosInteractivos.concepto) {
      gastosFiltrados = gastosFiltrados.filter(g => g.concepto === this.filtrosInteractivos.concepto);
    }
    if (this.filtrosInteractivos.formaPago) {
      gastosFiltrados = gastosFiltrados.filter(g => g.formaPago === this.filtrosInteractivos.formaPago);
    }
    if (this.filtrosInteractivos.mes) {
      const meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
        'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
      gastosFiltrados = gastosFiltrados.filter(g => {
        if (!g.fecha) return false;
        const partes = g.fecha.split('/');
        if (partes.length !== 3) return false;
        const mesNum = parseInt(partes[1], 10) - 1;
        if (mesNum < 0 || mesNum > 11) return false;
        return meses[mesNum].toLowerCase() === this.filtrosInteractivos.mes?.toLowerCase();
      });
    }
    
    this.datosFiltrados.gastosDetallados = gastosFiltrados;
  }

  recalcularTodosLosGraficos(): void {
    this.recalcularGastosPorMes();
    this.recalcularGastosVsPresupuesto();
    this.recalcularGastosVsAnioAnterior();
    this.recalcularGastosPorCategoria();
    this.recalcularGastosPorConcepto();
    this.recalcularGastosPorProveedor();
    this.recalcularGastosPorFormaPago();
  }

  aplicarFiltrosInteractivos(): void {
    if (!this.inicializado) {
      return;
    }

    const datosBase = Object.keys(this.datosOriginales).length > 0
      ? this.datosOriginales
      : (this.datos || {});

    // Crear una copia profunda de los datos para filtrar
    this.datosFiltrados = this.clonarDatos(datosBase);

    // Filtrar los gastos detallados (gráficos); detalle grid = paginado en servidor.
    this.filtrarGastosDetallados();

    // Recalcular todos los gráficos
    this.recalcularTodosLosGraficos();

    // Recalcular métricas
    this.recalcularMetricas();

    // Actualizar referencia expuesta
    this.datos = this.clonarDatos(this.datosFiltrados);

    this.recalcularRowsCache();
    this.cdr.markForCheck();
  }

  /**
   * Año seleccionado en el filtro (número). Fallback: año calendario actual.
   */
  private anioFiltroNumerico(): number {
    const n = parseInt(String(this.anio || '').trim(), 10);
    return Number.isFinite(n) ? n : new Date().getFullYear();
  }

  recalcularMetricas(): void {
    /**
     * Sin filtros interactivos (clics en gráficos), las cards deben reflejar el JSON de
     * `/api/gastos/cards` (`metricasGastos`). Recalcular desde detalle pisaba esos valores
     * y además usaba siempre el año/mes calendario actual → en 2024/2025 las cards quedaban en 0.
     */
    if (
      !this.tieneFiltrosInteractivos() &&
      this.datosOriginales?.metricasGastos &&
      Object.keys(this.datosOriginales.metricasGastos).length > 0
    ) {
      this.datosFiltrados.metricasGastos = this.clonarDatos(
        this.datosOriginales.metricasGastos,
      );
      return;
    }

    // Con filtros interactivos: métricas desde gastosDetallados (no del grid paginado).
    if (!this.datosFiltrados.gastosDetallados) return;

    const gastos = (this.datosFiltrados.gastosDetallados || []).map((g: any) => ({
      fecha: g.fecha,
      gastosConIVA: g.gastosConIva ?? g.gastosConIVA ?? 0,
    }));
    const gastosConIVA = gastos.reduce(
      (sum: number, g: any) => sum + (g.gastosConIVA || 0),
      0,
    );

    const anioRef = this.anioFiltroNumerico();
    const mesStr = String(this.mes || '').trim();
    /** Mes de referencia 0–11: explícito en filtro, o mes calendario actual (misma “posición” en el año elegido). */
    const mesRef = mesStr
      ? Math.min(11, Math.max(0, parseInt(mesStr, 10) - 1))
      : new Date().getMonth();

    const gastoEnMesRef = (lista: any[]): number =>
      lista.reduce((sum: number, g: any) => {
        if (!g?.fecha) return sum;
        const fecha = new Date(g.fecha);
        if (Number.isNaN(fecha.getTime())) return sum;
        if (fecha.getFullYear() !== anioRef || fecha.getMonth() !== mesRef) {
          return sum;
        }
        return sum + (g.gastosConIVA || 0);
      }, 0);

    const gastosMesActual = gastoEnMesRef(gastos);

    const mesAnt = mesRef === 0 ? 11 : mesRef - 1;
    const anioAnt = mesRef === 0 ? anioRef - 1 : anioRef;
    const originalDetallados = this.datosOriginales.gastosDetallados || [];
    const gastosMesAnterior = originalDetallados.reduce((sum: number, g: any) => {
        if (!g?.fecha) return sum;
        const fecha = new Date(g.fecha);
        if (Number.isNaN(fecha.getTime())) return sum;
        if (fecha.getFullYear() !== anioAnt || fecha.getMonth() !== mesAnt) {
          return sum;
        }
        return sum + (g.gastosConIva ?? g.gastosConIVA ?? 0);
      }, 0);

    const variacion = gastosMesActual - gastosMesAnterior;
    const aumentoPorcentaje =
      gastosMesAnterior > 0
        ? Math.round((variacion / gastosMesAnterior) * 100)
        : 0;

    if (!this.datosFiltrados.metricasGastos) {
      this.datosFiltrados.metricasGastos = {};
    }
    this.datosFiltrados.metricasGastos.gastosConIVA = gastosConIVA;
    this.datosFiltrados.metricasGastos.gastosMesActual = gastosMesActual;
    this.datosFiltrados.metricasGastos.gastosMesAnterior = gastosMesAnterior;
    this.datosFiltrados.metricasGastos.variacionGastos = variacion;
    this.datosFiltrados.metricasGastos.aumentoCostosPorcentaje = aumentoPorcentaje;
  }

  recalcularGastosPorMes(): void {
    if (!this.tieneFiltrosInteractivos()) {
      if (this.datosOriginales?.gastosPorMesConfig) {
        this.datosFiltrados.gastosPorMesConfig = this.clonarDatos(this.datosOriginales.gastosPorMesConfig);
      }
      return;
    }

    if (!this.datosFiltrados.gastosDetallados) return;

    const meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
      'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
    const gastosPorMes: { [key: string]: number } = {};

    // Inicializar todos los meses en 0
    meses.forEach(mes => {
      gastosPorMes[mes] = 0;
    });

    this.datosFiltrados.gastosDetallados.forEach((g: any) => {
      if (g.fecha) {
        const partes = g.fecha.split('/');
        if (partes.length === 3) {
          const mesNum = parseInt(partes[1], 10) - 1; // 0-indexed
          if (mesNum >= 0 && mesNum <= 11) {
            const mesNombre = meses[mesNum];
            gastosPorMes[mesNombre] = (gastosPorMes[mesNombre] || 0) + (g.gastosConIva || 0);
          }
        }
      }
    });

    let labels = meses.filter(m => gastosPorMes[m] !== undefined && gastosPorMes[m] !== 0);
    if (labels.length === 0) {
      labels = meses;
    }
    const data = labels.map(m => gastosPorMes[m] || 0);

    this.datosFiltrados.gastosPorMesConfig = {
      title: '',
      type: 'line',
      showArea: false,
      smooth: false,
      showYAxisLabels: false,
      showXAxisLine: false,
      labels,
      data,
      colors: ['#F19447'],
      barLabelExactUnder1000: true,
    };
  }

  recalcularGastosPorCategoria(): void {
    if (!this.tieneFiltrosInteractivos()) {
      if (this.datosOriginales?.gastosPorCategoriaConfig) {
        this.datosFiltrados.gastosPorCategoriaConfig = this.clonarDatos(this.datosOriginales.gastosPorCategoriaConfig);
      }
      return;
    }

    if (!this.datosFiltrados.gastosDetallados) return;

    const gastosPorCategoria: { [key: string]: number } = {};

    this.datosFiltrados.gastosDetallados.forEach((g: any) => {
      const categoria = g.categoria || 'Gastos varios';
      gastosPorCategoria[categoria] = (gastosPorCategoria[categoria] || 0) + (g.gastosConIva || 0);
    });

    // Ordenar categorías de mayor a menor monto
    const sorted = Object.entries(gastosPorCategoria)
      .sort((a, b) => b[1] - a[1]);

    const labels = sorted.map(([name]) => name);
    const data = sorted.map(([, value]) => value);

    this.datosFiltrados.gastosPorCategoriaConfig = {
      title: '',
      type: 'bar',
      labels,
      data,
      colors: ['#F19447'],
      horizontal: true,
      showXAxisLabels: false,
      graduatedOpacity: true,
      barLabelExactUnder1000: true,
    };
  }

  recalcularGastosPorConcepto(): void {
    if (!this.tieneFiltrosInteractivos()) {
      if (this.datosOriginales?.gastosPorConceptoConfig) {
        this.datosFiltrados.gastosPorConceptoConfig = this.clonarDatos(this.datosOriginales.gastosPorConceptoConfig);
      }
      return;
    }

    if (!this.datosFiltrados.gastosDetallados) return;

    const gastosPorConcepto: { [key: string]: number } = {};

    this.datosFiltrados.gastosDetallados.forEach((g: any) => {
      const concepto = g.concepto || 'Sin concepto';
      gastosPorConcepto[concepto] = (gastosPorConcepto[concepto] || 0) + (g.gastosConIva || 0);
    });

    const sorted = Object.entries(gastosPorConcepto)
      .sort((a, b) => b[1] - a[1])
      .slice(0, 13);

    const labels = sorted.map(([name]) => name.length > 15 ? name.substring(0, 15) + '...' : name);
    const data = sorted.map(([, value]) => value);

    this.datosFiltrados.gastosPorConceptoConfig = {
      title: '',
      type: 'bar',
      collapseExcessBars: true,
      initialVisibleBars: 5,
      labels,
      data,
      colors: ['#F19447'],
      rotateLabels: 45,
      graduatedOpacity: true,
      barLabelExactUnder1000: true,
    };
  }

  recalcularGastosPorProveedor(): void {
    if (!this.tieneFiltrosInteractivos()) {
      if (this.datosOriginales?.gastosPorProveedor) {
        this.datosFiltrados.gastosPorProveedor = this.clonarDatos(this.datosOriginales.gastosPorProveedor);
      }
      return;
    }

    if (!this.datosFiltrados.gastosDetallados) return;

    const gastosPorProveedor: { [key: string]: number } = {};

    this.datosFiltrados.gastosDetallados.forEach((g: any) => {
      const proveedor = g.proveedor || 'Sin proveedor';
      gastosPorProveedor[proveedor] = (gastosPorProveedor[proveedor] || 0) + (g.gastosConIva || 0);
    });

    this.datosFiltrados.gastosPorProveedor = Object.entries(gastosPorProveedor)
      .map(([name, amount]) => ({ name, amount: amount as number }))
      .sort((a, b) => Math.abs(b.amount) - Math.abs(a.amount));
  }

  recalcularGastosPorFormaPago(): void {
    if (!this.tieneFiltrosInteractivos()) {
      if (this.datosOriginales?.gastosPorFormaPagoConfig) {
        this.datosFiltrados.gastosPorFormaPagoConfig = this.clonarDatos(this.datosOriginales.gastosPorFormaPagoConfig);
      }
      return;
    }

    if (!this.datosFiltrados.gastosDetallados) return;

    const gastosPorFormaPago: { [key: string]: number } = {};

    this.datosFiltrados.gastosDetallados.forEach((g: any) => {
      const formaPago = g.formaPago || 'Sin forma de pago';
      gastosPorFormaPago[formaPago] = (gastosPorFormaPago[formaPago] || 0) + (g.gastosConIva || 0);
    });

    const labels = Object.keys(gastosPorFormaPago);
    const numericData = labels.map(fp => gastosPorFormaPago[fp]);
    const total = numericData.reduce((s, x) => s + x, 0);
    const porcentajes = numericData.map((v) =>
      total > 0 ? (v / total) * 100 : 0
    );

    const data = Object.entries(gastosPorFormaPago)
      .map(([name, value]) => ({ name, value }))
      .sort((a, b) => b.value - a.value);

    this.datosFiltrados.gastosPorFormaPagoConfig = {
      type: 'doughnut',
      labels,
      data,
      porcentajes,
    };
  }

  recalcularGastosVsPresupuesto(): void {
    if (!this.tieneFiltrosInteractivos()) {
      if (this.datosOriginales?.gastosVsPresupuestoConfig) {
        this.datosFiltrados.gastosVsPresupuestoConfig = this.clonarDatos(this.datosOriginales.gastosVsPresupuestoConfig);
      }
      return;
    }

    if (!this.datosFiltrados.gastosDetallados) return;

    const meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
      'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
    const gastosPorMes: { [key: string]: number } = {};

    this.datosFiltrados.gastosDetallados.forEach((g: any) => {
      if (g.fecha) {
        const partes = g.fecha.split('/');
        if (partes.length === 3) {
          const mesNum = parseInt(partes[1], 10) - 1;
          if (mesNum >= 0 && mesNum <= 11) {
            const mesNombre = meses[mesNum];
            gastosPorMes[mesNombre] = (gastosPorMes[mesNombre] || 0) + (g.gastosConIva || 0);
          }
        }
      }
    });

    const presupuestosOriginales = this.datosOriginales?.gastosVsPresupuestoConfig?.dataExtra || [];

    let filteredIndices = meses
      .map((mes, index) => index)
      .filter(index => {
        const mes = meses[index];
        return (gastosPorMes[mes] || 0) !== 0 || (presupuestosOriginales[index] || 0) !== 0;
      });

    if (filteredIndices.length === 0) {
      filteredIndices = meses.map((_, index) => index);
    }

    const labels = filteredIndices.map(index => meses[index]);
    const dataGastos = filteredIndices.map(index => gastosPorMes[meses[index]] || 0);
    const dataPresupuesto = filteredIndices.map(index => presupuestosOriginales[index] || 0);

    this.datosFiltrados.gastosVsPresupuestoConfig = {
      title: '',
      type: 'bar',
      labels,
      data: [
        {
          name: 'Gastos totales',
          data: dataGastos
        },
        {
          name: 'Presupuestado',
          data: dataPresupuesto
        }
      ],
      colors: ['#F19447', '#d3d3d3'],
      barLabelExactUnder1000: true,
    };
  }

  recalcularGastosVsAnioAnterior(): void {
    if (!this.tieneFiltrosInteractivos()) {
      if (this.datosOriginales?.gastosVsAnioAnteriorConfig) {
        this.datosFiltrados.gastosVsAnioAnteriorConfig = this.clonarDatos(this.datosOriginales.gastosVsAnioAnteriorConfig);
      }
      return;
    }

    if (!this.datosFiltrados.gastosDetallados) return;

    const meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
      'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
    const gastosPorMes: { [key: string]: number } = {};

    this.datosFiltrados.gastosDetallados.forEach((g: any) => {
      if (g.fecha) {
        const partes = g.fecha.split('/');
        if (partes.length === 3) {
          const mesNum = parseInt(partes[1], 10) - 1;
          if (mesNum >= 0 && mesNum <= 11) {
            const mesNombre = meses[mesNum];
            gastosPorMes[mesNombre] = (gastosPorMes[mesNombre] || 0) + (g.gastosConIva || 0);
          }
        }
      }
    });

    const gastosAnioAnteriorOriginales = this.datosOriginales?.gastosVsAnioAnteriorConfig?.dataExtra || [];

    let filteredIndices = meses
      .map((mes, index) => index)
      .filter(index => {
        const mes = meses[index];
        return (gastosPorMes[mes] || 0) !== 0 || (gastosAnioAnteriorOriginales[index] || 0) !== 0;
      });

    if (filteredIndices.length === 0) {
      filteredIndices = meses.map((_, index) => index);
    }

    const labels = filteredIndices.map(index => meses[index]);
    const dataGastosActual = filteredIndices.map(index => gastosPorMes[meses[index]] || 0);
    const dataGastosAnterior = filteredIndices.map(index => gastosAnioAnteriorOriginales[index] || 0);

    this.datosFiltrados.gastosVsAnioAnteriorConfig = {
      title: '',
      type: 'bar',
      labels,
      data: [
        {
          name: 'Año actual',
          data: dataGastosActual
        },
        {
          name: 'Año anterior',
          data: dataGastosAnterior
        }
      ],
      colors: ['#F19447', '#d3d3d3'],
      barLabelExactUnder1000: true,
    };
  }

  /**
   * Recalcula cache auxiliar. Detalle grid: carga lazy (no viene en el merge).
   */
  private recalcularRowsCache(): void {
    const datos = this.datosFiltrados && Object.keys(this.datosFiltrados).length > 0 ? this.datosFiltrados : this.datos;
    const currentHash = this.generarHashDatos(datos);
    if (currentHash === this._lastDatosHash) {
      return; // No hay cambios
    }
    this._lastDatosHash = currentHash;
  }

  get detalleGastosRows(): any[] {
    return this._detalleGastosRowsCache;
  }

  get totalDetalleGastos(): string {
    return this._totalDetalleGastosCache;
  }

  // Getter para obtener los datos correctos (filtrados o originales)
  get datosParaVista(): any {
    return this.datosFiltrados && Object.keys(this.datosFiltrados).length > 0 ? this.datosFiltrados : this.datos;
  }

  // ─────────────────────────────────────────────
  // TrackBy functions para optimizar ngFor
  // ─────────────────────────────────────────────

  trackByIndex(index: number, item: any): number {
    return index;
  }

  trackByName(index: number, item: any): string | number {
    return item.name || index;
  }

  trackByProveedor(index: number, item: any): string | number {
    return item.proveedor || index;
  }

}
