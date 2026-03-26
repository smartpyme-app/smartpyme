import { Component, Input, OnInit, OnChanges, SimpleChanges, ViewChild, Output, EventEmitter, ChangeDetectorRef, ChangeDetectionStrategy } from '@angular/core';
import { CashFlowItem } from '../../models/chart-config.model';
import { RevoGrid } from '@revolist/angular-datagrid';
import { SortingPlugin, FilterPlugin, ExportFilePlugin } from '@revolist/revogrid';

@Component({
  selector: 'app-resultados',
  templateUrl: './resultados.component.html',
  styleUrls: ['./resultados.component.css'],
  changeDetection: ChangeDetectionStrategy.OnPush
})
export class ResultadosComponent implements OnInit, OnChanges {
  @Input() datos: any = {};
  @Output() filtrosCambiados = new EventEmitter<any>();

  // Propiedades cacheadas para evitar recálculos
  private _ventasRowsCache: any[] = [];
  private _gastosRowsCache: any[] = [];
  private _cobrar30RowsCache: any[] = [];
  private _pagar30RowsCache: any[] = [];
  private _totalCobrar30Cache: number = 0;
  private _totalPagar30Cache: number = 0;
  private _lastDatosHash: string = '';

  private inicializado: boolean = false;

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

  // Filtros
  anios = [2024, 2025, 2026];
  anioSeleccionado: number = new Date().getFullYear();

  sucursales = [
    { id: 'todas', nombre: 'Todas' },
    { id: '1', nombre: 'Sucursal 1' },
    { id: '2', nombre: 'Sucursal 2' }
  ];
  sucursalSeleccionada: string = 'todas';

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
    console.log('Resultados - Hash actual:', currentHash);
    console.log('Resultados - Hash anterior:', this._lastDatosHash);

    if (currentHash === this._lastDatosHash && this._lastDatosHash !== '') {
      console.log('Resultados - Hash igual, saltando recalculo');
      return; // No hay cambios
    }
    this._lastDatosHash = currentHash;
    console.log('Resultados - Recalculando cache con nuevos datos');

    // Por ahora las grillas no se usan en el template actual
    // Si en el futuro se necesitan, se pueden implementar aquí
    this._ventasRowsCache = [];
    this._gastosRowsCache = [];
    this._cobrar30RowsCache = [];
    this._pagar30RowsCache = [];
    this._totalCobrar30Cache = 0;
    this._totalPagar30Cache = 0;
  }

  ngOnInit(): void {
    // Recalcular cache si hay datos
    if (this.datos && Object.keys(this.datos).length > 0) {
      this.recalcularRowsCache();
    }
    // Marcar como inicializado después de un pequeño delay
    setTimeout(() => {
      this.inicializado = true;
      this.cdr.markForCheck();
    }, 100);
  }

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['datos']) {
      console.log('Resultados - ngOnChanges ejecutado');
      console.log('Resultados - ESTRUCTURA COMPLETA DE DATOS:', JSON.stringify(this.datos, null, 2));
      console.log('Resultados - Propiedades disponibles:', Object.keys(this.datos || {}));

      if (this.datos && Object.keys(this.datos).length > 0) {
        // Recalcular cache cuando los datos cambien
        this.recalcularRowsCache();
        console.log('Resultados - cache recalculado');
        console.log('Resultados - ventasRows:', this._ventasRowsCache);
        console.log('Resultados - gastosRows:', this._gastosRowsCache);
        this.cdr.markForCheck();
      }
    }
  }

  cambiarAnio(anio: number): void {
    this.anioSeleccionado = anio;
    this.aplicarFiltros();
  }

  cambiarSucursal(): void {
    this.aplicarFiltros();
  }

  aplicarFiltros(): void {
    // No emitir durante la inicialización
    if (!this.inicializado) {
      return;
    }

    const filtros = {
      anio: this.anioSeleccionado,
      sucursal: this.sucursalSeleccionada
    };

    // Emitir evento al componente padre para recargar datos
    this.filtrosCambiados.emit(filtros);
  }

  formatCurrency(value: number): string {
    return this.currencyFormatter.format(value);
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
    return this.filtrarVentas(this._ventasRowsCache);
  }

  get gastosRows(): any[] {
    return this.filtrarGastos(this._gastosRowsCache);
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
    return this.filtrarCobrar30(this._cobrar30RowsCache);
  }

  get pagar30Rows(): any[] {
    return this.filtrarPagar30(this._pagar30RowsCache);
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
    return this._totalCobrar30Cache;
  }

  get totalPagar30(): number {
    return this._totalPagar30Cache;
  }

  /**
   * TrackBy functions para optimización de *ngFor
   */
  trackByIndex(index: number, item: any): number {
    return index;
  }

  trackById(index: number, item: any): string | number {
    return item.id || index;
  }

  trackByName(index: number, item: any): string | number {
    return item.nombre || item.name || index;
  }
}
