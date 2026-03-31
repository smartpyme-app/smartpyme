import { Component, Input, OnInit, OnChanges, SimpleChanges, Output, EventEmitter, ViewChild, ChangeDetectorRef, ChangeDetectionStrategy } from '@angular/core';
import { ColDef, GridOptions, GridApi, ColumnApi } from 'ag-grid-community';

@Component({
  selector: 'app-inventario',
  templateUrl: './inventario.component.html',
  styleUrls: ['./inventario.component.css'],
  changeDetection: ChangeDetectionStrategy.OnPush
})
export class InventarioComponent implements OnInit, OnChanges {
  @Input() datos: any = {};
  @Output() filtrosCambiados = new EventEmitter<any>();

  @ViewChild('inventarioProductosGrid') inventarioProductosGrid: any;
  @ViewChild('entradasSalidasGrid') entradasSalidasGrid: any;
  @ViewChild('ajustesGrid') ajustesGrid: any;

  // AG Grid configuration - Inventario productos
  inventarioProductosColumnDefs: ColDef[] = [];
  inventarioProductosGridOptions: GridOptions = {};
  private gridApi!: GridApi;
  private gridColumnApi!: ColumnApi;
  quickFilterText: string = '';

  // AG Grid configuration - Entradas y salidas
  entradasSalidasColumnDefs: ColDef[] = [];
  entradasSalidasGridOptions: GridOptions = {};
  private entradasSalidasGridApi!: GridApi;
  private entradasSalidasGridColumnApi!: ColumnApi;
  quickFilterTextEntradasSalidas: string = '';

  // AG Grid configuration - Ajustes
  ajustesColumnDefs: ColDef[] = [];
  ajustesGridOptions: GridOptions = {};
  private ajustesGridApi!: GridApi;
  private ajustesGridColumnApi!: ColumnApi;
  quickFilterTextAjustes: string = '';

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
  
  // Opciones para filtros
  categorias: any[] = [];
  productos: any[] = [];
  sucursales: any[] = [];
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

  constructor(private cdr: ChangeDetectorRef) { }

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
  private currencyFormatter = new Intl.NumberFormat('es-GT', {
    style: 'currency',
    currency: 'USD',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  });

