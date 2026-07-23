import {
  Component,
  Input,
  OnInit,
  OnChanges,
  SimpleChanges,
  ViewChild,
  Output,
  EventEmitter,
  ChangeDetectorRef,
  ChangeDetectionStrategy,
  OnDestroy,
} from '@angular/core';
import { Subject } from 'rxjs';
import { debounceTime, distinctUntilChanged, switchMap, takeUntil } from 'rxjs/operators';
import { DashboardDataService } from '../../services/dashboard-data.service';
import { CashFlowItem } from '../../models/chart-config.model';
import { RevoGrid } from '@revolist/angular-datagrid';
import { SortingPlugin, FilterPlugin, ExportFilePlugin } from '@revolist/revogrid';
import { WebdatarocksComponent } from '@webdatarocks/ngx-webdatarocks';
import { ApiService } from '@services/api.service';
import { DropdownMultiFiltroSelection } from '../../components/dropdown-multi-filtro/dropdown-multi-filtro.component';
import { DashboardFiltrosCatalogoService } from '../../services/dashboard-filtros-catalogo.service';
import { ColDef, GridOptions, GridApi } from 'ag-grid-community';
import { formatEmpresaCurrency, getEmpresaCurrencySymbol } from '@helpers/currency-format.helper';
import {
  CashflowVentasTotales,
  CashflowGastosTotales,
  AbonosCxcTotales,
  AbonosCxpTotales,
} from '../../services/resultados-dashboard-data.service';

@Component({
  selector: 'app-resultados',
  templateUrl: './resultados.component.html',
  styleUrls: ['./resultados.component.css'],
  changeDetection: ChangeDetectionStrategy.OnPush
})
export class ResultadosComponent implements OnInit, OnChanges, OnDestroy {
  @Input() datos: any = {};
  @Input() datosCompletos = false;
  @Output() filtrosCambiados = new EventEmitter<any>();

  // Propiedades cacheadas para evitar recálculos
  private _ventasRowsCache: any[] = [];
  /** Binding directo OnPush para ag-grid Ventas del mes. */
  ventasRows: any[] = [];
  ventasListo = false;
  ventasLoading = false;
  ventasExporting = false;
  readonly ventasPageSize = 50;
  ventasOffset = 0;
  ventasTotal = 0;
  ventasTotales: CashflowVentasTotales = { monto: 0 };
  private readonly destroy$ = new Subject<void>();
  private readonly ventasPage$ = new Subject<{
    offset: number;
    q: string;
    append: boolean;
  }>();
  private readonly quickFilterVentas$ = new Subject<string>();
  private ventasAppendPending = false;

  private _gastosRowsCache: any[] = [];
  /** Binding directo OnPush para ag-grid Gastos del mes. */
  gastosRows: any[] = [];
  gastosListo = false;
  gastosLoading = false;
  gastosExporting = false;
  readonly gastosPageSize = 50;
  gastosOffset = 0;
  gastosTotal = 0;
  gastosTotales: CashflowGastosTotales = { monto: 0 };
  private readonly gastosPage$ = new Subject<{
    offset: number;
    q: string;
    append: boolean;
  }>();
  private readonly quickFilterGastos$ = new Subject<string>();
  private gastosAppendPending = false;

  private _cobrar30RowsCache: any[] = [];
  private _pagar30RowsCache: any[] = [];
  private _totalCobrar30Cache: number = 0;
  private _totalPagar30Cache: number = 0;

  private _abonosCxcRowsCache: any[] = [];
  /** Binding directo OnPush para ag-grid Abonos CXC. */
  abonosCxcRows: any[] = [];
  abonosCxcListo = false;
  abonosCxcLoading = false;
  abonosCxcExporting = false;
  readonly abonosCxcPageSize = 50;
  abonosCxcOffset = 0;
  abonosCxcTotal = 0;
  abonosCxcTotalesApi: AbonosCxcTotales = { monto: 0 };
  private readonly abonosCxcPage$ = new Subject<{
    offset: number;
    q: string;
    append: boolean;
  }>();
  private readonly quickFilterAbonosCxc$ = new Subject<string>();
  private abonosCxcAppendPending = false;

  private _abonosCxpRowsCache: any[] = [];
  /** Binding directo OnPush para ag-grid Abonos CXP. */
  abonosCxpRows: any[] = [];
  abonosCxpListo = false;
  abonosCxpLoading = false;
  abonosCxpExporting = false;
  readonly abonosCxpPageSize = 50;
  abonosCxpOffset = 0;
  abonosCxpTotal = 0;
  abonosCxpTotalesApi: AbonosCxpTotales = { monto: 0 };
  private readonly abonosCxpPage$ = new Subject<{
    offset: number;
    q: string;
    append: boolean;
  }>();
  private readonly quickFilterAbonosCxp$ = new Subject<string>();
  private abonosCxpAppendPending = false;

  private _totalAbonosCxcCache: number = 0;
  private _totalAbonosCxpCache: number = 0;

  private _lastDatosHash: string = '';

  // AG Grid APIs y configuraciones
  private ventasGridApi!: GridApi;
  private gastosGridApi!: GridApi;
  private cobrar30GridApi!: GridApi;
  private pagar30GridApi!: GridApi;

  // 👇 NUEVO: APIs para abonos
  private abonosCxcGridApi!: GridApi;
  private abonosCxpGridApi!: GridApi;

  ventasGridOptions: GridOptions = {};
  gastosGridOptions: GridOptions = {};
  cobrar30GridOptions: GridOptions = {};
  pagar30GridOptions: GridOptions = {};

  // 👇 NUEVO: Grid options para abonos
  abonosCxcGridOptions: GridOptions = {};
  abonosCxpGridOptions: GridOptions = {};

  ventasColumnDefs: ColDef[] = [
    {
      field: 'cliente',
      headerName: 'Cliente',
      flex: 2,
      sortable: true,
      filter: true
    },
    {
      field: 'factura',
      headerName: '# factura',
      width: 130,
      sortable: true,
      filter: true
    },
    {
      field: 'monto',
      headerName: 'Monto',
      width: 160,
      sortable: true,
      filter: true,
      valueFormatter: (params: any) => {
        return params.value ? this.formatCurrency(params.value) : '';
      },
      cellStyle: { textAlign: 'right' },
      type: 'numericColumn'
    }
  ];

  gastosColumnDefs: ColDef[] = [
    {
      field: 'proveedor',
      headerName: 'Proveedor',
      flex: 2,
      sortable: true,
      filter: true
    },
    {
      field: 'factura',
      headerName: '# factura',
      width: 130,
      sortable: true,
      filter: true
    },
    {
      field: 'monto',
      headerName: 'Monto',
      width: 160,
      sortable: true,
      filter: true,
      valueFormatter: (params: any) => {
        return params.value ? this.formatCurrency(params.value) : '';
      },
      cellStyle: { textAlign: 'right' },
      type: 'numericColumn'
    }
  ];

  cobrar30ColumnDefs: ColDef[] = [
    { field: 'factura', headerName: '# Factura', width: 120, sortable: true, filter: true },
    { field: 'cliente', headerName: 'Cliente', flex: 2, sortable: true, filter: true },
    { field: 'vence', headerName: 'Vence', width: 120, sortable: true, filter: true },
    {
      field: 'diasVencimiento',
      headerName: 'Días venc.',
      width: 110,
      sortable: true,
      filter: true,
      cellStyle: { textAlign: 'right' },
      type: 'numericColumn'
    },
    {
      field: 'monto',
      headerName: 'Monto',
      width: 150,
      sortable: true,
      filter: true,
      valueFormatter: (params: any) => params.value ? this.formatCurrency(params.value) : '',
      cellStyle: { textAlign: 'right' },
      type: 'numericColumn'
    }
  ];

  pagar30ColumnDefs: ColDef[] = [
    { field: 'factura', headerName: '# Factura', width: 120, sortable: true, filter: true },
    { field: 'proveedor', headerName: 'Proveedor', flex: 2, sortable: true, filter: true },
    { field: 'vence', headerName: 'Vence', width: 120, sortable: true, filter: true },
    {
      field: 'diasVencimiento',
      headerName: 'Días venc.',
      width: 110,
      sortable: true,
      filter: true,
      cellStyle: { textAlign: 'right' },
      type: 'numericColumn'
    },
    {
      field: 'monto',
      headerName: 'Monto',
      width: 150,
      sortable: true,
      filter: true,
      valueFormatter: (params: any) => params.value ? this.formatCurrency(params.value) : '',
      cellStyle: { textAlign: 'right' },
      type: 'numericColumn'
    }
  ];

