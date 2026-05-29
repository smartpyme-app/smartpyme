import { Component, Input, OnInit, OnChanges, SimpleChanges, Output, EventEmitter, ViewChild, ChangeDetectorRef, ChangeDetectionStrategy } from '@angular/core';
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

@Component({
  selector: 'app-control-cuentas',
  templateUrl: './control-cuentas.component.html',
  styleUrls: ['./control-cuentas.component.css'],
  changeDetection: ChangeDetectionStrategy.OnPush
})
export class ControlCuentasComponent implements OnInit, OnChanges {
  @Input() datos: any = {};
  @Output() filtrosCambiados = new EventEmitter<any>();

  @ViewChild('detalleCuentasGrid') detalleCuentasGrid: any;
  @ViewChild('resumenCuentasPagarGrid') resumenCuentasPagarGrid: any;

  // AG Grid configuration
  detalleCuentasColumnDefs: ColDef[] = [];
  detalleCuentasGridOptions: GridOptions = {};
  resumenCuentasPagarColumnDefs: ColDef[] = [];
  resumenCuentasPagarGridOptions: GridOptions = {};
  private gridApi!: GridApi;
  private gridColumnApi!: ColumnApi;
  private resumenGridApi!: GridApi;
  private resumenGridColumnApi!: ColumnApi;
  quickFilterText: string = '';
  quickFilterTextResumen: string = '';

  anio: string = new Date().getFullYear().toString();
  mes: string = '';
  
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
  private currencyFormatter = new Intl.NumberFormat('es-GT', {
    style: 'currency',
    currency: 'USD',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  });

