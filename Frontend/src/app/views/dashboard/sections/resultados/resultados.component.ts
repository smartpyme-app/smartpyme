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
import { DashboardDataService } from '../../services/dashboard-data.service';
import { CashFlowItem } from '../../models/chart-config.model';


import { WebdatarocksComponent } from '@webdatarocks/ngx-webdatarocks';
import { ApiService } from '@services/api.service';
import { DropdownMultiFiltroSelection } from '../../components/dropdown-multi-filtro/dropdown-multi-filtro.component';
import { DashboardFiltrosCatalogoService } from '../../services/dashboard-filtros-catalogo.service';
import { ColDef, GridOptions, GridApi, themeQuartz, AllCommunityModule } from 'ag-grid-community';
import { formatEmpresaCurrency, getEmpresaCurrencySymbol } from '@helpers/currency-format.helper';

import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { AgGridModule } from 'ag-grid-angular';
import { WebdatarocksPivotModule } from '@webdatarocks/ngx-webdatarocks';
import { SharedModule } from '@shared/shared.module';
import { PipesModule } from '@pipes/pipes.module';
import { DropdownMultiFiltroComponent } from '../../components/dropdown-multi-filtro/dropdown-multi-filtro.component';
import { BarChartComponent } from '../../components/bar-chart/bar-chart.component';
import { AccountsListComponent } from '../../components/accounts-list/accounts-list.component';
import { ChartCardComponent } from '../../components/chart-card/chart-card.component';
import { BudgetCardComponent } from '../../components/budget-card/budget-card.component';

@Component({
  selector: 'app-resultados',
  templateUrl: './resultados.component.html',
  styleUrls: ['./resultados.component.css'],
  changeDetection: ChangeDetectionStrategy.OnPush,
  standalone: true,
  imports: [
    CommonModule,
    FormsModule,
    AgGridModule,
    WebdatarocksPivotModule,
    SharedModule,
    PipesModule,
    DropdownMultiFiltroComponent,
    BarChartComponent,
    AccountsListComponent,
    ChartCardComponent,
    BudgetCardComponent
  ]
})
export class ResultadosComponent implements OnInit, OnChanges, OnDestroy {
  public miTema = themeQuartz;
  public modules: any[] = [AllCommunityModule];

  @Input() datos: any = {};
  @Output() filtrosCambiados = new EventEmitter<any>();

  // Propiedades cacheadas para evitar recálculos
  private _ventasRowsCache: any[] = [];
  private _gastosRowsCache: any[] = [];
  private _cobrar30RowsCache: any[] = [];
  private _pagar30RowsCache: any[] = [];
  private _totalCobrar30Cache: number = 0;
  private _totalPagar30Cache: number = 0;

  private _abonosCxcRowsCache: any[] = [];
  private _abonosCxpRowsCache: any[] = [];
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
  cobrar30Plugins = [];
  pagar30Plugins = [];

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

    this._ventasRowsCache = this.datos?.cashflow?.ventas || [];
    this._gastosRowsCache = this.datos?.cashflow?.gastos || [];

    const nVentas = this._ventasRowsCache.length;
    const nGastos = this._gastosRowsCache.length;

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

    // 👇 NUEVO: Calcular cache de abonos
    this._abonosCxcRowsCache = this.datos?.abonos?.cxc || [];
    this._abonosCxpRowsCache = this.datos?.abonos?.cxp || [];

    this._totalAbonosCxcCache = this._abonosCxcRowsCache.reduce(
      (acc, curr) => acc + (curr.monto || 0),
      0
    );
    this._totalAbonosCxpCache = this._abonosCxpRowsCache.reduce(
      (acc, curr) => acc + (curr.monto || 0),
      0
    );

