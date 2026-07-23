import { Component, Input, OnInit, OnChanges, SimpleChanges, Output, EventEmitter, ViewChild, ChangeDetectorRef, ChangeDetectionStrategy, OnDestroy } from '@angular/core';
import { Subject } from 'rxjs';
import { debounceTime, distinctUntilChanged, switchMap, takeUntil } from 'rxjs/operators';
import { DashboardDataService } from '../../services/dashboard-data.service';
import { ApiService } from '@services/api.service';
import {
  DashboardFiltrosCatalogoService,
  DashboardFiltroCatalogoItem,
} from '../../services/dashboard-filtros-catalogo.service';
import {
  DropdownMultiFiltroItem,
  DropdownMultiFiltroSelection,
} from '../../components/dropdown-multi-filtro/dropdown-multi-filtro.component';
import { ColDef, GridOptions, GridApi, ColumnApi } from 'ag-grid-community';
import { formatEmpresaCurrency, getEmpresaCurrencySymbol } from '@helpers/currency-format.helper';
import { MetricCard } from '../../models/chart-config.model';
import {
  DetalleProductosTotales,
  DetalleAjustesTotales,
  DetalleEsTotales,
} from '../../services/inventario-dashboard-data.service';

@Component({
  selector: 'app-inventario',
  templateUrl: './inventario.component.html',
  styleUrls: ['./inventario.component.css'],
  changeDetection: ChangeDetectionStrategy.OnPush
})
export class InventarioComponent implements OnInit, OnChanges, OnDestroy {
  @Input() datos: any = {};
  @Input() datosCompletos = false;
  @Output() filtrosCambiados = new EventEmitter<any>();

  get metricasCards(): MetricCard[] {
    const m = (this.datosFiltrados?.metricasInventario) || this.datos?.metricasInventario || {};
    return [
      { title: 'Productos en stock', value: m.productosEnStock || 0, type: 'number' },
      { title: 'Promedio invertido',  value: m.promedioInvertido  || 0, type: 'currency' },
      { title: 'Ventas esperadas',    value: m.ventasEsperadas    || 0, type: 'currency' },
      { title: 'Utilidad esperada',   value: m.utilidadEsperada   || 0, type: 'currency' }
    ];
  }

  get metricasEntradasSalidasCards(): MetricCard[] {
    const m = (this.datosFiltrados?.entradasSalidas) || this.datos?.entradasSalidas || {};
    return [
      { title: 'Productos en stock', value: m.productosEnStock  || 0, type: 'number' },
      { title: 'Entradas',           value: m.entradas          || 0, type: 'number' },
      { title: 'Salidas',            value: m.salidas           || 0, type: 'number' },
      { title: 'Utilidad esperada',  value: m.utilidadEsperada  || 0, type: 'currency' }
    ];
  }

  get metricasAjustesCards(): MetricCard[] {
    const m = (this.datosFiltrados?.ajustes) || this.datos?.ajustes || {};
    return [
      { title: 'Productos en stock',    value: m.productosEnStock      || 0, type: 'number' },
      { title: 'Unidades perdidas',     value: m.unidadesPerdidas      || 0, type: 'number' },
      { title: 'Unidades recuperadas',  value: m.unidadesRecuperadas   || 0, type: 'number' },
      { title: 'Monto total recuperado',value: m.montoTotalRecuperado  || 0, type: 'currency' }
    ];
  }

  @ViewChild('inventarioProductosGrid') inventarioProductosGrid: any;
  @ViewChild('entradasSalidasGrid') entradasSalidasGrid: any;
  @ViewChild('ajustesGrid') ajustesGrid: any;

  // AG Grid configuration - Inventario productos (paginación server-side)
  inventarioProductosColumnDefs: ColDef[] = [];
  inventarioProductosGridOptions: GridOptions = {};
  pinnedBottomRowDataInventario: any[] = [];
  private gridApi!: GridApi;
  private gridColumnApi!: ColumnApi;
  quickFilterText: string = '';
  detalleListo = false;
  detalleLoading = false;
  detalleExporting = false;
  /** Chunks del API; la UI pagina con ag-grid a 15 (como antes). */
  readonly detallePageSize = 50;
  detalleOffset = 0;
  detalleTotal = 0;
  detalleTotales: DetalleProductosTotales = {
    stock: 0,
    inversionPromedio: 0,
    ventasEsperadas: 0,
  };
  private readonly destroy$ = new Subject<void>();
  private readonly detallePage$ = new Subject<{
    offset: number;
    q: string;
    append: boolean;
  }>();
  private readonly quickFilter$ = new Subject<string>();
  private detalleAppendPending = false;

  // AG Grid configuration - Entradas y salidas (paginación lazy)
  entradasSalidasColumnDefs: ColDef[] = [];
  entradasSalidasGridOptions: GridOptions = {};
  pinnedBottomRowDataEntradasSalidas: any[] = [];
  private entradasSalidasGridApi!: GridApi;
  private entradasSalidasGridColumnApi!: ColumnApi;
  quickFilterTextEntradasSalidas: string = '';
  esListo = false;
  esLoading = false;
  esExporting = false;
  readonly esPageSize = 50;
  esOffset = 0;
  esTotal = 0;
  esTotales: DetalleEsTotales = {
    entradas: 0,
    valorEntradas: 0,
    salidas: 0,
    valorSalidas: 0,
  };
  private readonly esPage$ = new Subject<{
    offset: number;
    q: string;
    append: boolean;
  }>();
  private readonly quickFilterEs$ = new Subject<string>();
  private esAppendPending = false;

  // AG Grid configuration - Ajustes (paginación lazy, mismo patrón que detalle productos)
  ajustesColumnDefs: ColDef[] = [];
  ajustesGridOptions: GridOptions = {};
  pinnedBottomRowDataAjustes: any[] = [];
  private ajustesGridApi!: GridApi;
  private ajustesGridColumnApi!: ColumnApi;
  quickFilterTextAjustes: string = '';
  ajustesListo = false;
  ajustesLoading = false;
  ajustesExporting = false;
  readonly ajustesPageSize = 50;
  ajustesOffset = 0;
  ajustesTotal = 0;
  ajustesTotales: DetalleAjustesTotales = { costoTotal: 0 };
  private readonly ajustesPage$ = new Subject<{
    offset: number;
    q: string;
    append: boolean;
  }>();
  private readonly quickFilterAjustes$ = new Subject<string>();
  private ajustesAppendPending = false;

  anio: string = new Date().getFullYear().toString();
  mes: string = '';
  
  // Filtros adicionales
  mostrarFiltrosAdicionales: boolean = false;
  filtroCategoria: string = '';
  filtroProducto: string = '';
  filtroSucursal: string = '';
  filtroProveedor: string = '';

  /** Valores ya emitidos al padre (año/mes siempre desde el control). */
  filtroCategoriaAplicado: string = '';
  filtroProductoAplicado: string = '';
  filtroSucursalAplicado: string = '';
  filtroProveedorAplicado: string = '';
  
  // Opciones para filtros (catálogos cargados desde el servicio)
  catalogoSucursales: DashboardFiltroCatalogoItem[] = [];
  catalogoCategorias: DashboardFiltroCatalogoItem[] = [];
  catalogoProductos:  DashboardFiltroCatalogoItem[] = [];
  catalogoProveedores: DashboardFiltroCatalogoItem[] = [];

  // Estado multi-select de cada filtro adicional (borrador)
  filtroAdSucursalTodasImplicitas  = true;
  filtroAdSucursalSeleccionadas:   string[] = [];
  filtroAdCategoriaTodasImplicitas = true;
  filtroAdCategoriaSeleccionadas:  string[] = [];
  filtroAdProductoTodasImplicitas  = true;
  filtroAdProductoSeleccionadas:   string[] = [];
  filtroAdProveedorTodasImplicitas = true;
  filtroAdProveedorSeleccionadas:  string[] = [];

  // Estado aplicado (para detectar cambios)
  filtroAdSucursalTodasImplicitasAplicado  = true;
  filtroAdSucursalSeleccionadasAplicado:   string[] = [];
  filtroAdCategoriaTodasImplicitasAplicado = true;
  filtroAdCategoriaSeleccionadasAplicado:  string[] = [];
  filtroAdProductoTodasImplicitasAplicado  = true;
  filtroAdProductoSeleccionadasAplicado:   string[] = [];
  filtroAdProveedorTodasImplicitasAplicado = true;
  filtroAdProveedorSeleccionadasAplicado:  string[] = [];

