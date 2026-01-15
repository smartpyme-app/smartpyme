import { Component, Input, OnInit, ViewChild } from '@angular/core';
import { RevoGrid } from '@revolist/angular-datagrid';
import { SortingPlugin, FilterPlugin, ExportFilePlugin } from '@revolist/revogrid';

@Component({
  selector: 'app-ventas',
  templateUrl: './ventas.component.html',
  styleUrls: ['./ventas.component.css']
})
export class VentasComponent implements OnInit {
  @Input() datos: any = {};

  @ViewChild('ventasDetalladasGrid') ventasDetalladasGrid!: RevoGrid;

  ventasDetalladasPlugins = [SortingPlugin, FilterPlugin, ExportFilePlugin];
  busquedaVentasDetalladas: string = '';

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
