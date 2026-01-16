import { Component, Input, OnInit, ViewChild, Output, EventEmitter } from '@angular/core';
import { RevoGrid } from '@revolist/angular-datagrid';
import { SortingPlugin, FilterPlugin, ExportFilePlugin } from '@revolist/revogrid';
import { ColDef, GridOptions, GridApi, ColumnApi } from 'ag-grid-community';

@Component({
  selector: 'app-ventas',
  templateUrl: './ventas.component.html',
  styleUrls: ['./ventas.component.css']
})
export class VentasComponent implements OnInit {
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
    // Marcar como inicializado después de un pequeño delay para evitar emitir durante la inicialización
    setTimeout(() => {
      this.inicializado = true;
    }, 100);
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
    return this.filtrarVentasDetalladas(rows);
  }

  filtrarVentasDetalladas(rows: any[]): any[] {
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

}
