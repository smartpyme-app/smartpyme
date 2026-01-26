import { Component, Input, OnInit, OnChanges, SimpleChanges, ViewChild, Output, EventEmitter } from '@angular/core';
import { RevoGrid } from '@revolist/angular-datagrid';
import { SortingPlugin, FilterPlugin, ExportFilePlugin } from '@revolist/revogrid';
import { ColDef, GridOptions, GridApi, ColumnApi } from 'ag-grid-community';

@Component({
  selector: 'app-ventas',
  templateUrl: './ventas.component.html',
  styleUrls: ['./ventas.component.css']
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

  // Filtros de fechas
  fechaInicio: string = '';
  fechaFin: string = '';
  
  // Filtros adicionales
  mostrarFiltrosAdicionales: boolean = false;
  filtroSucursal: string = '';
  filtroEstado: string = '';
  filtroCanal: string = '';
  filtroCliente: string = '';
  filtroVendedor: string = '';
  
  // Opciones para filtros
  sucursales: any[] = [];
  canales: any[] = [];
  clientes: any[] = [];
  vendedores: any[] = [];

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

  constructor() { }

  private inicializado: boolean = false;

  ngOnInit(): void {
    this.inicializarFechas();
    this.cargarOpcionesFiltros();
    this.configurarAGGrid();
    this.configurarAGGridClientes();
    // Guardar datos originales si existen
    if (this.datos && Object.keys(this.datos).length > 0) {
      this.datosOriginales = JSON.parse(JSON.stringify(this.datos));
      this.datosFiltrados = JSON.parse(JSON.stringify(this.datos));
      // Asegurar que los arrays estén ordenados de mayor a menor
      this.ordenarArraysIniciales();
    }
    // Marcar como inicializado después de un pequeño delay para evitar emitir durante la inicialización
    setTimeout(() => {
      this.inicializado = true;
    }, 100);
  }

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['datos']) {
      // Actualizar datos originales cuando cambian
      if (this.datos && Object.keys(this.datos).length > 0) {
        this.datosOriginales = JSON.parse(JSON.stringify(this.datos));
        // Aplicar filtros interactivos si existen
        if (Object.keys(this.filtrosInteractivos).length > 0) {
          this.aplicarFiltrosInteractivos();
        } else {
          this.datosFiltrados = JSON.parse(JSON.stringify(this.datos));
          this.ordenarArraysIniciales();
          this.datos = this.datosFiltrados;
        }
      }
    }
  }

  configurarAGGrid(): void {
    this.ventasPorProductoColumnDefs = [
      { 
        field: 'categoria', 
        headerName: 'Categoría',
        width: 150,
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
        field: 'producto', 
        headerName: 'Producto',
        width: 400,
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
        field: 'formaPago', 
        headerName: 'Forma de pago',
        width: 150,
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
        field: 'cantidad', 
        headerName: 'Cantidad',
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
        field: 'precioUnitario', 
        headerName: 'Precio unitario',
        width: 130,
        sortable: true,
        filter: true,
        cellStyle: (params: any): any => {
          if (params.data?.isTotal) {
            return { fontWeight: '600', backgroundColor: '#66A3FF', color: '#ffffff', textAlign: 'right' };
          }
          return { textAlign: 'right' } as any;
        }
      },
      { 
        field: 'descuento', 
        headerName: 'Descuento',
        width: 120,
        sortable: true,
        filter: true,
        cellStyle: (params: any): any => {
          if (params.data?.isTotal) {
            return { fontWeight: '600', backgroundColor: '#66A3FF', color: '#ffffff', textAlign: 'right' };
          }
          return { textAlign: 'right' } as any;
        }
      },
      { 
        field: 'ventasSinIVA', 
        headerName: 'Ventas totales sin IVA',
        width: 180,
        sortable: true,
        filter: true,
        cellStyle: (params: any): any => {
          if (params.data?.isTotal) {
            return { fontWeight: '600', backgroundColor: '#66A3FF', color: '#ffffff', textAlign: 'right' };
          }
          return { textAlign: 'right' } as any;
        }
      },
      { 
        field: 'costoTotal', 
        headerName: 'Costo total',
        width: 130,
        sortable: true,
        filter: true,
        cellStyle: (params: any): any => {
          if (params.data?.isTotal) {
            return { fontWeight: '600', backgroundColor: '#66A3FF', color: '#ffffff', textAlign: 'right' };
          }
          return { textAlign: 'right' } as any;
        }
      },
      { 
        field: 'utilidad', 
        headerName: 'Utilidad',
        width: 120,
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

    this.ventasPorProductoGridOptions = {
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
      // Habilitar selección de texto en celdas
      enableCellTextSelection: true,
      ensureDomOrder: true,
      // Habilitar rangos de celdas (selección múltiple)
      enableRangeSelection: true,
      // Eventos de celda
      onCellClicked: (params: any) => {
        this.onCellClicked(params);
      },
      onCellDoubleClicked: (params: any) => {
        this.onCellDoubleClicked(params);
      },
      onCellKeyDown: (params: any) => {
        this.onCellKeyDown(params);
      },
      onGridReady: (params: any) => {
        this.gridApi = params.api;
        this.gridColumnApi = params.columnApi;
      },
      suppressExcelExport: false,
      suppressCsvExport: false
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
      enableRangeSelection: true,
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

  inicializarFechas(): void {
    const hoy = new Date();
    const primerDiaMes = new Date(hoy.getFullYear(), hoy.getMonth(), 1);
    
    this.fechaFin = hoy.toISOString().split('T')[0];
    this.fechaInicio = primerDiaMes.toISOString().split('T')[0];
    
    // NO aplicar filtros automáticamente durante la inicialización
    // Los filtros se aplicarán cuando el usuario interactúe o cuando el componente esté listo
  }

  cargarOpcionesFiltros(): void {
    // Aquí cargarías las opciones desde el servicio
    // Por ahora valores de ejemplo
    this.sucursales = [];
    this.canales = [];
    this.clientes = [];
    this.vendedores = [];
    this.categoriasProductos = [];
    this.productos = [];
  }

  toggleFiltrosAdicionales(): void {
    this.mostrarFiltrosAdicionales = !this.mostrarFiltrosAdicionales;
  }

  cambiarVistaMetricas(vista: string): void {
    this.vistaMetricas = vista;
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
    const hoy = new Date();
    const primerDiaMes = new Date(hoy.getFullYear(), hoy.getMonth(), 1);
    
    this.fechaFin = hoy.toISOString().split('T')[0];
    this.fechaInicio = primerDiaMes.toISOString().split('T')[0];
    this.filtroSucursal = '';
    this.filtroEstado = '';
    this.filtroCanal = '';
    this.filtroCliente = '';
    this.filtroVendedor = '';
    this.aplicarFiltros();
  }

  aplicarFiltros(): void {
    // No emitir durante la inicialización
    if (!this.inicializado) {
      return;
    }
    
    const filtros = {
      fechaInicio: this.fechaInicio,
      fechaFin: this.fechaFin,
      sucursal: this.filtroSucursal,
      estado: this.filtroEstado,
      canal: this.filtroCanal,
      cliente: this.filtroCliente,
      vendedor: this.filtroVendedor
    };
    
    // Emitir evento al componente padre para recargar datos
    this.filtrosCambiados.emit(filtros);
  }

  formatCurrency(value: number): string {
    return new Intl.NumberFormat('es-GT', {
      style: 'currency',
      currency: 'USD',
      minimumFractionDigits: 2,
      maximumFractionDigits: 2
    }).format(value);
  }

  get ventasDetalladasRows(): any[] {
    if (!this.datos.ventasDetalladas) return [];
    const rows = this.datos.ventasDetalladas.map((v: any) => ({
      fecha: v.fecha || '-',
      cliente: v.cliente || '-',
      factura: v.factura || '-',
      productos: v.productos || 0,
      monto: this.formatCurrency(v.monto || 0),
      montoOriginal: v.monto || 0,
      estado: v.estado || '-'
    }));
    return this.filtrarVentasDetalladasPorBusqueda(rows);
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

  get ventasPorProductoRows(): any[] {
    if (!this.datos.ventasPorProducto) return [];
    const rows = this.datos.ventasPorProducto.map((item: any) => ({
      categoria: item.categoria || '-',
      producto: item.producto || '-',
      formaPago: item.formaPago || '-',
      cantidad: item.cantidad || 0,
      precioUnitario: this.formatCurrency(item.precioUnitario || 0),
      precioUnitarioOriginal: item.precioUnitario || 0,
      descuento: this.formatCurrency(item.descuento || 0),
      descuentoOriginal: item.descuento || 0,
      ventasSinIVA: this.formatCurrency(item.ventasSinIVA || 0),
      ventasSinIVAOriginal: item.ventasSinIVA || 0,
      costoTotal: this.formatCurrency(item.costoTotal || 0),
      costoTotalOriginal: item.costoTotal || 0,
      utilidad: this.formatCurrency(item.utilidad || 0),
      utilidadOriginal: item.utilidad || 0,
      isTotal: false
    }));
    
    // Agregar fila de totales al final
    const totales = this.totalVentasPorProducto;
    if (totales.cantidad > 0) {
      rows.push({
        categoria: 'TOTAL',
        producto: '',
        formaPago: '',
        cantidad: totales.cantidad,
        precioUnitario: '',
        precioUnitarioOriginal: 0,
        descuento: '',
        descuentoOriginal: 0,
        ventasSinIVA: this.formatCurrency(totales.ventasSinIVA),
        ventasSinIVAOriginal: totales.ventasSinIVA,
        costoTotal: this.formatCurrency(totales.costoTotal),
        costoTotalOriginal: totales.costoTotal,
        utilidad: this.formatCurrency(totales.utilidad),
        utilidadOriginal: totales.utilidad,
        isTotal: true
      });
    }
    
    return rows;
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

  get ventasPorClienteRows(): any[] {
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
    this.datosFiltrados = JSON.parse(JSON.stringify(datosBase));

    // Primero filtrar las ventas detalladas según todos los filtros activos
    this.filtrarVentasDetalladas();

    // Luego recalcular todos los gráficos basándose en las ventas filtradas
    this.recalcularTodosLosGraficos();

    // Recalcular métricas
    this.recalcularMetricas();

    // Actualizar los datos que se muestran (crear nueva referencia para que Angular detecte cambios)
    this.datos = JSON.parse(JSON.stringify(this.datosFiltrados));
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
      this.datosFiltrados = JSON.parse(JSON.stringify(this.datosOriginales));
      this.datos = this.datosFiltrados;
    } else if (this.datos) {
      // Si no hay datos originales guardados, recargar desde el input
      this.datosFiltrados = JSON.parse(JSON.stringify(this.datos));
      this.datos = this.datosFiltrados;
    }
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

}