  // 👇 NUEVO: Column definitions para abonos
  abonosCxcColumnDefs: ColDef[] = [
    { field: 'factura', headerName: '# Factura', width: 120, sortable: true, filter: true },
    { field: 'cliente', headerName: 'Cliente', flex: 2, sortable: true, filter: true },
    { field: 'vence', headerName: 'Vence', width: 120, sortable: true, filter: true },
    {
      field: 'diasVencimiento',
      headerName: 'Días venc.',
      width: 110,
      sortable: true,
      filter: true,
      cellStyle: { textAlign: 'right' },
      type: 'numericColumn'
    },
    {
      field: 'monto',
      headerName: 'Monto',
      width: 150,
      sortable: true,
      filter: true,
      valueFormatter: (params: any) => params.value ? this.formatCurrency(params.value) : '',
      cellStyle: { textAlign: 'right' },
      type: 'numericColumn'
    }
  ];

  abonosCxpColumnDefs: ColDef[] = [
    { field: 'factura', headerName: '# Factura', width: 120, sortable: true, filter: true },
    { field: 'proveedor', headerName: 'Proveedor', flex: 2, sortable: true, filter: true },
    { field: 'vence', headerName: 'Vence', width: 120, sortable: true, filter: true },
    {
      field: 'diasVencimiento',
      headerName: 'Días venc.',
      width: 110,
      sortable: true,
      filter: true,
      cellStyle: { textAlign: 'right' },
      type: 'numericColumn'
    },
    {
      field: 'monto',
      headerName: 'Monto',
      width: 150,
      sortable: true,
      filter: true,
      valueFormatter: (params: any) => params.value ? this.formatCurrency(params.value) : '',
      cellStyle: { textAlign: 'right' },
      type: 'numericColumn'
    }
  ];

  private inicializado: boolean = false;

  @ViewChild('ventasPivot') ventasPivotRef!: WebdatarocksComponent;
  @ViewChild('gastosPivot') gastosPivotRef!: WebdatarocksComponent;
  @ViewChild('cobrar30Pivot') cobrar30PivotRef!: WebdatarocksComponent;
  @ViewChild('pagar30Pivot') pagar30PivotRef!: WebdatarocksComponent;

  // Plugins para revo-grid legacy (CXC/CXP aún en uso si los hay)
  cobrar30Plugins = [SortingPlugin, FilterPlugin, ExportFilePlugin];
  pagar30Plugins = [SortingPlugin, FilterPlugin, ExportFilePlugin];

  // ── WebDataRocks: Ventas del mes ──────────────────────────────────────────
  private _ventasPivotInstance: any = null;

  /** Configuración global: idioma español */
  ventasPivotGlobal: any = {
    localization: 'https://cdn.webdatarocks.com/loc/es.json'
  };

  /** Reporte inicial (vacío; se actualiza en onVentasPivotReady / ngOnChanges) */
  ventasPivotReport: any = {};

  // ── WebDataRocks: Gastos del mes ─────────────────────────────────────────
  private _gastosPivotInstance: any = null;

  /** Configuración global: idioma español */
  gastosPivotGlobal: any = {
    localization: 'https://cdn.webdatarocks.com/loc/es.json'
  };

  /** Reporte inicial (vacío; se actualiza en onGastosPivotReady / ngOnChanges) */
  gastosPivotReport: any = {};

  // ── WebDataRocks: CXC próximos 30 días ─────────────────────────────────
  private _cobrar30PivotInstance: any = null;
  cobrar30PivotGlobal: any = { localization: 'https://cdn.webdatarocks.com/loc/es.json' };
  cobrar30PivotReport: any = {};

  // ── WebDataRocks: CXP próximos 30 días ─────────────────────────────────
  private _pagar30PivotInstance: any = null;
  pagar30PivotGlobal: any = { localization: 'https://cdn.webdatarocks.com/loc/es.json' };
  pagar30PivotReport: any = {};

  // Búsqueda (quick filter)
  busquedaVentas: string = '';
  busquedaGastos: string = '';
  busquedaCobrar30: string = '';
  busquedaPagar30: string = '';

  // Quick filter para ag-grid
  quickFilterVentas: string = '';
  quickFilterGastos: string = '';
  quickFilterCobrar30: string = '';
  quickFilterPagar30: string = '';

  quickFilterAbonosCxc: string = '';
  quickFilterAbonosCxp: string = '';

  // Pinned bottom row data for totals
  pinnedBottomRowDataVentas: any[] = [];
  pinnedBottomRowDataGastos: any[] = [];
  pinnedBottomRowDataAbonosCxc: any[] = [];
  pinnedBottomRowDataAbonosCxp: any[] = [];

  // Filtros
  anios = [2024, 2025, 2026];
  anioSeleccionado: number = new Date().getFullYear();

  /** Mes para flujo de efectivo (1–12). `null` = todo el año (sin `mes` en el API). */
  mesFlujoEfectivo: number | null = null;

  private readonly nombresMes = [
    'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
    'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'
  ];

  /** Sucursales reales (sin opción “Todas”: vacío en el multi = todas). */
  sucursales: { id: string; nombre: string }[] = [];
  /** IDs seleccionados. Vacío + `sucursalesTodasImplicitas` → API “todas”. */
  sucursalesSeleccionadas: string[] = [];

  /**
   * true: “Todas” (todas las sucursales en el filtro); checkboxes de sucursal se muestran marcados.
   * false + []: usuario desmarcó “Seleccionar todo”; checkboxes desmarcados; API sigue en “todas” hasta que elija al menos una.
   */
  sucursalesTodasImplicitas = true;

  /** true mientras se espera la respuesta del servidor tras cambiar un filtro */
  filtrosLocked = false;
  private _filtrosLockTimeout: any = null;

  constructor(
    private cdr: ChangeDetectorRef,
    private apiService: ApiService,
    private filtrosCatalogo: DashboardFiltrosCatalogoService,
    private dashboardDataService: DashboardDataService
  ) { }

  private cargarSucursales(): void {
    this.filtrosCatalogo.sucursalesParaFiltro().subscribe({
      next: (items) => {
        this.sucursales = items;
        const user = this.apiService.auth_user();
        const tieneEstadoGuardado = !!this.dashboardDataService.obtenerFiltrosUI('Resultados');
        if (!tieneEstadoGuardado) {
          if (items.length === 0) {
            this.sucursalesSeleccionadas = [];
            this.sucursalesTodasImplicitas = true;
          } else if (user?.tipo !== 'Administrador' && user?.id_sucursal != null) {
            this.sucursalesTodasImplicitas = false;
            this.sucursalesSeleccionadas = [String(user.id_sucursal)];
            setTimeout(() => {
              if (this.inicializado) {
                this.aplicarFiltros();
              }
            }, 150);
          } else if (user?.tipo === 'Administrador') {
            this.sucursalesSeleccionadas = [];
            this.sucursalesTodasImplicitas = true;
          }
        }
        this.cdr.markForCheck();
      },
    });
  }

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
   * Formateador con caché
   */

  /**
   * Recalcula todas las filas cacheadas
   */
  private recalcularRowsCache(): void {
    const currentHash = this.generarHashDatos(this.datos);

    if (currentHash === this._lastDatosHash && this._lastDatosHash !== '') {
      return;
    }
    this._lastDatosHash = currentHash;

    // Ventas/gastos cashflow: carga lazy (no vienen en el merge).

    this._cobrar30RowsCache = this.datos?.cuentas30?.cobrar || [];
    this._pagar30RowsCache = this.datos?.cuentas30?.pagar || [];

    this._totalCobrar30Cache = this._cobrar30RowsCache.reduce(
      (acc, curr) => acc + (curr.monto || 0),
      0
    );
    this._totalPagar30Cache = this._pagar30RowsCache.reduce(
      (acc, curr) => acc + (curr.monto || 0),
      0
    );

    // Abonos CXC/CXP: lazy.
  }

