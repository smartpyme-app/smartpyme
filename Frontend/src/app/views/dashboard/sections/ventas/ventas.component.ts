import { Component, Input, OnInit, OnChanges, SimpleChanges, ViewChild, Output, EventEmitter, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
import { ApiService } from '@services/api.service';
import {
  DashboardFiltrosCatalogoService,
  DashboardFiltroCatalogoItem,
} from '../../services/dashboard-filtros-catalogo.service';
import { DropdownMultiFiltroSelection } from '../../components/dropdown-multi-filtro/dropdown-multi-filtro.component';
import { RevoGrid } from '@revolist/angular-datagrid';
import { SortingPlugin, FilterPlugin, ExportFilePlugin } from '@revolist/revogrid';
import { ColDef, GridOptions, GridApi, ColumnApi } from 'ag-grid-community';

@Component({
  selector: 'app-ventas',
  templateUrl: './ventas.component.html',
  styleUrls: ['./ventas.component.css'],
  changeDetection: ChangeDetectionStrategy.OnPush
})
export class VentasComponent implements OnInit, OnChanges {
  @Input() datos: any = {};
  @Output() filtrosCambiados = new EventEmitter<any>();

  @ViewChild('ventasDetalladasGrid') ventasDetalladasGrid!: RevoGrid;
  @ViewChild('ventasPorProductoGrid') ventasPorProductoGrid: any;
  @ViewChild('ventasPorClienteGrid') ventasPorClienteGrid: any;

  ventasDetalladasPlugins = [SortingPlugin, FilterPlugin, ExportFilePlugin];
  ventasPorProductoPlugins = [SortingPlugin, FilterPlugin, ExportFilePlugin];

  // AG Grid configuration
  ventasPorProductoColumnDefs: ColDef[] = [];
  ventasPorProductoGridOptions: GridOptions = {};
  ventasPorClienteColumnDefs: ColDef[] = [];
  ventasPorClienteGridOptions: GridOptions = {};
  private gridApi!: GridApi;
  private gridColumnApi!: ColumnApi;
  private clienteGridApi!: GridApi;
  private clienteGridColumnApi!: ColumnApi;
  quickFilterText: string = '';
  busquedaVentasDetalladas: string = '';

  // Filtro por año (obligatorio en API) y mes (opcional; vacío = año completo)
  anio: string = new Date().getFullYear().toString();
  mes: string = '';
  
  // Filtros adicionales
  mostrarFiltrosAdicionales: boolean = false;

  readonly ventasEstadosFiltroItems: { id: string; nombre: string }[] = [
    { id: 'completada', nombre: 'Completada' },
    { id: 'pendiente', nombre: 'Pendiente' },
    { id: 'cancelada', nombre: 'Cancelada' },
  ];

  filtroAdSucursalTodasImplicitas = true;
  filtroAdSucursalSeleccionadas: string[] = [];
  filtroAdEstadoTodasImplicitas = true;
  filtroAdEstadoSeleccionadas: string[] = [];
  filtroAdCanalTodasImplicitas = true;
  filtroAdCanalSeleccionadas: string[] = [];
  filtroAdClienteTodasImplicitas = true;
  filtroAdClienteSeleccionadas: string[] = [];
  filtroAdVendedorTodasImplicitas = true;
  filtroAdVendedorSeleccionadas: string[] = [];

  sucursales: DashboardFiltroCatalogoItem[] = [];
  canales: DashboardFiltroCatalogoItem[] = [];
  clientes: DashboardFiltroCatalogoItem[] = [];
  vendedores: DashboardFiltroCatalogoItem[] = [];

  // Vista de métricas
  vistaMetricas: string = 'mes';

  // Filtros de productos
  filtroCategoriaProducto: string = '';
  filtroProducto: string = '';
  categoriasProductos: any[] = [];
  productos: any[] = [];

  // Filtros interactivos (se aplican localmente sin recargar)
  filtrosInteractivos: {
    canal?: string;
    vendedor?: string;
    formaPago?: string;
    categoria?: string;
    producto?: string;
    cliente?: string;
    mes?: string;
  } = {};
  
  // Datos originales (sin filtrar)
  datosOriginales: any = {};
  
  // Datos filtrados (se muestran en la vista)
  datosFiltrados: any = {};

  ventasDetalladasColumns = [
    { 
      prop: 'fecha', 
      name: 'Fecha', 
      size: 120,
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
      prop: 'factura', 
      name: '# factura', 
      size: 100,
      sortable: true,
      filterable: true
    },
    { 
      prop: 'productos', 
      name: 'Productos', 
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
    },
    { 
      prop: 'estado', 
      name: 'Estado', 
      size: 120,
      sortable: true,
      filterable: true
    }
  ];

  // Propiedades cacheadas para evitar recálculos
  private _ventasDetalladasRowsCache: any[] = [];
  private _ventasPorProductoRowsCache: any[] = [];
  private _ventasPorClienteRowsCache: any[] = [];
  private _lastDatosHash: string = '';

  ventasPorProductoColumns = [
    { 
      prop: 'categoria', 
      name: 'Categoría', 
      size: 150,
      sortable: true,
      filterable: true
    },
    { 
      prop: 'producto', 
      name: 'Producto', 
      size: 400,
      sortable: true,
      filterable: true
    },
    { 
      prop: 'formaPago', 
      name: 'Forma de pago', 
      size: 150,
      sortable: true,
      filterable: true
    },
    { 
      prop: 'cantidad', 
      name: 'Cantidad', 
      size: 100,
      sortable: true,
      filterable: true
    },
    { 
      prop: 'precioUnitario', 
      name: 'Precio unitario', 
      size: 130,
      sortable: true,
      filterable: true
    },
    { 
      prop: 'descuento', 
      name: 'Descuento', 
      size: 120,
      sortable: true,
      filterable: true
    },
    { 
      prop: 'ventasSinIVA', 
      name: 'Ventas totales sin IVA', 
      size: 180,
      sortable: true,
      filterable: true
    },
    { 
      prop: 'costoTotal', 
      name: 'Costo total', 
      size: 130,
      sortable: true,
      filterable: true
    },
    { 
      prop: 'utilidad', 
      name: 'Utilidad', 
      size: 120,
      sortable: true,
      filterable: true
    }
  ];

  constructor(
    private cdr: ChangeDetectorRef,
    private apiService: ApiService,
    private filtrosCatalogo: DashboardFiltrosCatalogoService
  ) { }

  private inicializado: boolean = false;

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
  private currencyFormatter = new Intl.NumberFormat('es-GT', {
    style: 'currency',
    currency: 'USD',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  });

  ngOnInit(): void {
    this.cargarOpcionesFiltros();
    this.configurarAGGrid();
    this.configurarAGGridClientes();
    // Guardar datos originales si existen
    if (this.datos && Object.keys(this.datos).length > 0) {
      this.datosOriginales = this.clonarDatos(this.datos);
      this.datosFiltrados = this.clonarDatos(this.datos);
      // Asegurar que los arrays estén ordenados de mayor a menor
      this.ordenarArraysIniciales();
      this.recalcularRowsCache();
    }
    // Marcar como inicializado después de un pequeño delay para evitar emitir durante la inicialización
    setTimeout(() => {
      this.inicializado = true;
      this.cdr.markForCheck();
    }, 100);
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
  }

  configurarAGGrid(): void {
    const usdFmt = (params: any): string => {
      const v = params.value;
      if (v == null || v === '') return '';
      const n = Number(v);
      if (Number.isNaN(n)) return '';
      return this.currencyFormatter.format(n);
    };

    this.ventasPorProductoColumnDefs = [
      {
        field: 'categoria',
        headerName: 'Categoría',
        sortable: true,
        filter: true,
      },
      {
        field: 'producto',
        headerName: 'Producto',
        sortable: true,
        filter: true,
      },
      {
        field: 'formaPago',
        headerName: 'Forma de Pago',
        sortable: true,
        filter: true,
      },
      {
        field: 'cantidad',
        headerName: 'Cantidad',
        sortable: true,
        filter: 'agNumberColumnFilter',
      },
      {
        field: 'precioUnitario',
        headerName: 'Precio Unitario',
        sortable: true,
        filter: 'agNumberColumnFilter',
        valueFormatter: usdFmt,
      },
      {
        field: 'descuento',
        headerName: 'Descuento',
        sortable: true,
        filter: 'agNumberColumnFilter',
        valueFormatter: usdFmt,
      },
      {
        field: 'ventasSinIVA',
        headerName: 'Ventas',
        sortable: true,
        filter: 'agNumberColumnFilter',
        valueFormatter: usdFmt,
      },
      {
        field: 'costoTotal',
        headerName: 'Costo Total',
        sortable: true,
        filter: 'agNumberColumnFilter',
        valueFormatter: usdFmt,
      },
      {
        field: 'utilidad',
        headerName: 'Utilidad',
        sortable: true,
        filter: 'agNumberColumnFilter',
        valueFormatter: usdFmt,
        cellStyle: (params: any): any => {
          const base = { textAlign: 'right' as const };
          const v = params.value;
          if (v == null || v === '') return base;
          const n = Number(v);
          if (n > 0) return { ...base, color: 'green' };
          if (n < 0) return { ...base, color: 'red' };
          return base;
        },
      },
    ];

    this.ventasPorProductoGridOptions = {
      pagination: true,
      paginationPageSize: 20,
      suppressMenuHide: true,
      quickFilterText: '',
      getRowClass: (params: any) => (params.data?.isTotal ? 'ag-row-total' : ''),
      onGridReady: (params: any) => {
        this.gridApi = params.api;
        this.gridColumnApi = params.columnApi;
        setTimeout(() => params.api.sizeColumnsToFit(), 0);
      },
    };
  }

  configurarAGGridClientes(): void {
    this.ventasPorClienteColumnDefs = [
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
        field: 'ultimaVenta', 
        headerName: 'Última venta',
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
        field: 'dias', 
        headerName: 'Días',
        width: 100,
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
        field: 'transacciones', 
        headerName: 'Transacciones',
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
          return params.value ? params.value.toLocaleString('es-GT') : '';
        }
      },
      { 
        field: 'ventas', 
        headerName: 'Ventas',
        width: 150,
        sortable: true,
        filter: true,
        cellStyle: (params: any): any => {
          if (params.data?.isTotal) {
            return { fontWeight: '600', backgroundColor: '#66A3FF', color: '#ffffff', textAlign: 'right' };
          }
          return { textAlign: 'right' } as any;
        }
      }
    ];

    this.ventasPorClienteGridOptions = {
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
      suppressScrollOnNewData: false,
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
          this.copiarSeleccionAlPortapapelesClientes();
        }
      },
      onGridReady: (params: any) => {
        this.clienteGridApi = params.api;
        this.clienteGridColumnApi = params.columnApi;
        // Asegurar que el scroll funcione correctamente
        setTimeout(() => {
          params.api.sizeColumnsToFit();
        }, 100);
      },
      suppressExcelExport: false,
      suppressCsvExport: false
    };
  }

  copiarSeleccionAlPortapapelesClientes(): void {
    if (!this.clienteGridApi) return;

    const selectedRows = this.clienteGridApi.getSelectedRows();
    if (selectedRows.length > 0) {
      const headers = this.ventasPorClienteColumnDefs
        .map(col => col.headerName || col.field)
        .join('\t');
      
      const rows = selectedRows
        .filter((row: any) => !row.isTotal)
        .map((row: any) => {
          return this.ventasPorClienteColumnDefs
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
      const headers = this.ventasPorClienteColumnDefs
        .map(col => col.headerName || col.field)
        .join('\t');
      
      this.clienteGridApi.forEachNodeAfterFilterAndSort((node: any) => {
        if (!node.data?.isTotal) {
          const row = this.ventasPorClienteColumnDefs
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

  /**
   * Catálogos vía `DashboardFiltrosCatalogoService` (una petición por recurso en la sesión).
   */
  cargarOpcionesFiltros(): void {
    this.categoriasProductos = [];
    this.productos = [];

    this.filtrosCatalogo.sucursalesParaFiltro().subscribe({
      next: (items) => {
        this.sucursales = items;
        const user = this.apiService.auth_user();
        if (items.length === 0) {
          this.filtroAdSucursalSeleccionadas = [];
          this.filtroAdSucursalTodasImplicitas = true;
        } else if (user?.tipo !== 'Administrador' && user?.id_sucursal != null) {
          this.filtroAdSucursalTodasImplicitas = false;
          this.filtroAdSucursalSeleccionadas = [String(user.id_sucursal)];
          setTimeout(() => {
            if (this.inicializado) {
              this.aplicarFiltros();
            }
          }, 150);
        } else if (user?.tipo === 'Administrador') {
          this.filtroAdSucursalSeleccionadas = [];
          this.filtroAdSucursalTodasImplicitas = true;
        }
        this.cdr.markForCheck();
      },
    });

    this.filtrosCatalogo.canalesParaFiltro().subscribe({
      next: (items) => {
        this.canales = items;
        this.cdr.markForCheck();
      },
    });

    this.filtrosCatalogo.clientesParaFiltro().subscribe({
      next: (items) => {
        this.clientes = items;
        this.cdr.markForCheck();
      },
    });

    this.filtrosCatalogo.vendedoresParaFiltro().subscribe({
      next: (items) => {
        this.vendedores = items;
        this.cdr.markForCheck();
      },
    });

    this.filtrosCatalogo.categoriasParaFiltro().subscribe({
      next: (items) => {
        this.categoriasProductos = items;
        this.cdr.markForCheck();
      },
    });
  }

  /**
   * Opciones del combo "Producto" a partir del detalle que ya trae el dashboard (sin endpoint extra).
   */
  private sincronizarProductosDesdeDatosVentas(): void {
    const detalle = this.datos?.ventasPorProducto;
    if (!Array.isArray(detalle) || detalle.length === 0) {
      this.productos = [];
      return;
    }
    const nombres = new Set<string>();
    for (const row of detalle) {
      const n = String(row?.producto ?? '').trim();
      if (n) nombres.add(n);
    }
    this.productos = [...nombres]
      .sort((a, b) => a.localeCompare(b, 'es'))
      .map((nombre) => ({ id: nombre, nombre }));
    if (
      this.filtroProducto &&
      !this.productos.some((p) => String(p.id) === String(this.filtroProducto))
    ) {
      this.filtroProducto = '';
    }
  }

  /** Usuario con una sola sucursal asignada no puede cambiar el filtro (como Resultados). */
  get filtroAdSucursalMultiDisabled(): boolean {
    const user = this.apiService.auth_user();
    return user?.tipo !== 'Administrador' && this.sucursales.length <= 1;
  }

  private idsDeListaFiltro(items: any[]): string[] {
    return (items || []).map((x: any) => String(x.id));
  }

  /**
   * Convierte selección múltiple a un string para query (vacío = sin filtro / todas).
   */
  private filtroAdMultiAString(
    todasImplicitas: boolean,
    seleccionados: string[],
    todosIds: string[]
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

  private filtroAdSucursalParaApi(): string | string[] {
    const todosIds = this.idsDeListaFiltro(this.sucursales);
    const sel = this.filtroAdSucursalSeleccionadas;
    if (this.filtroAdSucursalTodasImplicitas || sel.length === 0) {
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

  private filtroAdicionalEstaActivo(todasImplicitas: boolean, seleccionados: string[]): boolean {
    return !todasImplicitas || seleccionados.length > 0;
  }

  get filtroAdSucursalesItems(): { id: string; nombre: string }[] {
    return (this.sucursales || []).map((s: any) => ({
      id: String(s.id),
      nombre: s.nombre ?? '',
    }));
  }

  get filtroAdCanalesItems(): { id: string; nombre: string }[] {
    return (this.canales || []).map((c: any) => ({
      id: String(c.id),
      nombre: c.nombre ?? '',
    }));
  }

  get filtroAdClientesItems(): { id: string; nombre: string }[] {
    return (this.clientes || []).map((c: any) => ({
      id: String(c.id),
      nombre: c.nombre ?? '',
    }));
  }

  get filtroAdVendedoresItems(): { id: string; nombre: string }[] {
    return (this.vendedores || []).map((v: any) => ({
      id: String(v.id),
      nombre: v.nombre ?? '',
    }));
  }

  onFiltroAdSucursalChange(ev: DropdownMultiFiltroSelection): void {
    this.filtroAdSucursalTodasImplicitas = ev.todasImplicitas;
    this.filtroAdSucursalSeleccionadas = [...ev.seleccionados];
    this.aplicarFiltros();
    this.cdr.markForCheck();
  }

  onFiltroAdEstadoChange(ev: DropdownMultiFiltroSelection): void {
    this.filtroAdEstadoTodasImplicitas = ev.todasImplicitas;
    this.filtroAdEstadoSeleccionadas = [...ev.seleccionados];
    this.aplicarFiltros();
    this.cdr.markForCheck();
  }

  onFiltroAdCanalChange(ev: DropdownMultiFiltroSelection): void {
    this.filtroAdCanalTodasImplicitas = ev.todasImplicitas;
    this.filtroAdCanalSeleccionadas = [...ev.seleccionados];
    this.aplicarFiltros();
    this.cdr.markForCheck();
  }

  onFiltroAdClienteChange(ev: DropdownMultiFiltroSelection): void {
    this.filtroAdClienteTodasImplicitas = ev.todasImplicitas;
    this.filtroAdClienteSeleccionadas = [...ev.seleccionados];
    this.aplicarFiltros();
    this.cdr.markForCheck();
  }

  onFiltroAdVendedorChange(ev: DropdownMultiFiltroSelection): void {
    this.filtroAdVendedorTodasImplicitas = ev.todasImplicitas;
    this.filtroAdVendedorSeleccionadas = [...ev.seleccionados];
    this.aplicarFiltros();
    this.cdr.markForCheck();
  }

  toggleFiltrosAdicionales(): void {
    this.mostrarFiltrosAdicionales = !this.mostrarFiltrosAdicionales;
    this.cdr.markForCheck();
  }

  cambiarVistaMetricas(vista: string): void {
    this.vistaMetricas = vista;
    this.cdr.markForCheck();
    // Aquí puedes recargar los datos según la vista seleccionada
    // Por ejemplo, emitir un evento o llamar a un servicio
  }

  getTituloGraficoVentas(): string {
    switch (this.vistaMetricas) {
      case 'presupuesto':
        return 'Ventas totales vs presupuesto mensual';
      case 'anio':
        return 'Ventas totales año actual vs año anterior';
      default:
        return 'Ventas totales por mes';
    }
  }

  aplicarFiltrosProductos(): void {
    // Aquí puedes aplicar los filtros de productos
    // Por ejemplo, recargar datos o emitir un evento
    console.log('Filtros de productos:', {
      categoria: this.filtroCategoriaProducto,
      producto: this.filtroProducto
    });
  }

  limpiarFiltros(): void {
    this.anio = new Date().getFullYear().toString();
    this.mes = '';
    this.filtroAdSucursalTodasImplicitas = true;
    this.filtroAdSucursalSeleccionadas = [];
    this.filtroAdEstadoTodasImplicitas = true;
    this.filtroAdEstadoSeleccionadas = [];
    this.filtroAdCanalTodasImplicitas = true;
    this.filtroAdCanalSeleccionadas = [];
    this.filtroAdClienteTodasImplicitas = true;
    this.filtroAdClienteSeleccionadas = [];
    this.filtroAdVendedorTodasImplicitas = true;
    this.filtroAdVendedorSeleccionadas = [];
    this.aplicarFiltros();
  }

  aplicarFiltros(): void {
    // No emitir durante la inicialización
    if (!this.inicializado) {
      return;
    }

    if (!this.anio) {
      this.anio = new Date().getFullYear().toString();
    }

    const idsEstado = this.ventasEstadosFiltroItems.map((e) => e.id);
    const filtros: any = {
      anio: this.anio,
      sucursal: this.filtroAdSucursalParaApi(),
      estado: this.filtroAdMultiAString(
        this.filtroAdEstadoTodasImplicitas,
        this.filtroAdEstadoSeleccionadas,
        idsEstado
      ),
      canal: this.filtroAdMultiAString(
        this.filtroAdCanalTodasImplicitas,
        this.filtroAdCanalSeleccionadas,
        this.idsDeListaFiltro(this.canales)
      ),
      cliente: this.filtroAdMultiAString(
        this.filtroAdClienteTodasImplicitas,
        this.filtroAdClienteSeleccionadas,
        this.idsDeListaFiltro(this.clientes)
      ),
      vendedor: this.filtroAdMultiAString(
        this.filtroAdVendedorTodasImplicitas,
        this.filtroAdVendedorSeleccionadas,
        this.idsDeListaFiltro(this.vendedores)
      ),
    };
    if (this.mes) {
      filtros.mes = this.mes;
    }

    this.filtrosCambiados.emit(filtros);
  }

  get puedeLimpiarFiltrosVentas(): boolean {
    const anioActual = new Date().getFullYear().toString();
    const filtrosAdicionales =
      this.filtroAdicionalEstaActivo(
        this.filtroAdSucursalTodasImplicitas,
        this.filtroAdSucursalSeleccionadas
      ) ||
      this.filtroAdicionalEstaActivo(
        this.filtroAdEstadoTodasImplicitas,
        this.filtroAdEstadoSeleccionadas
      ) ||
      this.filtroAdicionalEstaActivo(
        this.filtroAdCanalTodasImplicitas,
        this.filtroAdCanalSeleccionadas
      ) ||
      this.filtroAdicionalEstaActivo(
        this.filtroAdClienteTodasImplicitas,
        this.filtroAdClienteSeleccionadas
      ) ||
      this.filtroAdicionalEstaActivo(
        this.filtroAdVendedorTodasImplicitas,
        this.filtroAdVendedorSeleccionadas
      );
    return !!this.mes || this.anio !== anioActual || filtrosAdicionales;
  }

  formatCurrency(value: number): string {
    return this.currencyFormatter.format(value);
  }

  /**
   * Recalcula todas las filas cacheadas
   */
  private recalcularRowsCache(): void {
    const currentHash = this.generarHashDatos(this.datos);
    if (currentHash === this._lastDatosHash) {
      return; // No hay cambios
    }
    this._lastDatosHash = currentHash;

    // Recalcular ventas detalladas
    if (this.datos.ventasDetalladas) {
      this._ventasDetalladasRowsCache = this.datos.ventasDetalladas.map((v: any) => ({
        fecha: v.fecha || '-',
        cliente: v.cliente || '-',
        factura: v.factura || '-',
        productos: v.productos || 0,
        monto: this.formatCurrency(v.monto || 0),
        montoOriginal: v.monto || 0,
        estado: v.estado || '-'
      }));
    } else {
      this._ventasDetalladasRowsCache = [];
    }

    // Recalcular ventas por producto
    this._ventasPorProductoRowsCache = this.calcularVentasPorProductoRows();
    this.sincronizarProductosDesdeDatosVentas();

    // Recalcular ventas por cliente
    this._ventasPorClienteRowsCache = this.calcularVentasPorClienteRows();
  }

  get ventasDetalladasRows(): any[] {
    return this.filtrarVentasDetalladasPorBusqueda(this._ventasDetalladasRowsCache);
  }

  filtrarVentasDetalladasPorBusqueda(rows: any[]): any[] {
    if (!this.busquedaVentasDetalladas) return rows;
    const busqueda = this.busquedaVentasDetalladas.toLowerCase();
    return rows.filter(row => 
      (row.cliente || '').toLowerCase().includes(busqueda) ||
      (row.factura || '').toLowerCase().includes(busqueda) ||
      (row.monto || '').toLowerCase().includes(busqueda) ||
      (row.fecha || '').toLowerCase().includes(busqueda) ||
      (row.estado || '').toLowerCase().includes(busqueda)
    );
  }

  onBusquedaVentasDetalladasChange(): void {
    this.cdr.markForCheck();
  }

  exportarVentasDetalladas(): void {
    if (this.ventasDetalladasRows.length > 0) {
      const fecha = new Date().toISOString().split('T')[0];
      this.exportarACSV(this.ventasDetalladasRows, this.ventasDetalladasColumns, `ventas-detalladas-${fecha}.csv`);
    } else {
      alert('No hay datos de ventas para exportar');
    }
  }

  get totalVentasDetalladas(): number {
    if (!this.datos.ventasDetalladas) return 0;
    return this.datos.ventasDetalladas.reduce((sum: number, item: any) => sum + (item.monto || 0), 0);
  }

  private calcularVentasPorProductoRows(): any[] {
    if (!this.datos.ventasPorProducto) return [];
    const rows = this.datos.ventasPorProducto.map((item: any) => ({
      categoria: item.categoria || '-',
      producto: item.producto || '-',
      formaPago: item.formaPago || '-',
      cantidad: item.cantidad || 0,
      precioUnitario: item.precioUnitario || 0,
      descuento: item.descuento || 0,
      ventasSinIVA: item.ventasSinIVA || 0,
      costoTotal: item.costoTotal || 0,
      utilidad: item.utilidad || 0,
      isTotal: false,
    }));

    const totales = this.totalVentasPorProducto;
    if (totales.cantidad > 0) {
      rows.push({
        categoria: 'TOTAL',
        producto: '',
        formaPago: '',
        cantidad: totales.cantidad,
        precioUnitario: null,
        descuento: null,
        ventasSinIVA: totales.ventasSinIVA,
        costoTotal: totales.costoTotal,
        utilidad: totales.utilidad,
        isTotal: true,
      });
    }

    return rows;
  }

  get ventasPorProductoRows(): any[] {
    return this._ventasPorProductoRowsCache;
  }

  get totalVentasPorProducto(): any {
    if (!this.datos.ventasPorProducto || this.datos.ventasPorProducto.length === 0) {
      return { cantidad: 0, ventasSinIVA: 0, costoTotal: 0, utilidad: 0 };
    }
    return this.datos.ventasPorProducto.reduce((totals: any, item: any) => ({
      cantidad: totals.cantidad + (item.cantidad || 0),
      ventasSinIVA: totals.ventasSinIVA + (item.ventasSinIVA || 0),
      costoTotal: totals.costoTotal + (item.costoTotal || 0),
      utilidad: totals.utilidad + (item.utilidad || 0)
    }), { cantidad: 0, ventasSinIVA: 0, costoTotal: 0, utilidad: 0 });
  }

  private calcularVentasPorClienteRows(): any[] {
    if (!this.datos.ventasPorCliente) return [];
    const rows = this.datos.ventasPorCliente.map((item: any) => ({
      cliente: item.cliente || '-',
      ultimaVenta: item.ultimaVenta || '-',
      dias: item.dias || 0,
      transacciones: item.transacciones || 0,
      ventas: this.formatCurrency(item.ventas || 0),
      ventasOriginal: item.ventas || 0,
      isTotal: false
    }));

    // Agregar fila de totales al final
    const totales = this.totalVentasPorCliente;
    if (totales.transacciones > 0) {
      rows.push({
        cliente: 'Total',
        ultimaVenta: totales.ultimaVenta || '-',
        dias: totales.dias || 0,
        transacciones: totales.transacciones,
        ventas: this.formatCurrency(totales.ventas),
        ventasOriginal: totales.ventas,
        isTotal: true
      });
    }

    return rows;
  }

  get ventasPorClienteRows(): any[] {
    return this._ventasPorClienteRowsCache;
  }

  get totalVentasPorCliente(): any {
    if (!this.datos.ventasPorCliente || this.datos.ventasPorCliente.length === 0) {
      return { transacciones: 0, ventas: 0, dias: 0, ultimaVenta: '' };
    }
    const totales = this.datos.ventasPorCliente.reduce((totals: any, item: any) => ({
      transacciones: totals.transacciones + (item.transacciones || 0),
      ventas: totals.ventas + (item.ventas || 0),
      dias: Math.max(totals.dias || 0, item.dias || 0),
      ultimaVenta: item.ultimaVenta || totals.ultimaVenta || ''
    }), { transacciones: 0, ventas: 0, dias: 0, ultimaVenta: '' });
    
    // Obtener la fecha más reciente
    const fechas = this.datos.ventasPorCliente
      .map((item: any) => item.ultimaVenta)
      .filter((fecha: string) => fecha && fecha !== '-');
    
    if (fechas.length > 0) {
      // Ordenar fechas y tomar la más reciente
      fechas.sort((a: string, b: string) => {
        const dateA = new Date(a.split('/').reverse().join('-'));
        const dateB = new Date(b.split('/').reverse().join('-'));
        return dateB.getTime() - dateA.getTime();
      });
      totales.ultimaVenta = fechas[0];
    }
    
    return totales;
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

  // Métodos para AG Grid
  onQuickFilterChange(): void {
    if (this.gridApi) {
      this.gridApi.setQuickFilter(this.quickFilterText);
    }
  }

  exportarCSV(): void {
    if (this.gridApi) {
      const fecha = new Date().toISOString().split('T')[0];
      this.gridApi.exportDataAsCsv({
        fileName: `ventas-por-producto-${fecha}.csv`,
        processCellCallback: (params: any) => {
          // Excluir la fila de totales del export si es necesario, o incluirla
          return params.value || '';
        }
      });
    }
  }

  exportarExcel(): void {
    // AG Grid Community solo soporta CSV
    // Para Excel necesitarías ag-grid-enterprise
    // Por ahora exportamos como CSV con extensión .xlsx (puede abrirse en Excel)
    if (this.gridApi) {
      const fecha = new Date().toISOString().split('T')[0];
      this.gridApi.exportDataAsCsv({
        fileName: `ventas-por-producto-${fecha}.csv`,
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

  // Operaciones básicas de celda
  onCellClicked(params: any): void {
    // Al hacer clic en una celda, se puede seleccionar
    // La selección de rangos se maneja automáticamente por AG Grid
  }

  onCellDoubleClicked(params: any): void {
    // Doble clic: copiar valor de celda al portapapeles
    if (params.value !== null && params.value !== undefined) {
      const cellValue = params.value.toString();
      this.copiarAlPortapapeles(cellValue);
    }
  }

  onCellKeyDown(params: any): void {
    const event = params.event;
    
    // Ctrl+C o Cmd+C: Copiar selección al portapapeles
    if ((event.ctrlKey || event.metaKey) && event.key === 'c') {
      event.preventDefault();
      this.copiarSeleccionAlPortapapeles();
    }
    
    // Ctrl+A o Cmd+A: Seleccionar todas las filas visibles
    if ((event.ctrlKey || event.metaKey) && event.key === 'a') {
      event.preventDefault();
      if (this.gridApi) {
        this.gridApi.selectAll();
      }
    }
    
    // Delete o Backspace: Limpiar filtro de columna si está en el header
    if ((event.key === 'Delete' || event.key === 'Backspace') && params.node.rowPinned === 'top') {
      if (params.column) {
        const filterInstance = this.gridApi.getFilterInstance(params.column.colId);
        if (filterInstance) {
          filterInstance.setModel(null);
          this.gridApi.onFilterChanged();
        }
      }
    }
    
    // F2: Editar celda (si fuera editable)
    if (event.key === 'F2') {
      // AG Grid Community no tiene edición inline por defecto
      // Pero podemos seleccionar el texto de la celda
      if (params.event.target) {
        params.event.target.select();
      }
    }
  }

  copiarAlPortapapeles(texto: string): void {
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(texto).then(() => {
        console.log('Valor copiado al portapapeles');
      }).catch(err => {
        console.error('Error al copiar:', err);
        // Fallback para navegadores antiguos
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
      console.log('Valor copiado al portapapeles (fallback)');
    } catch (err) {
      console.error('Error al copiar:', err);
    }
    document.body.removeChild(textArea);
  }

  copiarSeleccionAlPortapapeles(): void {
    if (!this.gridApi) return;

    const selectedRanges = this.gridApi.getCellRanges();
    
    if (selectedRanges && selectedRanges.length > 0) {
      // Copiar rangos seleccionados
      const range = selectedRanges[0];
      const rows: string[] = [];
      
      // Obtener todas las columnas usando columnApi
      const allColumns = this.gridColumnApi?.getAllColumns() || [];
      if (allColumns.length === 0) {
        return;
      }

      // Obtener índices de inicio y fin
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
      // Si no hay rango seleccionado, copiar filas seleccionadas
      const selectedRows = this.gridApi.getSelectedRows();
      if (selectedRows.length > 0) {
        const headers = this.ventasPorProductoColumnDefs
          .map(col => col.headerName || col.field)
          .join('\t');
        
        const rows = selectedRows
          .filter((row: any) => !row.isTotal)
          .map((row: any) => {
            return this.ventasPorProductoColumnDefs
              .map(col => {
                const value = row[col.field || ''] || '';
                return value.toString();
              })
              .join('\t');
          });
        
        const texto = [headers, ...rows].join('\n');
        this.copiarAlPortapapeles(texto);
      } else {
        // Si no hay selección, copiar toda la tabla visible
        const allRows: string[] = [];
        const headers = this.ventasPorProductoColumnDefs
          .map(col => col.headerName || col.field)
          .join('\t');
        
        this.gridApi.forEachNodeAfterFilterAndSort((node: any) => {
          if (!node.data?.isTotal) {
            const row = this.ventasPorProductoColumnDefs
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

  // Métodos para filtros interactivos
  onCanalClick(event: { name: string; amount: number }): void {
    if (this.filtrosInteractivos.canal === event.name) {
      // Si ya está filtrado por este canal, quitar el filtro
      delete this.filtrosInteractivos.canal;
    } else {
      // Aplicar filtro de canal
      this.filtrosInteractivos.canal = event.name;
    }
    this.aplicarFiltrosInteractivos();
  }

  onVendedorClick(event: { name: string; value: any; index: number }): void {
    if (this.filtrosInteractivos.vendedor === event.name) {
      delete this.filtrosInteractivos.vendedor;
    } else {
      this.filtrosInteractivos.vendedor = event.name;
    }
    this.aplicarFiltrosInteractivos();
  }

  onFormaPagoClick(event: { name: string; value: any; index: number }): void {
    if (this.filtrosInteractivos.formaPago === event.name) {
      delete this.filtrosInteractivos.formaPago;
    } else {
      this.filtrosInteractivos.formaPago = event.name;
    }
    this.aplicarFiltrosInteractivos();
  }

  onCategoriaClick(event: { name: string; amount: number }): void {
    if (this.filtrosInteractivos.categoria === event.name) {
      delete this.filtrosInteractivos.categoria;
    } else {
      this.filtrosInteractivos.categoria = event.name;
    }
    this.aplicarFiltrosInteractivos();
  }

  onProductoClick(event: { name: string; amount: number }): void {
    if (this.filtrosInteractivos.producto === event.name) {
      delete this.filtrosInteractivos.producto;
    } else {
      this.filtrosInteractivos.producto = event.name;
    }
    this.aplicarFiltrosInteractivos();
  }

  onClienteClick(event: { name: string; amount: number }): void {
    if (this.filtrosInteractivos.cliente === event.name) {
      delete this.filtrosInteractivos.cliente;
    } else {
      this.filtrosInteractivos.cliente = event.name;
    }
    this.aplicarFiltrosInteractivos();
  }

  onMesClick(event: { name: string; value: any; index: number }): void {
    if (this.filtrosInteractivos.mes === event.name) {
      delete this.filtrosInteractivos.mes;
    } else {
      this.filtrosInteractivos.mes = event.name;
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

    // Primero filtrar las ventas detalladas según todos los filtros activos
    this.filtrarVentasDetalladas();

    // Luego recalcular todos los gráficos basándose en las ventas filtradas
    this.recalcularTodosLosGraficos();

    // Recalcular métricas
    this.recalcularMetricas();

    // Actualizar los datos que se muestran (crear nueva referencia para que Angular detecte cambios)
    this.datos = this.clonarDatos(this.datosFiltrados);

    // Recalcular cache y marcar para detección de cambios
    this.recalcularRowsCache();
    this.cdr.markForCheck();
  }

  filtrarVentasDetalladas(): void {
    if (!this.datosFiltrados.ventasDetalladas) {
      return;
    }

    let ventasFiltradas = [...this.datosFiltrados.ventasDetalladas];

    // Aplicar todos los filtros activos
    if (this.filtrosInteractivos.canal) {
      ventasFiltradas = ventasFiltradas.filter((v: any) => v.canal === this.filtrosInteractivos.canal);
    }
    if (this.filtrosInteractivos.vendedor) {
      ventasFiltradas = ventasFiltradas.filter((v: any) => v.vendedor === this.filtrosInteractivos.vendedor);
    }
    if (this.filtrosInteractivos.formaPago) {
      ventasFiltradas = ventasFiltradas.filter((v: any) => v.formaPago === this.filtrosInteractivos.formaPago);
    }
    if (this.filtrosInteractivos.cliente) {
      ventasFiltradas = ventasFiltradas.filter((v: any) => v.cliente === this.filtrosInteractivos.cliente);
    }
    if (this.filtrosInteractivos.mes) {
      ventasFiltradas = ventasFiltradas.filter((v: any) => {
        if (!v.fecha) return false;
        const fecha = new Date(v.fecha);
        const mesIndex = fecha.getMonth();
        const meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 
                       'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
        const mesNombre = meses[mesIndex];
        return mesNombre.toLowerCase() === this.filtrosInteractivos.mes?.toLowerCase() ||
               mesNombre === this.filtrosInteractivos.mes;
      });
    }
    if (this.filtrosInteractivos.categoria) {
      ventasFiltradas = ventasFiltradas.filter((v: any) => v.categoria === this.filtrosInteractivos.categoria);
    }
    if (this.filtrosInteractivos.producto) {
      ventasFiltradas = ventasFiltradas.filter((v: any) => v.producto === this.filtrosInteractivos.producto);
    }

    this.datosFiltrados.ventasDetalladas = ventasFiltradas;
  }

  recalcularTodosLosGraficos(): void {
    const ventasFiltradas = this.datosFiltrados.ventasDetalladas || [];

    // Recalcular ventas por canal
    this.recalcularVentasPorCanal(ventasFiltradas);

    // Recalcular ventas por vendedor
    this.recalcularVentasPorVendedor(ventasFiltradas);

    // Recalcular ventas por forma de pago
    this.recalcularVentasPorFormaPago(ventasFiltradas);

    // Recalcular ventas por categoría
    this.recalcularVentasPorCategoria(ventasFiltradas);

    // Recalcular top productos
    this.recalcularTopProductos(ventasFiltradas);

    // Recalcular top clientes
    this.recalcularTopClientes(ventasFiltradas);

    // Recalcular ventas por mes
    this.recalcularVentasPorMes(ventasFiltradas);

    // Recalcular ventas por producto (tabla detallada)
    this.recalcularVentasPorProducto(ventasFiltradas);

    // Recalcular ventas por cliente (tabla detallada)
    this.recalcularVentasPorCliente(ventasFiltradas);
  }

  /**
   * Ordena los arrays iniciales de mayor a menor
   */
  private ordenarArraysIniciales(): void {
    const arraysParaOrdenar = [
      'ventasPorCanal',
      'ventasPorCategoria',
      'topProductosVendidos',
      'topClientes'
    ];

    arraysParaOrdenar.forEach(key => {
      const array = this.datosFiltrados[key];
      if (Array.isArray(array)) {
        this.datosFiltrados[key] = [...array].sort((a: any, b: any) => {
          const amountA = Math.abs(a.amount || 0);
          const amountB = Math.abs(b.amount || 0);
          return amountB - amountA;
        });
      }
    });
  }

  recalcularVentasPorCanal(ventas: any[]): void {
    const ventasPorCanal: { [key: string]: number } = {};
    
    ventas.forEach((v: any) => {
      const canal = v.canal || 'Sin canal';
      ventasPorCanal[canal] = (ventasPorCanal[canal] || 0) + (v.monto || 0);
    });

    this.datosFiltrados.ventasPorCanal = Object.entries(ventasPorCanal)
      .map(([name, amount]) => ({ name, amount: amount as number }))
      .sort((a, b) => Math.abs(b.amount) - Math.abs(a.amount));
  }

  recalcularVentasPorVendedor(ventas: any[]): void {
    const ventasPorVendedor: { [key: string]: number } = {};
    
    ventas.forEach((v: any) => {
      const vendedor = v.vendedor || 'Sin vendedor';
      ventasPorVendedor[vendedor] = (ventasPorVendedor[vendedor] || 0) + (v.monto || 0);
    });

    const labels = Object.keys(ventasPorVendedor);
    const data = labels.map(v => ventasPorVendedor[v]);

    if (this.datosFiltrados.ventasPorVendedorChartConfig) {
      this.datosFiltrados.ventasPorVendedorChartConfig = {
        ...this.datosFiltrados.ventasPorVendedorChartConfig,
        labels,
        data
      };
    }
  }

  recalcularVentasPorFormaPago(ventas: any[]): void {
    const ventasPorFormaPago: { [key: string]: number } = {};
    
    ventas.forEach((v: any) => {
      const formaPago = v.formaPago || 'Sin forma de pago';
      ventasPorFormaPago[formaPago] = (ventasPorFormaPago[formaPago] || 0) + (v.monto || 0);
    });

    const labels = Object.keys(ventasPorFormaPago);
    const data = labels.map(fp => ventasPorFormaPago[fp]);

    if (this.datosFiltrados.ventasPorFormaPagoConfig) {
      this.datosFiltrados.ventasPorFormaPagoConfig = {
        ...this.datosFiltrados.ventasPorFormaPagoConfig,
        labels,
        data
      };
    }
  }

  recalcularVentasPorCategoria(ventas: any[]): void {
    const ventasPorCategoria: { [key: string]: number } = {};
    
    ventas.forEach((v: any) => {
      const categoria = v.categoria || 'Sin categoría';
      ventasPorCategoria[categoria] = (ventasPorCategoria[categoria] || 0) + (v.monto || 0);
    });

    this.datosFiltrados.ventasPorCategoria = Object.entries(ventasPorCategoria)
      .map(([name, amount]) => ({ name, amount: amount as number }))
      .sort((a, b) => Math.abs(b.amount) - Math.abs(a.amount));
  }

  recalcularTopProductos(ventas: any[]): void {
    const ventasPorProducto: { [key: string]: number } = {};
    
    ventas.forEach((v: any) => {
      const producto = v.producto || 'Sin producto';
      ventasPorProducto[producto] = (ventasPorProducto[producto] || 0) + (v.monto || 0);
    });

    this.datosFiltrados.topProductosVendidos = Object.entries(ventasPorProducto)
      .map(([name, amount]) => ({ name, amount: amount as number }))
      .sort((a, b) => Math.abs(b.amount) - Math.abs(a.amount))
      .slice(0, 15); // Top 15
  }

  recalcularTopClientes(ventas: any[]): void {
    const ventasPorCliente: { [key: string]: { ventas: number; transacciones: number; ultimaVenta: string } } = {};
    
    ventas.forEach((v: any) => {
      const cliente = v.cliente || 'Sin cliente';
      if (!ventasPorCliente[cliente]) {
        ventasPorCliente[cliente] = { ventas: 0, transacciones: 0, ultimaVenta: v.fecha || '' };
      }
      ventasPorCliente[cliente].ventas += (v.monto || 0);
      ventasPorCliente[cliente].transacciones += 1;
      if (v.fecha && v.fecha > ventasPorCliente[cliente].ultimaVenta) {
        ventasPorCliente[cliente].ultimaVenta = v.fecha;
      }
    });

    this.datosFiltrados.topClientes = Object.entries(ventasPorCliente)
      .map(([name, datos]) => ({ name, amount: datos.ventas }))
      .sort((a, b) => Math.abs(b.amount) - Math.abs(a.amount))
      .slice(0, 25); // Top 25
  }

  recalcularVentasPorMes(ventas: any[]): void {
    const meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 
                   'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
    const ventasPorMes: { [key: string]: number } = {};
    
    ventas.forEach((v: any) => {
      if (v.fecha) {
        const fecha = new Date(v.fecha);
        const mesIndex = fecha.getMonth();
        const mesNombre = meses[mesIndex];
        ventasPorMes[mesNombre] = (ventasPorMes[mesNombre] || 0) + (v.monto || 0);
      }
    });

    // Mantener el orden de los meses y solo incluir los que tienen datos
    const labels = meses.filter(m => ventasPorMes[m] !== undefined && ventasPorMes[m] !== 0);
    const data = labels.map(m => ventasPorMes[m] || 0);

    if (this.datosFiltrados.ventasPorMesConfig) {
      this.datosFiltrados.ventasPorMesConfig = {
        ...this.datosFiltrados.ventasPorMesConfig,
        labels: labels.length > 0 ? labels : meses, // Si no hay datos, mostrar todos los meses
        data: data.length > 0 ? data : meses.map(() => 0)
      };
    }
  }

  recalcularVentasPorProducto(ventas: any[]): void {
    const ventasPorProductoMap: { [key: string]: any } = {};
    
    ventas.forEach((v: any) => {
      const key = `${v.producto || 'Sin producto'}_${v.formaPago || 'Sin forma de pago'}`;
      if (!ventasPorProductoMap[key]) {
        ventasPorProductoMap[key] = {
          categoria: v.categoria || 'Sin categoría',
          producto: v.producto || 'Sin producto',
          formaPago: v.formaPago || 'Sin forma de pago',
          cantidad: 0,
          precioUnitario: v.precioUnitario || 0,
          descuento: 0,
          ventasSinIVA: 0,
          costoTotal: 0,
          utilidad: 0
        };
      }
      ventasPorProductoMap[key].cantidad += (v.cantidad || 0);
      ventasPorProductoMap[key].ventasSinIVA += (v.monto || 0) / 1.12;
      ventasPorProductoMap[key].descuento += (v.descuento || 0);
      ventasPorProductoMap[key].costoTotal += (v.costoTotal || 0);
      ventasPorProductoMap[key].utilidad += (v.utilidad || 0);
    });

    this.datosFiltrados.ventasPorProducto = Object.values(ventasPorProductoMap);
  }

  recalcularVentasPorCliente(ventas: any[]): void {
    const ventasPorClienteMap: { [key: string]: any } = {};
    
    ventas.forEach((v: any) => {
      const cliente = v.cliente || 'Sin cliente';
      if (!ventasPorClienteMap[cliente]) {
        ventasPorClienteMap[cliente] = {
          cliente,
          ultimaVenta: v.fecha || '',
          dias: 0,
          transacciones: 0,
          ventas: 0
        };
      }
      ventasPorClienteMap[cliente].transacciones += 1;
      ventasPorClienteMap[cliente].ventas += (v.monto || 0);
      if (v.fecha && v.fecha > ventasPorClienteMap[cliente].ultimaVenta) {
        ventasPorClienteMap[cliente].ultimaVenta = v.fecha;
      }
    });

    // Calcular días desde última venta
    const hoy = new Date();
    Object.values(ventasPorClienteMap).forEach((cliente: any) => {
      if (cliente.ultimaVenta) {
        const fechaUltimaVenta = new Date(cliente.ultimaVenta);
        const diffTime = Math.abs(hoy.getTime() - fechaUltimaVenta.getTime());
        cliente.dias = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
      }
    });

    this.datosFiltrados.ventasPorCliente = Object.values(ventasPorClienteMap)
      .sort((a: any, b: any) => b.ventas - a.ventas);
  }

  // Los métodos filtrarPor* ya no se usan directamente, todo se maneja en aplicarFiltrosInteractivos

  recalcularMetricas(): void {
    // Recalcular métricas de ventas basadas en los datos filtrados
    if (this.datosFiltrados.ventasDetalladas) {
      const ventas = this.datosFiltrados.ventasDetalladas;
      const ventasConIVA = ventas.reduce((sum: number, v: any) => sum + (v.monto || 0), 0);
      const ventasSinIVA = ventasConIVA / 1.12; // Asumiendo IVA del 12%
      const transacciones = ventas.length;
      const ticketPromedio = transacciones > 0 ? ventasConIVA / transacciones : 0;

      if (!this.datosFiltrados.metricasVentas) {
        this.datosFiltrados.metricasVentas = {};
      }
      this.datosFiltrados.metricasVentas.ventasConIVA = ventasConIVA;
      this.datosFiltrados.metricasVentas.ventasSinIVA = ventasSinIVA;
      this.datosFiltrados.metricasVentas.transacciones = transacciones;
      this.datosFiltrados.metricasVentas.ticketPromedio = ticketPromedio;
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
    if (this.filtrosInteractivos.canal) filtros.push(`Canal: ${this.filtrosInteractivos.canal}`);
    if (this.filtrosInteractivos.vendedor) filtros.push(`Vendedor: ${this.filtrosInteractivos.vendedor}`);
    if (this.filtrosInteractivos.formaPago) filtros.push(`Forma de pago: ${this.filtrosInteractivos.formaPago}`);
    if (this.filtrosInteractivos.categoria) filtros.push(`Categoría: ${this.filtrosInteractivos.categoria}`);
    if (this.filtrosInteractivos.producto) filtros.push(`Producto: ${this.filtrosInteractivos.producto}`);
    if (this.filtrosInteractivos.cliente) filtros.push(`Cliente: ${this.filtrosInteractivos.cliente}`);
    if (this.filtrosInteractivos.mes) filtros.push(`Mes: ${this.filtrosInteractivos.mes}`);
    return filtros.join(', ');
  }

  // ─────────────────────────────────────────────
  // TrackBy functions para optimizar ngFor
  // ─────────────────────────────────────────────

  trackByIndex(index: number, item: any): number {
    return index;
  }

  trackByFactura(index: number, item: any): string | number {
    return item.factura || index;
  }

  trackByProducto(index: number, item: any): string | number {
    return item.producto ? `${item.producto}_${item.formaPago || ''}` : index;
  }

  trackByCliente(index: number, item: any): string | number {
    return item.cliente || index;
  }

  trackByName(index: number, item: any): string | number {
    return item.name || index;
  }

}