  private numberFormatter = new Intl.NumberFormat('es-GT', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 0
  });

  ngOnInit(): void {
    this.cargarOpcionesFiltros();
    this.configurarAGGrid();
    this.configurarAGGridEntradasSalidas();
    this.configurarAGGridAjustes();
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

  cargarOpcionesFiltros(): void {
    // Aquí cargarías las opciones desde el servicio
    // Por ahora valores de ejemplo
    this.categorias = [];
    this.productos = [];
    this.sucursales = [];
    this.proveedores = [];
  }

  private copiarFiltrosAdicionalesInventarioAplicadoABorrador(): void {
    this.filtroCategoria = this.filtroCategoriaAplicado;
    this.filtroProducto = this.filtroProductoAplicado;
    this.filtroSucursal = this.filtroSucursalAplicado;
    this.filtroProveedor = this.filtroProveedorAplicado;
  }

  private copiarFiltrosAdicionalesInventarioBorradorAAplicado(): void {
    this.filtroCategoriaAplicado = this.filtroCategoria;
    this.filtroProductoAplicado = this.filtroProducto;
    this.filtroSucursalAplicado = this.filtroSucursal;
    this.filtroProveedorAplicado = this.filtroProveedor;
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
    return (
      this.filtroCategoria !== this.filtroCategoriaAplicado ||
      this.filtroProducto !== this.filtroProductoAplicado ||
      this.filtroSucursal !== this.filtroSucursalAplicado ||
      this.filtroProveedor !== this.filtroProveedorAplicado
    );
  }

  limpiarFiltros(): void {
    this.anio = new Date().getFullYear().toString();
    this.mes = '';
    this.filtroCategoria = '';
    this.filtroProducto = '';
    this.filtroSucursal = '';
    this.filtroProveedor = '';
    this.copiarFiltrosAdicionalesInventarioBorradorAAplicado();
    this.limpiarFiltrosInteractivos();
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

    const filtros: any = {
      anio: this.anio,
      categoria: this.filtroCategoriaAplicado,
      producto: this.filtroProductoAplicado,
      sucursal: this.filtroSucursalAplicado,
      proveedor: this.filtroProveedorAplicado,
    };
    if (this.mes) {
      filtros.mes = this.mes;
    }

    this.filtrosCambiados.emit(filtros);
  }

  get puedeLimpiarFiltrosInventario(): boolean {
    const anioActual = new Date().getFullYear().toString();
    if (!!this.mes || this.anio !== anioActual) {
      return true;
    }
    if (this.tieneFiltrosInteractivos()) {
      return true;
    }
    return (
      !!this.filtroCategoria ||
      !!this.filtroProducto ||
      !!this.filtroSucursal ||
      !!this.filtroProveedor ||
      !!this.filtroCategoriaAplicado ||
      !!this.filtroProductoAplicado ||
      !!this.filtroSucursalAplicado ||
      !!this.filtroProveedorAplicado
    );
  }

  formatCurrency(value: number): string {
    return this.currencyFormatter.format(value);
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

    // Recalcular inventario productos
    if (datos.detalleInventario) {
      const rows = datos.detalleInventario.map((item: any) => ({
        producto: item.producto || '-',
        stock: item.stock || 0,
        costo: item.costo || 0,
        inversionPromedio: item.inversionPromedio || 0,
        precio: item.precio || 0,
        ventasEsperadas: item.ventasEsperadas || 0,
        isTotal: false
      }));

      // Calcular totales
      const totales = datos.detalleInventario.reduce((totals: any, item: any) => ({
        stock: totals.stock + (item.stock || 0),
        inversionPromedio: totals.inversionPromedio + (item.inversionPromedio || 0),
        precio: totals.precio + (item.precio || 0),
        ventasEsperadas: totals.ventasEsperadas + (item.ventasEsperadas || 0)
      }), { stock: 0, inversionPromedio: 0, precio: 0, ventasEsperadas: 0 });

      this._totalInventarioProductosCache = totales;

      // Agregar fila de totales
      if (totales.stock !== 0 || totales.inversionPromedio !== 0) {
        rows.push({
          producto: 'Total',
          stock: totales.stock,
          costo: '',
          inversionPromedio: totales.inversionPromedio,
          precio: totales.precio,
          ventasEsperadas: totales.ventasEsperadas,
          isTotal: true
        });
      }

      this._inventarioProductosRowsCache = rows;
    } else {
      this._inventarioProductosRowsCache = [];
      this._totalInventarioProductosCache = { stock: 0, inversionPromedio: 0, precio: 0, ventasEsperadas: 0 };
    }

    // Recalcular entradas y salidas
    if (datos.detalleEntradasSalidas) {
      this._entradasSalidasRowsCache = datos.detalleEntradasSalidas.map((item: any) => ({
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
    } else {
      this._entradasSalidasRowsCache = [];
    }

    // Recalcular ajustes
    if (datos.detalleAjustes) {
      const rows = datos.detalleAjustes.map((item: any) => ({
        fecha: item.fecha || '-',
        producto: item.producto || '-',
        concepto: item.concepto || '-',
        stockInicial: item.stockInicial || 0,
        stockReal: item.stockReal || 0,
        ajuste: item.ajuste || 0,
        costoTotal: item.costoTotal || 0,
        isTotal: false
      }));

      // Calcular total de ajustes
      const totales = datos.detalleAjustes.reduce((totals: any, item: any) => ({
        costoTotal: totals.costoTotal + (item.costoTotal || 0)
      }), { costoTotal: 0 });

      this._totalAjustesCache = totales;

      // Agregar fila de totales
      if (totales.costoTotal !== 0) {
        rows.push({
          fecha: 'Total',
          producto: '',
          concepto: '',
          stockInicial: '',
          stockReal: '',
          ajuste: '',
          costoTotal: totales.costoTotal,
          isTotal: true
        });
      }

      this._ajustesRowsCache = rows;
    } else {
      this._ajustesRowsCache = [];
      this._totalAjustesCache = { costoTotal: 0 };
    }
  }

  get inventarioProductosRows(): any[] {
    return this._inventarioProductosRowsCache;
  }

  get totalInventarioProductos(): any {
    return this._totalInventarioProductosCache;
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
        fileName: `inventario-productos-${fecha}.csv`,
        processCellCallback: (params: any) => {
          return params.value || '';
        }
      });
    }
  }

  exportarExcel(): void {
    // AG Grid Community solo soporta CSV
    if (this.gridApi) {
      const fecha = new Date().toISOString().split('T')[0];
      this.gridApi.exportDataAsCsv({
        fileName: `inventario-productos-${fecha}.csv`,
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
      suppressExcelExport: false,
      suppressCsvExport: false
    };
  }

  get entradasSalidasRows(): any[] {
    return this._entradasSalidasRowsCache;
  }

  onQuickFilterChangeEntradasSalidas(): void {
    if (this.entradasSalidasGridApi) {
      this.entradasSalidasGridApi.setQuickFilter(this.quickFilterTextEntradasSalidas);
    }
  }

  exportarCSVEntradasSalidas(): void {
    if (this.entradasSalidasGridApi) {
      const fecha = new Date().toISOString().split('T')[0];
      this.entradasSalidasGridApi.exportDataAsCsv({
        fileName: `entradas-salidas-${fecha}.csv`,
        processCellCallback: (params: any) => {
          return params.value || '';
        }
      });
    }
  }

  exportarExcelEntradasSalidas(): void {
    if (this.entradasSalidasGridApi) {
      const fecha = new Date().toISOString().split('T')[0];
      this.entradasSalidasGridApi.exportDataAsCsv({
        fileName: `entradas-salidas-${fecha}.csv`,
        processCellCallback: (params: any) => {
          return params.value || '';
        }
      });
    }
  }

  limpiarFiltrosGridEntradasSalidas(): void {
    if (this.entradasSalidasGridApi) {
      this.entradasSalidasGridApi.setFilterModel(null);
      this.quickFilterTextEntradasSalidas = '';
      this.entradasSalidasGridApi.setQuickFilter('');
    }
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
    // Filtrar detalle de inventario por categoría (usando el gráfico de stock por categoría)
    if (this.filtrosInteractivos.categoria && this.datosFiltrados.detalleInventario) {
      // Filtrar productos que pertenezcan a la categoría seleccionada
      // Esto requiere que los datos de detalleInventario tengan información de categoría
      // Por ahora, el filtro se aplica principalmente al gráfico
    }

    // Filtrar detalle de entradas y salidas por mes
    if (this.filtrosInteractivos.mes && this.datosFiltrados.detalleEntradasSalidas) {
      const meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 
                     'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
      const mesIndex = meses.findIndex(m => m.toLowerCase() === this.filtrosInteractivos.mes?.toLowerCase());
      
      if (mesIndex !== -1) {
        const datosOriginales = this.datosOriginales.detalleEntradasSalidas || [];
        this.datosFiltrados.detalleEntradasSalidas = datosOriginales.filter((item: any) => {
          if (item.mes != null && item.mes >= 1 && item.mes <= 12) {
            return item.mes === mesIndex + 1;
          }
          if (!item.fecha) return false;
          const partes = String(item.fecha).split('/');
          if (partes.length === 3) {
            const mes = parseInt(partes[1], 10) - 1;
            return mes === mesIndex;
          }
          const f = String(item.fecha).trim().toLowerCase();
          const m = meses[mesIndex].toLowerCase();
          return f === m || f.startsWith(`${m} `);
        });
      }
    }

    // Filtrar detalle de inventario por producto
    if (this.filtrosInteractivos.producto) {
      if (this.datosFiltrados.detalleInventario) {
        const datosOriginales = this.datosOriginales.detalleInventario || [];
        this.datosFiltrados.detalleInventario = datosOriginales.filter((item: any) => {
          return item.producto && item.producto.toLowerCase().includes(this.filtrosInteractivos.producto?.toLowerCase() || '');
        });
      }
      if (this.datosFiltrados.detalleEntradasSalidas) {
        const datosOriginales = this.datosOriginales.detalleEntradasSalidas || [];
        this.datosFiltrados.detalleEntradasSalidas = datosOriginales.filter((item: any) => {
          return item.producto && item.producto.toLowerCase().includes(this.filtrosInteractivos.producto?.toLowerCase() || '');
        });
      }
      if (this.datosFiltrados.detalleAjustes) {
        const datosOriginales = this.datosOriginales.detalleAjustes || [];
        this.datosFiltrados.detalleAjustes = datosOriginales.filter((item: any) => {
          return item.producto && item.producto.toLowerCase().includes(this.filtrosInteractivos.producto?.toLowerCase() || '');
        });
      }
    }
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
    if (!this.datosFiltrados.detalleEntradasSalidas) return;

    const meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 
                   'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
    
    const entradasPorMes: { [key: string]: number } = {};
    const salidasPorMes: { [key: string]: number } = {};

    this.datosFiltrados.detalleEntradasSalidas.forEach((item: any) => {
      let mesNombre: string | null = null;
      if (item.mes != null && item.mes >= 1 && item.mes <= 12) {
        mesNombre = meses[item.mes - 1];
      } else if (item.fecha) {
        const partes = String(item.fecha).split('/');
        if (partes.length === 3) {
          const mes = parseInt(partes[1], 10) - 1;
          if (mes >= 0 && mes < 12) mesNombre = meses[mes];
        } else {
          const f = String(item.fecha).trim().toLowerCase();
          const idx = meses.findIndex(
            (m) =>
              f === m.toLowerCase() ||
              f.startsWith(`${m.toLowerCase()} `),
          );
          if (idx !== -1) mesNombre = meses[idx];
        }
      }
      if (!mesNombre) return;

      const ent = Number(item.entradas) || 0;
      const sal = Number(item.salidas) || 0;
      if (ent) entradasPorMes[mesNombre] = (entradasPorMes[mesNombre] || 0) + ent;
      if (sal) salidasPorMes[mesNombre] = (salidasPorMes[mesNombre] || 0) + sal;
    });

    // Si hay filtro de mes, mostrar solo ese mes
    if (this.filtrosInteractivos.mes) {
      const mesFiltrado = this.filtrosInteractivos.mes;
      this.datosFiltrados.entradasSalidasPorMesConfig = {
        ...this.datosFiltrados.entradasSalidasPorMesConfig,
        labels: [mesFiltrado],
        data: [
          {
            name: 'Entradas',
            data: [entradasPorMes[mesFiltrado] || 0]
          },
          {
            name: 'Salidas',
            data: [salidasPorMes[mesFiltrado] || 0]
          }
        ]
      };
    } else {
      // Mostrar todos los meses con datos
      const labels = meses.filter(m => entradasPorMes[m] !== undefined || salidasPorMes[m] !== undefined);
      this.datosFiltrados.entradasSalidasPorMesConfig = {
        ...this.datosFiltrados.entradasSalidasPorMesConfig,
        labels: labels.length > 0 ? labels : meses,
        data: [
          {
            name: 'Entradas',
            data: labels.length > 0 ? labels.map(m => entradasPorMes[m] || 0) : meses.map(() => 0)
          },
          {
            name: 'Salidas',
            data: labels.length > 0 ? labels.map(m => salidasPorMes[m] || 0) : meses.map(() => 0)
          }
        ]
      };
    }
  }

  recalcularMetricasEntradasSalidas(): void {
    if (!this.datosFiltrados.detalleEntradasSalidas) return;

    const totalEntradas = this.datosFiltrados.detalleEntradasSalidas.reduce((sum: number, item: any) => 
      sum + (item.entradas || 0), 0);
    const totalSalidas = this.datosFiltrados.detalleEntradasSalidas.reduce((sum: number, item: any) => 
      sum + (item.salidas || 0), 0);
    const totalUtilidad = this.datosFiltrados.detalleEntradasSalidas.reduce((sum: number, item: any) => 
      sum + ((item.valorEntradas || 0) - (item.valorSalidas || 0)), 0);

    if (!this.datosFiltrados.entradasSalidas) {
      this.datosFiltrados.entradasSalidas = {};
    }
    this.datosFiltrados.entradasSalidas.entradas = totalEntradas;
    this.datosFiltrados.entradasSalidas.salidas = totalSalidas;
    this.datosFiltrados.entradasSalidas.utilidadEsperada = totalUtilidad;
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
              return `(${this.formatCurrency(Math.abs(value)).replace('$', '')})`;
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
    if (this.ajustesGridApi) {
      this.ajustesGridApi.setQuickFilter(this.quickFilterTextAjustes);
    }
  }

  exportarCSVAjustes(): void {
    if (this.ajustesGridApi) {
      const fecha = new Date().toISOString().split('T')[0];
      this.ajustesGridApi.exportDataAsCsv({
        fileName: `ajustes-inventario-${fecha}.csv`,
        processCellCallback: (params: any) => {
          return params.value || '';
        }
      });
    }
  }

  exportarExcelAjustes(): void {
    if (this.ajustesGridApi) {
      const fecha = new Date().toISOString().split('T')[0];
      this.ajustesGridApi.exportDataAsCsv({
        fileName: `ajustes-inventario-${fecha}.csv`,
        processCellCallback: (params: any) => {
          return params.value || '';
        }
      });
    }
  }

  limpiarFiltrosGridAjustes(): void {
    if (this.ajustesGridApi) {
      this.ajustesGridApi.setFilterModel(null);
      this.quickFilterTextAjustes = '';
      this.ajustesGridApi.setQuickFilter('');
    }
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
    } else {
      this.filtrosInteractivos.producto = producto;
    }
    this.aplicarFiltrosInteractivos();
  }

  onProductoClickEntradasSalidas(producto: string): void {
    if (this.filtrosInteractivos.producto === producto) {
      delete this.filtrosInteractivos.producto;
    } else {
      this.filtrosInteractivos.producto = producto;
    }
    this.aplicarFiltrosInteractivos();
  }

  onProductoClickAjustes(producto: string): void {
    if (this.filtrosInteractivos.producto === producto) {
      delete this.filtrosInteractivos.producto;
    } else {
      this.filtrosInteractivos.producto = producto;
    }
    this.aplicarFiltrosInteractivos();
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