  // Mantener strings de compatibilidad (usadas en emitirFiltros)
  categorias:  any[] = [];
  productos:   any[] = [];
  sucursales:  any[] = [];
  proveedores: any[] = [];

  // Filtros interactivos (se aplican localmente sin recargar)
  filtrosInteractivos: {
    categoria?: string;
    producto?: string;
    mes?: string;
  } = {};
  
  // Datos originales (sin filtrar)
  datosOriginales: any = {};

  // Datos filtrados (se muestran en la vista)
  datosFiltrados: any = {};

  // Propiedades cacheadas para evitar recálculos
  private _inventarioProductosRowsCache: any[] = [];
  private _totalInventarioProductosCache: any = { stock: 0, inversionPromedio: 0, precio: 0, ventasEsperadas: 0 };
  private _entradasSalidasRowsCache: any[] = [];
  private _ajustesRowsCache: any[] = [];
  private _totalAjustesCache: any = { costoTotal: 0 };
  private _lastDatosHash: string = '';

  private inicializado: boolean = false;

  /** true mientras se espera la respuesta del servidor tras cambiar un filtro */
  filtrosLocked = false;
  private _filtrosLockTimeout: any = null;


  constructor(
    private cdr: ChangeDetectorRef,
    private dashboardDataService: DashboardDataService,
    private apiService: ApiService,
    private filtrosCatalogo: DashboardFiltrosCatalogoService
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
   * Formateadores con caché
   */
  private numberFormatter = new Intl.NumberFormat('es-GT', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 0
  });