  configurarAGGrid(): void {
    const defaultDefs = {
      resizable: true,
      sortable: true,
      filter: true
    };

    const sizeToFit = (api: any) => { try { api.sizeColumnsToFit(); } catch { } };

    const getRowClassCallback = (params: any) => {
      if (params.node.rowPinned === 'bottom') {
        return 'ag-row-total';
      }
      return '';
    };

    this.ventasGridOptions = {
      defaultColDef: defaultDefs,
      enableCellTextSelection: true,
      ensureDomOrder: true,
      pagination: true,
      paginationPageSize: 25,
      getRowClass: getRowClassCallback,
      onGridReady: (params: any) => {
        this.ventasGridApi = params.api;
        if (this.ventasRows.length > 0) {
          params.api.setRowData(this.ventasRows);
        }
        if (this.pinnedBottomRowDataVentas.length > 0) {
          params.api.setPinnedBottomRowData(this.pinnedBottomRowDataVentas);
        }
        sizeToFit(params.api);
        this.recalcularTotalesVentas();
      },
      onFirstDataRendered: (params: any) => sizeToFit(params.api),
      onGridSizeChanged: (params: any) => sizeToFit(params.api),
      onPaginationChanged: () => {
        this.maybeLoadMoreVentasFromGrid();
      },
      onFilterChanged: () => {
        this.onFilterChangedVentas();
      }
    };

    this.gastosGridOptions = {
      defaultColDef: defaultDefs,
      enableCellTextSelection: true,
      ensureDomOrder: true,
      pagination: true,
      paginationPageSize: 25,
      getRowClass: getRowClassCallback,
      onGridReady: (params: any) => {
        this.gastosGridApi = params.api;
        if (this.gastosRows.length > 0) {
          params.api.setRowData(this.gastosRows);
        }
        if (this.pinnedBottomRowDataGastos.length > 0) {
          params.api.setPinnedBottomRowData(this.pinnedBottomRowDataGastos);
        }
        sizeToFit(params.api);
        this.recalcularTotalesGastos();
      },
      onFirstDataRendered: (params: any) => sizeToFit(params.api),
      onGridSizeChanged: (params: any) => sizeToFit(params.api),
      onPaginationChanged: () => {
        this.maybeLoadMoreGastosFromGrid();
      },
      onFilterChanged: () => {
        this.onFilterChangedGastos();
      }
    };

    this.cobrar30GridOptions = {
      defaultColDef: defaultDefs,
      enableCellTextSelection: true,
      ensureDomOrder: true,
      pagination: true,
      paginationPageSize: 10,
      onGridReady: (params: any) => {
        this.cobrar30GridApi = params.api;
        sizeToFit(params.api);
      },
      onFirstDataRendered: (params: any) => sizeToFit(params.api),
      onGridSizeChanged: (params: any) => sizeToFit(params.api)
    };

    this.pagar30GridOptions = {
      defaultColDef: defaultDefs,
      enableCellTextSelection: true,
      ensureDomOrder: true,
      pagination: true,
      paginationPageSize: 10,
      onGridReady: (params: any) => {
        this.pagar30GridApi = params.api;
        sizeToFit(params.api);
      },
      onFirstDataRendered: (params: any) => sizeToFit(params.api),
      onGridSizeChanged: (params: any) => sizeToFit(params.api)
    };

    // 👇 NUEVO: Configurar grids de abonos
    this.abonosCxcGridOptions = {
      defaultColDef: defaultDefs,
      enableCellTextSelection: true,
      ensureDomOrder: true,
      pagination: true,
      paginationPageSize: 25,
      getRowClass: getRowClassCallback,
      onGridReady: (params: any) => {
        this.abonosCxcGridApi = params.api;
        if (this.abonosCxcRows.length > 0) {
          params.api.setRowData(this.abonosCxcRows);
        }
        if (this.pinnedBottomRowDataAbonosCxc.length > 0) {
          params.api.setPinnedBottomRowData(this.pinnedBottomRowDataAbonosCxc);
        }
        sizeToFit(params.api);
        this.recalcularTotalesAbonosCxc();
      },
      onFirstDataRendered: (params: any) => sizeToFit(params.api),
      onGridSizeChanged: (params: any) => sizeToFit(params.api),
      onPaginationChanged: () => {
        this.maybeLoadMoreAbonosCxcFromGrid();
      },
      onFilterChanged: () => {
        this.onFilterChangedAbonosCxc();
      }
    };

    this.abonosCxpGridOptions = {
      defaultColDef: defaultDefs,
      enableCellTextSelection: true,
      ensureDomOrder: true,
      pagination: true,
      paginationPageSize: 25,
      getRowClass: getRowClassCallback,
      onGridReady: (params: any) => {
        this.abonosCxpGridApi = params.api;
        if (this.abonosCxpRows.length > 0) {
          params.api.setRowData(this.abonosCxpRows);
        }
        if (this.pinnedBottomRowDataAbonosCxp.length > 0) {
          params.api.setPinnedBottomRowData(this.pinnedBottomRowDataAbonosCxp);
        }
        sizeToFit(params.api);
        this.recalcularTotalesAbonosCxp();
      },
      onFirstDataRendered: (params: any) => sizeToFit(params.api),
      onGridSizeChanged: (params: any) => sizeToFit(params.api),
      onPaginationChanged: () => {
        this.maybeLoadMoreAbonosCxpFromGrid();
      },
      onFilterChanged: () => {
        this.onFilterChangedAbonosCxp();
      }
    };
  }

  ngOnInit(): void {
    this.configurarAGGrid();
    this.wireCashflowVentasStreams();
    this.wireCashflowGastosStreams();
    this.wireAbonosCxcStreams();
    this.wireAbonosCxpStreams();

    const savedState = this.dashboardDataService.obtenerFiltrosUI('Resultados');
    const tieneEstadoGuardado = !!savedState;
    if (savedState) {
      Object.assign(this, savedState);
    }

    // Recalcular cache si hay datos
    if (this.datos && Object.keys(this.datos).length > 0) {
      this.recalcularRowsCache();
    }
    if (!tieneEstadoGuardado) {
      this.aplicarDefectoMesFlujoEfectivo();
    }
    this.cargarSucursales();
    // Marcar como inicializado después de un pequeño delay
    setTimeout(() => {
      this.inicializado = true;
      if (!tieneEstadoGuardado) {
        this.aplicarFiltros();
      } else {
        this.cargarCashflowVentasPagina(0);
        this.cargarCashflowGastosPagina(0);
        this.cargarAbonosCxcPagina(0);
        this.cargarAbonosCxpPagina(0);
      }
      this.cdr.markForCheck();
    }, 100);
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    this.dashboardDataService.guardarFiltrosUI('Resultados', {
      anioSeleccionado: this.anioSeleccionado,
      mesFlujoEfectivo: this.mesFlujoEfectivo,
      sucursalesSeleccionadas: this.sucursalesSeleccionadas,
      sucursalesTodasImplicitas: this.sucursalesTodasImplicitas
    });
  }

  /** Inicia en el mes actual. */
  private aplicarDefectoMesFlujoEfectivo(): void {
    this.mesFlujoEfectivo = new Date().getMonth() + 1;
  }

  /** Último mes disponible en el desplegable según año (año actual → hasta hoy; otro año → 12). */
  maxMesDisponibleParaAnio(anio: number): number {
    const now = new Date();
    const cy = now.getFullYear();
    const mesActual = now.getMonth() + 1;
    if (anio < cy || anio > cy) {
      return 12;
    }
    return mesActual;
  }

  /** Opciones del select de mes (dinámico según `anioSeleccionado`). */
  get opcionesMesFlujo(): { valor: number; etiqueta: string }[] {
    const max = this.maxMesDisponibleParaAnio(this.anioSeleccionado);
    const out: { valor: number; etiqueta: string }[] = [];
    for (let m = 1; m <= max; m++) {
      out.push({ valor: m, etiqueta: this.nombresMes[m - 1] });
    }
    return out;
  }

  private ajustarMesFlujoAlCambiarAnio(): void {
    const max = this.maxMesDisponibleParaAnio(this.anioSeleccionado);
    if (this.mesFlujoEfectivo != null && this.mesFlujoEfectivo > max) {
      this.mesFlujoEfectivo = max;
    }
  }

  onMesFlujoEfectivoChange(): void {
    this.aplicarFiltros();
  }

