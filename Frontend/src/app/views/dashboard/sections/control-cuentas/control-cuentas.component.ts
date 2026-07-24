import { Component, Input, OnInit, OnChanges, SimpleChanges, Output, EventEmitter, ViewChild, ChangeDetectorRef, ChangeDetectionStrategy, OnDestroy } from '@angular/core';
import { Subject } from 'rxjs';
import { debounceTime, distinctUntilChanged, switchMap, takeUntil } from 'rxjs/operators';
import { DashboardDataService } from '../../services/dashboard-data.service';
import { ColDef, GridOptions, GridApi, ColumnApi } from 'ag-grid-community';
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
import {
  DetalleCxcTotales,
  DetalleCxpTotales,
} from '../../services/cuentas-dashboard-data.service';

@Component({
  selector: 'app-control-cuentas',
  templateUrl: './control-cuentas.component.html',
  styleUrls: ['./control-cuentas.component.css'],
  changeDetection: ChangeDetectionStrategy.OnPush
})
export class ControlCuentasComponent implements OnInit, OnChanges, OnDestroy {
  @Input() datos: any = {};
  @Input() datosCompletos = false;
  @Output() filtrosCambiados = new EventEmitter<any>();

  get metricasCardsCxc(): MetricCard[] {
    const m = this.datos?.metricasCuentas || {};
    return [
      {
        title: 'Cuentas por cobrar',
        value: m.cuentasPorCobrarTotal || 0,
        type: 'currency'
      },
      {
        title: 'Cuentas por cobrar a 30 días',
        value: m.cuentasPorCobrar30Dias || 0,
        type: 'currency'
      },
      {
        title: 'Cuentas por cobrar a 60 días',
        value: m.cuentasPorCobrar60Dias || 0,
        type: 'currency'
      },
      {
        title: 'Cuentas por cobrar a 90 días',
        value: m.cuentasPorCobrar90Dias || 0,
        type: 'currency'
      }
    ];
  }

  get metricasCardsCxp(): MetricCard[] {
    const m = this.datos?.metricasCuentas || {};
    return [
      {
        title: 'Cuentas por pagar',
        value: m.cuentasPorPagarTotal || 0,
        type: 'currency'
      },
      {
        title: 'Cuentas por pagar a 30 días',
        value: m.cuentasPorPagar30Dias || 0,
        type: 'currency'
      },
      {
        title: 'Cuentas por pagar a 60 días',
        value: m.cuentasPorPagar60Dias || 0,
        type: 'currency'
      },
      {
        title: 'Cuentas por pagar a 90 días',
        value: m.cuentasPorPagar90Dias || 0,
        type: 'currency'
      }
    ];
  }

  @ViewChild('detalleCuentasGrid') detalleCuentasGrid: any;
  @ViewChild('resumenCuentasPagarGrid') resumenCuentasPagarGrid: any;

  // AG Grid configuration — CXC detalle (paginación lazy)
  detalleCuentasColumnDefs: ColDef[] = [];
  detalleCuentasGridOptions: GridOptions = {};
  resumenCuentasPagarColumnDefs: ColDef[] = [];
  resumenCuentasPagarGridOptions: GridOptions = {};
  pinnedBottomRowDataCxc: any[] = [];
  pinnedBottomRowDataCxp: any[] = [];
  private gridApi!: GridApi;
  private gridColumnApi!: ColumnApi;
  private resumenGridApi!: GridApi;
  private resumenGridColumnApi!: ColumnApi;
  quickFilterText: string = '';
  quickFilterTextResumen: string = '';
  cxcListo = false;
  cxcLoading = false;
  cxcExporting = false;
  readonly cxcPageSize = 50;
  cxcOffset = 0;
  cxcTotal = 0;
  cxcTotales: DetalleCxcTotales = {
    ventasConIVA: 0,
    montoAbonado: 0,
    saldoPendiente: 0,
  };
  cxpListo = false;
  cxpLoading = false;
  cxpExporting = false;
  readonly cxpPageSize = 50;
  cxpOffset = 0;
  cxpTotal = 0;
  cxpTotales: DetalleCxpTotales = {
    gastosTotalesConIVA: 0,
    totalAbonado: 0,
    saldoPendiente: 0,
  };
  private readonly destroy$ = new Subject<void>();
  private readonly cxcPage$ = new Subject<{
    offset: number;
    q: string;
    append: boolean;
  }>();
  private readonly quickFilterCxc$ = new Subject<string>();
  private cxcAppendPending = false;
  private readonly cxpPage$ = new Subject<{
    offset: number;
    q: string;
    append: boolean;
  }>();
  private readonly quickFilterCxp$ = new Subject<string>();
  private cxpAppendPending = false;

  anio: string = new Date().getFullYear().toString();
  mes: string = '';

  /** true mientras se espera la respuesta del servidor tras cambiar un filtro */
  filtrosLocked = false;
  private _filtrosLockTimeout: any = null;

  
  // Filtros adicionales (Cuentas por cobrar)
  mostrarFiltrosAdicionales: boolean = false;
  // Filtros adicionales (Cuentas por pagar)
  mostrarFiltrosAdicionalesPagar: boolean = false;

  /** Evita emitir al padre antes de tiempo (mismo patrón que Gastos). */
  private filtrosListosParaEmitir = false;

  /** Multi-select CXC */
  filtroCxcSucTodasImplicitas = true;
  filtroCxcSucSeleccionadas: string[] = [];
  filtroCxcCliTodasImplicitas = true;
  filtroCxcCliSeleccionadas: string[] = [];
  filtroCxcVigTodasImplicitas = true;
  filtroCxcVigSeleccionadas: string[] = [];

  /** Multi-select CXP */
  filtroCxpProvTodasImplicitas = true;
  filtroCxpProvSeleccionadas: string[] = [];
  filtroCxpVigTodasImplicitas = true;
  filtroCxpVigSeleccionadas: string[] = [];
  filtroCxpCatTodasImplicitas = true;
  filtroCxpCatSeleccionadas: string[] = [];

  /** Copias ya emitidas al padre (año/mes siempre desde el control). */
  filtroCxcSucTodasImplicitasAplicado = true;
  filtroCxcSucSeleccionadasAplicado: string[] = [];
  filtroCxcCliTodasImplicitasAplicado = true;
  filtroCxcCliSeleccionadasAplicado: string[] = [];
  filtroCxcVigTodasImplicitasAplicado = true;
  filtroCxcVigSeleccionadasAplicado: string[] = [];
  filtroCxpProvTodasImplicitasAplicado = true;
  filtroCxpProvSeleccionadasAplicado: string[] = [];
  filtroCxpVigTodasImplicitasAplicado = true;
  filtroCxpVigSeleccionadasAplicado: string[] = [];
  filtroCxpCatTodasImplicitasAplicado = true;
  filtroCxpCatSeleccionadasAplicado: string[] = [];

  /** Catálogos desde Laravel / API Go (`DashboardFiltrosCatalogoService`), como Gastos. */
  sucursales: DashboardFiltroCatalogoItem[] = [];
  clientes: DashboardFiltroCatalogoItem[] = [];
  proveedores: DashboardFiltroCatalogoItem[] = [];
  categoriasGasto: DashboardFiltroCatalogoItem[] = [];
  /** Valores de vigencia: etiquetas de los gráficos de cartera + dimensiones si existen. */
  estadosVigencia: DashboardFiltroCatalogoItem[] = [];
  private vigenciaCatalogoApi: DashboardFiltroCatalogoItem[] = [];
  /**
   * Unión de todos los estados de vigencia vistos (API + gráficos).
   * Sin esto, al filtrar el backend devuelve menos segmentos en el doughnut y el multi-select pierde opciones.
   */
  private vigenciaFiltroOpcionesAcumuladas = new Map<
    string,
    DashboardFiltroCatalogoItem
  >();

  // Filtros interactivos (se aplican localmente sin recargar)
  filtrosInteractivos: {
    vigencia?: string;
    cliente?: string;
    vigenciaPagar?: string;
    proveedor?: string;
  } = {};
  
  // Datos originales (sin filtrar)
  datosOriginales: any = {};
  
  // Datos filtrados (se muestran en la vista)
  datosFiltrados: any = {};

  // Propiedades cacheadas para evitar recálculos
  private _detalleCuentasRowsCache: any[] = [];
  private _totalesDetalleCuentasCache: any = { ventasConIVA: 0, montoAbonado: 0, saldoPendiente: 0 };
  private _resumenCuentasPagarRowsCache: any[] = [];
  private _totalesResumenCuentasPagarCache: any = { gastosTotalesConIVA: 0, totalAbonado: 0, saldoPendiente: 0 };
  private _lastDatosHash: string = '';

  private inicializado: boolean = false;

  constructor(
    private cdr: ChangeDetectorRef,
    private apiService: ApiService,
    private filtrosCatalogo: DashboardFiltrosCatalogoService,
    private dashboardDataService: DashboardDataService
  ) {}

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
    if (currentHash === this._lastDatosHash) {
      return; // No hay cambios
    }
    this._lastDatosHash = currentHash;