  /**
   * Recalcula todas las filas cacheadas
   */
  private recalcularRowsCache(): void {
    const currentHash = this.generarHashDatos(this.datos);
    if (currentHash === this._lastDatosHash) {
      return; // No hay cambios
    }
    this._lastDatosHash = currentHash;

    // Recalcular detalle de cuentas por cobrar
    if (this.datos.detalleCuentasPorCobrar) {
      const rows = this.datos.detalleCuentasPorCobrar.map((item: any) => ({
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
        isTotal: false
      }));

      // Calcular totales
      const totales = this.datos.detalleCuentasPorCobrar.reduce((totals: any, item: any) => ({
        ventasConIVA: totals.ventasConIVA + (item.ventasConIVA || 0),
        montoAbonado: totals.montoAbonado + (item.montoAbonado || 0),
        saldoPendiente: totals.saldoPendiente + (item.saldoPendiente || 0)
      }), { ventasConIVA: 0, montoAbonado: 0, saldoPendiente: 0 });

      this._totalesDetalleCuentasCache = totales;

      // Agregar fila de totales
      if (totales.ventasConIVA > 0 || totales.montoAbonado > 0) {
        rows.push({
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
          isTotal: true
        });
      }

      this._detalleCuentasRowsCache = rows;
    } else {
      this._detalleCuentasRowsCache = [];
      this._totalesDetalleCuentasCache = { ventasConIVA: 0, montoAbonado: 0, saldoPendiente: 0 };
    }

    // Recalcular resumen de cuentas por pagar
    if (this.datos.resumenCuentasPorPagar) {
      const rows = this.datos.resumenCuentasPorPagar.map((item: any) => ({
        proveedor: item.proveedor != null && String(item.proveedor).trim() !== ''
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

      // Calcular totales
      const totales = this.datos.resumenCuentasPorPagar.reduce((totals: any, item: any) => ({
        gastosTotalesConIVA: totals.gastosTotalesConIVA + (item.gastosTotalesConIVA || 0),
        totalAbonado: totals.totalAbonado + (item.totalAbonado || 0),
        saldoPendiente: totals.saldoPendiente + (item.saldoPendiente || 0)
      }), { gastosTotalesConIVA: 0, totalAbonado: 0, saldoPendiente: 0 });

      this._totalesResumenCuentasPagarCache = totales;

      // Agregar fila de totales
      if (
        totales.gastosTotalesConIVA > 0 ||
        totales.totalAbonado > 0 ||
        totales.saldoPendiente > 0
      ) {
        rows.push({
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
          isTotal: true,
        });
      }

      this._resumenCuentasPagarRowsCache = rows;
    } else {
      this._resumenCuentasPagarRowsCache = [];
      this._totalesResumenCuentasPagarCache = { gastosTotalesConIVA: 0, totalAbonado: 0, saldoPendiente: 0 };
    }
  }

  ngOnInit(): void {
    this.cargarOpcionesFiltros();
    this.configurarAGGrid();
    this.configurarAGGridResumenPagar();
    // Guardar datos originales si existen
    if (this.datos && Object.keys(this.datos).length > 0) {
      this.datosOriginales = this.clonarDatos(this.datos);
      this.datosFiltrados = this.clonarDatos(this.datos);
      this.recalcularRowsCache();
    }
    // Marcar como inicializado después de un pequeño delay para evitar emitir durante la inicialización
    setTimeout(() => {
      this.inicializado = true;
      this.filtrosListosParaEmitir = true;
      this.cdr.markForCheck();
    }, 100);
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
  }

  cargarOpcionesFiltros(): void {
    this.filtrosCatalogo.sucursalesParaFiltro().subscribe({
      next: (items) => {
        this.sucursales = items;
        this.aplicarRestriccionSucursalCxcPorRol();
        this.copiarTodoFiltrosAdicionalesBorradorAAplicado();
        setTimeout(() => {
          if (this.filtrosListosParaEmitir) {
            this.aplicarFiltros();
          }
        }, 150);
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
    return this.currencyFormatter.format(value);
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
  }

  onClienteCuentaClick(event: { name: string; amount: number }): void {
    if (this.filtrosInteractivos.cliente === event.name) {
      delete this.filtrosInteractivos.cliente;
    } else {
      this.filtrosInteractivos.cliente = event.name;
    }
    this.aplicarFiltrosInteractivos();
  }

  onVigenciaPagarClick(event: { name: string; value: any; index: number }): void {
    if (this.filtrosInteractivos.vigenciaPagar === event.name) {
      delete this.filtrosInteractivos.vigenciaPagar;
    } else {
      this.filtrosInteractivos.vigenciaPagar = event.name;
    }
    this.aplicarFiltrosInteractivos();
  }

  onProveedorCuentaClick(event: { name: string; amount: number }): void {
    if (this.filtrosInteractivos.proveedor === event.name) {
      delete this.filtrosInteractivos.proveedor;
    } else {
      this.filtrosInteractivos.proveedor = event.name;
    }
    this.aplicarFiltrosInteractivos();
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
    // Filtrar detalle de cuentas por cobrar
    if (this.datosFiltrados.detalleCuentasPorCobrar) {
      let cuentasFiltradas = [...this.datosFiltrados.detalleCuentasPorCobrar];

      if (this.filtrosInteractivos.cliente) {
        cuentasFiltradas = cuentasFiltradas.filter((c: any) => 
          c.cliente === this.filtrosInteractivos.cliente
        );
      }

      if (this.filtrosInteractivos.vigencia) {
        // Filtrar por vigencia (30, 60, 90 días, etc.)
        const vigenciaLower = this.filtrosInteractivos.vigencia.toLowerCase();
        cuentasFiltradas = cuentasFiltradas.filter((c: any) => {
          const dias = c.diasVencimiento || 0;
          if (vigenciaLower.includes('30')) {
            return dias <= 30;
          } else if (vigenciaLower.includes('60')) {
            return dias > 30 && dias <= 60;
          } else if (vigenciaLower.includes('90')) {
            return dias > 60 && dias <= 90;
          } else if (vigenciaLower.includes('90+') || vigenciaLower.includes('más')) {
            return dias > 90;
          }
          return true;
        });
      }

      this.datosFiltrados.detalleCuentasPorCobrar = cuentasFiltradas;
    }

    // Filtrar resumen de cuentas por pagar
    if (this.datosFiltrados.resumenCuentasPorPagar) {
      let cuentasFiltradas = [...this.datosFiltrados.resumenCuentasPorPagar];

      if (this.filtrosInteractivos.proveedor) {
        const nombre = this.filtrosInteractivos.proveedor.toLowerCase();
        cuentasFiltradas = cuentasFiltradas.filter((c: any) => {
          const p = (c.proveedor ?? '').toString().trim();
          const etiqueta = p || 'Sin proveedor';
          return etiqueta.toLowerCase() === nombre;
        });
      }

      if (this.filtrosInteractivos.vigenciaPagar) {
        const vigenciaLower = this.filtrosInteractivos.vigenciaPagar.toLowerCase();
        cuentasFiltradas = cuentasFiltradas.filter((c: any) => {
          const dias = c.diasVencimiento || 0;
          if (vigenciaLower.includes('30')) {
            return dias <= 30;
          } else if (vigenciaLower.includes('60')) {
            return dias > 30 && dias <= 60;
          } else if (vigenciaLower.includes('90')) {
            return dias > 60 && dias <= 90;
          } else if (vigenciaLower.includes('90+') || vigenciaLower.includes('más')) {
            return dias > 90;
          }
          return true;
        });
      }

      this.datosFiltrados.resumenCuentasPorPagar = cuentasFiltradas;
    }
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
    if (!this.datosFiltrados.detalleCuentasPorCobrar) return;

    const cuentas = this.datosFiltrados.detalleCuentasPorCobrar;
    const vigenciaMap: { [key: string]: number } = {};

    cuentas.forEach((c: any) => {
      const dias = c.diasVencimiento || 0;
      let categoria = '';
      
      if (dias <= 30) {
        categoria = '0-30 días';
      } else if (dias <= 60) {
        categoria = '31-60 días';
      } else if (dias <= 90) {
        categoria = '61-90 días';
      } else {
        categoria = 'Más de 90 días';
      }

      vigenciaMap[categoria] = (vigenciaMap[categoria] || 0) + (c.saldoPendiente || 0);
    });

    const data = Object.entries(vigenciaMap).map(([name, value]) => ({
      name,
      value: value as number
    }));

    if (this.datosFiltrados.cuentasPorVigenciaConfig) {
      this.datosFiltrados.cuentasPorVigenciaConfig = {
        ...this.datosFiltrados.cuentasPorVigenciaConfig,
        data
      };
    }
  }

  recalcularCuentasPorCobrarClientes(): void {
    if (!this.datosFiltrados.detalleCuentasPorCobrar) return;

    const cuentas = this.datosFiltrados.detalleCuentasPorCobrar;
    const clientesMap: { [key: string]: number } = {};

    cuentas.forEach((c: any) => {
      const cliente = c.cliente || 'Sin cliente';
      clientesMap[cliente] = (clientesMap[cliente] || 0) + (c.saldoPendiente || 0);
    });

    this.datosFiltrados.cuentasPorCobrarClientes = Object.entries(clientesMap)
      .map(([name, amount]) => ({ name, amount: amount as number }))
      .sort((a, b) => Math.abs(b.amount) - Math.abs(a.amount));
  }

  recalcularCuentasPorPagarVigencia(): void {
    if (!this.datosFiltrados.resumenCuentasPorPagar) return;

    const cuentas = this.datosFiltrados.resumenCuentasPorPagar;
    const vigenciaMap: { [key: string]: number } = {};

    cuentas.forEach((c: any) => {
      const dias = c.diasVencimiento || 0;
      let categoria = '';
      
      if (dias <= 30) {
        categoria = '0-30 días';
      } else if (dias <= 60) {
        categoria = '31-60 días';
      } else if (dias <= 90) {
        categoria = '61-90 días';
      } else {
        categoria = 'Más de 90 días';
      }

      vigenciaMap[categoria] = (vigenciaMap[categoria] || 0) + (c.saldoPendiente || 0);
    });

    const data = Object.entries(vigenciaMap).map(([name, value]) => ({
      name,
      value: value as number
    }));

    if (this.datosFiltrados.cuentasPorPagarVigenciaConfig) {
      this.datosFiltrados.cuentasPorPagarVigenciaConfig = {
        ...this.datosFiltrados.cuentasPorPagarVigenciaConfig,
        data
      };
    }
  }

  recalcularCuentasPorPagarProveedores(): void {
    if (!this.datosFiltrados.resumenCuentasPorPagar) return;

    const cuentas = this.datosFiltrados.resumenCuentasPorPagar;
    const proveedoresMap: { [key: string]: number } = {};

    cuentas.forEach((c: any) => {
      // Necesitamos obtener el proveedor de alguna manera
      // Por ahora, asumimos que hay un campo proveedor o similar
      // Esto puede necesitar ajuste según la estructura real de datos
      const proveedor = c.proveedor || 'Sin proveedor';
      proveedoresMap[proveedor] = (proveedoresMap[proveedor] || 0) + (c.saldoPendiente || 0);
    });

    this.datosFiltrados.cuentasPorPagarProveedores = Object.entries(proveedoresMap)
      .map(([name, amount]) => ({ name, amount: amount as number }))
      .sort((a, b) => Math.abs(b.amount) - Math.abs(a.amount));
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
        if (params.data?.isTotal) {
          return {
            fontWeight: '600',
            backgroundColor: '#F19447',
            color: '#ffffff',
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
          if (params.data?.isTotal) {
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
          if (params.data?.isTotal) {
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
      getRowClass: (params: any) => {
        if (params.data?.isTotal) {
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

  onQuickFilterChangeResumen(): void {
    if (this.resumenGridApi) {
      this.resumenGridApi.setQuickFilter(this.quickFilterTextResumen);
    }
  }

  exportarCSVResumen(): void {
    if (this.resumenGridApi) {
      const fecha = new Date().toISOString().split('T')[0];
      this.resumenGridApi.exportDataAsCsv({
        fileName: `resumen-cuentas-por-pagar-${fecha}.csv`,
        processCellCallback: (params: any) => {
          return params.value || '';
        }
      });
    }
  }

  exportarExcelResumen(): void {
    if (this.resumenGridApi) {
      const fecha = new Date().toISOString().split('T')[0];
      this.resumenGridApi.exportDataAsCsv({
        fileName: `resumen-cuentas-por-pagar-${fecha}.csv`,
        processCellCallback: (params: any) => {
          return params.value || '';
        }
      });
    }
  }

  limpiarFiltrosGridResumen(): void {
    if (this.resumenGridApi) {
      this.resumenGridApi.setFilterModel(null);
      this.quickFilterTextResumen = '';
      this.resumenGridApi.setQuickFilter('');
    }
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
    this.detalleCuentasColumnDefs = [
      { 
        field: 'cliente', 
        headerName: 'Cliente',
        width: 250,
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
        field: 'factura', 
        headerName: '# factura',
        width: 120,
        sortable: true,
        filter: true,
        cellStyle: (params: any): any => {
          if (params.data?.isTotal) {
            return { fontWeight: '600', backgroundColor: '#66A3FF', color: '#ffffff', textAlign: 'center' };
          }
          return { textAlign: 'center' } as any;
        }
      },
      { 
        field: 'fechaVenta', 
        headerName: 'Fecha de venta',
        width: 130,
        sortable: true,
        filter: true,
        cellStyle: (params: any): any => {
          if (params.data?.isTotal) {
            return { fontWeight: '600', backgroundColor: '#66A3FF', color: '#ffffff', textAlign: 'center' };
          }
          return { textAlign: 'center' } as any;
        }
      },
      { 
        field: 'fechaPago', 
        headerName: 'fecha_pago',
        width: 130,
        sortable: true,
        filter: true,
        cellStyle: (params: any): any => {
          if (params.data?.isTotal) {
            return { fontWeight: '600', backgroundColor: '#66A3FF', color: '#ffffff', textAlign: 'center' };
          }
          return { textAlign: 'center' } as any;
        }
      },
      { 
        field: 'diasVencimiento', 
        headerName: 'Dias vencimiento',
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
            return params.value ? params.value.toLocaleString('es-GT') : '';
          }
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
          if (params.data?.isTotal) {
            return '';
          }
          const estado = params.value || '';
          const iconClass = estado === 'Vigente' ? 'fas fa-circle' : 'fas fa-circle';
          const color = estado === 'Vigente' ? '#28a745' : '#6c757d';
          return `<span><i class="${iconClass}" style="color: ${color}; font-size: 8px; margin-right: 5px;"></i>${estado}</span>`;
        },
        cellStyle: (params: any): any => {
          if (params.data?.isTotal) {
            return { fontWeight: '600', backgroundColor: '#66A3FF', color: '#ffffff', textAlign: 'left' };
          }
          return { textAlign: 'left' } as any;
        }
      },
      { 
        field: 'ventasConIVA', 
        headerName: 'Ventas con IVA',
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
        cellStyle: (params: any): any => {
          if (params.data?.isTotal) {
            return { fontWeight: '600', backgroundColor: '#66A3FF', color: '#ffffff', textAlign: 'right' };
          }
          return { textAlign: 'right' } as any;
        },
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
        cellStyle: (params: any): any => {
          if (params.data?.isTotal) {
            return { fontWeight: '600', backgroundColor: '#66A3FF', color: '#ffffff', textAlign: 'right' };
          }
          return { textAlign: 'right' } as any;
        },
        valueFormatter: (params: any) => {
          if (params.data?.isTotal) {
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
        cellStyle: (params: any): any => {
          if (params.data?.isTotal) {
            return { fontWeight: '600', backgroundColor: '#66A3FF', color: '#ffffff', textAlign: 'right' };
          }
          return { textAlign: 'right' } as any;
        },
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
    if (this.gridApi) {
      this.gridApi.setQuickFilter(this.quickFilterText);
    }
  }

  exportarCSV(): void {
    if (this.gridApi) {
      const fecha = new Date().toISOString().split('T')[0];
      this.gridApi.exportDataAsCsv({
        fileName: `detalle-cuentas-por-cobrar-${fecha}.csv`,
        processCellCallback: (params: any) => {
          return params.value || '';
        }
      });
    }
  }

  exportarExcel(): void {
    if (this.gridApi) {
      const fecha = new Date().toISOString().split('T')[0];
      this.gridApi.exportDataAsCsv({
        fileName: `detalle-cuentas-por-cobrar-${fecha}.csv`,
        processCellCallback: (params: any) => {
          return params.value || '';
        }
      });
    }
  }

  limpiarFiltrosGrid(): void {
    if (this.gridApi) {
      this.gridApi.setFilterModel(null);
      this.quickFilterText = '';
      this.gridApi.setQuickFilter('');
    }
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
