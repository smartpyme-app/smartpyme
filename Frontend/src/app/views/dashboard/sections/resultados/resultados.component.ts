import {
  Component,
  Input,
  OnInit,
  OnChanges,
  SimpleChanges,
  ViewChild,
  Output,
  EventEmitter,
  ChangeDetectorRef,
  ChangeDetectionStrategy,
} from '@angular/core';
import { CashFlowItem } from '../../models/chart-config.model';
import { RevoGrid } from '@revolist/angular-datagrid';
import { SortingPlugin, FilterPlugin, ExportFilePlugin } from '@revolist/revogrid';
import { ApiService } from '@services/api.service';
import { AlertService } from '@services/alert.service';
import { DropdownMultiFiltroSelection } from '../../components/dropdown-multi-filtro/dropdown-multi-filtro.component';

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

  /** Mes para flujo de efectivo (1–12). `null` = todo el año (sin `mes` en el API). */
  mesFlujoEfectivo: number | null = null;

  private readonly nombresMes = [
    'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
    'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'
  ];

  /** Sucursales reales (sin opción “Todas”: vacío en el multi = todas). */
  sucursales: { id: string; nombre: string }[] = [];
  /** IDs seleccionados. Vacío + `sucursalesTodasImplicitas` → API “todas”. */
  sucursalesSeleccionadas: string[] = [];

  /**
   * true: “Todas” (todas las sucursales en el filtro); checkboxes de sucursal se muestran marcados.
   * false + []: usuario desmarcó “Seleccionar todo”; checkboxes desmarcados; API sigue en “todas” hasta que elija al menos una.
   */
  sucursalesTodasImplicitas = true;

  constructor(
    private cdr: ChangeDetectorRef,
    private apiService: ApiService,
    private alertService: AlertService
  ) { }

  private cargarSucursales(): void {
    this.apiService.getAll('sucursales/list').subscribe({
      next: (list: any[]) => {
        let items = (list || []).map((s: any) => ({
          id: String(s.id),
          nombre: s.nombre ?? ''
        }));

        const user = this.apiService.auth_user();
        if (user?.tipo !== 'Administrador' && user?.id_sucursal != null) {
          const sid = String(user.id_sucursal);
          items = items.filter(s => s.id === sid);
        }

        this.sucursales = items;

        if (user?.tipo !== 'Administrador' && user?.id_sucursal != null && items.length > 0) {
          this.sucursalesTodasImplicitas = false;
          this.sucursalesSeleccionadas = [String(user.id_sucursal)];
          setTimeout(() => {
            if (this.inicializado) {
              this.aplicarFiltros();
            }
          }, 150);
        } else if (user?.tipo === 'Administrador') {
          this.sucursalesSeleccionadas = [];
          this.sucursalesTodasImplicitas = true;
        }

        this.cdr.markForCheck();
      },
      error: (err) => {
        this.alertService.error(err);
        this.sucursales = [];
        this.sucursalesSeleccionadas = [];
        this.sucursalesTodasImplicitas = true;
        this.cdr.markForCheck();
      }
    });
  }

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

    if (currentHash === this._lastDatosHash && this._lastDatosHash !== '') {
      return;
    }
    this._lastDatosHash = currentHash;

    this._ventasRowsCache = this.datos?.cashflow?.ventas || [];
    this._gastosRowsCache = this.datos?.cashflow?.gastos || [];

    const nVentas = this._ventasRowsCache.length;
    const nGastos = this._gastosRowsCache.length;
    console.log('[Resultados][Flujo efectivo] Ventas del mes:', {
      tieneDatos: nVentas > 0,
      filas: nVentas,
      muestra: nVentas ? this._ventasRowsCache[0] : null
    });
    console.log('[Resultados][Flujo efectivo] Gastos del mes:', {
      tieneDatos: nGastos > 0,
      filas: nGastos,
      muestra: nGastos ? this._gastosRowsCache[0] : null
    });

    this._cobrar30RowsCache = this.datos?.cuentas30?.cobrar || [];
    this._pagar30RowsCache = this.datos?.cuentas30?.pagar || [];

    this._totalCobrar30Cache = this._cobrar30RowsCache.reduce(
      (acc, curr) => acc + (curr.monto || 0),
      0
    );
    this._totalPagar30Cache = this._pagar30RowsCache.reduce(
      (acc, curr) => acc + (curr.monto || 0),
      0
    );
  }

  ngOnInit(): void {
    // Recalcular cache si hay datos
    if (this.datos && Object.keys(this.datos).length > 0) {
      this.recalcularRowsCache();
    }
    this.aplicarDefectoMesFlujoEfectivo();
    this.cargarSucursales();
    // Marcar como inicializado después de un pequeño delay
    setTimeout(() => {
      this.inicializado = true;
      this.aplicarFiltros();
      this.cdr.markForCheck();
    }, 100);
  }

  /**
   * Año en curso → mes actual por defecto. Años pasados/futuros → todo el año (`null`).
   */
  private aplicarDefectoMesFlujoEfectivo(): void {
    const now = new Date();
    const cy = now.getFullYear();
    this.mesFlujoEfectivo = this.anioSeleccionado === cy ? now.getMonth() + 1 : null;
  }

  /** Último mes disponible en el desplegable según año (año actual → hasta hoy; otro año → 12). */
  maxMesDisponibleParaAnio(anio: number): number {
    const now = new Date();
    const cy = now.getFullYear();
    const mesActual = now.getMonth() + 1;
    if (anio < cy || anio > cy) {
      return 12;
    }
    return mesActual;
  }

  /** Opciones del select de mes (dinámico según `anioSeleccionado`). */
  get opcionesMesFlujo(): { valor: number; etiqueta: string }[] {
    const max = this.maxMesDisponibleParaAnio(this.anioSeleccionado);
    const out: { valor: number; etiqueta: string }[] = [];
    for (let m = 1; m <= max; m++) {
      out.push({ valor: m, etiqueta: this.nombresMes[m - 1] });
    }
    return out;
  }

  private ajustarMesFlujoAlCambiarAnio(): void {
    const max = this.maxMesDisponibleParaAnio(this.anioSeleccionado);
    if (this.mesFlujoEfectivo != null && this.mesFlujoEfectivo > max) {
      this.mesFlujoEfectivo = max;
    }
  }

  onMesFlujoEfectivoChange(): void {
    this.aplicarFiltros();
  }

  trackByMesValor(_i: number, item: { valor: number }): number {
    return item.valor;
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
    this.ajustarMesFlujoAlCambiarAnio();
    this.aplicarFiltros();
  }

  onSucursalesMultiFiltroChange(ev: DropdownMultiFiltroSelection): void {
    this.sucursalesTodasImplicitas = ev.todasImplicitas;
    this.sucursalesSeleccionadas = [...ev.seleccionados];
    this.aplicarFiltros();
    this.cdr.markForCheck();
  }

  /** Usuario con una sola sucursal asignada no puede cambiar el filtro. */
  get sucursalesMultiDisabled(): boolean {
    const user = this.apiService.auth_user();
    return user?.tipo !== 'Administrador' && this.sucursales.length <= 1;
  }

  aplicarFiltros(): void {
    // No emitir durante la inicialización
    if (!this.inicializado) {
      return;
    }

    const allIds = this.sucursales.map(s => s.id);
    const sel = this.sucursalesSeleccionadas;
    const sucursal =
      this.sucursalesTodasImplicitas
      || sel.length === 0
      || (allIds.length > 0 && sel.length === allIds.length && allIds.every(id => sel.includes(id)))
        ? 'todas'
        : [...sel];

    const filtros = {
      anio: this.anioSeleccionado,
      sucursal,
      ...(this.mesFlujoEfectivo != null ? { mes: this.mesFlujoEfectivo } : {})
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