  trackByMesValor(_i: number, item: { valor: number }): number {
    return item.valor;
  }

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['datos']) {
      if (this.datos && Object.keys(this.datos).length > 0) {
        // Recalcular cache cuando los datos cambien (progresivo)
        this.recalcularRowsCache();
        this.actualizarVentasPivot();
        this.actualizarGastosPivot();
        this.actualizarCobrar30Pivot();
        this.actualizarPagar30Pivot();
        this.cdr.markForCheck();
      }
    }

    // datosCompletos=true: todas las APIs terminaron → desbloquear filtros
    if (changes['datosCompletos'] && this.datosCompletos) {
      this._desbloquearFiltros();
    }
  }

  cambiarAnio(anio: number): void {
    this.anioSeleccionado = anio;
    this.ajustarMesFlujoAlCambiarAnio();
    this.aplicarFiltros();
  }

  onSucursalesMultiFiltroChange(ev: DropdownMultiFiltroSelection): void {
    this.sucursalesTodasImplicitas = ev.todasImplicitas;
    this.sucursalesSeleccionadas = [...ev.seleccionados];
    this.aplicarFiltros();
    this.cdr.markForCheck();
  }

  /** Usuario con una sola sucursal asignada no puede cambiar el filtro. */
  get sucursalesMultiDisabled(): boolean {
    const user = this.apiService.auth_user();
    return user?.tipo !== 'Administrador' && this.sucursales.length <= 1;
  }

  aplicarFiltros(): void {
    // No emitir durante la inicialización
    if (!this.inicializado) {
      return;
    }

    // Bloquear filtros mientras se espera la respuesta
    this._bloquearFiltros();

    const allIds = this.sucursales.map(s => s.id);
    const sel = this.sucursalesSeleccionadas;
    const sucursal =
      this.sucursalesTodasImplicitas
        || sel.length === 0
        || (allIds.length > 0 && sel.length === allIds.length && allIds.every(id => sel.includes(id)))
        ? 'todas'
        : [...sel];

    const filtros = {
      anio: this.anioSeleccionado,
      sucursal,
      ...(this.mesFlujoEfectivo != null ? { mes: this.mesFlujoEfectivo } : {})
    };

    // Emitir evento al componente padre para recargar datos
    this.filtrosCambiados.emit(filtros);
    this.cargarCashflowVentasPagina(0);
    this.cargarCashflowGastosPagina(0);
    this.cargarAbonosCxcPagina(0);
    this.cargarAbonosCxpPagina(0);
  }

  private getFiltrosCashflowDetalle(): any {
    const allIds = this.sucursales.map((s) => s.id);
    const sel = this.sucursalesSeleccionadas;
    const sucursal =
      this.sucursalesTodasImplicitas ||
      sel.length === 0 ||
      (allIds.length > 0 &&
        sel.length === allIds.length &&
        allIds.every((id) => sel.includes(id)))
        ? 'todas'
        : [...sel];
    const filtros: any = {
      anio: this.anioSeleccionado,
      sucursal,
    };
    if (this.mesFlujoEfectivo != null) {
      filtros.mes = this.mesFlujoEfectivo;
    }
    return filtros;
  }

  private wireCashflowVentasStreams(): void {
    this.ventasPage$
      .pipe(
        switchMap(({ offset, q, append }) => {
          this.ventasAppendPending = append;
          this.ventasLoading = true;
          this.cdr.markForCheck();
          return this.dashboardDataService.obtenerCashflowVentasPagina(
            this.getFiltrosCashflowDetalle(),
            {
              limite: this.ventasPageSize,
              offset,
              q: q || undefined,
            },
          );
        }),
        takeUntil(this.destroy$),
      )
      .subscribe({
        next: (page) => {
          this.ventasOffset = page.offset;
          this.ventasTotal = page.total;
          this.ventasTotales = page.totales;
          const append =
            this.ventasAppendPending && (page.items?.length ?? 0) > 0;
          this.applyVentasPageRows(page.items, page.totales, append);
          this.ventasAppendPending = false;
          this.ventasListo = true;
          this.ventasLoading = false;
          this.cdr.markForCheck();
        },
        error: () => {
          this.ventasAppendPending = false;
          this.ventasLoading = false;
          this.ventasListo = true;
          this.cdr.markForCheck();
        },
      });

    this.quickFilterVentas$
      .pipe(
        debounceTime(300),
        distinctUntilChanged(),
        takeUntil(this.destroy$),
      )
      .subscribe(() => {
        this.cargarCashflowVentasPagina(0);
      });
  }

  cargarCashflowVentasPagina(offset: number, append = false): void {
    this.ventasPage$.next({
      offset: Math.max(0, offset),
      q: (this.quickFilterVentas || '').trim(),
      append,
    });
  }

  private maybeLoadMoreVentasFromGrid(): void {
    if (!this.ventasGridApi || this.ventasLoading || !this.ventasListo) {
      return;
    }
    const loaded = this._ventasRowsCache.length;
    if (loaded >= this.ventasTotal) return;
    const pageSize = this.ventasGridApi.paginationGetPageSize() || 25;
    const currentPage = this.ventasGridApi.paginationGetCurrentPage();
    const lastLoadedPage = Math.max(0, Math.ceil(loaded / pageSize) - 1);
    if (currentPage >= lastLoadedPage) {
      this.cargarCashflowVentasPagina(loaded, true);
    }
  }

  private applyVentasPageRows(
    items: any[],
    totales: CashflowVentasTotales,
    append = false,
  ): void {
    const mapped = (items ?? []).map((v: any) => ({
      cliente: v.cliente || '-',
      factura: v.factura || '',
      monto: Number(v.monto) || 0,
    }));
    if (append && this._ventasRowsCache.length > 0) {
      this._ventasRowsCache = [...this._ventasRowsCache, ...mapped];
    } else {
      this._ventasRowsCache = mapped;
      if (this.ventasGridApi) {
        this.ventasGridApi.paginationGoToFirstPage();
      }
    }
    this.ventasRows = [...this._ventasRowsCache];
    if (this.ventasGridApi) {
      this.ventasGridApi.setRowData(this.ventasRows);
    }
    this.aplicarPinnedTotalesVentas(totales);
    this.actualizarVentasPivot();
  }

  private aplicarPinnedTotalesVentas(totales: CashflowVentasTotales): void {
    if ((totales?.monto || 0) !== 0) {
      this.pinnedBottomRowDataVentas = [
        { cliente: 'Total', factura: '', monto: totales.monto },
      ];
    } else {
      this.pinnedBottomRowDataVentas = [];
    }
    if (this.ventasGridApi) {
      this.ventasGridApi.setPinnedBottomRowData(this.pinnedBottomRowDataVentas);
    }
  }

  private csvEscape(value: string): string {
    if (/[",\n\r]/.test(value)) {
      return `"${value.replace(/"/g, '""')}"`;
    }
    return value;
  }

  private wireCashflowGastosStreams(): void {
    this.gastosPage$
      .pipe(
        switchMap(({ offset, q, append }) => {
          this.gastosAppendPending = append;
          this.gastosLoading = true;
          this.cdr.markForCheck();
          return this.dashboardDataService.obtenerCashflowGastosPagina(
            this.getFiltrosCashflowDetalle(),
            {
              limite: this.gastosPageSize,
              offset,
              q: q || undefined,
            },
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
        this.cargarCashflowGastosPagina(0);
      });
  }

  cargarCashflowGastosPagina(offset: number, append = false): void {
    this.gastosPage$.next({
      offset: Math.max(0, offset),
      q: (this.quickFilterGastos || '').trim(),
      append,
    });
  }

  private maybeLoadMoreGastosFromGrid(): void {
    if (!this.gastosGridApi || this.gastosLoading || !this.gastosListo) {
      return;
    }
    const loaded = this._gastosRowsCache.length;
    if (loaded >= this.gastosTotal) return;
    const pageSize = this.gastosGridApi.paginationGetPageSize() || 25;
    const currentPage = this.gastosGridApi.paginationGetCurrentPage();
    const lastLoadedPage = Math.max(0, Math.ceil(loaded / pageSize) - 1);
    if (currentPage >= lastLoadedPage) {
      this.cargarCashflowGastosPagina(loaded, true);
    }
  }

  private applyGastosPageRows(
    items: any[],
    totales: CashflowGastosTotales,
    append = false,
  ): void {
    const mapped = (items ?? []).map((g: any) => ({
      proveedor: g.proveedor || '-',
      factura: g.factura || '',
      monto: Number(g.monto) || 0,
    }));
    if (append && this._gastosRowsCache.length > 0) {
      this._gastosRowsCache = [...this._gastosRowsCache, ...mapped];
    } else {
      this._gastosRowsCache = mapped;
      if (this.gastosGridApi) {
        this.gastosGridApi.paginationGoToFirstPage();
      }
    }
    this.gastosRows = [...this._gastosRowsCache];
    if (this.gastosGridApi) {
      this.gastosGridApi.setRowData(this.gastosRows);
    }
    this.aplicarPinnedTotalesGastos(totales);
    this.actualizarGastosPivot();
  }

  private aplicarPinnedTotalesGastos(totales: CashflowGastosTotales): void {
    if ((totales?.monto || 0) !== 0) {
      this.pinnedBottomRowDataGastos = [
        { proveedor: 'Total', factura: '', monto: totales.monto },
      ];
    } else {
      this.pinnedBottomRowDataGastos = [];
    }
    if (this.gastosGridApi) {
      this.gastosGridApi.setPinnedBottomRowData(this.pinnedBottomRowDataGastos);
    }
  }

  private wireAbonosCxcStreams(): void {
    this.abonosCxcPage$
      .pipe(
        switchMap(({ offset, q, append }) => {
          this.abonosCxcAppendPending = append;
          this.abonosCxcLoading = true;
          this.cdr.markForCheck();
          return this.dashboardDataService.obtenerAbonosCxcPagina(
            this.getFiltrosCashflowDetalle(),
            {
              limite: this.abonosCxcPageSize,
              offset,
              q: q || undefined,
            },
          );
        }),
        takeUntil(this.destroy$),
      )
      .subscribe({
        next: (page) => {
          this.abonosCxcOffset = page.offset;
          this.abonosCxcTotal = page.total;
          this.abonosCxcTotalesApi = page.totales;
          const append =
            this.abonosCxcAppendPending && (page.items?.length ?? 0) > 0;
          this.applyAbonosCxcPageRows(page.items, page.totales, append);
          this.abonosCxcAppendPending = false;
          this.abonosCxcListo = true;
          this.abonosCxcLoading = false;
          this.cdr.markForCheck();
        },
        error: () => {
          this.abonosCxcAppendPending = false;
          this.abonosCxcLoading = false;
          this.abonosCxcListo = true;
          this.cdr.markForCheck();
        },
      });

    this.quickFilterAbonosCxc$
      .pipe(
        debounceTime(300),
        distinctUntilChanged(),
        takeUntil(this.destroy$),
      )
      .subscribe(() => {
        this.cargarAbonosCxcPagina(0);
      });
  }

  cargarAbonosCxcPagina(offset: number, append = false): void {
    this.abonosCxcPage$.next({
      offset: Math.max(0, offset),
      q: (this.quickFilterAbonosCxc || '').trim(),
      append,
    });
  }

  private maybeLoadMoreAbonosCxcFromGrid(): void {
    if (
      !this.abonosCxcGridApi ||
      this.abonosCxcLoading ||
      !this.abonosCxcListo
    ) {
      return;
    }
    const loaded = this._abonosCxcRowsCache.length;
    if (loaded >= this.abonosCxcTotal) return;
    const pageSize = this.abonosCxcGridApi.paginationGetPageSize() || 25;
    const currentPage = this.abonosCxcGridApi.paginationGetCurrentPage();
    const lastLoadedPage = Math.max(0, Math.ceil(loaded / pageSize) - 1);
    if (currentPage >= lastLoadedPage) {
      this.cargarAbonosCxcPagina(loaded, true);
    }
  }

  private applyAbonosCxcPageRows(
    items: any[],
    totales: AbonosCxcTotales,
    append = false,
  ): void {
    const mapped = (items ?? []).map((i: any) => ({
      factura: i.factura || '',
      cliente: i.cliente || '-',
      vence: i.vence || '',
      diasVencimiento: Number(i.diasVencimiento) || 0,
      monto: Number(i.monto) || 0,
    }));
    if (append && this._abonosCxcRowsCache.length > 0) {
      this._abonosCxcRowsCache = [...this._abonosCxcRowsCache, ...mapped];
    } else {
      this._abonosCxcRowsCache = mapped;
      if (this.abonosCxcGridApi) {
        this.abonosCxcGridApi.paginationGoToFirstPage();
      }
    }
    this.abonosCxcRows = [...this._abonosCxcRowsCache];
    this._totalAbonosCxcCache = totales?.monto || 0;
    if (this.abonosCxcGridApi) {
      this.abonosCxcGridApi.setRowData(this.abonosCxcRows);
    }
    this.aplicarPinnedTotalesAbonosCxc(totales);
  }

  private aplicarPinnedTotalesAbonosCxc(totales: AbonosCxcTotales): void {
    if ((totales?.monto || 0) !== 0) {
      this.pinnedBottomRowDataAbonosCxc = [
        {
          cliente: 'Total',
          factura: '',
          vence: '',
          diasVencimiento: null,
          monto: totales.monto,
        },
      ];
    } else {
      this.pinnedBottomRowDataAbonosCxc = [];
    }
    if (this.abonosCxcGridApi) {
      this.abonosCxcGridApi.setPinnedBottomRowData(
        this.pinnedBottomRowDataAbonosCxc,
      );
    }
  }

  private wireAbonosCxpStreams(): void {
    this.abonosCxpPage$
      .pipe(
        switchMap(({ offset, q, append }) => {
          this.abonosCxpAppendPending = append;
          this.abonosCxpLoading = true;
          this.cdr.markForCheck();
          return this.dashboardDataService.obtenerAbonosCxpPagina(
            this.getFiltrosCashflowDetalle(),
            {
              limite: this.abonosCxpPageSize,
              offset,
              q: q || undefined,
            },
          );
        }),
        takeUntil(this.destroy$),
      )
      .subscribe({
        next: (page) => {
          this.abonosCxpOffset = page.offset;
          this.abonosCxpTotal = page.total;
          this.abonosCxpTotalesApi = page.totales;
          const append =
            this.abonosCxpAppendPending && (page.items?.length ?? 0) > 0;
          this.applyAbonosCxpPageRows(page.items, page.totales, append);
          this.abonosCxpAppendPending = false;
          this.abonosCxpListo = true;
          this.abonosCxpLoading = false;
          this.cdr.markForCheck();
        },
        error: () => {
          this.abonosCxpAppendPending = false;
          this.abonosCxpLoading = false;
          this.abonosCxpListo = true;
          this.cdr.markForCheck();
        },
      });

    this.quickFilterAbonosCxp$
      .pipe(
        debounceTime(300),
        distinctUntilChanged(),
        takeUntil(this.destroy$),
      )
      .subscribe(() => {
        this.cargarAbonosCxpPagina(0);
      });
  }

  cargarAbonosCxpPagina(offset: number, append = false): void {
    this.abonosCxpPage$.next({
      offset: Math.max(0, offset),
      q: (this.quickFilterAbonosCxp || '').trim(),
      append,
    });
  }

  private maybeLoadMoreAbonosCxpFromGrid(): void {
    if (
      !this.abonosCxpGridApi ||
      this.abonosCxpLoading ||
      !this.abonosCxpListo
    ) {
      return;
    }
    const loaded = this._abonosCxpRowsCache.length;
    if (loaded >= this.abonosCxpTotal) return;
    const pageSize = this.abonosCxpGridApi.paginationGetPageSize() || 25;
    const currentPage = this.abonosCxpGridApi.paginationGetCurrentPage();
    const lastLoadedPage = Math.max(0, Math.ceil(loaded / pageSize) - 1);
    if (currentPage >= lastLoadedPage) {
      this.cargarAbonosCxpPagina(loaded, true);
    }
  }

  private applyAbonosCxpPageRows(
    items: any[],
    totales: AbonosCxpTotales,
    append = false,
  ): void {
    const mapped = (items ?? []).map((i: any) => ({
      factura: i.factura || '',
      proveedor: i.proveedor || '-',
      vence: i.vence || '',
      diasVencimiento: Number(i.diasVencimiento) || 0,
      monto: Number(i.monto) || 0,
    }));
    if (append && this._abonosCxpRowsCache.length > 0) {
      this._abonosCxpRowsCache = [...this._abonosCxpRowsCache, ...mapped];
    } else {
      this._abonosCxpRowsCache = mapped;
      if (this.abonosCxpGridApi) {
        this.abonosCxpGridApi.paginationGoToFirstPage();
      }
    }
    this.abonosCxpRows = [...this._abonosCxpRowsCache];
    this._totalAbonosCxpCache = totales?.monto || 0;
    if (this.abonosCxpGridApi) {
      this.abonosCxpGridApi.setRowData(this.abonosCxpRows);
    }
    this.aplicarPinnedTotalesAbonosCxp(totales);
  }

  private aplicarPinnedTotalesAbonosCxp(totales: AbonosCxpTotales): void {
    if ((totales?.monto || 0) !== 0) {
      this.pinnedBottomRowDataAbonosCxp = [
        {
          proveedor: 'Total',
          factura: '',
          vence: '',
          diasVencimiento: null,
          monto: totales.monto,
        },
      ];
    } else {
      this.pinnedBottomRowDataAbonosCxp = [];
    }
    if (this.abonosCxpGridApi) {
      this.abonosCxpGridApi.setPinnedBottomRowData(
        this.pinnedBottomRowDataAbonosCxp,
      );
    }
  }

  private _bloquearFiltros(): void {
    this.filtrosLocked = true;
    this.cdr.markForCheck();
    // Safety net: desbloquear tras 8s aunque no lleguen datos
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

  formatCurrency(value: number): string {
    if (value === null || value === undefined) {
      value = 0;
    }
    return formatEmpresaCurrency(value, this.apiService.auth_user()?.empresa);
  }

  private getPivotCurrencyFormats() {
    return [{
      name: 'currency',
      currencySymbol: getEmpresaCurrencySymbol(this.apiService.auth_user()?.empresa),
      currencySymbolAlign: 'left',
      decimalPlaces: 2,
      thousandsSeparator: ',',
    }];
  }

  ventasColumns = [
    {
      prop: 'cliente',
      name: 'Cliente',
      size: 200,
      sortable: true,
      filterable: true
    },
    {
      prop: 'factura',
      name: '# factura',
      size: 100,
      sortable: true,
      filterable: true
    },
    {
      prop: 'monto',
      name: 'Monto',
      size: 150,
      sortable: true,
      filterable: true
    }
  ];

  gastosColumns = [
    {
      prop: 'proveedor',
      name: 'Proveedor',
      size: 200,
      sortable: true,
      filterable: true
    },
    {
      prop: 'factura',
      name: '# factura',
      size: 100,
      sortable: true,
      filterable: true
    },
    {
      prop: 'monto',
      name: 'Monto',
      size: 150,
      sortable: true,
      filterable: true
    }
  ];

  onBusquedaGastosChange(): void {
  }

  // ─── Quick-filter handlers ─────────────────────────────────────────────────

  onQuickFilterVentasChange(): void {
    this.quickFilterVentas$.next((this.quickFilterVentas || '').trim());
  }

  onQuickFilterGastosChange(): void {
    this.quickFilterGastos$.next((this.quickFilterGastos || '').trim());
  }

  onQuickFilterCobrar30Change(): void {
    // El binding [quickFilterText] en el template propaga el valor automáticamente
  }

  onQuickFilterPagar30Change(): void {
    // El binding [quickFilterText] en el template propaga el valor automáticamente
  }

  onQuickFilterAbonosCxcChange(): void {
    this.quickFilterAbonosCxc$.next((this.quickFilterAbonosCxc || '').trim());
  }

  onQuickFilterAbonosCxpChange(): void {
    this.quickFilterAbonosCxp$.next((this.quickFilterAbonosCxp || '').trim());
  }

  // ─── Limpiar filtros ───────────────────────────────────────────────────────

  limpiarFiltrosVentas(): void {
    this.quickFilterVentas = '';
    if (this.ventasGridApi) {
      this.ventasGridApi.setFilterModel(null);
    }
    this.cargarCashflowVentasPagina(0);
  }

  limpiarFiltrosGastos(): void {
    this.quickFilterGastos = '';
    if (this.gastosGridApi) {
      this.gastosGridApi.setFilterModel(null);
    }
    this.cargarCashflowGastosPagina(0);
  }

  limpiarFiltrosCobrar30(): void {
    this.quickFilterCobrar30 = '';
    if (this.cobrar30GridApi) {
      this.cobrar30GridApi.setFilterModel(null);
    }
  }

  limpiarFiltrosPagar30(): void {
    this.quickFilterPagar30 = '';
    if (this.pagar30GridApi) {
      this.pagar30GridApi.setFilterModel(null);
    }
  }

  limpiarFiltrosAbonosCxc(): void {
    this.quickFilterAbonosCxc = '';
    if (this.abonosCxcGridApi) {
      this.abonosCxcGridApi.setFilterModel(null);
    }
    this.cargarAbonosCxcPagina(0);
  }

  limpiarFiltrosAbonosCxp(): void {
    this.quickFilterAbonosCxp = '';
    if (this.abonosCxpGridApi) {
      this.abonosCxpGridApi.setFilterModel(null);
    }
    this.cargarAbonosCxpPagina(0);
  }

  // ─── Exportar CSV ──────────────────────────────────────────────────────────

  exportarVentasCSV(): void {
    if (this.ventasGridApi) {
      const fecha = new Date().toISOString().split('T')[0];
      this.ventasGridApi.exportDataAsCsv({ fileName: `ventas-mes-${fecha}.csv` });
    } else {
      alert('No hay datos de ventas para exportar');
    }
  }

  exportarGastosCSV(): void {
    if (this.gastosGridApi) {
      const fecha = new Date().toISOString().split('T')[0];
      this.gastosGridApi.exportDataAsCsv({ fileName: `gastos-mes-${fecha}.csv` });
    } else {
      alert('No hay datos de gastos para exportar');
    }
  }

  exportarCobrar30CSV(): void {
    if (this.cobrar30GridApi) {
      const fecha = new Date().toISOString().split('T')[0];
      this.cobrar30GridApi.exportDataAsCsv({ fileName: `cxc-30-dias-${fecha}.csv` });
    } else {
      alert('No hay datos de CXC para exportar');
    }
  }

  exportarPagar30CSV(): void {
    if (this.pagar30GridApi) {
      const fecha = new Date().toISOString().split('T')[0];
      this.pagar30GridApi.exportDataAsCsv({ fileName: `cxp-30-dias-${fecha}.csv` });
    } else {
      alert('No hay datos de CXP para exportar');
    }
  }

  private exportarComoExcel(api: GridApi | undefined, filename: string): void {
    if (!api) { alert('No hay datos para exportar'); return; }
    const csvFilename = filename.replace(/\.xlsx$/i, '.csv');
    api.exportDataAsCsv({ fileName: csvFilename });
  }

  exportarVentasExcel(): void {
    if (this.ventasExporting) return;
    this.ventasExporting = true;
    this.cdr.markForCheck();
    this.dashboardDataService
      .obtenerCashflowVentasCompleto(this.getFiltrosCashflowDetalle(), {
        q: (this.quickFilterVentas || '').trim() || undefined,
      })
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: (page) => {
          const fecha = new Date().toISOString().split('T')[0];
          const cols = this.ventasColumnDefs.filter((c) => c.field);
          const header = cols
            .map((c) => this.csvEscape(String(c.headerName || c.field)))
            .join(',');
          const lines = (page.items ?? []).map((item: any) =>
            cols
              .map((c) => {
                const raw = item[c.field || ''];
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
          a.download = `ventas-mes-${fecha}.csv`;
          a.click();
          URL.revokeObjectURL(url);
          this.ventasExporting = false;
          this.cdr.markForCheck();
        },
        error: () => {
          this.ventasExporting = false;
          this.cdr.markForCheck();
        },
      });
  }

  exportarGastosExcel(): void {
    if (this.gastosExporting) return;
    this.gastosExporting = true;
    this.cdr.markForCheck();
    this.dashboardDataService
      .obtenerCashflowGastosCompleto(this.getFiltrosCashflowDetalle(), {
        q: (this.quickFilterGastos || '').trim() || undefined,
      })
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: (page) => {
          const fecha = new Date().toISOString().split('T')[0];
          const cols = this.gastosColumnDefs.filter((c) => c.field);
          const header = cols
            .map((c) => this.csvEscape(String(c.headerName || c.field)))
            .join(',');
          const lines = (page.items ?? []).map((item: any) =>
            cols
              .map((c) => {
                const raw = item[c.field || ''];
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
          a.download = `gastos-mes-${fecha}.csv`;
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

  exportarCobrar30Excel(): void {
    const fecha = new Date().toISOString().split('T')[0];
    this.exportarComoExcel(this.cobrar30GridApi, `cxc-30-dias-${fecha}.xlsx`);
  }

  exportarPagar30Excel(): void {
    const fecha = new Date().toISOString().split('T')[0];
    this.exportarComoExcel(this.pagar30GridApi, `cxp-30-dias-${fecha}.xlsx`);
  }

  exportarAbonosCxcExcel(): void {
    if (this.abonosCxcExporting) return;
    this.abonosCxcExporting = true;
    this.cdr.markForCheck();
    this.dashboardDataService
      .obtenerAbonosCxcCompleto(this.getFiltrosCashflowDetalle(), {
        q: (this.quickFilterAbonosCxc || '').trim() || undefined,
      })
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: (page) => {
          const fecha = new Date().toISOString().split('T')[0];
          const cols = this.abonosCxcColumnDefs.filter((c) => c.field);
          const header = cols
            .map((c) => this.csvEscape(String(c.headerName || c.field)))
            .join(',');
          const lines = (page.items ?? []).map((item: any) =>
            cols
              .map((c) => {
                const raw = item[c.field || ''];
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
          a.download = `abonos-cxc-${fecha}.csv`;
          a.click();
          URL.revokeObjectURL(url);
          this.abonosCxcExporting = false;
          this.cdr.markForCheck();
        },
        error: () => {
          this.abonosCxcExporting = false;
          this.cdr.markForCheck();
        },
      });
  }

  exportarAbonosCxpExcel(): void {
    if (this.abonosCxpExporting) return;
    this.abonosCxpExporting = true;
    this.cdr.markForCheck();
    this.dashboardDataService
      .obtenerAbonosCxpCompleto(this.getFiltrosCashflowDetalle(), {
        q: (this.quickFilterAbonosCxp || '').trim() || undefined,
      })
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: (page) => {
          const fecha = new Date().toISOString().split('T')[0];
          const cols = this.abonosCxpColumnDefs.filter((c) => c.field);
          const header = cols
            .map((c) => this.csvEscape(String(c.headerName || c.field)))
            .join(',');
          const lines = (page.items ?? []).map((item: any) =>
            cols
              .map((c) => {
                const raw = item[c.field || ''];
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
          a.download = `abonos-cxp-${fecha}.csv`;
          a.click();
          URL.revokeObjectURL(url);
          this.abonosCxpExporting = false;
          this.cdr.markForCheck();
        },
        error: () => {
          this.abonosCxpExporting = false;
          this.cdr.markForCheck();
        },
      });
  }

  // ── WebDataRocks: Ventas ─────────────────────────────────────────────────

  onVentasPivotReady(pivot: any): void {
    this._ventasPivotInstance = pivot;
    // setTimeout(0): espera al siguiente tick para que WebDataRocks
    // termine su inicialización interna antes de llamar setReport()
    setTimeout(() => this.actualizarVentasPivot(), 0);
  }

  private actualizarVentasPivot(): void {
    if (!this._ventasPivotInstance) return;
    const filas: any[] = this._ventasRowsCache.map(v => ({
      Cliente: v.cliente || '',
      Factura: v.factura || '',
      Monto: typeof v.monto === 'number' ? v.monto : parseFloat(v.monto) || 0,
    }));
    this._ventasPivotInstance.setReport({
      dataSource: { data: filas },
      slice: {
        rows: [{ uniqueName: 'Cliente' }, { uniqueName: 'Factura' }],
        columns: [{ uniqueName: '[Measures]' }],
        measures: [{ uniqueName: 'Monto', aggregation: 'sum', format: 'currency', caption: 'Monto' }]
      },
      formats: this.getPivotCurrencyFormats(),
      options: { grid: { showGrandTotals: 'on', showTotals: 'on' } }
    });
  }

  exportarVentasPivot(): void {
    if (this._ventasPivotInstance) {
      this._ventasPivotInstance.exportTo('csv', { filename: `ventas-mes-${new Date().toISOString().split('T')[0]}` });
    } else {
      alert('No hay datos de ventas para exportar');
    }
  }

  // ── WebDataRocks: Gastos ─────────────────────────────────────────────────

  onGastosPivotReady(pivot: any): void {
    this._gastosPivotInstance = pivot;
    setTimeout(() => this.actualizarGastosPivot(), 0);
  }

  private actualizarGastosPivot(): void {
    if (!this._gastosPivotInstance) return;
    const filas: any[] = this._gastosRowsCache.map(g => ({
      Proveedor: g.proveedor || '',
      Factura: g.factura || '',
      Monto: typeof g.monto === 'number' ? g.monto : parseFloat(g.monto) || 0,
    }));
    this._gastosPivotInstance.setReport({
      dataSource: { data: filas },
      slice: {
        rows: [{ uniqueName: 'Proveedor' }, { uniqueName: 'Factura' }],
        columns: [{ uniqueName: '[Measures]' }],
        measures: [{ uniqueName: 'Monto', aggregation: 'sum', format: 'currency', caption: 'Monto' }]
      },
      formats: this.getPivotCurrencyFormats(),
      options: { grid: { showGrandTotals: 'on', showTotals: 'on' } }
    });
  }

  exportarGastosPivot(): void {
    if (this._gastosPivotInstance) {
      this._gastosPivotInstance.exportTo('csv', { filename: `gastos-mes-${new Date().toISOString().split('T')[0]}` });
    } else {
      alert('No hay datos de gastos para exportar');
    }
  }

  // ── WebDataRocks: CXC próximos 30 días ────────────────────────────────

  onCobrar30PivotReady(pivot: any): void {
    this._cobrar30PivotInstance = pivot;
    setTimeout(() => this.actualizarCobrar30Pivot(), 0);
  }

  private actualizarCobrar30Pivot(): void {
    if (!this._cobrar30PivotInstance) return;
    const filas: any[] = this._cobrar30RowsCache.map(r => ({
      Cliente: r.cliente || '',
      Factura: r.factura || '',
      Vence: r.vence || '',
      DiasVenc: typeof r.diasVencimiento === 'number' ? r.diasVencimiento : 0,
      Monto: typeof r.monto === 'number' ? r.monto : parseFloat(r.monto) || 0,
    }));
    this._cobrar30PivotInstance.setReport({
      dataSource: { data: filas },
      slice: {
        rows: [{ uniqueName: 'Cliente' }, { uniqueName: 'Factura' }, { uniqueName: 'Vence' }, { uniqueName: 'DiasVenc', caption: 'Días venc.' }],
        columns: [{ uniqueName: '[Measures]' }],
        measures: [{ uniqueName: 'Monto', aggregation: 'sum', format: 'currency', caption: 'Monto' }]
      },
      formats: this.getPivotCurrencyFormats(),
      options: { grid: { showGrandTotals: 'on', showTotals: 'on' } }
    });
  }

  exportarCobrar30Pivot(): void {
    if (this._cobrar30PivotInstance) {
      this._cobrar30PivotInstance.exportTo('csv', { filename: `cxc-30-dias-${new Date().toISOString().split('T')[0]}` });
    } else {
      alert('No hay datos de CXC para exportar');
    }
  }

  // ── WebDataRocks: CXP próximos 30 días ────────────────────────────────

  onPagar30PivotReady(pivot: any): void {
    this._pagar30PivotInstance = pivot;
    setTimeout(() => this.actualizarPagar30Pivot(), 0);
  }

  private actualizarPagar30Pivot(): void {
    if (!this._pagar30PivotInstance) return;
    const filas: any[] = this._pagar30RowsCache.map(r => ({
      Proveedor: r.proveedor || '',
      Factura: r.factura || '',
      Vence: r.vence || '',
      DiasVenc: typeof r.diasVencimiento === 'number' ? r.diasVencimiento : 0,
      Monto: typeof r.monto === 'number' ? r.monto : parseFloat(r.monto) || 0,
    }));
    this._pagar30PivotInstance.setReport({
      dataSource: { data: filas },
      slice: {
        rows: [{ uniqueName: 'Proveedor' }, { uniqueName: 'Factura' }, { uniqueName: 'Vence' }, { uniqueName: 'DiasVenc', caption: 'Días venc.' }],
        columns: [{ uniqueName: '[Measures]' }],
        measures: [{ uniqueName: 'Monto', aggregation: 'sum', format: 'currency', caption: 'Monto' }]
      },
      formats: this.getPivotCurrencyFormats(),
      options: { grid: { showGrandTotals: 'on', showTotals: 'on' } }
    });
  }

  exportarPagar30Pivot(): void {
    if (this._pagar30PivotInstance) {
      this._pagar30PivotInstance.exportTo('csv', { filename: `cxp-30-dias-${new Date().toISOString().split('T')[0]}` });
    } else {
      alert('No hay datos de CXP para exportar');
    }
  }

  exportarVentas(): void {
    if (this.ventasGridApi) {
      const fecha = new Date().toISOString().split('T')[0];
      this.ventasGridApi.exportDataAsCsv({
        fileName: `ventas-mes-${fecha}.csv`
      });
    } else if (this.ventasRows.length > 0) {
      const fecha = new Date().toISOString().split('T')[0];
      this.exportarACSV(this.ventasRows, this.ventasColumns, `ventas-mes-${fecha}.csv`);
    } else {
      alert('No hay datos de ventas para exportar');
    }
  }

  exportarGastos(): void {
    if (this.gastosGridApi) {
      const fecha = new Date().toISOString().split('T')[0];
      this.gastosGridApi.exportDataAsCsv({
        fileName: `gastos-mes-${fecha}.csv`
      });
    } else if (this.gastosRows.length > 0) {
      const fecha = new Date().toISOString().split('T')[0];
      this.exportarACSV(this.gastosRows, this.gastosColumns, `gastos-mes-${fecha}.csv`);
    } else {
      alert('No hay datos de gastos para exportar');
    }
  }

  exportarACSV(data: any[], columns: any[], filename: string): void {
    if (data.length === 0) {
      alert('No hay datos para exportar');
      return;
    }

    const headers = columns.map(col => col.name).join(',');
    const rows = data.map(row => {
      return columns.map(col => {
        const value = row[col.prop] || '';
        const stringValue = value.toString();
        if (stringValue.includes(',') || stringValue.includes('"') || stringValue.includes('\n')) {
          return `"${stringValue.replace(/"/g, '""')}"`;
        }
        return stringValue;
      }).join(',');
    });

    const BOM = '\uFEFF';
    const csvContent = BOM + [headers, ...rows].join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);

    link.setAttribute('href', url);
    link.setAttribute('download', filename);
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
  }

  // Columnas para cuentas por cobrar a 30 días
  cobrar30Columns = [
    {
      prop: 'factura',
      name: '# factura',
      size: 100,
      sortable: true,
      filterable: true
    },
    {
      prop: 'cliente',
      name: 'Cliente',
      size: 200,
      sortable: true,
      filterable: true
    },
    {
      prop: 'vence',
      name: 'Vence',
      size: 120,
      sortable: true,
      filterable: true
    },
    {
      prop: 'diasVencimiento',
      name: 'Días vencimiento',
      size: 150,
      sortable: true,
      filterable: true
    }
  ];

  // Columnas para cuentas por pagar a 30 días
  pagar30Columns = [
    {
      prop: 'factura',
      name: '# factura',
      size: 100,
      sortable: true,
      filterable: true
    },
    {
      prop: 'proveedor',
      name: 'Proveedor',
      size: 200,
      sortable: true,
      filterable: true
    },
    {
      prop: 'vence',
      name: 'Vence',
      size: 120,
      sortable: true,
      filterable: true
    },
    {
      prop: 'diasVencimiento',
      name: 'Días vencimiento',
      size: 150,
      sortable: true,
      filterable: true
    }
  ];

  get cobrar30Rows(): any[] {
    return this.filtrarCobrar30(this._cobrar30RowsCache);
  }

  get pagar30Rows(): any[] {
    return this.filtrarPagar30(this._pagar30RowsCache);
  }

  filtrarCobrar30(rows: any[]): any[] {
    if (!this.busquedaCobrar30) return rows;
    const busqueda = this.busquedaCobrar30.toLowerCase();
    return rows.filter(row =>
      (row.factura || '').toLowerCase().includes(busqueda) ||
      (row.cliente || '').toLowerCase().includes(busqueda) ||
      (row.vence || '').toLowerCase().includes(busqueda) ||
      (row.diasVencimiento || '').toString().includes(busqueda)
    );
  }

  filtrarPagar30(rows: any[]): any[] {
    if (!this.busquedaPagar30) return rows;
    const busqueda = this.busquedaPagar30.toLowerCase();
    return rows.filter(row =>
      (row.factura || '').toLowerCase().includes(busqueda) ||
      (row.proveedor || '').toLowerCase().includes(busqueda) ||
      (row.vence || '').toLowerCase().includes(busqueda) ||
      (row.diasVencimiento || '').toString().includes(busqueda)
    );
  }

  onBusquedaCobrar30Change(): void {
  }

  onBusquedaPagar30Change(): void {
  }

  exportarCobrar30(): void {
    if (this.cobrar30GridApi) {
      const fecha = new Date().toISOString().split('T')[0];
      this.cobrar30GridApi.exportDataAsCsv({ fileName: `cuentas-por-cobrar-30-dias-${fecha}.csv` });
    } else if (this.cobrar30Rows.length > 0) {
      const fecha = new Date().toISOString().split('T')[0];
      this.exportarACSV(this.cobrar30Rows, this.cobrar30Columns, `cuentas-por-cobrar-30-dias-${fecha}.csv`);
    } else {
      alert('No hay datos para exportar');
    }
  }

  exportarPagar30(): void {
    if (this.pagar30GridApi) {
      const fecha = new Date().toISOString().split('T')[0];
      this.pagar30GridApi.exportDataAsCsv({ fileName: `cuentas-por-pagar-30-dias-${fecha}.csv` });
    } else if (this.pagar30Rows.length > 0) {
      const fecha = new Date().toISOString().split('T')[0];
      this.exportarACSV(this.pagar30Rows, this.pagar30Columns, `cuentas-por-pagar-30-dias-${fecha}.csv`);
    } else {
      alert('No hay datos para exportar');
    }
  }

  get totalCobrar30(): number {
    return this._totalCobrar30Cache;
  }

  get totalPagar30(): number {
    return this._totalPagar30Cache;
  }

  get totalAbonosCxc(): number {
    return this._totalAbonosCxcCache;
  }

  get totalAbonosCxp(): number {
    return this._totalAbonosCxpCache;
  }

  /** Totales pinned desde API (`totales`); no sumar solo la página cargada. */
  recalcularTotalesVentas(): void {
    this.aplicarPinnedTotalesVentas(this.ventasTotales);
  }

  onFilterChangedVentas(): void {
    this.recalcularTotalesVentas();
    this.cdr.markForCheck();
  }

  /** Totales pinned desde API (`totales`); no sumar solo la página cargada. */
  recalcularTotalesGastos(): void {
    this.aplicarPinnedTotalesGastos(this.gastosTotales);
  }

  onFilterChangedGastos(): void {
    this.recalcularTotalesGastos();
    this.cdr.markForCheck();
  }

  /** Totales pinned desde API (`totales`); no sumar solo la página cargada. */
  recalcularTotalesAbonosCxc(): void {
    this.aplicarPinnedTotalesAbonosCxc(this.abonosCxcTotalesApi);
  }

  onFilterChangedAbonosCxc(): void {
    this.recalcularTotalesAbonosCxc();
    this.cdr.markForCheck();
  }

  /** Totales pinned desde API (`totales`); no sumar solo la página cargada. */
  recalcularTotalesAbonosCxp(): void {
    this.aplicarPinnedTotalesAbonosCxp(this.abonosCxpTotalesApi);
  }

  onFilterChangedAbonosCxp(): void {
    this.recalcularTotalesAbonosCxp();
    this.cdr.markForCheck();
  }

  onVentasGridReady(params: any): void {
    this.ventasGridApi = params.api;
    if (this.ventasRows.length > 0) {
      params.api.setRowData(this.ventasRows);
    }
    try { params.api.sizeColumnsToFit(); } catch { }
    this.recalcularTotalesVentas();
  }

  onGastosGridReady(params: any): void {
    this.gastosGridApi = params.api;
    if (this.gastosRows.length > 0) {
      params.api.setRowData(this.gastosRows);
    }
    try { params.api.sizeColumnsToFit(); } catch { }
    this.recalcularTotalesGastos();
  }

  onAbonosCxcGridReady(params: any): void {
    this.abonosCxcGridApi = params.api;
    if (this.abonosCxcRows.length > 0) {
      params.api.setRowData(this.abonosCxcRows);
    }
    try { params.api.sizeColumnsToFit(); } catch { }
    this.recalcularTotalesAbonosCxc();
  }

  onAbonosCxpGridReady(params: any): void {
    this.abonosCxpGridApi = params.api;
    if (this.abonosCxpRows.length > 0) {
      params.api.setRowData(this.abonosCxpRows);
    }
    try { params.api.sizeColumnsToFit(); } catch { }
    this.recalcularTotalesAbonosCxp();
  }

  /**
   * TrackBy functions para optimización de *ngFor
   */
  trackByIndex(index: number, item: any): number {
    return index;
  }

  trackById(index: number, item: any): string | number {
    return item.id || index;
  }

  trackByName(index: number, item: any): string | number {
    return item.nombre || item.name || index;
  }
}
