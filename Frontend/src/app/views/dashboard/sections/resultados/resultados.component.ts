import { Component, Input, OnInit, ViewChild } from '@angular/core';
import { CashFlowItem } from '../../models/chart-config.model';
import { RevoGrid } from '@revolist/angular-datagrid';
import { SortingPlugin, FilterPlugin, ExportFilePlugin } from '@revolist/revogrid';

@Component({
  selector: 'app-resultados',
  templateUrl: './resultados.component.html',
  styleUrls: ['./resultados.component.css']
})
export class ResultadosComponent implements OnInit {
  @Input() datos: any = {};
  @Input() presupuestoSeleccionado: string = 'todas';
  @Input() presupuestos: any[] = [];

  @ViewChild('ventasGrid') ventasGrid!: RevoGrid;
  @ViewChild('gastosGrid') gastosGrid!: RevoGrid;
  @ViewChild('cobrar30Grid') cobrar30Grid!: RevoGrid;
  @ViewChild('pagar30Grid') pagar30Grid!: RevoGrid;

  // Plugins para las tablas
  ventasPlugins = [SortingPlugin, FilterPlugin, ExportFilePlugin];
  gastosPlugins = [SortingPlugin, FilterPlugin, ExportFilePlugin];
  cobrar30Plugins = [SortingPlugin, FilterPlugin, ExportFilePlugin];
  pagar30Plugins = [SortingPlugin, FilterPlugin, ExportFilePlugin];

  // Búsqueda
  busquedaVentas: string = '';
  busquedaGastos: string = '';
  busquedaCobrar30: string = '';
  busquedaPagar30: string = '';

  constructor() { }

  ngOnInit(): void {
  }

  formatCurrency(value: number): string {
    return new Intl.NumberFormat('es-GT', {
      style: 'currency',
      currency: 'USD',
      minimumFractionDigits: 2,
      maximumFractionDigits: 2
    }).format(value);
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
    if (!this.datos.cashFlow) return [];
    const rows = this.datos.cashFlow.ventasDelMes.map((v: CashFlowItem) => ({
      cliente: v.cliente || '-',
      factura: v.factura,
      monto: this.formatCurrency(v.monto),
      montoOriginal: v.monto
    }));
    return this.filtrarVentas(rows);
  }

  get gastosRows(): any[] {
    if (!this.datos.cashFlow) return [];
    const rows = this.datos.cashFlow.gastosDelMes.map((g: CashFlowItem) => ({
      proveedor: g.proveedor || '-',
      factura: g.factura || '-',
      monto: this.formatCurrency(g.monto),
      montoOriginal: g.monto
    }));
    return this.filtrarGastos(rows);
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

  onBusquedaVentasChange(): void {
  }

  onBusquedaGastosChange(): void {
  }

  exportarVentas(): void {
    if (this.ventasRows.length > 0) {
      const fecha = new Date().toISOString().split('T')[0];
      this.exportarACSV(this.ventasRows, this.ventasColumns, `ventas-mes-${fecha}.csv`);
    } else {
      alert('No hay datos de ventas para exportar');
    }
  }

  exportarGastos(): void {
    if (this.gastosRows.length > 0) {
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
    if (!this.datos.cuentasPorCobrar30Dias) return [];
    const rows = this.datos.cuentasPorCobrar30Dias.map((item: any) => ({
      factura: item.factura || '-',
      cliente: item.cliente || '-',
      vence: item.vence || '-',
      diasVencimiento: item.diasVencimiento || 0
    }));
    return this.filtrarCobrar30(rows);
  }

  get pagar30Rows(): any[] {
    if (!this.datos.cuentasPorPagar30Dias) return [];
    const rows = this.datos.cuentasPorPagar30Dias.map((item: any) => ({
      factura: item.factura || '-',
      proveedor: item.proveedor || '-',
      vence: item.vence || '-',
      diasVencimiento: item.diasVencimiento || 0
    }));
    return this.filtrarPagar30(rows);
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
    if (this.cobrar30Rows.length > 0) {
      const fecha = new Date().toISOString().split('T')[0];
      this.exportarACSV(this.cobrar30Rows, this.cobrar30Columns, `cuentas-por-cobrar-30-dias-${fecha}.csv`);
    } else {
      alert('No hay datos para exportar');
    }
  }

  exportarPagar30(): void {
    if (this.pagar30Rows.length > 0) {
      const fecha = new Date().toISOString().split('T')[0];
      this.exportarACSV(this.pagar30Rows, this.pagar30Columns, `cuentas-por-pagar-30-dias-${fecha}.csv`);
    } else {
      alert('No hay datos para exportar');
    }
  }

  get totalCobrar30(): number {
    if (!this.datos.cuentasPorCobrar30Dias) return 0;
    return this.datos.cuentasPorCobrar30Dias.reduce((sum: number, item: any) => sum + (item.monto || 0), 0);
  }

  get totalPagar30(): number {
    if (!this.datos.cuentasPorPagar30Dias) return 0;
    return this.datos.cuentasPorPagar30Dias.reduce((sum: number, item: any) => sum + (item.monto || 0), 0);
  }
}
