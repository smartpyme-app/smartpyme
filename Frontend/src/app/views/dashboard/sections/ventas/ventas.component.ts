import { Component, Input, OnInit, ViewChild, Output, EventEmitter } from '@angular/core';
import { RevoGrid } from '@revolist/angular-datagrid';
import { SortingPlugin, FilterPlugin, ExportFilePlugin } from '@revolist/revogrid';

@Component({
  selector: 'app-ventas',
  templateUrl: './ventas.component.html',
  styleUrls: ['./ventas.component.css']
})
export class VentasComponent implements OnInit {
  @Input() datos: any = {};
  @Output() filtrosCambiados = new EventEmitter<any>();

  @ViewChild('ventasDetalladasGrid') ventasDetalladasGrid!: RevoGrid;
  @ViewChild('ventasPorProductoGrid') ventasPorProductoGrid!: RevoGrid;

  ventasDetalladasPlugins = [SortingPlugin, FilterPlugin, ExportFilePlugin];
  ventasPorProductoPlugins = [SortingPlugin, FilterPlugin, ExportFilePlugin];
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
    // Marcar como inicializado después de un pequeño delay para evitar emitir durante la inicialización
    setTimeout(() => {
      this.inicializado = true;
    }, 100);
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
}