    // Recalcular filas fijas de totales
    this.recalcularTotalesVentas();
    this.recalcularTotalesGastos();
    this.recalcularTotalesAbonosCxc();
    this.recalcularTotalesAbonosCxp();
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
      paginationPageSize: 10,
      paginationPageSizeSelector: [10, 20, 50, 100],
      getRowClass: getRowClassCallback,
      onGridReady: (params: any) => {
        this.ventasGridApi = params.api;
        sizeToFit(params.api);
      },
      onFirstDataRendered: (params: any) => sizeToFit(params.api),
      onGridSizeChanged: (params: any) => sizeToFit(params.api),
      onFilterChanged: () => {
        this.onFilterChangedVentas();
      }
    };

    this.gastosGridOptions = {
      defaultColDef: defaultDefs,
      enableCellTextSelection: true,
      ensureDomOrder: true,
      pagination: true,
      paginationPageSize: 10,
      paginationPageSizeSelector: [10, 20, 50, 100],
      getRowClass: getRowClassCallback,
      onGridReady: (params: any) => {
        this.gastosGridApi = params.api;
        sizeToFit(params.api);
      },
      onFirstDataRendered: (params: any) => sizeToFit(params.api),
      onGridSizeChanged: (params: any) => sizeToFit(params.api),
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
      paginationPageSizeSelector: [10, 20, 50, 100],
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
      paginationPageSizeSelector: [10, 20, 50, 100],
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
      paginationPageSize: 10,
      paginationPageSizeSelector: [10, 20, 50, 100],
      getRowClass: getRowClassCallback,
      onGridReady: (params: any) => {
        this.abonosCxcGridApi = params.api;
        sizeToFit(params.api);
      },
      onFirstDataRendered: (params: any) => sizeToFit(params.api),
      onGridSizeChanged: (params: any) => sizeToFit(params.api),
      onFilterChanged: () => {
        this.onFilterChangedAbonosCxc();
      }
    };

    this.abonosCxpGridOptions = {
      defaultColDef: defaultDefs,
      enableCellTextSelection: true,
      ensureDomOrder: true,
      pagination: true,
      paginationPageSize: 10,
      paginationPageSizeSelector: [10, 20, 50, 100],
      getRowClass: getRowClassCallback,
      onGridReady: (params: any) => {
        this.abonosCxpGridApi = params.api;
        sizeToFit(params.api);
      },
      onFirstDataRendered: (params: any) => sizeToFit(params.api),
      onGridSizeChanged: (params: any) => sizeToFit(params.api),
      onFilterChanged: () => {
        this.onFilterChangedAbonosCxp();
      }
    };
  }

  ngOnInit(): void {
    this.configurarAGGrid();

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
      }
      this.cdr.markForCheck();
    }, 100);
  }

  ngOnDestroy(): void {
    this.dashboardDataService.guardarFiltrosUI('Resultados', {
      anioSeleccionado: this.anioSeleccionado,
      mesFlujoEfectivo: this.mesFlujoEfectivo,
      sucursalesSeleccionadas: this.sucursalesSeleccionadas,
      sucursalesTodasImplicitas: this.sucursalesTodasImplicitas
    });
  }

  /**
   * Siempre inicia en "Todo el año" (null).
   */
  private aplicarDefectoMesFlujoEfectivo(): void {
    this.mesFlujoEfectivo = null;
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
        // Recalcular cache cuando los datos cambien
        this.recalcularRowsCache();
        this.actualizarVentasPivot();
        this.actualizarGastosPivot();
        this.actualizarCobrar30Pivot();
        this.actualizarPagar30Pivot();
        this.cdr.markForCheck();
      }
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

  get ventasRows(): any[] {
    return this.filtrarVentas(this._ventasRowsCache);
  }

  get gastosRows(): any[] {
    return this.filtrarGastos(this._gastosRowsCache);
  }

  filtrarVentas(rows: any[]): any[] {
    if (!this.busquedaVentas) return rows;
    const busqueda = this.busquedaVentas.toLowerCase();
    return rows.filter(row =>
      (row.cliente || '').toLowerCase().includes(busqueda) ||
      (row.factura || '').toLowerCase().includes(busqueda) ||
      (row.monto || '').toLowerCase().includes(busqueda)
    );
  }

  filtrarGastos(rows: any[]): any[] {
    if (!this.busquedaGastos) return rows;
    const busqueda = this.busquedaGastos.toLowerCase();
    return rows.filter(row =>
      (row.proveedor || '').toLowerCase().includes(busqueda) ||
      (row.factura || '').toLowerCase().includes(busqueda) ||
      (row.monto || '').toLowerCase().includes(busqueda)
    );
  }

  onBusquedaGastosChange(): void {
  }

  // ─── Quick-filter handlers ─────────────────────────────────────────────────

  onQuickFilterVentasChange(): void {
    // El binding [quickFilterText] en el template propaga el valor automáticamente
  }

  onQuickFilterGastosChange(): void {
    // El binding [quickFilterText] en el template propaga el valor automáticamente
  }

  onQuickFilterCobrar30Change(): void {
    // El binding [quickFilterText] en el template propaga el valor automáticamente
  }

  onQuickFilterPagar30Change(): void {
    // El binding [quickFilterText] en el template propaga el valor automáticamente
  }

  onQuickFilterAbonosCxcChange(): void {
    // El binding [quickFilterText] en el template propaga el valor automáticamente
  }

  onQuickFilterAbonosCxpChange(): void {
    // El binding [quickFilterText] en el template propaga el valor automáticamente
  }

  // ─── Limpiar filtros ───────────────────────────────────────────────────────

  limpiarFiltrosVentas(): void {
    this.quickFilterVentas = '';
    if (this.ventasGridApi) {
      this.ventasGridApi.setFilterModel(null);
    }
  }

  limpiarFiltrosGastos(): void {
    this.quickFilterGastos = '';
    if (this.gastosGridApi) {
      this.gastosGridApi.setFilterModel(null);
    }
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
  }

  limpiarFiltrosAbonosCxp(): void {
    this.quickFilterAbonosCxp = '';
    if (this.abonosCxpGridApi) {
      this.abonosCxpGridApi.setFilterModel(null);
    }
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
    const fecha = new Date().toISOString().split('T')[0];
    this.exportarComoExcel(this.ventasGridApi, `ventas-mes-${fecha}.xlsx`);
  }

  exportarGastosExcel(): void {
    const fecha = new Date().toISOString().split('T')[0];
    this.exportarComoExcel(this.gastosGridApi, `gastos-mes-${fecha}.xlsx`);
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
    const fecha = new Date().toISOString().split('T')[0];
    this.exportarComoExcel(this.abonosCxcGridApi, `abonos-cxc-${fecha}.xlsx`);
  }

  exportarAbonosCxpExcel(): void {
    const fecha = new Date().toISOString().split('T')[0];
    this.exportarComoExcel(this.abonosCxpGridApi, `abonos-cxp-${fecha}.xlsx`);
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

  get abonosCxcRows(): any[] {
    return this._abonosCxcRowsCache;
  }

  get abonosCxpRows(): any[] {
    return this._abonosCxpRowsCache;
  }

  get totalAbonosCxc(): number {
    return this._totalAbonosCxcCache;
  }

  get totalAbonosCxp(): number {
    return this._totalAbonosCxpCache;
  }

  recalcularTotalesVentas(): void {
    let total = 0;
    if (this.ventasGridApi && !this.ventasGridApi.isDestroyed()) {
      this.ventasGridApi.forEachNodeAfterFilter((node: any) => {
        if (node.data) {
          total += (node.data.monto || 0);
        }
      });
    } else {
      total = (this.datos?.cashflow?.ventas || []).reduce((acc: number, curr: any) => acc + (curr.monto || 0), 0);
    }
    this.pinnedBottomRowDataVentas = [{
      cliente: 'Total',
      factura: '',
      monto: total
    }];
  }

  onFilterChangedVentas(): void {
    this.recalcularTotalesVentas();
    this.cdr.markForCheck();
  }

  recalcularTotalesGastos(): void {
    let total = 0;
    if (this.gastosGridApi && !this.gastosGridApi.isDestroyed()) {
      this.gastosGridApi.forEachNodeAfterFilter((node: any) => {
        if (node.data) {
          total += (node.data.monto || 0);
        }
      });
    } else {
      total = (this.datos?.cashflow?.gastos || []).reduce((acc: number, curr: any) => acc + (curr.monto || 0), 0);
    }
    this.pinnedBottomRowDataGastos = [{
      proveedor: 'Total',
      factura: '',
      monto: total
    }];
  }

  onFilterChangedGastos(): void {
    this.recalcularTotalesGastos();
    this.cdr.markForCheck();
  }

  recalcularTotalesAbonosCxc(): void {
    let total = 0;
    if (this.abonosCxcGridApi && !this.abonosCxcGridApi.isDestroyed()) {
      this.abonosCxcGridApi.forEachNodeAfterFilter((node: any) => {
        if (node.data) {
          total += (node.data.monto || 0);
        }
      });
    } else {
      total = (this.datos?.abonos?.cxc || []).reduce((acc: number, curr: any) => acc + (curr.monto || 0), 0);
    }
    this.pinnedBottomRowDataAbonosCxc = [{
      cliente: 'Total',
      factura: '',
      vence: '',
      diasVencimiento: null,
      monto: total
    }];
  }

  onFilterChangedAbonosCxc(): void {
    this.recalcularTotalesAbonosCxc();
    this.cdr.markForCheck();
  }

  recalcularTotalesAbonosCxp(): void {
    let total = 0;
    if (this.abonosCxpGridApi && !this.abonosCxpGridApi.isDestroyed()) {
      this.abonosCxpGridApi.forEachNodeAfterFilter((node: any) => {
        if (node.data) {
          total += (node.data.monto || 0);
        }
      });
    } else {
      total = (this.datos?.abonos?.cxp || []).reduce((acc: number, curr: any) => acc + (curr.monto || 0), 0);
    }
    this.pinnedBottomRowDataAbonosCxp = [{
      proveedor: 'Total',
      factura: '',
      vence: '',
      diasVencimiento: null,
      monto: total
    }];
  }

  onFilterChangedAbonosCxp(): void {
    this.recalcularTotalesAbonosCxp();
    this.cdr.markForCheck();
  }

  onVentasGridReady(params: any): void {
    this.ventasGridApi = params.api;
    try { params.api.sizeColumnsToFit(); } catch { }
    this.recalcularTotalesVentas();
  }

  onGastosGridReady(params: any): void {
    this.gastosGridApi = params.api;
    try { params.api.sizeColumnsToFit(); } catch { }
    this.recalcularTotalesGastos();
  }

  onAbonosCxcGridReady(params: any): void {
    this.abonosCxcGridApi = params.api;
    try { params.api.sizeColumnsToFit(); } catch { }
    this.recalcularTotalesAbonosCxc();
  }

  onAbonosCxpGridReady(params: any): void {
    this.abonosCxpGridApi = params.api;
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