    // Detalle CXC / CXP: carga lazy; no vienen en el merge.
  }

  ngOnInit(): void {
    const savedState = this.dashboardDataService.obtenerFiltrosUI('Control de cuentas');
    const tieneEstadoGuardado = !!savedState;
    if (savedState) {
      Object.assign(this, savedState);
    }

    this.cargarOpcionesFiltros();
    this.configurarAGGrid();
    this.configurarAGGridResumenPagar();
    this.wireDetalleCxcStreams();
    this.wireDetalleCxpStreams();
    // Guardar datos originales si existen
    if (this.datos && Object.keys(this.datos).length > 0) {
      this.datosOriginales = this.clonarDatos(this.datos);
      this.datosFiltrados = this.clonarDatos(this.datos);
      this.recalcularRowsCache();
    }
    // Siempre emitir al padre tras restaurar UI; si no, al reentrar al dashboard
    // el padre queda sin datos y la vista se queda en loaders.
    setTimeout(() => {
      this.inicializado = true;
      this.filtrosListosParaEmitir = true;
      this.aplicarFiltros();
      this.cdr.markForCheck();
    }, 100);
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    this.dashboardDataService.guardarFiltrosUI('Control de cuentas', {
      anio: this.anio,
      mes: this.mes,
      filtroCxcSucTodasImplicitas: this.filtroCxcSucTodasImplicitas,
      filtroCxcSucSeleccionadas: this.filtroCxcSucSeleccionadas,
      filtroCxcCliTodasImplicitas: this.filtroCxcCliTodasImplicitas,
      filtroCxcCliSeleccionadas: this.filtroCxcCliSeleccionadas,
      filtroCxcVigTodasImplicitas: this.filtroCxcVigTodasImplicitas,
      filtroCxcVigSeleccionadas: this.filtroCxcVigSeleccionadas,
      filtroCxpProvTodasImplicitas: this.filtroCxpProvTodasImplicitas,
      filtroCxpProvSeleccionadas: this.filtroCxpProvSeleccionadas,
      filtroCxpVigTodasImplicitas: this.filtroCxpVigTodasImplicitas,
      filtroCxpVigSeleccionadas: this.filtroCxpVigSeleccionadas,
      filtroCxpCatTodasImplicitas: this.filtroCxpCatTodasImplicitas,
      filtroCxpCatSeleccionadas: this.filtroCxpCatSeleccionadas,
      filtroCxcSucTodasImplicitasAplicado: this.filtroCxcSucTodasImplicitasAplicado,
      filtroCxcSucSeleccionadasAplicado: this.filtroCxcSucSeleccionadasAplicado,
      filtroCxcCliTodasImplicitasAplicado: this.filtroCxcCliTodasImplicitasAplicado,
      filtroCxcCliSeleccionadasAplicado: this.filtroCxcCliSeleccionadasAplicado,
      filtroCxcVigTodasImplicitasAplicado: this.filtroCxcVigTodasImplicitasAplicado,
      filtroCxcVigSeleccionadasAplicado: this.filtroCxcVigSeleccionadasAplicado,
      filtroCxpProvTodasImplicitasAplicado: this.filtroCxpProvTodasImplicitasAplicado,
      filtroCxpProvSeleccionadasAplicado: this.filtroCxpProvSeleccionadasAplicado,
      filtroCxpVigTodasImplicitasAplicado: this.filtroCxpVigTodasImplicitasAplicado,
      filtroCxpVigSeleccionadasAplicado: this.filtroCxpVigSeleccionadasAplicado,
      filtroCxpCatTodasImplicitasAplicado: this.filtroCxpCatTodasImplicitasAplicado,
      filtroCxpCatSeleccionadasAplicado: this.filtroCxpCatSeleccionadasAplicado,
      filtrosInteractivos: this.filtrosInteractivos
    });
  }

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['datos']) {
      this.refrescarEstadosVigenciaCombinados();
      // Actualizar datos originales cuando cambian
      if (this.datos && Object.keys(this.datos).length > 0) {
        this.datosOriginales = this.clonarDatos(this.datos);
        // Aplicar filtros interactivos si existen
        if (Object.keys(this.filtrosInteractivos).length > 0) {
          this.aplicarFiltrosInteractivos();
        } else {
          this.datosFiltrados = this.clonarDatos(this.datos);
          this.datos = this.datosFiltrados;
          this.recalcularRowsCache();
        }
        this.cdr.markForCheck();
      }
    }

    // datosCompletos=true: todas las APIs terminaron → desbloquear filtros
    if (changes['datosCompletos'] && this.datosCompletos) {
      this._desbloquearFiltros();
    }
  }

  private wireDetalleCxcStreams(): void {
    this.cxcPage$
      .pipe(
        switchMap(({ offset, q, append }) => {
          this.cxcAppendPending = append;
          this.cxcLoading = true;
          this.cdr.markForCheck();
          return this.dashboardDataService.obtenerDetalleCxcPagina(
            this.getFiltrosCxcDetalle(),
            { limite: this.cxcPageSize, offset, q: q || undefined },
          );
        }),
        takeUntil(this.destroy$),
      )
      .subscribe({
        next: (page) => {
          this.cxcOffset = page.offset;
          this.cxcTotal = page.total;
          this.cxcTotales = page.totales;
          const append =
            this.cxcAppendPending && (page.items?.length ?? 0) > 0;
          this.applyCxcPageRows(page.items, page.totales, append);
          this.cxcAppendPending = false;
          this.cxcListo = true;
          this.cxcLoading = false;
          this.cdr.markForCheck();
        },
        error: () => {
          this.cxcAppendPending = false;
          this.cxcLoading = false;
          this.cxcListo = true;
          this.cdr.markForCheck();
        },
      });

    this.quickFilterCxc$
      .pipe(
        debounceTime(300),
        distinctUntilChanged(),
        takeUntil(this.destroy$),
      )
      .subscribe(() => {
        this.cargarDetalleCxcPagina(0);
      });
  }

  private wireDetalleCxpStreams(): void {
    this.cxpPage$
      .pipe(
        switchMap(({ offset, q, append }) => {
          this.cxpAppendPending = append;
          this.cxpLoading = true;
          this.cdr.markForCheck();
          return this.dashboardDataService.obtenerDetalleCxpPagina(
            this.getFiltrosCxpDetalle(),
            { limite: this.cxpPageSize, offset, q: q || undefined },
          );
        }),
        takeUntil(this.destroy$),
      )
      .subscribe({
        next: (page) => {
          this.cxpOffset = page.offset;
          this.cxpTotal = page.total;
          this.cxpTotales = page.totales;
          const append =
            this.cxpAppendPending && (page.items?.length ?? 0) > 0;
          this.applyCxpPageRows(page.items, page.totales, append);
          this.cxpAppendPending = false;
          this.cxpListo = true;
          this.cxpLoading = false;
          this.cdr.markForCheck();
        },
        error: () => {
          this.cxpAppendPending = false;
          this.cxpLoading = false;
          this.cxpListo = true;
          this.cdr.markForCheck();
        },
      });

    this.quickFilterCxp$
      .pipe(
        debounceTime(300),
        distinctUntilChanged(),
        takeUntil(this.destroy$),
      )
      .subscribe(() => {
        this.cargarDetalleCxpPagina(0);
      });
  }

  /** Filtros panel CXC + overrides interactivos (vigencia del chart). */
  private getFiltrosCxcDetalle(): any {
    const filtros: any = {
      anio: this.anio || new Date().getFullYear().toString(),
    };
    if (this.mes) filtros.mes = this.mes;

    const suc = this.filtroCxcSucursalParaApiAplicado();
    if (suc !== '' && suc != null) filtros.sucursal = suc;

    const cli = this.filtroMultiAString(
      this.filtroCxcCliTodasImplicitasAplicado,
      this.filtroCxcCliSeleccionadasAplicado,
      this.idsDeListaFiltro(this.clientes),
    );
    if (cli) filtros.cliente = cli;

    const vig = this.vigenciaParaApiAplicado();
    if (vig) filtros.estadoVigencia = vig;

    if (this.filtrosInteractivos.vigencia) {
      filtros.estadoVigencia = this.filtrosInteractivos.vigencia;
    }
    return filtros;
  }

  private getCxcSearchQ(): string {
    const typed = this.quickFilterText.trim();
    if (typed) return typed;
    return (this.filtrosInteractivos.cliente || '').trim();
  }

  cargarDetalleCxcPagina(offset: number, append = false): void {
    this.cxcPage$.next({
      offset: Math.max(0, offset),
      q: this.getCxcSearchQ(),
      append,
    });
  }

  private maybeLoadMoreCxcFromGrid(): void {
    if (!this.gridApi || this.cxcLoading || !this.cxcListo) return;
    const loaded = this._detalleCuentasRowsCache.length;
    if (loaded >= this.cxcTotal) return;
    const pageSize = this.gridApi.paginationGetPageSize() || 25;
    const currentPage = this.gridApi.paginationGetCurrentPage();
    // Solo al llegar a la última página del buffer (evita prefetch en page 0 con chunk=50).
    const lastLoadedPage = Math.max(0, Math.ceil(loaded / pageSize) - 1);
    if (currentPage >= lastLoadedPage) {
      this.cargarDetalleCxcPagina(loaded, true);
    }
  }

  private mapCxcRows(items: any[]): any[] {
    return (items ?? []).map((item: any) => ({
      cliente: item.cliente || '-',
      factura: item.factura || '-',
      fechaVenta: item.fechaVenta || '-',
      fechaPago: item.fechaPago || '-',
      diasVencimiento: item.diasVencimiento || 0,
      estado: item.estado || '-',
      ventasConIVA: item.ventasConIVA || 0,
      montoAbonado: item.montoAbonado || 0,
      diasAbono: item.diasAbono || null,
      saldoPendiente: item.saldoPendiente || 0,
      isTotal: false,
    }));
  }

  private applyCxcPageRows(
    items: any[],
    totales: DetalleCxcTotales,
    append = false,
  ): void {
    const mapped = this.mapCxcRows(items);
    if (append && this._detalleCuentasRowsCache.length > 0) {
      this._detalleCuentasRowsCache = [
        ...this._detalleCuentasRowsCache,
        ...mapped,
      ];
    } else {
      this._detalleCuentasRowsCache = mapped;
      if (this.gridApi) {
        this.gridApi.paginationGoToFirstPage();
      }
    }
    this._totalesDetalleCuentasCache = { ...totales };
    this.aplicarPinnedTotalesCxc(totales);
  }

  private aplicarPinnedTotalesCxc(totales: DetalleCxcTotales): void {
    if (
      totales.ventasConIVA !== 0 ||
      totales.montoAbonado !== 0 ||
      totales.saldoPendiente !== 0
    ) {
      this.pinnedBottomRowDataCxc = [
        {
          cliente: 'Total',
          factura: '',
          fechaVenta: '',
          fechaPago: '',
          diasVencimiento: '',
          estado: '',
          ventasConIVA: totales.ventasConIVA,
          montoAbonado: totales.montoAbonado,
          diasAbono: '',
          saldoPendiente: totales.saldoPendiente,
        },
      ];
    } else {
      this.pinnedBottomRowDataCxc = [];
    }
  }

  /** Filtros panel CXP + overrides interactivos (vigencia / proveedor). */
  private getFiltrosCxpDetalle(): any {
    const filtros: any = {
      anio: this.anio || new Date().getFullYear().toString(),
    };
    if (this.mes) filtros.mes = this.mes;

    const suc = this.filtroCxcSucursalParaApiAplicado();
    if (suc !== '' && suc != null) filtros.sucursal = suc;

    const prov = this.filtroMultiAString(
      this.filtroCxpProvTodasImplicitasAplicado,
      this.filtroCxpProvSeleccionadasAplicado,
      this.idsDeListaFiltro(this.proveedores),
    );
    if (prov) filtros.proveedor = prov;

    const cat = this.filtroMultiAString(
      this.filtroCxpCatTodasImplicitasAplicado,
      this.filtroCxpCatSeleccionadasAplicado,
      this.idsDeListaFiltro(this.categoriasGasto),
    );
    if (cat) filtros.categoria = cat;

    const vig = this.vigenciaParaApiAplicado();
    if (vig) filtros.estadoVigencia = vig;

    if (this.filtrosInteractivos.vigenciaPagar) {
      filtros.estadoVigencia = this.filtrosInteractivos.vigenciaPagar;
    }
    return filtros;
  }

  private getCxpSearchQ(): string {
    const typed = this.quickFilterTextResumen.trim();
    if (typed) return typed;
    return (this.filtrosInteractivos.proveedor || '').trim();
  }

  cargarDetalleCxpPagina(offset: number, append = false): void {
    this.cxpPage$.next({
      offset: Math.max(0, offset),
      q: this.getCxpSearchQ(),
      append,
    });
  }

  private maybeLoadMoreCxpFromGrid(): void {
    if (!this.resumenGridApi || this.cxpLoading || !this.cxpListo) return;
    const loaded = this._resumenCuentasPagarRowsCache.length;
    if (loaded >= this.cxpTotal) return;
    const pageSize = this.resumenGridApi.paginationGetPageSize() || 25;
    const currentPage = this.resumenGridApi.paginationGetCurrentPage();
    // Solo al llegar a la última página del buffer (evita prefetch en page 0 con chunk=50).
    const lastLoadedPage = Math.max(0, Math.ceil(loaded / pageSize) - 1);
    if (currentPage >= lastLoadedPage) {
      this.cargarDetalleCxpPagina(loaded, true);
    }
  }

  private mapCxpRows(items: any[]): any[] {
    return (items ?? []).map((item: any) => ({
      proveedor:
        item.proveedor != null && String(item.proveedor).trim() !== ''
          ? String(item.proveedor).trim()
          : '-',
      correlativo:
        item.correlativo != null && String(item.correlativo).trim() !== ''
          ? String(item.correlativo).trim()
          : '-',
      fechaCompra: item.fechaCompra || '-',
      vencimiento: item.vencimiento || '-',
      diasVencimiento:
        item.diasVencimiento !== undefined && item.diasVencimiento !== null
          ? item.diasVencimiento
          : 0,
      estado: item.estado || '-',
      gastosTotalesConIVA: item.gastosTotalesConIVA || 0,
      totalAbonado: item.totalAbonado || 0,
      ultimoAbono:
        item.ultimoAbono != null && String(item.ultimoAbono).trim() !== ''
          ? String(item.ultimoAbono).trim()
          : '-',
      saldoPendiente: item.saldoPendiente || 0,
      isTotal: false,
    }));
  }

  private applyCxpPageRows(
    items: any[],
    totales: DetalleCxpTotales,
    append = false,
  ): void {
    const mapped = this.mapCxpRows(items);
    if (append && this._resumenCuentasPagarRowsCache.length > 0) {
      this._resumenCuentasPagarRowsCache = [
        ...this._resumenCuentasPagarRowsCache,
        ...mapped,
      ];
    } else {
      this._resumenCuentasPagarRowsCache = mapped;
      if (this.resumenGridApi) {
        this.resumenGridApi.paginationGoToFirstPage();
      }
    }
    this._totalesResumenCuentasPagarCache = { ...totales };
    this.aplicarPinnedTotalesCxp(totales);
  }

  private aplicarPinnedTotalesCxp(totales: DetalleCxpTotales): void {
    if (
      totales.gastosTotalesConIVA !== 0 ||
      totales.totalAbonado !== 0 ||
      totales.saldoPendiente !== 0
    ) {
      this.pinnedBottomRowDataCxp = [
        {
          proveedor: '',
          correlativo: '',
          fechaCompra: 'Total',
          vencimiento: '',
          diasVencimiento: '',
          estado: '',
          gastosTotalesConIVA: totales.gastosTotalesConIVA,
          totalAbonado: totales.totalAbonado,
          ultimoAbono: '',
          saldoPendiente: totales.saldoPendiente,
        },
      ];
    } else {
      this.pinnedBottomRowDataCxp = [];
    }
  }

  private csvEscape(value: string): string {
    if (/[",\n\r]/.test(value)) {
      return `"${value.replace(/"/g, '""')}"`;
    }
    return value;
  }

  cargarOpcionesFiltros(): void {
    const tieneEstadoGuardado = !!this.dashboardDataService.obtenerFiltrosUI('Control de cuentas');
    this.filtrosCatalogo.sucursalesParaFiltro().subscribe({
      next: (items) => {
        this.sucursales = items;
        if (!tieneEstadoGuardado) {
          this.aplicarRestriccionSucursalCxcPorRol();
          this.copiarTodoFiltrosAdicionalesBorradorAAplicado();
          setTimeout(() => {
            if (this.filtrosListosParaEmitir) {
              this.aplicarFiltros();
            }
          }, 150);
        }
        this.cdr.markForCheck();
      },
    });

    this.filtrosCatalogo.clientesParaFiltro().subscribe({
      next: (items) => {
        this.clientes = items;
        this.cdr.markForCheck();
      },
    });

    this.filtrosCatalogo.proveedoresParaFiltro().subscribe({
      next: (items) => {
        this.proveedores = items;
        this.cdr.markForCheck();
      },
    });

    this.filtrosCatalogo.categoriasParaFiltro().subscribe({
      next: (items) => {
        this.categoriasGasto = items;
        this.cdr.markForCheck();
      },
    });

    // this.filtrosCatalogo.estadosVigenciaCuentasParaFiltro().subscribe({
    //   next: (items: DashboardFiltroCatalogoItem[]) => {
    //     this.vigenciaCatalogoApi = items;
    //     this.refrescarEstadosVigenciaCombinados();
    //     this.cdr.markForCheck();
    //   },
    // });
  }

  /**
   * Suma opciones de vigencia (no reemplaza): API dimensiones + etiquetas de gráficos.
   * Los gráficos filtrados traen menos labels; solo añadimos claves nuevas al acumulado.
   */
  private refrescarEstadosVigenciaCombinados(): void {
    for (const x of this.vigenciaCatalogoApi) {
      const id = String(x.id).trim();
      if (!id) {
        continue;
      }
      this.vigenciaFiltroOpcionesAcumuladas.set(id, {
        id,
        nombre: String(x.nombre ?? id).trim() || id,
      });
    }
    const d = this.datos || {};
    for (const L of (d.cuentasPorVigenciaConfig?.labels as string[]) || []) {
      const s = String(L).trim();
      if (s && !this.vigenciaFiltroOpcionesAcumuladas.has(s)) {
        this.vigenciaFiltroOpcionesAcumuladas.set(s, { id: s, nombre: s });
      }
    }
    for (const L of (d.cuentasPorPagarVigenciaConfig?.labels as string[]) ||
      []) {
      const s = String(L).trim();
      if (s && !this.vigenciaFiltroOpcionesAcumuladas.has(s)) {
        this.vigenciaFiltroOpcionesAcumuladas.set(s, { id: s, nombre: s });
      }
    }
    this.estadosVigencia = [
      ...this.vigenciaFiltroOpcionesAcumuladas.values(),
    ].sort((a, b) => a.nombre.localeCompare(b.nombre, 'es'));
  }

  get filtroCxcSucursalDisabled(): boolean {
    const user = this.apiService.auth_user();
    return user?.tipo !== 'Administrador' && this.sucursales.length <= 1;
  }

  private aplicarRestriccionSucursalCxcPorRol(): void {
    const user = this.apiService.auth_user();
    const items = this.sucursales;
    if (items.length === 0) {
      this.filtroCxcSucTodasImplicitas = true;
      this.filtroCxcSucSeleccionadas = [];
      return;
    }
    if (user?.tipo !== 'Administrador' && user?.id_sucursal != null) {
      this.filtroCxcSucTodasImplicitas = false;
      this.filtroCxcSucSeleccionadas = [String(user.id_sucursal)];
    } else {
      this.filtroCxcSucTodasImplicitas = true;
      this.filtroCxcSucSeleccionadas = [];
    }
  }

  private idsDeListaFiltro(items: DashboardFiltroCatalogoItem[]): string[] {
    return (items || []).map((x) => String(x.id));
  }

  private filtroMultiAString(
    todasImplicitas: boolean,
    seleccionados: string[],
    todosIds: string[],
  ): string {
    if (todasImplicitas || seleccionados.length === 0) {
      return '';
    }
    if (
      todosIds.length > 0 &&
      seleccionados.length === todosIds.length &&
      todosIds.every((id) => seleccionados.includes(id))
    ) {
      return '';
    }
    return seleccionados.join(',');
  }

  private filtroSucursalParaApiDesde(
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

  private filtroCxcSucursalParaApiAplicado(): string | string[] {
    return this.filtroSucursalParaApiDesde(
      this.filtroCxcSucTodasImplicitasAplicado,
      this.filtroCxcSucSeleccionadasAplicado,
    );
  }

  private copiarFiltrosCxcAplicadoABorrador(): void {
    this.filtroCxcSucTodasImplicitas = this.filtroCxcSucTodasImplicitasAplicado;
    this.filtroCxcSucSeleccionadas = [...this.filtroCxcSucSeleccionadasAplicado];
    this.filtroCxcCliTodasImplicitas = this.filtroCxcCliTodasImplicitasAplicado;
    this.filtroCxcCliSeleccionadas = [...this.filtroCxcCliSeleccionadasAplicado];
    this.filtroCxcVigTodasImplicitas = this.filtroCxcVigTodasImplicitasAplicado;
    this.filtroCxcVigSeleccionadas = [...this.filtroCxcVigSeleccionadasAplicado];
  }

  private copiarFiltrosCxpAplicadoABorrador(): void {
    this.filtroCxpProvTodasImplicitas = this.filtroCxpProvTodasImplicitasAplicado;
    this.filtroCxpProvSeleccionadas = [...this.filtroCxpProvSeleccionadasAplicado];
    this.filtroCxpVigTodasImplicitas = this.filtroCxpVigTodasImplicitasAplicado;
    this.filtroCxpVigSeleccionadas = [...this.filtroCxpVigSeleccionadasAplicado];
    this.filtroCxpCatTodasImplicitas = this.filtroCxpCatTodasImplicitasAplicado;
    this.filtroCxpCatSeleccionadas = [...this.filtroCxpCatSeleccionadasAplicado];
  }

  private copiarFiltrosCxcBorradorAAplicado(): void {
    this.filtroCxcSucTodasImplicitasAplicado = this.filtroCxcSucTodasImplicitas;
    this.filtroCxcSucSeleccionadasAplicado = [...this.filtroCxcSucSeleccionadas];
    this.filtroCxcCliTodasImplicitasAplicado = this.filtroCxcCliTodasImplicitas;
    this.filtroCxcCliSeleccionadasAplicado = [...this.filtroCxcCliSeleccionadas];
    this.filtroCxcVigTodasImplicitasAplicado = this.filtroCxcVigTodasImplicitas;
    this.filtroCxcVigSeleccionadasAplicado = [...this.filtroCxcVigSeleccionadas];
  }

  private copiarFiltrosCxpBorradorAAplicado(): void {
    this.filtroCxpProvTodasImplicitasAplicado = this.filtroCxpProvTodasImplicitas;
    this.filtroCxpProvSeleccionadasAplicado = [...this.filtroCxpProvSeleccionadas];
    this.filtroCxpVigTodasImplicitasAplicado = this.filtroCxpVigTodasImplicitas;
    this.filtroCxpVigSeleccionadasAplicado = [...this.filtroCxpVigSeleccionadas];
    this.filtroCxpCatTodasImplicitasAplicado = this.filtroCxpCatTodasImplicitas;
    this.filtroCxpCatSeleccionadasAplicado = [...this.filtroCxpCatSeleccionadas];
  }

  private copiarTodoFiltrosAdicionalesBorradorAAplicado(): void {
    this.copiarFiltrosCxcBorradorAAplicado();
    this.copiarFiltrosCxpBorradorAAplicado();
  }

  private arraysMismoContenidoCc(a: string[], b: string[]): boolean {
    if (a.length !== b.length) return false;
    const sa = [...a].map(String).sort();
    const sb = [...b].map(String).sort();
    return sa.every((v, i) => v === sb[i]);
  }

  private mismoEstadoFiltroMultiCc(
    todasA: boolean,
    selA: string[],
    todasB: boolean,
    selB: string[],
  ): boolean {
    return todasA === todasB && this.arraysMismoContenidoCc(selA, selB);
  }

  private vigenciaParaApiAplicado(): string {
    const todos = this.idsDeListaFiltro(this.estadosVigencia);
    const a = this.filtroMultiAString(
      this.filtroCxcVigTodasImplicitasAplicado,
      this.filtroCxcVigSeleccionadasAplicado,
      todos,
    );
    const b = this.filtroMultiAString(
      this.filtroCxpVigTodasImplicitasAplicado,
      this.filtroCxpVigSeleccionadasAplicado,
      todos,
    );
    if (a && b) {
      const set = new Set([
        ...a.split(',').map((s) => s.trim()),
        ...b.split(',').map((s) => s.trim()),
      ]);
      return [...set].filter(Boolean).join(',');
    }
    return a || b || '';
  }

  get filtroCxcSucursalesItems(): DropdownMultiFiltroItem[] {
    return (this.sucursales || []).map((s) => ({
      id: String(s.id),
      nombre: s.nombre ?? '',
    }));
  }

  get filtroCxcClientesItems(): DropdownMultiFiltroItem[] {
    return (this.clientes || []).map((x) => ({
      id: String(x.id),
      nombre: x.nombre ?? '',
    }));
  }

  get filtroCxcVigenciaItems(): DropdownMultiFiltroItem[] {
    return (this.estadosVigencia || []).map((x) => ({
      id: String(x.id),
      nombre: x.nombre ?? '',
    }));
  }

  get filtroCxpProveedoresItems(): DropdownMultiFiltroItem[] {
    return (this.proveedores || []).map((x) => ({
      id: String(x.id),
      nombre: x.nombre ?? '',
    }));
  }

  get filtroCxpVigenciaItems(): DropdownMultiFiltroItem[] {
    return this.filtroCxcVigenciaItems;
  }

  get filtroCxpCategoriasItems(): DropdownMultiFiltroItem[] {
    return (this.categoriasGasto || []).map((x) => ({
      id: String(x.id),
      nombre: x.nombre ?? '',
    }));
  }

  onFiltroCxcSucursalChange(ev: DropdownMultiFiltroSelection): void {
    this.filtroCxcSucTodasImplicitas = ev.todasImplicitas;
    this.filtroCxcSucSeleccionadas = [...ev.seleccionados];
    this.cdr.markForCheck();
  }

  onFiltroCxcClienteChange(ev: DropdownMultiFiltroSelection): void {
    this.filtroCxcCliTodasImplicitas = ev.todasImplicitas;
    this.filtroCxcCliSeleccionadas = [...ev.seleccionados];
    this.cdr.markForCheck();
  }

  onFiltroCxcVigenciaChange(ev: DropdownMultiFiltroSelection): void {
    this.filtroCxcVigTodasImplicitas = ev.todasImplicitas;
    this.filtroCxcVigSeleccionadas = [...ev.seleccionados];
    this.cdr.markForCheck();
  }

  onFiltroCxpProveedorChange(ev: DropdownMultiFiltroSelection): void {
    this.filtroCxpProvTodasImplicitas = ev.todasImplicitas;
    this.filtroCxpProvSeleccionadas = [...ev.seleccionados];
    this.cdr.markForCheck();
  }

  onFiltroCxpVigenciaChange(ev: DropdownMultiFiltroSelection): void {
    this.filtroCxpVigTodasImplicitas = ev.todasImplicitas;
    this.filtroCxpVigSeleccionadas = [...ev.seleccionados];
    this.cdr.markForCheck();
  }

  onFiltroCxpCategoriaChange(ev: DropdownMultiFiltroSelection): void {
    this.filtroCxpCatTodasImplicitas = ev.todasImplicitas;
    this.filtroCxpCatSeleccionadas = [...ev.seleccionados];
    this.cdr.markForCheck();
  }

  toggleFiltrosAdicionalesPagar(): void {
    this.mostrarFiltrosAdicionalesPagar = !this.mostrarFiltrosAdicionalesPagar;
    this.copiarFiltrosCxpAplicadoABorrador();
    this.cdr.markForCheck();
  }

  toggleFiltrosAdicionales(): void {
    this.mostrarFiltrosAdicionales = !this.mostrarFiltrosAdicionales;
    this.copiarFiltrosCxcAplicadoABorrador();
    this.cdr.markForCheck();
  }

  /** Confirma solo filtros de cuentas por cobrar (borrador → aplicado) y emite. */
  confirmarOtrosFiltrosCxc(): void {
    this.copiarFiltrosCxcBorradorAAplicado();
    this.aplicarFiltros();
    this.cdr.markForCheck();
  }

  /** Confirma solo filtros de cuentas por pagar (borrador → aplicado) y emite. */
  confirmarOtrosFiltrosCxp(): void {
    this.copiarFiltrosCxpBorradorAAplicado();
    this.aplicarFiltros();
    this.cdr.markForCheck();
  }

  get mostrarBotonAplicarOtrosFiltrosCxc(): boolean {
    if (!this.mostrarFiltrosAdicionales) return false;
    return !(
      this.mismoEstadoFiltroMultiCc(
        this.filtroCxcSucTodasImplicitas,
        this.filtroCxcSucSeleccionadas,
        this.filtroCxcSucTodasImplicitasAplicado,
        this.filtroCxcSucSeleccionadasAplicado,
      ) &&
      this.mismoEstadoFiltroMultiCc(
        this.filtroCxcCliTodasImplicitas,
        this.filtroCxcCliSeleccionadas,
        this.filtroCxcCliTodasImplicitasAplicado,
        this.filtroCxcCliSeleccionadasAplicado,
      ) &&
      this.mismoEstadoFiltroMultiCc(
        this.filtroCxcVigTodasImplicitas,
        this.filtroCxcVigSeleccionadas,
        this.filtroCxcVigTodasImplicitasAplicado,
        this.filtroCxcVigSeleccionadasAplicado,
      )
    );
  }

  get mostrarBotonAplicarOtrosFiltrosCxp(): boolean {
    if (!this.mostrarFiltrosAdicionalesPagar) return false;
    return !(
      this.mismoEstadoFiltroMultiCc(
        this.filtroCxpProvTodasImplicitas,
        this.filtroCxpProvSeleccionadas,
        this.filtroCxpProvTodasImplicitasAplicado,
        this.filtroCxpProvSeleccionadasAplicado,
      ) &&
      this.mismoEstadoFiltroMultiCc(
        this.filtroCxpVigTodasImplicitas,
        this.filtroCxpVigSeleccionadas,
        this.filtroCxpVigTodasImplicitasAplicado,
        this.filtroCxpVigSeleccionadasAplicado,
      ) &&
      this.mismoEstadoFiltroMultiCc(
        this.filtroCxpCatTodasImplicitas,
        this.filtroCxpCatSeleccionadas,
        this.filtroCxpCatTodasImplicitasAplicado,
        this.filtroCxpCatSeleccionadasAplicado,
      )
    );
  }

  limpiarFiltros(): void {
    this.anio = new Date().getFullYear().toString();
    this.mes = '';
    this.filtroCxcCliTodasImplicitas = true;
    this.filtroCxcCliSeleccionadas = [];
    this.filtroCxcVigTodasImplicitas = true;
    this.filtroCxcVigSeleccionadas = [];
    this.filtroCxpProvTodasImplicitas = true;
    this.filtroCxpProvSeleccionadas = [];
    this.filtroCxpVigTodasImplicitas = true;
    this.filtroCxpVigSeleccionadas = [];
    this.filtroCxpCatTodasImplicitas = true;
    this.filtroCxpCatSeleccionadas = [];
    this.aplicarRestriccionSucursalCxcPorRol();
    this.copiarTodoFiltrosAdicionalesBorradorAAplicado();
    this.limpiarFiltrosInteractivos();
    this.aplicarFiltros();
  }

  aplicarFiltros(): void {
    if (!this.inicializado || !this.filtrosListosParaEmitir) {
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

    const suc = this.filtroCxcSucursalParaApiAplicado();
    if (suc !== '' && suc != null) {
      filtros.sucursal = suc;
    }

    const cli = this.filtroMultiAString(
      this.filtroCxcCliTodasImplicitasAplicado,
      this.filtroCxcCliSeleccionadasAplicado,
      this.idsDeListaFiltro(this.clientes),
    );
    if (cli) {
      filtros.cliente = cli;
    }

    const vig = this.vigenciaParaApiAplicado();
    if (vig) {
      filtros.estadoVigencia = vig;
    }

    const prov = this.filtroMultiAString(
      this.filtroCxpProvTodasImplicitasAplicado,
      this.filtroCxpProvSeleccionadasAplicado,
      this.idsDeListaFiltro(this.proveedores),
    );
    if (prov) {
      filtros.proveedor = prov;
    }

    const cat = this.filtroMultiAString(
      this.filtroCxpCatTodasImplicitasAplicado,
      this.filtroCxpCatSeleccionadasAplicado,
      this.idsDeListaFiltro(this.categoriasGasto),
    );
    if (cat) {
      filtros.categoria = cat;
    }

    if (this.mes) {
      filtros.mes = this.mes;
    }

    this.filtrosCambiados.emit(filtros);
    this._desbloquearSiPadreOmiteRecarga();
    this.cargarDetalleCxcPagina(0);
    this.cargarDetalleCxpPagina(0);
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


  private filtroMultiExplicitoActivo(
    todasImplicitas: boolean,
    seleccionados: string[],
  ): boolean {
    return !todasImplicitas || seleccionados.length > 0;
  }

  private evaluarPuedeLimpiarFiltrosControlCuentas(): boolean {
    const anioActual = new Date().getFullYear().toString();
    if (!!this.mes || this.anio !== anioActual) {
      return true;
    }
    if (this.tieneFiltrosInteractivos()) {
      return true;
    }
    const paresBorrador: [boolean, string[]][] = [
      [this.filtroCxcSucTodasImplicitas, this.filtroCxcSucSeleccionadas],
      [this.filtroCxcCliTodasImplicitas, this.filtroCxcCliSeleccionadas],
      [this.filtroCxcVigTodasImplicitas, this.filtroCxcVigSeleccionadas],
      [this.filtroCxpProvTodasImplicitas, this.filtroCxpProvSeleccionadas],
      [this.filtroCxpVigTodasImplicitas, this.filtroCxpVigSeleccionadas],
      [this.filtroCxpCatTodasImplicitas, this.filtroCxpCatSeleccionadas],
    ];
    const paresAplicado: [boolean, string[]][] = [
      [this.filtroCxcSucTodasImplicitasAplicado, this.filtroCxcSucSeleccionadasAplicado],
      [this.filtroCxcCliTodasImplicitasAplicado, this.filtroCxcCliSeleccionadasAplicado],
      [this.filtroCxcVigTodasImplicitasAplicado, this.filtroCxcVigSeleccionadasAplicado],
      [this.filtroCxpProvTodasImplicitasAplicado, this.filtroCxpProvSeleccionadasAplicado],
      [this.filtroCxpVigTodasImplicitasAplicado, this.filtroCxpVigSeleccionadasAplicado],
      [this.filtroCxpCatTodasImplicitasAplicado, this.filtroCxpCatSeleccionadasAplicado],
    ];
    if (
      paresBorrador.some(([t, s]) => this.filtroMultiExplicitoActivo(t, s))
    ) {
      return true;
    }
    if (
      paresAplicado.some(([t, s]) => this.filtroMultiExplicitoActivo(t, s))
    ) {
      return true;
    }
    return false;
  }

  get puedeLimpiarFiltrosControlCuentas(): boolean {
    return this.evaluarPuedeLimpiarFiltrosControlCuentas();
  }

  get puedeLimpiarFiltrosCxc(): boolean {
    return this.evaluarPuedeLimpiarFiltrosControlCuentas();
  }

  get puedeLimpiarFiltrosCxp(): boolean {
    return this.evaluarPuedeLimpiarFiltrosControlCuentas();
  }

  formatCurrency(value: number): string {
    if (value === null || value === undefined) {
      value = 0;
    }
    return formatEmpresaCurrency(value, this.apiService.auth_user()?.empresa);
  }

  // Métodos para filtros interactivos
  onVigenciaClick(event: { name: string; value: any; index: number }): void {
    if (this.filtrosInteractivos.vigencia === event.name) {
      // Si ya está filtrado por esta vigencia, quitar el filtro
      delete this.filtrosInteractivos.vigencia;
    } else {
      // Aplicar filtro de vigencia
      this.filtrosInteractivos.vigencia = event.name;
    }
    this.aplicarFiltrosInteractivos();
    this.cargarDetalleCxcPagina(0);
  }

  onClienteCuentaClick(event: { name: string; amount: number }): void {
    if (this.filtrosInteractivos.cliente === event.name) {
      delete this.filtrosInteractivos.cliente;
      this.quickFilterText = '';
    } else {
      this.filtrosInteractivos.cliente = event.name;
      this.quickFilterText = event.name;
    }
    this.aplicarFiltrosInteractivos();
    this.cargarDetalleCxcPagina(0);
  }

  onVigenciaPagarClick(event: { name: string; value: any; index: number }): void {
    if (this.filtrosInteractivos.vigenciaPagar === event.name) {
      delete this.filtrosInteractivos.vigenciaPagar;
    } else {
      this.filtrosInteractivos.vigenciaPagar = event.name;
    }
    this.aplicarFiltrosInteractivos();
    this.cargarDetalleCxpPagina(0);
  }

  onProveedorCuentaClick(event: { name: string; amount: number }): void {
    if (this.filtrosInteractivos.proveedor === event.name) {
      delete this.filtrosInteractivos.proveedor;
      this.quickFilterTextResumen = '';
    } else {
      this.filtrosInteractivos.proveedor = event.name;
      this.quickFilterTextResumen = event.name;
    }
    this.aplicarFiltrosInteractivos();
    this.cargarDetalleCxpPagina(0);
  }

  aplicarFiltrosInteractivos(): void {
    // Si no hay datos originales, usar los datos actuales
    const datosBase = Object.keys(this.datosOriginales).length > 0
      ? this.datosOriginales
      : (this.datos || {});

    // Crear una copia profunda de los datos para filtrar
    this.datosFiltrados = this.clonarDatos(datosBase);

    // Filtrar datos según los filtros activos
    this.filtrarDatos();

    // Recalcular todos los gráficos basándose en los datos filtrados
    this.recalcularTodosLosGraficos();

    // Recalcular métricas
    this.recalcularMetricas();

    // Actualizar los datos que se muestran (crear nueva referencia para que Angular detecte cambios)
    this.datos = this.clonarDatos(this.datosFiltrados);

    // Recalcular cache y forzar detección de cambios
    this.recalcularRowsCache();
    this.cdr.markForCheck();
  }

  filtrarDatos(): void {
    // Detalle CXC / CXP: paginado en servidor (estado_vigencia / q).
  }

  recalcularTodosLosGraficos(): void {
    // Recalcular cuentas por vigencia (gráfico de pastel)
    this.recalcularCuentasPorVigencia();

    // Recalcular cuentas por cobrar clientes
    this.recalcularCuentasPorCobrarClientes();

    // Recalcular cuentas por pagar vigencia
    this.recalcularCuentasPorPagarVigencia();

    // Recalcular cuentas por pagar proveedores
    this.recalcularCuentasPorPagarProveedores();
  }

  recalcularCuentasPorVigencia(): void {
    // Chart desde API; opcionalmente resaltar vigencia interactiva
    const configOriginal =
      this.datosOriginales.cuentasPorVigenciaConfig ||
      this.datos.cuentasPorVigenciaConfig;
    if (!configOriginal) return;

    if (
      this.filtrosInteractivos.vigencia &&
      configOriginal.labels &&
      configOriginal.data
    ) {
      const labels: string[] = configOriginal.labels;
      const idx = labels.findIndex(
        (l) => l === this.filtrosInteractivos.vigencia,
      );
      if (idx !== -1) {
        this.datosFiltrados.cuentasPorVigenciaConfig = {
          ...configOriginal,
          labels: [labels[idx]],
          data: [configOriginal.data[idx]],
        };
        return;
      }
    }
    this.datosFiltrados.cuentasPorVigenciaConfig =
      this.clonarDatos(configOriginal);
  }

  recalcularCuentasPorCobrarClientes(): void {
    const original =
      this.datosOriginales.cuentasPorCobrarClientes ||
      this.datos.cuentasPorCobrarClientes ||
      [];
    if (this.filtrosInteractivos.cliente) {
      this.datosFiltrados.cuentasPorCobrarClientes = original.filter(
        (c: any) => c.name === this.filtrosInteractivos.cliente,
      );
      return;
    }
    this.datosFiltrados.cuentasPorCobrarClientes = this.clonarDatos(original);
  }

  recalcularCuentasPorPagarVigencia(): void {
    const configOriginal =
      this.datosOriginales.cuentasPorPagarVigenciaConfig ||
      this.datos.cuentasPorPagarVigenciaConfig;
    if (!configOriginal) return;

    if (
      this.filtrosInteractivos.vigenciaPagar &&
      configOriginal.labels &&
      configOriginal.data
    ) {
      const labels: string[] = configOriginal.labels;
      const idx = labels.findIndex(
        (l) => l === this.filtrosInteractivos.vigenciaPagar,
      );
      if (idx !== -1) {
        this.datosFiltrados.cuentasPorPagarVigenciaConfig = {
          ...configOriginal,
          labels: [labels[idx]],
          data: [configOriginal.data[idx]],
        };
        return;
      }
    }
    this.datosFiltrados.cuentasPorPagarVigenciaConfig =
      this.clonarDatos(configOriginal);
  }

  recalcularCuentasPorPagarProveedores(): void {
    const original =
      this.datosOriginales.cuentasPorPagarProveedores ||
      this.datos.cuentasPorPagarProveedores ||
      [];
    if (this.filtrosInteractivos.proveedor) {
      this.datosFiltrados.cuentasPorPagarProveedores = original.filter(
        (c: any) => c.name === this.filtrosInteractivos.proveedor,
      );
      return;
    }
    this.datosFiltrados.cuentasPorPagarProveedores = this.clonarDatos(original);
  }

  recalcularMetricas(): void {
    // Recalcular métricas de cuentas por cobrar
    if (this.datosFiltrados.detalleCuentasPorCobrar) {
      const cuentas = this.datosFiltrados.detalleCuentasPorCobrar;
      const total = cuentas.reduce((sum: number, c: any) => sum + (c.saldoPendiente || 0), 0);
      const a30 = cuentas.filter((c: any) => (c.diasVencimiento || 0) <= 30)
        .reduce((sum: number, c: any) => sum + (c.saldoPendiente || 0), 0);
      const a60 = cuentas.filter((c: any) => {
        const dias = c.diasVencimiento || 0;
        return dias > 30 && dias <= 60;
      }).reduce((sum: number, c: any) => sum + (c.saldoPendiente || 0), 0);
      const a90 = cuentas.filter((c: any) => {
        const dias = c.diasVencimiento || 0;
        return dias > 60 && dias <= 90;
      }).reduce((sum: number, c: any) => sum + (c.saldoPendiente || 0), 0);

      if (!this.datosFiltrados.metricasCuentas) {
        this.datosFiltrados.metricasCuentas = {};
      }
      this.datosFiltrados.metricasCuentas.cuentasPorCobrarTotal = total;
      this.datosFiltrados.metricasCuentas.cuentasPorCobrar30Dias = a30;
      this.datosFiltrados.metricasCuentas.cuentasPorCobrar60Dias = a60;
      this.datosFiltrados.metricasCuentas.cuentasPorCobrar90Dias = a90;
    }

    // Recalcular métricas de cuentas por pagar
    if (this.datosFiltrados.resumenCuentasPorPagar) {
      const cuentas = this.datosFiltrados.resumenCuentasPorPagar;
      const total = cuentas.reduce((sum: number, c: any) => sum + (c.saldoPendiente || 0), 0);
      const a30 = cuentas.filter((c: any) => (c.diasVencimiento || 0) <= 30)
        .reduce((sum: number, c: any) => sum + (c.saldoPendiente || 0), 0);
      const a60 = cuentas.filter((c: any) => {
        const dias = c.diasVencimiento || 0;
        return dias > 30 && dias <= 60;
      }).reduce((sum: number, c: any) => sum + (c.saldoPendiente || 0), 0);
      const a90 = cuentas.filter((c: any) => {
        const dias = c.diasVencimiento || 0;
        return dias > 60 && dias <= 90;
      }).reduce((sum: number, c: any) => sum + (c.saldoPendiente || 0), 0);

      if (!this.datosFiltrados.metricasCuentas) {
        this.datosFiltrados.metricasCuentas = {};
      }
      this.datosFiltrados.metricasCuentas.cuentasPorPagarTotal = total;
      this.datosFiltrados.metricasCuentas.cuentasPorPagar30Dias = a30;
      this.datosFiltrados.metricasCuentas.cuentasPorPagar60Dias = a60;
      this.datosFiltrados.metricasCuentas.cuentasPorPagar90Dias = a90;
    }
  }

  limpiarFiltrosInteractivos(): void {
    this.filtrosInteractivos = {};
    this.quickFilterText = '';
    this.quickFilterTextResumen = '';
    // Restaurar datos originales
    if (Object.keys(this.datosOriginales).length > 0) {
      this.datosFiltrados = this.clonarDatos(this.datosOriginales);
      this.datos = this.datosFiltrados;
    } else if (this.datos) {
      // Si no hay datos originales guardados, recargar desde el input
      this.datosFiltrados = this.clonarDatos(this.datos);
      this.datos = this.datosFiltrados;
    }
    this.recalcularRowsCache();
    if (this.inicializado) {
      this.cargarDetalleCxcPagina(0);
      this.cargarDetalleCxpPagina(0);
    }
    this.cdr.markForCheck();
  }

  tieneFiltrosInteractivos(): boolean {
    return Object.keys(this.filtrosInteractivos).length > 0;
  }

  getFiltrosInteractivosTexto(): string {
    const filtros: string[] = [];
    if (this.filtrosInteractivos.vigencia) filtros.push(`Vigencia: ${this.filtrosInteractivos.vigencia}`);
    if (this.filtrosInteractivos.cliente) filtros.push(`Cliente: ${this.filtrosInteractivos.cliente}`);
    if (this.filtrosInteractivos.vigenciaPagar) filtros.push(`Vigencia Pagar: ${this.filtrosInteractivos.vigenciaPagar}`);
    if (this.filtrosInteractivos.proveedor) filtros.push(`Proveedor: ${this.filtrosInteractivos.proveedor}`);
    return filtros.join(', ');
  }

  configurarAGGridResumenPagar(): void {
    const estiloResumen = (
      align: 'left' | 'center' | 'right',
    ): ((params: any) => any) => {
      return (params: any): any => {
        if (params.node.rowPinned === 'bottom') {
          return {
            fontWeight: '600',
            textAlign: align,
          };
        }
        return { textAlign: align } as any;
      };
    };

    this.resumenCuentasPagarColumnDefs = [
      {
        field: 'proveedor',
        headerName: 'Nombre de proveedor',
        flex: 1,
        minWidth: 160,
        sortable: true,
        filter: true,
        cellStyle: estiloResumen('left'),
      },
      {
        field: 'correlativo',
        headerName: 'Correlativo',
        width: 120,
        sortable: true,
        filter: true,
        cellStyle: estiloResumen('center'),
      },
      {
        field: 'fechaCompra',
        headerName: 'Fecha de compra',
        width: 130,
        sortable: true,
        filter: true,
        cellStyle: estiloResumen('center'),
      },
      {
        field: 'vencimiento',
        headerName: 'Vencimiento',
        width: 130,
        sortable: true,
        filter: true,
        cellStyle: estiloResumen('center'),
      },
      {
        field: 'diasVencimiento',
        headerName: 'Días de vencimiento',
        width: 130,
        sortable: true,
        filter: true,
        cellStyle: estiloResumen('right'),
        valueFormatter: (params: any) => {
          if (params.node.rowPinned === 'bottom') {
            return '';
          }
          const v = params.value;
          if (v === null || v === undefined) {
            return '-';
          }
          return Number(v).toLocaleString('es-GT');
        },
      },
      {
        field: 'estado',
        headerName: 'Estado',
        width: 120,
        sortable: true,
        filter: true,
        cellRenderer: (params: any) => {
          if (params.node.rowPinned === 'bottom') {
            return '';
          }
          const estado = params.value || '';
          if (estado === 'Pendiente' || estado === 'Pendient') {
            return `<span><i class="fas fa-exclamation-circle" style="color: #F19447; margin-right: 5px;"></i>${estado}</span>`;
          }
          return estado;
        },
        cellStyle: estiloResumen('left'),
      },
      {
        field: 'gastosTotalesConIVA',
        headerName: 'Gastos totales con IVA',
        width: 175,
        sortable: true,
        filter: true,
        cellStyle: estiloResumen('right'),
        valueFormatter: (params: any) => {
          if (params.value === null || params.value === undefined) {
            return '';
          }
          return this.formatCurrency(params.value);
        },
      },
      {
        field: 'totalAbonado',
        headerName: 'Total abonado',
        width: 140,
        sortable: true,
        filter: true,
        cellStyle: estiloResumen('right'),
        valueFormatter: (params: any) => {
          if (params.value === null || params.value === undefined) {
            return '';
          }
          return this.formatCurrency(params.value);
        },
      },
      {
        field: 'ultimoAbono',
        headerName: 'Último abono',
        width: 130,
        sortable: true,
        filter: true,
        cellStyle: estiloResumen('center'),
      },
      {
        field: 'saldoPendiente',
        headerName: 'Saldo pendiente (con IVA)',
        width: 190,
        sortable: true,
        filter: true,
        cellStyle: estiloResumen('right'),
        valueFormatter: (params: any) => {
          if (params.value === null || params.value === undefined) {
            return '';
          }
          return this.formatCurrency(params.value);
        },
      },
    ];

    this.resumenCuentasPagarGridOptions = {
      defaultColDef: {
        resizable: true,
        sortable: true,
        filter: true
      },
      pagination: true,
      paginationPageSize: 25,
      getRowClass: (params: any) => {
        if (params.node.rowPinned === 'bottom') {
          return 'ag-row-total-pagar';
        }
        return '';
      },
      enableCellTextSelection: true,
      ensureDomOrder: true,
      onCellDoubleClicked: (params: any) => {
        if (params.value !== null && params.value !== undefined) {
          const cellValue = params.value.toString();
          this.copiarAlPortapapeles(cellValue);
        }
      },
      onCellKeyDown: (params: any) => {
        const event = params.event;
        if ((event.ctrlKey || event.metaKey) && event.key === 'c') {
          event.preventDefault();
          this.copiarSeleccionAlPortapapelesResumen();
        }
      },
      onGridReady: (params: any) => {
        this.resumenGridApi = params.api;
        this.resumenGridColumnApi = params.columnApi;
        this.recalcularTotalesCxp();
        this.cdr.markForCheck();
      },
      onPaginationChanged: () => {
        this.maybeLoadMoreCxpFromGrid();
      },
      suppressExcelExport: false,
      suppressCsvExport: false
    };
  }

  get resumenCuentasPagarRows(): any[] {
    return this._resumenCuentasPagarRowsCache;
  }

  get totalesResumenCuentasPagar(): any {
    return this._totalesResumenCuentasPagarCache;
  }

  /** Totales pinned desde API (`totales`); no sumar solo la página cargada. */
  recalcularTotalesCxc(): void {
    this.aplicarPinnedTotalesCxc(this.cxcTotales);
  }

  /** Totales pinned desde API (`totales`); no sumar solo la página cargada. */
  recalcularTotalesCxp(): void {
    this.aplicarPinnedTotalesCxp(this.cxpTotales);
  }

  onQuickFilterChangeResumen(): void {
    this.quickFilterCxp$.next(this.quickFilterTextResumen.trim());
  }

  exportarCSVResumen(): void {
    this.exportarCxpCompletoCsv();
  }

  exportarExcelResumen(): void {
    this.exportarCxpCompletoCsv();
  }

  private exportarCxpCompletoCsv(): void {
    if (this.cxpExporting) return;
    this.cxpExporting = true;
    this.cdr.markForCheck();
    this.dashboardDataService
      .obtenerDetalleCxpCompleto(this.getFiltrosCxpDetalle(), {
        q: this.getCxpSearchQ() || undefined,
      })
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: (page) => {
          const fecha = new Date().toISOString().split('T')[0];
          const cols = this.resumenCuentasPagarColumnDefs.filter((c) => c.field);
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
          a.download = `resumen-cuentas-por-pagar-${fecha}.csv`;
          a.click();
          URL.revokeObjectURL(url);
          this.cxpExporting = false;
          this.cdr.markForCheck();
        },
        error: () => {
          this.cxpExporting = false;
          this.cdr.markForCheck();
        },
      });
  }

  limpiarFiltrosGridResumen(): void {
    if (this.resumenGridApi) {
      this.resumenGridApi.setFilterModel(null);
    }
    this.quickFilterTextResumen = '';
    this.cargarDetalleCxpPagina(0);
  }

  copiarSeleccionAlPortapapelesResumen(): void {
    if (!this.resumenGridApi) return;

    const selectedRanges = this.resumenGridApi.getCellRanges();
    
    if (selectedRanges && selectedRanges.length > 0) {
      const range = selectedRanges[0];
      const rows: string[] = [];
      const allColumns = this.resumenGridColumnApi?.getAllColumns() || [];
      if (allColumns.length === 0) return;

      const startRowIndex = range.startRow?.rowIndex || 0;
      const endRowIndex = range.endRow?.rowIndex || 0;
      const startColId = range.startColumn?.getColId() || '';
      const columns = range.columns || [];
      const endColId = columns.length > 0 ? columns[columns.length - 1]?.getColId() : startColId;
      
      let startColIndex = -1;
      let endColIndex = -1;
      
      allColumns.forEach((col: any, index: number) => {
        const colId = col.getColId();
        if (colId === startColId && startColIndex === -1) {
          startColIndex = index;
        }
        if (colId === endColId) {
          endColIndex = index;
        }
      });

      for (let rowIndex = startRowIndex; rowIndex <= endRowIndex; rowIndex++) {
        const row: string[] = [];
        const node = this.resumenGridApi.getDisplayedRowAtIndex(rowIndex);
        
        if (node && startColIndex >= 0 && endColIndex >= 0) {
          allColumns.forEach((column: any, colIndex: number) => {
            if (colIndex >= startColIndex && colIndex <= endColIndex) {
              const colId = column.getColId();
              const value = node.data[colId] || '';
              row.push(value.toString());
            }
          });
        }
        
        if (row.length > 0) {
          rows.push(row.join('\t'));
        }
      }
      
      if (rows.length > 0) {
        this.copiarAlPortapapeles(rows.join('\n'));
      }
    } else {
      const selectedRows = this.resumenGridApi.getSelectedRows();
      if (selectedRows.length > 0) {
        const headers = this.resumenCuentasPagarColumnDefs
          .map(col => col.headerName || col.field)
          .join('\t');
        
        const rows = selectedRows
          .filter((row: any) => !row.isTotal)
          .map((row: any) => {
            return this.resumenCuentasPagarColumnDefs
              .map(col => {
                const value = row[col.field || ''] || '';
                return value.toString();
              })
              .join('\t');
          });
        
        const texto = [headers, ...rows].join('\n');
        this.copiarAlPortapapeles(texto);
      } else {
        const allRows: string[] = [];
        const headers = this.resumenCuentasPagarColumnDefs
          .map(col => col.headerName || col.field)
          .join('\t');
        
        this.resumenGridApi.forEachNodeAfterFilterAndSort((node: any) => {
          if (!node.data?.isTotal) {
            const row = this.resumenCuentasPagarColumnDefs
              .map(col => {
                const value = node.data[col.field || ''] || '';
                return value.toString();
              })
              .join('\t');
            allRows.push(row);
          }
        });
        
        const texto = [headers, ...allRows].join('\n');
        this.copiarAlPortapapeles(texto);
      }
    }
  }

  configurarAGGrid(): void {
    const estiloCxc = (
      align: 'left' | 'center' | 'right',
    ): ((params: any) => any) => {
      return (params: any): any => {
        if (params.node.rowPinned === 'bottom') {
          return {
            fontWeight: '600',
            textAlign: align,
          };
        }
        return { textAlign: align } as any;
      };
    };

    this.detalleCuentasColumnDefs = [
      { 
        field: 'cliente', 
        headerName: 'Cliente',
        width: 250,
        sortable: true,
        filter: true,
        cellStyle: estiloCxc('left')
      },
      { 
        field: 'factura', 
        headerName: '# factura',
        width: 120,
        sortable: true,
        filter: true,
        cellStyle: estiloCxc('center')
      },
      { 
        field: 'fechaVenta', 
        headerName: 'Fecha de venta',
        width: 130,
        sortable: true,
        filter: true,
        cellStyle: estiloCxc('center')
      },
      { 
        field: 'fechaPago', 
        headerName: 'fecha_pago',
        width: 130,
        sortable: true,
        filter: true,
        cellStyle: estiloCxc('center')
      },
      { 
        field: 'diasVencimiento', 
        headerName: 'Dias vencimiento',
        width: 150,
        sortable: true,
        filter: true,
        cellStyle: estiloCxc('right'),
        valueFormatter: (params: any) => {
          return params.value ? params.value.toLocaleString('es-GT') : '';
        }
      },
      { 
        field: 'estado', 
        headerName: 'Estado',
        width: 130,
        sortable: true,
        filter: true,
        cellRenderer: (params: any) => {
          if (params.node.rowPinned === 'bottom') {
            return '';
          }
          const estado = params.value || '';
          const iconClass = 'fas fa-circle';
          const color = estado === 'Vigente' ? '#28a745' : '#6c757d';
          return `<span><i class="${iconClass}" style="color: ${color}; font-size: 8px; margin-right: 5px;"></i>${estado}</span>`;
        },
        cellStyle: estiloCxc('left')
      },
      { 
        field: 'ventasConIVA', 
        headerName: 'Ventas con IVA',
        width: 150,
        sortable: true,
        filter: true,
        cellStyle: estiloCxc('right'),
        valueFormatter: (params: any) => {
          if (params.value === null || params.value === undefined) {
            return '';
          }
          return this.formatCurrency(params.value);
        }
      },
      { 
        field: 'montoAbonado', 
        headerName: 'Monto abonado',
        width: 150,
        sortable: true,
        filter: true,
        cellStyle: estiloCxc('right'),
        valueFormatter: (params: any) => {
          if (params.value === null || params.value === undefined) {
            return '';
          }
          return this.formatCurrency(params.value);
        }
      },
      { 
        field: 'diasAbono', 
        headerName: 'Dias abono',
        width: 120,
        sortable: true,
        filter: true,
        cellStyle: estiloCxc('right'),
        valueFormatter: (params: any) => {
          if (params.node.rowPinned === 'bottom') {
            return '';
          }
          return params.value ? params.value.toLocaleString('es-GT') : '';
        }
      },
      { 
        field: 'saldoPendiente', 
        headerName: 'Saldo pendiente (con IVA)',
        width: 200,
        sortable: true,
        filter: true,
        cellStyle: estiloCxc('right'),
        valueFormatter: (params: any) => {
          if (params.value === null || params.value === undefined) {
            return '';
          }
          return this.formatCurrency(params.value);
        }
      }
    ];

    this.detalleCuentasGridOptions = {
      defaultColDef: {
        resizable: true,
        sortable: true,
        filter: true
      },
      pagination: true,
      paginationPageSize: 25,
      getRowClass: (params: any) => {
        if (params.node.rowPinned === 'bottom') {
          return 'ag-row-total';
        }
        return '';
      },
      enableCellTextSelection: true,
      ensureDomOrder: true,
      onCellDoubleClicked: (params: any) => {
        if (params.value !== null && params.value !== undefined) {
          const cellValue = params.value.toString();
          this.copiarAlPortapapeles(cellValue);
        }
      },
      onCellKeyDown: (params: any) => {
        const event = params.event;
        if ((event.ctrlKey || event.metaKey) && event.key === 'c') {
          event.preventDefault();
          this.copiarSeleccionAlPortapapeles();
        }
      },
      onGridReady: (params: any) => {
        this.gridApi = params.api;
        this.gridColumnApi = params.columnApi;
        this.recalcularTotalesCxc();
        this.cdr.markForCheck();
      },
      onPaginationChanged: () => {
        this.maybeLoadMoreCxcFromGrid();
      },
      suppressExcelExport: false,
      suppressCsvExport: false
    };
  }

  get detalleCuentasRows(): any[] {
    return this._detalleCuentasRowsCache;
  }

  get totalesDetalleCuentas(): any {
    return this._totalesDetalleCuentasCache;
  }

  onQuickFilterChange(): void {
    this.quickFilterCxc$.next(this.quickFilterText.trim());
  }

  exportarCSV(): void {
    this.exportarCxcCompletoCsv();
  }

  exportarExcel(): void {
    this.exportarCxcCompletoCsv();
  }

  private exportarCxcCompletoCsv(): void {
    if (this.cxcExporting) return;
    this.cxcExporting = true;
    this.cdr.markForCheck();
    this.dashboardDataService
      .obtenerDetalleCxcCompleto(this.getFiltrosCxcDetalle(), {
        q: this.getCxcSearchQ() || undefined,
      })
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: (page) => {
          const fecha = new Date().toISOString().split('T')[0];
          const cols = this.detalleCuentasColumnDefs.filter((c) => c.field);
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
          a.download = `detalle-cuentas-por-cobrar-${fecha}.csv`;
          a.click();
          URL.revokeObjectURL(url);
          this.cxcExporting = false;
          this.cdr.markForCheck();
        },
        error: () => {
          this.cxcExporting = false;
          this.cdr.markForCheck();
        },
      });
  }

  limpiarFiltrosGrid(): void {
    if (this.gridApi) {
      this.gridApi.setFilterModel(null);
    }
    this.quickFilterText = '';
    this.cargarDetalleCxcPagina(0);
  }

  copiarAlPortapapeles(texto: string): void {
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(texto).then(() => {
        // console.log('Valor copiado al portapapeles');
      }).catch(err => {
        // console.error('Error al copiar:', err);
        this.copiarAlPortapapelesFallback(texto);
      });
    } else {
      this.copiarAlPortapapelesFallback(texto);
    }
  }

  copiarAlPortapapelesFallback(texto: string): void {
    const textArea = document.createElement('textarea');
    textArea.value = texto;
    textArea.style.position = 'fixed';
    textArea.style.left = '-999999px';
    textArea.style.top = '-999999px';
    document.body.appendChild(textArea);
    textArea.focus();
    textArea.select();
    try {
      document.execCommand('copy');
      // console.log('Valor copiado al portapapeles (fallback)');
    } catch (err) {
      // console.error('Error al copiar:', err);
    }
    document.body.removeChild(textArea);
  }

  copiarSeleccionAlPortapapeles(): void {
    if (!this.gridApi) return;

    const selectedRanges = this.gridApi.getCellRanges();
    
    if (selectedRanges && selectedRanges.length > 0) {
      const range = selectedRanges[0];
      const rows: string[] = [];
      const allColumns = this.gridColumnApi?.getAllColumns() || [];
      if (allColumns.length === 0) return;

      const startRowIndex = range.startRow?.rowIndex || 0;
      const endRowIndex = range.endRow?.rowIndex || 0;
      const startColId = range.startColumn?.getColId() || '';
      const columns = range.columns || [];
      const endColId = columns.length > 0 ? columns[columns.length - 1]?.getColId() : startColId;
      
      let startColIndex = -1;
      let endColIndex = -1;
      
      allColumns.forEach((col: any, index: number) => {
        const colId = col.getColId();
        if (colId === startColId && startColIndex === -1) {
          startColIndex = index;
        }
        if (colId === endColId) {
          endColIndex = index;
        }
      });

      for (let rowIndex = startRowIndex; rowIndex <= endRowIndex; rowIndex++) {
        const row: string[] = [];
        const node = this.gridApi.getDisplayedRowAtIndex(rowIndex);
        
        if (node && startColIndex >= 0 && endColIndex >= 0) {
          allColumns.forEach((column: any, colIndex: number) => {
            if (colIndex >= startColIndex && colIndex <= endColIndex) {
              const colId = column.getColId();
              const value = node.data[colId] || '';
              row.push(value.toString());
            }
          });
        }
        
        if (row.length > 0) {
          rows.push(row.join('\t'));
        }
      }
      
      if (rows.length > 0) {
        this.copiarAlPortapapeles(rows.join('\n'));
      }
    } else {
      const selectedRows = this.gridApi.getSelectedRows();
      if (selectedRows.length > 0) {
        const headers = this.detalleCuentasColumnDefs
          .map(col => col.headerName || col.field)
          .join('\t');
        
        const rows = selectedRows
          .filter((row: any) => !row.isTotal)
          .map((row: any) => {
            return this.detalleCuentasColumnDefs
              .map(col => {
                const value = row[col.field || ''] || '';
                return value.toString();
              })
              .join('\t');
          });
        
        const texto = [headers, ...rows].join('\n');
        this.copiarAlPortapapeles(texto);
      } else {
        const allRows: string[] = [];
        const headers = this.detalleCuentasColumnDefs
          .map(col => col.headerName || col.field)
          .join('\t');
        
        this.gridApi.forEachNodeAfterFilterAndSort((node: any) => {
          if (!node.data?.isTotal) {
            const row = this.detalleCuentasColumnDefs
              .map(col => {
                const value = node.data[col.field || ''] || '';
                return value.toString();
              })
              .join('\t');
            allRows.push(row);
          }
        });
        
        const texto = [headers, ...allRows].join('\n');
        this.copiarAlPortapapeles(texto);
      }
    }
  }

  /**
   * TrackBy functions para optimización de *ngFor
   */
  trackByIndex(index: number, item: any): number {
    return index;
  }

  trackByCliente(index: number, item: any): string | number {
    return item.cliente ? `${item.cliente}_${item.factura || ''}` : index;
  }

  trackByFecha(index: number, item: any): string | number {
    return item.fechaCompra ? `${item.fechaCompra}_${item.vencimiento || ''}` : index;
  }

  trackById(index: number, item: any): string | number {
    return item.id || index;
  }

  trackByName(index: number, item: any): string | number {
    return item.name || item.nombre || index;
  }
}