  ngOnInit(): void {
    const savedState = this.dashboardDataService.obtenerFiltrosUI('Inventario');
    const tieneEstadoGuardado = !!savedState;
    if (savedState) {
      Object.assign(this, savedState);
    }

    this.cargarOpcionesFiltros();
    this.configurarAGGrid();
    this.configurarAGGridEntradasSalidas();
    this.configurarAGGridAjustes();
    this.wireDetalleProductosStreams();
    this.wireDetalleAjustesStreams();
    this.wireDetalleEsStreams();

    // Guardar datos originales si existen
    if (this.datos && Object.keys(this.datos).length > 0) {
      this.datosOriginales = this.clonarDatos(this.datos);
      this.datosFiltrados = this.clonarDatos(this.datos);
      // Asegurar que los arrays estén ordenados de mayor a menor
      this.ordenarArraysIniciales();
      this.recalcularRowsCache();
    }
    // Siempre emitir al padre tras restaurar UI; si no, al reentrar al dashboard
    // el padre queda sin datos y la vista se queda en loaders.
    setTimeout(() => {
      this.inicializado = true;
      this.aplicarFiltros();
      this.cdr.markForCheck();
    }, 100);
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
    this.dashboardDataService.guardarFiltrosUI('Inventario', {
      anio: this.anio,
      mes: this.mes,
      filtroCategoria: this.filtroCategoria,
      filtroProducto: this.filtroProducto,
      filtroSucursal: this.filtroSucursal,
      filtroProveedor: this.filtroProveedor,
      filtroCategoriaAplicado: this.filtroCategoriaAplicado,
      filtroProductoAplicado: this.filtroProductoAplicado,
      filtroSucursalAplicado: this.filtroSucursalAplicado,
      filtroProveedorAplicado: this.filtroProveedorAplicado,
      filtrosInteractivos: this.filtrosInteractivos
    });
  }

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['datos']) {
      // Actualizar datos originales cuando cambian
      if (this.datos && Object.keys(this.datos).length > 0) {
        this.datosOriginales = this.clonarDatos(this.datos);
        // Aplicar filtros interactivos si existen
        if (Object.keys(this.filtrosInteractivos).length > 0) {
          this.aplicarFiltrosInteractivos();
        } else {
          this.datosFiltrados = this.clonarDatos(this.datos);
          this.ordenarArraysIniciales();
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

  private wireDetalleProductosStreams(): void {
    this.detallePage$
      .pipe(
        switchMap(({ offset, q, append }) => {
          this.detalleAppendPending = append;
          this.detalleLoading = true;
          this.cdr.markForCheck();
          return this.dashboardDataService.obtenerDetalleProductosPagina(
            this.getFiltrosDetalle(),
            { limite: this.detallePageSize, offset, q: q || undefined },
          );
        }),
        takeUntil(this.destroy$),
      )
      .subscribe({
        next: (page) => {
          this.detalleOffset = page.offset;
          this.detalleTotal = page.total;
          this.detalleTotales = page.totales;
          const append = this.detalleAppendPending && (page.items?.length ?? 0) > 0;
          this.applyDetallePageRows(page.items, page.totales, append);
          this.detalleAppendPending = false;
          this.detalleListo = true;
          this.detalleLoading = false;
          this.cdr.markForCheck();
        },
        error: () => {
          this.detalleAppendPending = false;
          this.detalleLoading = false;
          this.detalleListo = true;
          this.cdr.markForCheck();
        },
      });

    this.quickFilter$
      .pipe(
        debounceTime(300),
        distinctUntilChanged(),
        takeUntil(this.destroy$),
      )
      .subscribe(() => {
        this.cargarDetallePagina(0);
      });
  }

  /** Filtros snapshot del panel + overrides interactivos (categoría del chart). */
  private getFiltrosDetalle(): any {
    const filtros: any = {
      sucursal: this.filtroAdSucursalTodasImplicitasAplicado
        ? ''
        : this.filtroAdSucursalSeleccionadasAplicado[0] || '',
      categoria: this.filtroAdCategoriaTodasImplicitasAplicado
        ? ''
        : this.filtroAdCategoriaSeleccionadasAplicado[0] || '',
      producto: this.filtroAdProductoTodasImplicitasAplicado
        ? ''
        : this.filtroAdProductoSeleccionadasAplicado[0] || '',
      proveedor: this.filtroAdProveedorTodasImplicitasAplicado
        ? ''
        : this.filtroAdProveedorSeleccionadasAplicado[0] || '',
    };
    if (this.filtrosInteractivos.categoria) {
      filtros.categoria = this.filtrosInteractivos.categoria;
    }
    return filtros;
  }

  /** Recarga desde offset (0 = reset). append=true concatena el siguiente chunk. */
  cargarDetallePagina(offset: number, append = false): void {
    const q = this.quickFilterText.trim();
    this.detallePage$.next({
      offset: Math.max(0, offset),
      q,
      append,
    });
  }

  /** Si el usuario llega a la última página del buffer, pide el siguiente chunk al API. */
  private maybeLoadMoreDetalleFromGrid(): void {
    if (!this.gridApi || this.detalleLoading || !this.detalleListo) return;
    const loaded = this._inventarioProductosRowsCache.length;
    if (loaded >= this.detalleTotal) return;
    const pageSize = this.gridApi.paginationGetPageSize() || 25;
    const currentPage = this.gridApi.paginationGetCurrentPage();
    // Evita prefetch en page 0 cuando chunk API (50) = 2 páginas UI (25).
    const lastLoadedPage = Math.max(0, Math.ceil(loaded / pageSize) - 1);
    if (currentPage >= lastLoadedPage) {
      this.cargarDetallePagina(loaded, true);
    }
  }

  private mapDetalleRows(items: any[]): any[] {
    return (items ?? []).map((item: any) => ({
      producto: item.producto || '-',
      stock: item.stock || 0,
      costo: item.costo || 0,
      inversionPromedio: item.inversionPromedio || 0,
      precio: item.precio || 0,
      ventasEsperadas: item.ventasEsperadas || 0,
    }));
  }

  private applyDetallePageRows(
    items: any[],
    totales: DetalleProductosTotales,
    append = false,
  ): void {
    const mapped = this.mapDetalleRows(items);
    if (append && this._inventarioProductosRowsCache.length > 0) {
      this._inventarioProductosRowsCache = [
        ...this._inventarioProductosRowsCache,
        ...mapped,
      ];
    } else {
      this._inventarioProductosRowsCache = mapped;
      if (this.gridApi) {
        this.gridApi.paginationGoToFirstPage();
      }
    }
    this._totalInventarioProductosCache = { ...totales };
    if (
      totales.stock !== 0 ||
      totales.inversionPromedio !== 0 ||
      totales.ventasEsperadas !== 0
    ) {
      this.pinnedBottomRowDataInventario = [
        {
          producto: 'Total',
          stock: totales.stock,
          costo: null,
          inversionPromedio: totales.inversionPromedio,
          precio: null,
          ventasEsperadas: totales.ventasEsperadas,
        },
      ];
    } else {
      this.pinnedBottomRowDataInventario = [];
    }
  }

  private wireDetalleAjustesStreams(): void {
    this.ajustesPage$
      .pipe(
        switchMap(({ offset, q, append }) => {
          this.ajustesAppendPending = append;
          this.ajustesLoading = true;
          this.cdr.markForCheck();
          return this.dashboardDataService.obtenerDetalleAjustesPagina(
            this.getFiltrosAjustes(),
            { limite: this.ajustesPageSize, offset, q: q || undefined },
          );
        }),
        takeUntil(this.destroy$),
      )
      .subscribe({
        next: (page) => {
          this.ajustesOffset = page.offset;
          this.ajustesTotal = page.total;
          this.ajustesTotales = page.totales;
          const append =
            this.ajustesAppendPending && (page.items?.length ?? 0) > 0;
          this.applyAjustesPageRows(page.items, page.totales, append);
          this.ajustesAppendPending = false;
          this.ajustesListo = true;
          this.ajustesLoading = false;
          this.cdr.markForCheck();
        },
        error: () => {
          this.ajustesAppendPending = false;
          this.ajustesLoading = false;
          this.ajustesListo = true;
          this.cdr.markForCheck();
        },
      });

    this.quickFilterAjustes$
      .pipe(
        debounceTime(300),
        distinctUntilChanged(),
        takeUntil(this.destroy$),
      )
      .subscribe(() => {
        this.cargarDetalleAjustesPagina(0);
      });
  }

  /** Filtros panel (anio/mes) para ajustes. */
  private getFiltrosAjustes(): any {
    const filtros = this.getFiltrosDetalle();
    filtros.anio = this.anio;
    if (this.mes) filtros.mes = this.mes;
    return filtros;
  }

  /** Filtros para E/S detalle: panel + mes interactivo del chart. */
  private getFiltrosMovimientos(): any {
    const filtros = this.getFiltrosAjustes();
    if (this.filtrosInteractivos.mes) {
      const meses = [
        'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
        'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre',
      ];
      const idx = meses.findIndex(
        (m) =>
          m.toLowerCase() === this.filtrosInteractivos.mes?.toLowerCase(),
      );
      if (idx !== -1) filtros.mes = idx + 1;
    }
    return filtros;
  }

  private wireDetalleEsStreams(): void {
    this.esPage$
      .pipe(
        switchMap(({ offset, q, append }) => {
          this.esAppendPending = append;
          this.esLoading = true;
          this.cdr.markForCheck();
          return this.dashboardDataService.obtenerDetalleEsPagina(
            this.getFiltrosMovimientos(),
            { limite: this.esPageSize, offset, q: q || undefined },
          );
        }),
        takeUntil(this.destroy$),
      )
      .subscribe({
        next: (page) => {
          this.esOffset = page.offset;
          this.esTotal = page.total;
          this.esTotales = page.totales;
          const append =
            this.esAppendPending && (page.items?.length ?? 0) > 0;
          this.applyEsPageRows(page.items, page.totales, append);
          this.esAppendPending = false;
          this.esListo = true;
          this.esLoading = false;
          this.cdr.markForCheck();
        },
        error: () => {
          this.esAppendPending = false;
          this.esLoading = false;
          this.esListo = true;
          this.cdr.markForCheck();
        },
      });

    this.quickFilterEs$
      .pipe(
        debounceTime(300),
        distinctUntilChanged(),
        takeUntil(this.destroy$),
      )
      .subscribe(() => {
        this.cargarDetalleEsPagina(0);
      });
  }

  cargarDetalleEsPagina(offset: number, append = false): void {
    const q = this.quickFilterTextEntradasSalidas.trim();
    this.esPage$.next({
      offset: Math.max(0, offset),
      q,
      append,
    });
  }

  private maybeLoadMoreEsFromGrid(): void {
    if (!this.entradasSalidasGridApi || this.esLoading || !this.esListo) {
      return;
    }
    const loaded = this._entradasSalidasRowsCache.length;
    if (loaded >= this.esTotal) return;
    const pageSize = this.entradasSalidasGridApi.paginationGetPageSize() || 25;
    const currentPage = this.entradasSalidasGridApi.paginationGetCurrentPage();
    const lastLoadedPage = Math.max(0, Math.ceil(loaded / pageSize) - 1);
    if (currentPage >= lastLoadedPage) {
      this.cargarDetalleEsPagina(loaded, true);
    }
  }

  private mapEsRows(items: any[]): any[] {
    return (items ?? []).map((item: any) => ({
      fecha: String(item.fecha ?? '').trim() || '-',
      mes: item.mes ?? null,
      producto: item.producto || '-',
      concepto: item.concepto || '-',
      referencia: item.referencia || '-',
      entradas: item.entradas ?? null,
      valorEntradas: item.valorEntradas ?? null,
      salidas: item.salidas ?? null,
      valorSalidas: item.valorSalidas ?? null,
    }));
  }

  private applyEsPageRows(
    items: any[],
    totales: DetalleEsTotales,
    append = false,
  ): void {
    const mapped = this.mapEsRows(items);
    if (append && this._entradasSalidasRowsCache.length > 0) {
      this._entradasSalidasRowsCache = [
        ...this._entradasSalidasRowsCache,
        ...mapped,
      ];
    } else {
      this._entradasSalidasRowsCache = mapped;
      if (this.entradasSalidasGridApi) {
        this.entradasSalidasGridApi.paginationGoToFirstPage();
      }
    }
    if (totales.entradas > 0 || totales.salidas > 0) {
      this.pinnedBottomRowDataEntradasSalidas = [
        {
          fecha: 'Total',
          producto: '',
          concepto: '',
          referencia: '',
          entradas: totales.entradas,
          valorEntradas: totales.valorEntradas,
          salidas: totales.salidas,
          valorSalidas: totales.valorSalidas,
        },
      ];
    } else {
      this.pinnedBottomRowDataEntradasSalidas = [];
    }
  }

  cargarDetalleAjustesPagina(offset: number, append = false): void {
    const q = this.quickFilterTextAjustes.trim();
    this.ajustesPage$.next({
      offset: Math.max(0, offset),
      q,
      append,
    });
  }

  private maybeLoadMoreAjustesFromGrid(): void {
    if (!this.ajustesGridApi || this.ajustesLoading || !this.ajustesListo) {
      return;
    }
    const loaded = this._ajustesRowsCache.length;
    if (loaded >= this.ajustesTotal) return;
    const pageSize = this.ajustesGridApi.paginationGetPageSize() || 25;
    const currentPage = this.ajustesGridApi.paginationGetCurrentPage();
    const lastLoadedPage = Math.max(0, Math.ceil(loaded / pageSize) - 1);
    if (currentPage >= lastLoadedPage) {
      this.cargarDetalleAjustesPagina(loaded, true);
    }
  }

  private mapAjustesRows(items: any[]): any[] {
    return (items ?? []).map((item: any) => ({
      fecha: item.fecha || '-',
      producto: item.producto || '-',
      concepto: item.concepto || '-',
      stockInicial: item.stockInicial || 0,
      stockReal: item.stockReal || 0,
      ajuste: item.ajuste || 0,
      costoTotal: item.costoTotal || 0,
      isTotal: false,
    }));
  }

  private applyAjustesPageRows(
    items: any[],
    totales: DetalleAjustesTotales,
    append = false,
  ): void {
    const mapped = this.mapAjustesRows(items);
    if (append && this._ajustesRowsCache.length > 0) {
      this._ajustesRowsCache = [...this._ajustesRowsCache, ...mapped];
    } else {
      this._ajustesRowsCache = mapped;
      if (this.ajustesGridApi) {
        this.ajustesGridApi.paginationGoToFirstPage();
      }
    }
    this._totalAjustesCache = { ...totales };
    if (totales.costoTotal !== 0) {
      this.pinnedBottomRowDataAjustes = [
        {
          fecha: 'Total',
          producto: '',
          concepto: '',
          stockInicial: '',
          stockReal: '',
          ajuste: '',
          costoTotal: totales.costoTotal,
          isTotal: true,
        },
      ];
    } else {
      this.pinnedBottomRowDataAjustes = [];
    }
  }

  cargarOpcionesFiltros(): void {
    this.filtrosCatalogo.sucursalesParaFiltro().subscribe({
      next: (items) => {
        this.catalogoSucursales = items;
        this.sucursales = items;
        const user = this.apiService.auth_user();
        if (items.length > 0 && user?.tipo !== 'Administrador' && user?.id_sucursal != null) {
          this.filtroAdSucursalTodasImplicitas = false;
          this.filtroAdSucursalSeleccionadas = [String(user.id_sucursal)];
          this.filtroAdSucursalTodasImplicitasAplicado = false;
          this.filtroAdSucursalSeleccionadasAplicado = [String(user.id_sucursal)];
        }
        this.cdr.markForCheck();
      },
    });

    this.filtrosCatalogo.categoriasParaFiltro().subscribe({
      next: (items) => {
        this.catalogoCategorias = items;
        this.categorias = items;
        this.cdr.markForCheck();
      },
    });

    this.filtrosCatalogo.productosParaFiltro().subscribe({
      next: (items) => {
        this.catalogoProductos = [...(items || [])].sort((a, b) =>
          a.nombre.localeCompare(b.nombre, 'es')
        );
        this.productos = this.catalogoProductos;
        this.cdr.markForCheck();
      },
    });

    this.filtrosCatalogo.proveedoresParaFiltro().subscribe({
      next: (items) => {
        this.catalogoProveedores = items;
        this.proveedores = items;
        this.cdr.markForCheck();
      },
    });
  }

  // ── Getters de items para app-dropdown-multi-filtro ──────────────────────
  get filtroAdSucursalesItems(): DropdownMultiFiltroItem[] {
    return this.catalogoSucursales.map((s) => ({ id: String(s.id), nombre: s.nombre ?? '' }));
  }
  get filtroAdCategoriasItems(): DropdownMultiFiltroItem[] {
    return this.catalogoCategorias.map((c) => ({ id: String(c.id), nombre: c.nombre ?? '' }));
  }
  get filtroAdProductosItems(): DropdownMultiFiltroItem[] {
    return this.catalogoProductos.map((p) => ({ id: String(p.id), nombre: p.nombre ?? '' }));
  }
  get filtroAdProveedoresItems(): DropdownMultiFiltroItem[] {
    return this.catalogoProveedores.map((v) => ({ id: String(v.id), nombre: v.nombre ?? '' }));
  }

  // ── Handlers de cambio de selección ─────────────────────────────────────
  onFiltroAdSucursalChange(ev: DropdownMultiFiltroSelection): void {
    this.filtroAdSucursalTodasImplicitas = ev.todasImplicitas;
    this.filtroAdSucursalSeleccionadas = [...ev.seleccionados];
    this.cdr.markForCheck();
  }
  onFiltroAdCategoriaChange(ev: DropdownMultiFiltroSelection): void {
    this.filtroAdCategoriaTodasImplicitas = ev.todasImplicitas;
    this.filtroAdCategoriaSeleccionadas = [...ev.seleccionados];
    this.cdr.markForCheck();
  }
  onFiltroAdProductoChange(ev: DropdownMultiFiltroSelection): void {
    this.filtroAdProductoTodasImplicitas = ev.todasImplicitas;
    this.filtroAdProductoSeleccionadas = [...ev.seleccionados];
    this.cdr.markForCheck();
  }
  onFiltroAdProveedorChange(ev: DropdownMultiFiltroSelection): void {
    this.filtroAdProveedorTodasImplicitas = ev.todasImplicitas;
    this.filtroAdProveedorSeleccionadas = [...ev.seleccionados];
    this.cdr.markForCheck();
  }

  private copiarFiltrosAdicionalesInventarioAplicadoABorrador(): void {
    this.filtroAdSucursalTodasImplicitas  = this.filtroAdSucursalTodasImplicitasAplicado;
    this.filtroAdSucursalSeleccionadas    = [...this.filtroAdSucursalSeleccionadasAplicado];
    this.filtroAdCategoriaTodasImplicitas = this.filtroAdCategoriaTodasImplicitasAplicado;
    this.filtroAdCategoriaSeleccionadas   = [...this.filtroAdCategoriaSeleccionadasAplicado];
    this.filtroAdProductoTodasImplicitas  = this.filtroAdProductoTodasImplicitasAplicado;
    this.filtroAdProductoSeleccionadas    = [...this.filtroAdProductoSeleccionadasAplicado];
    this.filtroAdProveedorTodasImplicitas = this.filtroAdProveedorTodasImplicitasAplicado;
    this.filtroAdProveedorSeleccionadas   = [...this.filtroAdProveedorSeleccionadasAplicado];
  }

  private copiarFiltrosAdicionalesInventarioBorradorAAplicado(): void {
    this.filtroAdSucursalTodasImplicitasAplicado  = this.filtroAdSucursalTodasImplicitas;
    this.filtroAdSucursalSeleccionadasAplicado    = [...this.filtroAdSucursalSeleccionadas];
    this.filtroAdCategoriaTodasImplicitasAplicado = this.filtroAdCategoriaTodasImplicitas;
    this.filtroAdCategoriaSeleccionadasAplicado   = [...this.filtroAdCategoriaSeleccionadas];
    this.filtroAdProductoTodasImplicitasAplicado  = this.filtroAdProductoTodasImplicitas;
    this.filtroAdProductoSeleccionadasAplicado    = [...this.filtroAdProductoSeleccionadas];
    this.filtroAdProveedorTodasImplicitasAplicado = this.filtroAdProveedorTodasImplicitas;
    this.filtroAdProveedorSeleccionadasAplicado   = [...this.filtroAdProveedorSeleccionadas];
  }

  toggleFiltrosAdicionales(): void {
    this.mostrarFiltrosAdicionales = !this.mostrarFiltrosAdicionales;
    this.copiarFiltrosAdicionalesInventarioAplicadoABorrador();
    this.cdr.markForCheck();
  }

  /** Confirma categoría/producto/sucursal/proveedor y emite al padre. */
  confirmarOtrosFiltrosInventario(): void {
    this.copiarFiltrosAdicionalesInventarioBorradorAAplicado();
    this.aplicarFiltros();
    this.cdr.markForCheck();
  }

  get mostrarBotonAplicarOtrosFiltrosInventario(): boolean {
    if (!this.mostrarFiltrosAdicionales) return false;
    const eq = (a: string[], b: string[]) =>
      a.length === b.length && a.every((v, i) => v === b[i]);
    return (
      this.filtroAdSucursalTodasImplicitas  !== this.filtroAdSucursalTodasImplicitasAplicado  ||
      !eq(this.filtroAdSucursalSeleccionadas,   this.filtroAdSucursalSeleccionadasAplicado)   ||
      this.filtroAdCategoriaTodasImplicitas !== this.filtroAdCategoriaTodasImplicitasAplicado ||
      !eq(this.filtroAdCategoriaSeleccionadas,  this.filtroAdCategoriaSeleccionadasAplicado)  ||
      this.filtroAdProductoTodasImplicitas  !== this.filtroAdProductoTodasImplicitasAplicado  ||
      !eq(this.filtroAdProductoSeleccionadas,   this.filtroAdProductoSeleccionadasAplicado)   ||
      this.filtroAdProveedorTodasImplicitas !== this.filtroAdProveedorTodasImplicitasAplicado ||
      !eq(this.filtroAdProveedorSeleccionadas,  this.filtroAdProveedorSeleccionadasAplicado)
    );
  }

  limpiarFiltros(): void {
    this.anio = new Date().getFullYear().toString();
    this.mes = '';
    // Resetear estado multi-select (borrador + aplicado)
    this.filtroAdSucursalTodasImplicitas  = true;  this.filtroAdSucursalSeleccionadas   = [];
    this.filtroAdCategoriaTodasImplicitas = true;  this.filtroAdCategoriaSeleccionadas  = [];
    this.filtroAdProductoTodasImplicitas  = true;  this.filtroAdProductoSeleccionadas   = [];
    this.filtroAdProveedorTodasImplicitas = true;  this.filtroAdProveedorSeleccionadas  = [];
    this.copiarFiltrosAdicionalesInventarioBorradorAAplicado();
    this.limpiarFiltrosInteractivos();
    this.aplicarFiltros();
  }

  aplicarFiltros(): void {
    if (!this.inicializado) return;

    // Bloquear filtros mientras se espera la respuesta
    this._bloquearFiltros();

    if (!this.anio) this.anio = new Date().getFullYear().toString();


    const filtros: any = {
      anio: this.anio,
      // Enviar arrays multi-select al padre
      sucursales: this.filtroAdSucursalTodasImplicitasAplicado  ? [] : this.filtroAdSucursalSeleccionadasAplicado,
      categorias: this.filtroAdCategoriaTodasImplicitasAplicado ? [] : this.filtroAdCategoriaSeleccionadasAplicado,
      productos:  this.filtroAdProductoTodasImplicitasAplicado  ? [] : this.filtroAdProductoSeleccionadasAplicado,
      proveedores:this.filtroAdProveedorTodasImplicitasAplicado ? [] : this.filtroAdProveedorSeleccionadasAplicado,
      // Compatibilidad con lógica previa (primer elemento o vacío)
      sucursal:   this.filtroAdSucursalTodasImplicitasAplicado  ? '' : (this.filtroAdSucursalSeleccionadasAplicado[0]  || ''),
      categoria:  this.filtroAdCategoriaTodasImplicitasAplicado ? '' : (this.filtroAdCategoriaSeleccionadasAplicado[0] || ''),
      producto:   this.filtroAdProductoTodasImplicitasAplicado  ? '' : (this.filtroAdProductoSeleccionadasAplicado[0]  || ''),
      proveedor:  this.filtroAdProveedorTodasImplicitasAplicado ? '' : (this.filtroAdProveedorSeleccionadasAplicado[0] || ''),
    };
    if (this.mes) filtros.mes = this.mes;
    this.filtrosCambiados.emit(filtros);
    this._desbloquearSiPadreOmiteRecarga();
    this.cargarDetallePagina(0);
    this.cargarDetalleEsPagina(0);
    this.cargarDetalleAjustesPagina(0);
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


  get puedeLimpiarFiltrosInventario(): boolean {
    const anioActual = new Date().getFullYear().toString();
    if (!!this.mes || this.anio !== anioActual) return true;
    if (this.tieneFiltrosInteractivos()) return true;
    const tieneMulti =
      !this.filtroAdSucursalTodasImplicitasAplicado  || this.filtroAdSucursalSeleccionadasAplicado.length  > 0 ||
      !this.filtroAdCategoriaTodasImplicitasAplicado || this.filtroAdCategoriaSeleccionadasAplicado.length > 0 ||
      !this.filtroAdProductoTodasImplicitasAplicado  || this.filtroAdProductoSeleccionadasAplicado.length  > 0 ||
      !this.filtroAdProveedorTodasImplicitasAplicado || this.filtroAdProveedorSeleccionadasAplicado.length > 0;
    return tieneMulti;
  }

  formatCurrency(value: number): string {
    if (value === null || value === undefined) {
      value = 0;
    }
    return formatEmpresaCurrency(value, this.apiService.auth_user()?.empresa);
  }

  formatNumber(value: number): string {
    return this.numberFormatter.format(value);
  }

  configurarAGGrid(): void {
    this.inventarioProductosColumnDefs = [
      { 
        field: 'producto', 
        headerName: 'Productos',
        width: 300,
        sortable: true,
        filter: true,
        cellStyle: (params: any): any => {
          if (params.data?.isTotal) {
            return { fontWeight: '600', backgroundColor: '#66A3FF', color: '#ffffff', textAlign: 'left' };
          }
          return { textAlign: 'left' } as any;
        }
      },
      { 
        field: 'stock', 
        headerName: 'Stock',
        width: 120,
        sortable: true,
        filter: true,
        cellStyle: (params: any): any => {
          if (params.data?.isTotal) {
            return { fontWeight: '600', backgroundColor: '#66A3FF', color: '#ffffff', textAlign: 'right' };
          }
          return { textAlign: 'right' } as any;
        },
        valueFormatter: (params: any) => {
          if (params.data?.isTotal) {
            return params.value ? params.value.toLocaleString('es-GT') : '';
          }
          return params.value !== null && params.value !== undefined ? params.value.toLocaleString('es-GT') : '';
        }
      },
      { 
        field: 'costo', 
        headerName: 'Costo',
        width: 120,
        sortable: true,
        filter: true,
        cellStyle: (params: any): any => {
          if (params.data?.isTotal) {
            return { fontWeight: '600', backgroundColor: '#66A3FF', color: '#ffffff', textAlign: 'right' };
          }
          return { textAlign: 'right' } as any;
        },
        valueFormatter: (params: any) => {
          if (params.value !== null && params.value !== undefined) {
            return this.formatCurrency(params.value);
          }
          return '';
        }
      },
      { 
        field: 'inversionPromedio', 
        headerName: 'Inversión prom.',
        width: 150,
        sortable: true,
        filter: true,
        cellStyle: (params: any): any => {
          if (params.data?.isTotal) {
            return { fontWeight: '600', backgroundColor: '#66A3FF', color: '#ffffff', textAlign: 'right' };
          }
          return { textAlign: 'right' } as any;
        },
        valueFormatter: (params: any) => {
          if (params.value !== null && params.value !== undefined) {
            return this.formatCurrency(params.value);
          }
          return '';
        }
      },
      { 
        field: 'precio', 
        headerName: 'Precio',
        width: 120,
        sortable: true,
        filter: true,
        cellStyle: (params: any): any => {
          if (params.data?.isTotal) {
            return { fontWeight: '600', backgroundColor: '#66A3FF', color: '#ffffff', textAlign: 'right' };
          }
          return { textAlign: 'right' } as any;
        },
        valueFormatter: (params: any) => {
          if (params.value !== null && params.value !== undefined) {
            return this.formatCurrency(params.value);
          }
          return '';
        }
      },
      { 
        field: 'ventasEsperadas', 
        headerName: 'Ventas e.',
        width: 150,
        sortable: true,
        filter: true,
        cellStyle: (params: any): any => {
          if (params.data?.isTotal) {
            return { fontWeight: '600', backgroundColor: '#66A3FF', color: '#ffffff', textAlign: 'right' };
          }
          return { textAlign: 'right' } as any;
        },
        valueFormatter: (params: any) => {
          if (params.value !== null && params.value !== undefined) {
            return this.formatCurrency(params.value);
          }
          return '';
        }
      }
    ];

    this.inventarioProductosGridOptions = {
      defaultColDef: {
        resizable: true,
        sortable: true,
        filter: true
      },
      pagination: true,
      paginationPageSize: 25,
      enableCellTextSelection: true,
      ensureDomOrder: true,
      onCellDoubleClicked: (params: any) => {
        if (params.value !== null && params.value !== undefined) {
          const cellValue = params.value.toString();
          this.copiarAlPortapapeles(cellValue);
        }
      },
      onRowClicked: (params: any) => {
        if (params.data && params.data.producto) {
          this.onProductoClickInventario(params.data.producto);
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
      },
      onPaginationChanged: () => {
        this.maybeLoadMoreDetalleFromGrid();
      },
      suppressExcelExport: false,
      suppressCsvExport: false
    };
  }

  /**
   * Recalcula todas las filas cacheadas
   */
  private recalcularRowsCache(): void {
    const datos = this.datosFiltrados && Object.keys(this.datosFiltrados).length > 0
      ? this.datosFiltrados
      : this.datos;
    const currentHash = this.generarHashDatos(datos);
    if (currentHash === this._lastDatosHash) {
      return; // No hay cambios
    }
    this._lastDatosHash = currentHash;

    // Detalle productos / E/S / ajustes: carga lazy aparte (no vienen en el merge).
  }

  get inventarioProductosRows(): any[] {
    return this._inventarioProductosRowsCache;
  }

  get totalInventarioProductos(): any {
    return this._totalInventarioProductosCache;
  }

  onQuickFilterChange(): void {
    this.quickFilter$.next(this.quickFilterText.trim());
  }

  /** Totales pinned vienen del API (`totales`); no recalcular desde filas parciales. */
  recalcularTotalesInventario(): void {
    const t = this.detalleTotales;
    if (t.stock !== 0 || t.inversionPromedio !== 0 || t.ventasEsperadas !== 0) {
      this.pinnedBottomRowDataInventario = [
        {
          producto: 'Total',
          stock: t.stock,
          costo: null,
          inversionPromedio: t.inversionPromedio,
          precio: null,
          ventasEsperadas: t.ventasEsperadas,
        },
      ];
    } else {
      this.pinnedBottomRowDataInventario = [];
    }
  }

  /** Totales pinned desde API (`totales`); no sumar solo la página cargada. */
  recalcularTotalesEntradasSalidas(): void {
    const t = this.esTotales;
    if (t.entradas > 0 || t.salidas > 0) {
      this.pinnedBottomRowDataEntradasSalidas = [
        {
          fecha: 'Total',
          producto: '',
          concepto: '',
          referencia: '',
          entradas: t.entradas,
          valorEntradas: t.valorEntradas,
          salidas: t.salidas,
          valorSalidas: t.valorSalidas,
        },
      ];
    } else {
      this.pinnedBottomRowDataEntradasSalidas = [];
    }
  }

  exportarCSV(): void {
    this.exportarDetalleCompletoCsv();
  }

  exportarExcel(): void {
    // AG Grid Community solo soporta CSV — pide el listado completo al API
    this.exportarDetalleCompletoCsv();
  }

  private exportarDetalleCompletoCsv(): void {
    if (this.detalleExporting) return;
    this.detalleExporting = true;
    this.cdr.markForCheck();
    const q = this.quickFilterText.trim();
    this.dashboardDataService
      .obtenerDetalleProductosCompleto(this.getFiltrosDetalle(), {
        q: q || undefined,
      })
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: (page) => {
          const fecha = new Date().toISOString().split('T')[0];
          const cols = this.inventarioProductosColumnDefs.filter((c) => c.field);
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
          a.download = `inventario-productos-${fecha}.csv`;
          a.click();
          URL.revokeObjectURL(url);
          this.detalleExporting = false;
          this.cdr.markForCheck();
        },
        error: () => {
          this.detalleExporting = false;
          this.cdr.markForCheck();
        },
      });
  }

  private csvEscape(value: string): string {
    if (/[",\n\r]/.test(value)) {
      return `"${value.replace(/"/g, '""')}"`;
    }
    return value;
  }

  limpiarFiltrosGrid(): void {
    if (this.gridApi) {
      this.gridApi.setFilterModel(null);
    }
    this.quickFilterText = '';
    this.cargarDetallePagina(0);
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
      if (allColumns.length === 0) {
        return;
      }

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
        const headers = this.inventarioProductosColumnDefs
          .map(col => col.headerName || col.field)
          .join('\t');
        
        const rows = selectedRows
          .filter((row: any) => !row.isTotal)
          .map((row: any) => {
            return this.inventarioProductosColumnDefs
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
        const headers = this.inventarioProductosColumnDefs
          .map(col => col.headerName || col.field)
          .join('\t');
        
        this.gridApi.forEachNodeAfterFilterAndSort((node: any) => {
          if (!node.data?.isTotal) {
            const row = this.inventarioProductosColumnDefs
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

  onCategoriaStockClick(event: { name: string; value: any; index: number }): void {
    if (this.filtrosInteractivos.categoria === event.name) {
      // Si ya está filtrado por esta categoría, quitar el filtro
      delete this.filtrosInteractivos.categoria;
    } else {
      // Aplicar filtro de categoría
      this.filtrosInteractivos.categoria = event.name;
    }
    this.aplicarFiltrosInteractivos();
    this.cargarDetallePagina(0);
    this.cargarDetalleEsPagina(0);
    this.cargarDetalleAjustesPagina(0);
  }

  configurarAGGridEntradasSalidas(): void {
    this.entradasSalidasColumnDefs = [
      { 
        field: 'fecha', 
        headerName: 'Mes',
        width: 120,
        sortable: true,
        filter: true,
        cellStyle: { textAlign: 'center' } as any
      },
      { 
        field: 'producto', 
        headerName: 'Producto',
        width: 250,
        sortable: true,
        filter: true,
        cellStyle: { textAlign: 'left' } as any
      },
      { 
        field: 'concepto', 
        headerName: 'Concepto',
        width: 180,
        sortable: true,
        filter: true,
        cellStyle: { textAlign: 'left' } as any
      },
      { 
        field: 'referencia', 
        headerName: 'Referencia',
        width: 120,
        sortable: true,
        filter: true,
        cellStyle: { textAlign: 'left' } as any
      },
      { 
        field: 'entradas', 
        headerName: 'Entradas',
        width: 120,
        sortable: true,
        filter: true,
        cellStyle: { textAlign: 'right' } as any,
        valueFormatter: (params: any) => {
          return params.value !== null && params.value !== undefined && params.value !== '' 
            ? params.value.toLocaleString('es-GT') 
            : '';
        }
      },
      { 
        field: 'valorEntradas', 
        headerName: 'Valor',
        width: 120,
        sortable: true,
        filter: true,
        cellStyle: { textAlign: 'right' } as any,
        valueFormatter: (params: any) => {
          if (params.value !== null && params.value !== undefined && params.value !== '') {
            return this.formatCurrency(params.value);
          }
          return '';
        }
      },
      { 
        field: 'salidas', 
        headerName: 'Salidas',
        width: 120,
        sortable: true,
        filter: true,
        cellStyle: { textAlign: 'right' } as any,
        valueFormatter: (params: any) => {
          return params.value !== null && params.value !== undefined && params.value !== '' 
            ? params.value.toLocaleString('es-GT') 
            : '';
        }
      },
      { 
        field: 'valorSalidas', 
        headerName: 'Valor',
        width: 120,
        sortable: true,
        filter: true,
        cellStyle: { textAlign: 'right' } as any,
        valueFormatter: (params: any) => {
          if (params.value !== null && params.value !== undefined && params.value !== '') {
            return this.formatCurrency(params.value);
          }
          return '';
        }
      }
    ];

    this.entradasSalidasGridOptions = {
      defaultColDef: {
        resizable: true,
        sortable: true,
        filter: true
      },
      pagination: true,
      paginationPageSize: 25,
      enableCellTextSelection: true,
      ensureDomOrder: true,
      onCellDoubleClicked: (params: any) => {
        if (params.value !== null && params.value !== undefined) {
          const cellValue = params.value.toString();
          this.copiarAlPortapapeles(cellValue);
        }
      },
      onRowClicked: (params: any) => {
        // Al hacer clic en una fila, filtrar por producto si es una columna de producto
        if (params.data && params.data.producto && params.data.producto !== '-') {
          this.onProductoClickEntradasSalidas(params.data.producto);
        }
      },
      onCellKeyDown: (params: any) => {
        const event = params.event;
        if ((event.ctrlKey || event.metaKey) && event.key === 'c') {
          event.preventDefault();
          this.copiarSeleccionAlPortapapelesEntradasSalidas();
        }
      },
      onGridReady: (params: any) => {
        this.entradasSalidasGridApi = params.api;
        this.entradasSalidasGridColumnApi = params.columnApi;
      },
      onPaginationChanged: () => {
        this.maybeLoadMoreEsFromGrid();
      },
      suppressExcelExport: false,
      suppressCsvExport: false
    };
  }

  get entradasSalidasRows(): any[] {
    return this._entradasSalidasRowsCache;
  }

  onQuickFilterChangeEntradasSalidas(): void {
    this.quickFilterEs$.next(this.quickFilterTextEntradasSalidas.trim());
  }

  exportarCSVEntradasSalidas(): void {
    this.exportarEsCompletoCsv();
  }

  exportarExcelEntradasSalidas(): void {
    this.exportarEsCompletoCsv();
  }

  private exportarEsCompletoCsv(): void {
    if (this.esExporting) return;
    this.esExporting = true;
    this.cdr.markForCheck();
    const q = this.quickFilterTextEntradasSalidas.trim();
    this.dashboardDataService
      .obtenerDetalleEsCompleto(this.getFiltrosMovimientos(), {
        q: q || undefined,
      })
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: (page) => {
          const fecha = new Date().toISOString().split('T')[0];
          const cols = this.entradasSalidasColumnDefs.filter((c) => c.field);
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
          a.download = `entradas-salidas-${fecha}.csv`;
          a.click();
          URL.revokeObjectURL(url);
          this.esExporting = false;
          this.cdr.markForCheck();
        },
        error: () => {
          this.esExporting = false;
          this.cdr.markForCheck();
        },
      });
  }

  limpiarFiltrosGridEntradasSalidas(): void {
    if (this.entradasSalidasGridApi) {
      this.entradasSalidasGridApi.setFilterModel(null);
    }
    this.quickFilterTextEntradasSalidas = '';
    this.cargarDetalleEsPagina(0);
  }

  copiarSeleccionAlPortapapelesEntradasSalidas(): void {
    if (!this.entradasSalidasGridApi) return;

    const selectedRanges = this.entradasSalidasGridApi.getCellRanges();
    
    if (selectedRanges && selectedRanges.length > 0) {
      const range = selectedRanges[0];
      const rows: string[] = [];
      
      const allColumns = this.entradasSalidasGridColumnApi?.getAllColumns() || [];
      if (allColumns.length === 0) {
        return;
      }

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
        const node = this.entradasSalidasGridApi.getDisplayedRowAtIndex(rowIndex);
        
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
      const selectedRows = this.entradasSalidasGridApi.getSelectedRows();
      if (selectedRows.length > 0) {
        const headers = this.entradasSalidasColumnDefs
          .map(col => col.headerName || col.field)
          .join('\t');
        
        const rows = selectedRows.map((row: any) => {
          return this.entradasSalidasColumnDefs
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
        const headers = this.entradasSalidasColumnDefs
          .map(col => col.headerName || col.field)
          .join('\t');
        
        this.entradasSalidasGridApi.forEachNodeAfterFilterAndSort((node: any) => {
          const row = this.entradasSalidasColumnDefs
            .map(col => {
              const value = node.data[col.field || ''] || '';
              return value.toString();
            })
            .join('\t');
          allRows.push(row);
        });
        
        const texto = [headers, ...allRows].join('\n');
        this.copiarAlPortapapeles(texto);
      }
    }
  }

  onMesEntradasSalidasClick(event: { name: string; value: any; index: number }): void {
    if (this.filtrosInteractivos.mes === event.name) {
      // Si ya está filtrado por este mes, quitar el filtro
      delete this.filtrosInteractivos.mes;
    } else {
      // Aplicar filtro de mes
      this.filtrosInteractivos.mes = event.name;
    }
    this.aplicarFiltrosInteractivos();
    this.cargarDetalleEsPagina(0);
  }

  /**
   * Ordena los arrays iniciales de mayor a menor
   */
  private ordenarArraysIniciales(): void {
    // No hay arrays específicos de inventario que necesiten ordenarse inicialmente
    // Los gráficos ya vienen ordenados desde el servicio
  }

  aplicarFiltrosInteractivos(): void {
    // Si no hay datos originales, usar los datos actuales
    const datosBase = Object.keys(this.datosOriginales).length > 0 
      ? this.datosOriginales 
      : (this.datos || {});

    // Crear una copia profunda de los datos para filtrar
    this.datosFiltrados = this.clonarDatos(datosBase);

    // Aplicar filtros según los filtros interactivos activos
    this.filtrarDatosInventario();
    
    // Recalcular todos los gráficos y métricas basándose en los datos filtrados
    this.recalcularTodosLosGraficos();
    
    // Recalcular métricas
    this.recalcularMetricas();

    // Actualizar los datos que se muestran (crear nueva referencia para que Angular detecte cambios)
    this.datos = this.clonarDatos(this.datosFiltrados);

    // Recalcular cache y forzar detección de cambios
    this.recalcularRowsCache();
    this.cdr.markForCheck();
  }

  filtrarDatosInventario(): void {
    // Detalles de productos / E/S / ajustes: paginados en servidor (mes, categoria, q).
  }

  recalcularTodosLosGraficos(): void {
    // Recalcular stock por categoría basado en detalleInventario filtrado
    this.recalcularStockPorCategoria();

    // Recalcular entradas y salidas por mes basado en detalleEntradasSalidas filtrado
    this.recalcularEntradasSalidasPorMes();

    // Recalcular métricas de entradas y salidas
    this.recalcularMetricasEntradasSalidas();
  }

  recalcularStockPorCategoria(): void {
    // Usar los datos originales del gráfico como base
    const configOriginal = this.datosOriginales.stockPorCategoriaConfig || this.datos.stockPorCategoriaConfig;
    
    if (!configOriginal) return;

    // Si hay filtro de categoría, mostrar solo esa categoría
    if (this.filtrosInteractivos.categoria && configOriginal.labels && configOriginal.data) {
      const labels = configOriginal.labels;
      const data = configOriginal.data;
      const categoriaIndex = labels.findIndex((l: string) => l === this.filtrosInteractivos.categoria);
      
      if (categoriaIndex !== -1) {
        this.datosFiltrados.stockPorCategoriaConfig = {
          ...configOriginal,
          labels: [labels[categoriaIndex]],
          data: [data[categoriaIndex]]
        };
      } else {
        // Si no se encuentra la categoría, usar la configuración original
        this.datosFiltrados.stockPorCategoriaConfig = this.clonarDatos(configOriginal);
      }
    } else {
      // Si no hay filtro, usar la configuración original
      this.datosFiltrados.stockPorCategoriaConfig = this.clonarDatos(configOriginal);
    }
  }

  recalcularEntradasSalidasPorMes(): void {
    // Filtrar el chart desde la config de API (ya no hay detalle completo en memoria)
    const configOriginal =
      this.datosOriginales.entradasSalidasPorMesConfig ||
      this.datos.entradasSalidasPorMesConfig;
    if (!configOriginal) return;

    if (this.filtrosInteractivos.mes && configOriginal.labels && configOriginal.data) {
      const labels: string[] = configOriginal.labels;
      const mesIndex = labels.findIndex(
        (l) => l === this.filtrosInteractivos.mes,
      );
      if (mesIndex !== -1) {
        const series = Array.isArray(configOriginal.data)
          ? configOriginal.data.map((s: any) => ({
              ...s,
              data: [s.data?.[mesIndex] ?? 0],
            }))
          : configOriginal.data;
        this.datosFiltrados.entradasSalidasPorMesConfig = {
          ...configOriginal,
          labels: [labels[mesIndex]],
          data: series,
        };
      } else {
        this.datosFiltrados.entradasSalidasPorMesConfig =
          this.clonarDatos(configOriginal);
      }
    } else {
      this.datosFiltrados.entradasSalidasPorMesConfig =
        this.clonarDatos(configOriginal);
    }
  }

  recalcularMetricasEntradasSalidas(): void {
    // Cards vienen de /es/cards; sin detalle completo no se recalculan aquí.
  }

  recalcularMetricas(): void {
    // Recalcular métricas de inventario basadas en los datos filtrados
    if (this.datosFiltrados.detalleInventario) {
      const productos = this.datosFiltrados.detalleInventario;
      const productosEnStock = productos.reduce((sum: number, item: any) => sum + (item.stock || 0), 0);
      const promedioInvertido = productos.reduce((sum: number, item: any) => sum + (item.inversionPromedio || 0), 0);
      const ventasEsperadas = productos.reduce((sum: number, item: any) => sum + (item.ventasEsperadas || 0), 0);
      const utilidadEsperada = ventasEsperadas - promedioInvertido;

      if (!this.datosFiltrados.metricasInventario) {
        this.datosFiltrados.metricasInventario = {};
      }
      this.datosFiltrados.metricasInventario.productosEnStock = productosEnStock;
      this.datosFiltrados.metricasInventario.promedioInvertido = promedioInvertido;
      this.datosFiltrados.metricasInventario.ventasEsperadas = ventasEsperadas;
      this.datosFiltrados.metricasInventario.utilidadEsperada = utilidadEsperada;
    }
  }

  limpiarFiltrosInteractivos(): void {
    this.filtrosInteractivos = {};
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
      this.cargarDetallePagina(0);
      this.cargarDetalleEsPagina(0);
      this.cargarDetalleAjustesPagina(0);
    }
    this.cdr.markForCheck();
  }

  tieneFiltrosInteractivos(): boolean {
    return Object.keys(this.filtrosInteractivos).length > 0;
  }

  getFiltrosInteractivosTexto(): string {
    const filtros: string[] = [];
    if (this.filtrosInteractivos.categoria) filtros.push(`Categoría: ${this.filtrosInteractivos.categoria}`);
    if (this.filtrosInteractivos.producto) filtros.push(`Producto: ${this.filtrosInteractivos.producto}`);
    if (this.filtrosInteractivos.mes) filtros.push(`Mes: ${this.filtrosInteractivos.mes}`);
    return filtros.join(', ');
  }

  configurarAGGridAjustes(): void {
    this.ajustesColumnDefs = [
      { 
        field: 'fecha', 
        headerName: 'Fecha',
        width: 120,
        sortable: true,
        filter: true,
        cellStyle: { textAlign: 'center' } as any
      },
      { 
        field: 'producto', 
        headerName: 'Productos',
        width: 250,
        sortable: true,
        filter: true,
        cellStyle: { textAlign: 'left' } as any
      },
      { 
        field: 'concepto', 
        headerName: 'Concepto',
        width: 250,
        sortable: true,
        filter: true,
        cellStyle: { textAlign: 'left' } as any
      },
      { 
        field: 'stockInicial', 
        headerName: 'Stock inicial',
        width: 130,
        sortable: true,
        filter: true,
        cellStyle: { textAlign: 'right' } as any,
        valueFormatter: (params: any) => {
          return params.value !== null && params.value !== undefined 
            ? params.value.toLocaleString('es-GT') 
            : '';
        }
      },
      { 
        field: 'stockReal', 
        headerName: 'Stock real',
        width: 130,
        sortable: true,
        filter: true,
        cellStyle: { textAlign: 'right' } as any,
        valueFormatter: (params: any) => {
          return params.value !== null && params.value !== undefined 
            ? params.value.toLocaleString('es-GT') 
            : '';
        }
      },
      { 
        field: 'ajuste', 
        headerName: 'Ajuste',
        width: 130,
        sortable: true,
        filter: true,
        cellStyle: (params: any): any => {
          return { textAlign: 'right', color: params.value < 0 ? '#dc3545' : '#333' } as any;
        },
        cellRenderer: (params: any) => {
          if (params.value === null || params.value === undefined || params.value === '') {
            return '';
          }
          const value = params.value;
          const formatted = value.toLocaleString('es-GT');
          if (value < 0) {
            return `<span style="color: #dc3545;"><span style="color: #dc3545; font-size: 10px;">▼</span> ${formatted}</span>`;
          }
          return formatted;
        }
      },
      { 
        field: 'costoTotal', 
        headerName: 'Costo total',
        width: 150,
        sortable: true,
        filter: true,
        cellStyle: (params: any): any => {
          if (params.data?.isTotal) {
            return { fontWeight: '600', backgroundColor: '#66A3FF', color: '#ffffff', textAlign: 'right' };
          }
          return { textAlign: 'right' } as any;
        },
        valueFormatter: (params: any) => {
          if (params.data?.isTotal) {
            if (params.value !== null && params.value !== undefined) {
              return this.formatCurrency(params.value);
            }
            return '';
          }
          if (params.value !== null && params.value !== undefined) {
            const value = params.value;
            if (value < 0) {
              const symbol = getEmpresaCurrencySymbol(this.apiService.auth_user()?.empresa);
              return `(${this.formatCurrency(Math.abs(value)).replace(symbol, '')})`;
            }
            return this.formatCurrency(value);
          }
          return '';
        }
      }
    ];

    this.ajustesGridOptions = {
      defaultColDef: {
        resizable: true,
        sortable: true,
        filter: true
      },
      pagination: true,
      paginationPageSize: 25,
      getRowClass: (params: any) => {
        if (params.data?.isTotal) {
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
      onRowClicked: (params: any) => {
        // Al hacer clic en una fila, filtrar por producto si es una columna de producto
        if (params.data && params.data.producto && params.data.producto !== 'Total') {
          this.onProductoClickAjustes(params.data.producto);
        }
      },
      onCellKeyDown: (params: any) => {
        const event = params.event;
        if ((event.ctrlKey || event.metaKey) && event.key === 'c') {
          event.preventDefault();
          this.copiarSeleccionAlPortapapelesAjustes();
        }
      },
      onGridReady: (params: any) => {
        this.ajustesGridApi = params.api;
        this.ajustesGridColumnApi = params.columnApi;
      },
      onPaginationChanged: () => {
        this.maybeLoadMoreAjustesFromGrid();
      },
      suppressExcelExport: false,
      suppressCsvExport: false
    };
  }

  get ajustesRows(): any[] {
    return this._ajustesRowsCache;
  }

  get totalAjustes(): any {
    return this._totalAjustesCache;
  }

  onQuickFilterChangeAjustes(): void {
    this.quickFilterAjustes$.next(this.quickFilterTextAjustes.trim());
  }

  exportarCSVAjustes(): void {
    this.exportarAjustesCompletoCsv();
  }

  exportarExcelAjustes(): void {
    this.exportarAjustesCompletoCsv();
  }

  private exportarAjustesCompletoCsv(): void {
    if (this.ajustesExporting) return;
    this.ajustesExporting = true;
    this.cdr.markForCheck();
    const q = this.quickFilterTextAjustes.trim();
    this.dashboardDataService
      .obtenerDetalleAjustesCompleto(this.getFiltrosAjustes(), {
        q: q || undefined,
      })
      .pipe(takeUntil(this.destroy$))
      .subscribe({
        next: (page) => {
          const fecha = new Date().toISOString().split('T')[0];
          const cols = this.ajustesColumnDefs.filter((c) => c.field);
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
          a.download = `ajustes-inventario-${fecha}.csv`;
          a.click();
          URL.revokeObjectURL(url);
          this.ajustesExporting = false;
          this.cdr.markForCheck();
        },
        error: () => {
          this.ajustesExporting = false;
          this.cdr.markForCheck();
        },
      });
  }

  limpiarFiltrosGridAjustes(): void {
    if (this.ajustesGridApi) {
      this.ajustesGridApi.setFilterModel(null);
    }
    this.quickFilterTextAjustes = '';
    this.cargarDetalleAjustesPagina(0);
  }

  copiarSeleccionAlPortapapelesAjustes(): void {
    if (!this.ajustesGridApi) return;

    const selectedRanges = this.ajustesGridApi.getCellRanges();
    
    if (selectedRanges && selectedRanges.length > 0) {
      const range = selectedRanges[0];
      const rows: string[] = [];
      
      const allColumns = this.ajustesGridColumnApi?.getAllColumns() || [];
      if (allColumns.length === 0) {
        return;
      }

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
        const node = this.ajustesGridApi.getDisplayedRowAtIndex(rowIndex);
        
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
      const selectedRows = this.ajustesGridApi.getSelectedRows();
      if (selectedRows.length > 0) {
        const headers = this.ajustesColumnDefs
          .map(col => col.headerName || col.field)
          .join('\t');
        
        const rows = selectedRows
          .filter((row: any) => !row.isTotal)
          .map((row: any) => {
            return this.ajustesColumnDefs
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
        const headers = this.ajustesColumnDefs
          .map(col => col.headerName || col.field)
          .join('\t');
        
        this.ajustesGridApi.forEachNodeAfterFilterAndSort((node: any) => {
          if (!node.data?.isTotal) {
            const row = this.ajustesColumnDefs
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

  onProductoClickInventario(producto: string): void {
    if (this.filtrosInteractivos.producto === producto) {
      delete this.filtrosInteractivos.producto;
      this.quickFilterText = '';
    } else {
      this.filtrosInteractivos.producto = producto;
      this.quickFilterText = producto;
    }
    this.aplicarFiltrosInteractivos();
    this.cargarDetallePagina(0);
    this.cargarDetalleEsPagina(0);
    this.cargarDetalleAjustesPagina(0);
  }

  onProductoClickEntradasSalidas(producto: string): void {
    if (this.filtrosInteractivos.producto === producto) {
      delete this.filtrosInteractivos.producto;
      this.quickFilterTextEntradasSalidas = '';
    } else {
      this.filtrosInteractivos.producto = producto;
      this.quickFilterTextEntradasSalidas = producto;
    }
    this.aplicarFiltrosInteractivos();
    this.cargarDetalleEsPagina(0);
  }

  onProductoClickAjustes(producto: string): void {
    if (this.filtrosInteractivos.producto === producto) {
      delete this.filtrosInteractivos.producto;
      this.quickFilterTextAjustes = '';
    } else {
      this.filtrosInteractivos.producto = producto;
      this.quickFilterTextAjustes = producto;
    }
    this.aplicarFiltrosInteractivos();
    this.cargarDetalleAjustesPagina(0);
  }

  /**
   * TrackBy functions para optimización de *ngFor
   */
  trackByIndex(index: number, item: any): number {
    return index;
  }

  trackByProducto(index: number, item: any): string | number {
    return item.producto ? `${item.producto}_${item.fecha || ''}` : index;
  }

  trackByFecha(index: number, item: any): string | number {
    return item.fecha ? `${item.fecha}_${item.producto || ''}` : index;
  }

  trackById(index: number, item: any): string | number {
    return item.id || index;
  }

  trackByName(index: number, item: any): string | number {
    return item.name || index;
  }
}
