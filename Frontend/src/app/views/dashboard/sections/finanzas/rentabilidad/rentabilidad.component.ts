import {
  ChangeDetectorRef,
  Component,
  DestroyRef,
  ElementRef,
  Input,
  OnChanges,
  OnInit,
  SimpleChanges,
  ViewChild,
  inject,
} from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { CommonModule } from '@angular/common';
import { ApiService } from '@services/api.service';
import { of, Subject } from 'rxjs';
import { debounceTime, distinctUntilChanged, finalize, map, switchMap, take } from 'rxjs/operators';
import {
  formatEmpresaCurrency,
  formatEmpresaCurrencyInt,
} from '@helpers/currency-format.helper';
import {
  RentabilidadDimensionId,
  RentabilidadHorizonteId,
  RentabilidadKpi,
  RentabilidadMontoPct,
  RentabilidadClienteDetalle,
  RentabilidadClienteFila,
  RentabilidadProductoDetalle,
  RentabilidadProductoTablaFila,
  RentabilidadProveedorDetalle,
  RentabilidadProveedorFila,
  RentabilidadProveedorResumenFila,
  RentabilidadProyectoBarTono,
  RentabilidadProyectoDetalle,
  RentabilidadProyectoFila,
  RentabilidadProyectoResultadoFila,
  RentabilidadSucursalFila,
  RentabilidadMock,
} from './rentabilidad.model';
import {
  FinanzasRentabilidadDataService,
} from '../../../services/finanzas-rentabilidad-data.service';
import {
  DashboardFiltroCatalogoItem,
  DashboardFiltrosCatalogoService,
} from '../../../services/dashboard-filtros-catalogo.service';
import { factorHorizonte, kpisDesdeResumen } from './rentabilidad-kpis.builder';
import { resumenDesdeFilas } from './rentabilidad-kpis.resumen';
import { detalleClienteDesdeFila } from './rentabilidad-cliente-detalle.builder';
import { detalleProveedorDesdeFila } from './rentabilidad-proveedor-detalle.builder';
import { detalleProyectoDesdeFila } from './rentabilidad-proyecto-detalle.builder';
import { detalleProductoDesdeFila } from './rentabilidad-producto-detalle.builder';
import { MetricCard } from '../../../models/chart-config.model';
import { FinanzasKpiCardComponent } from '../finanzas-kpi-card.component';
import {
  alternarOrden,
  EstadoOrden,
  ordenarFilas,
} from '../finanzas-table-sort.helper';

export interface RentKpiChartItem {
  metric: MetricCard;
  linea?: string;
  sublinea?: string;
  sublineaTono?: 'neg' | 'pos';
  alerta?: boolean;
}

const RENTABILIDAD_VACIA: RentabilidadMock = {
  kpis: [],
  resumen: null,
  filasSucursal: [],
  filasProducto: [],
  filasCliente: [],
  filasProveedor: [],
  filasProyecto: [],
};

@Component({
  selector: 'app-rentabilidad',
  templateUrl: './rentabilidad.component.html',
  styleUrls: ['./rentabilidad.component.css'],
  standalone: true,
  imports: [CommonModule, FinanzasKpiCardComponent],
})
export class RentabilidadComponent implements OnInit, OnChanges {
  private readonly api = inject(ApiService);
  private readonly rentabilidadData = inject(FinanzasRentabilidadDataService);
  private readonly filtrosCatalogo = inject(DashboardFiltrosCatalogoService);
  private readonly cdr = inject(ChangeDetectorRef);
  private readonly destroyRef = inject(DestroyRef);
  private readonly buscarProducto$ = new Subject<string>();
  private readonly buscarCliente$ = new Subject<string>();
  private busquedaSeq = 0;
  private clienteSeq = 0;

  @ViewChild('detalleProducto') private detalleProducto?: ElementRef<HTMLElement>;
  @ViewChild('detalleSucursal') private detalleSucursal?: ElementRef<HTMLElement>;
  @ViewChild('detalleCliente') private detalleCliente?: ElementRef<HTMLElement>;
  @ViewChild('detalleProveedor') private detalleProveedor?: ElementRef<HTMLElement>;
  @ViewChild('detalleProyecto') private detalleProyecto?: ElementRef<HTMLElement>;

  @Input() periodoTitulo = '';
  @Input() anio = new Date().getFullYear();
  @Input() mes = new Date().getMonth() + 1;
  @Input() sucursalId = 'consolidado';
  @Input() horizonte: RentabilidadHorizonteId = 'mes';
  /** En «Alertas y resúmenes» los KPIs superiores son fijos (alertas empresa). */
  @Input() kpiModo: 'dinamico' | 'alertas' = 'dinamico';

  data: RentabilidadMock = RENTABILIDAD_VACIA;
  cargando = false;
  contenidoListo = false;
  errorMensaje: string | null = null;
  private loadSeq = 0;

  readonly dimensionOpciones: {
    id: RentabilidadDimensionId;
    label: string;
  }[] = [
    { id: 'sucursal', label: 'Sucursal' },
    { id: 'producto', label: 'Producto' },
    { id: 'cliente', label: 'Cliente' },
    { id: 'proveedor', label: 'Proveedor' },
    { id: 'proyecto', label: 'Proyecto' },
  ];

  dimension: RentabilidadDimensionId = 'sucursal';
  productoFiltroId = 'todos';
  productoFiltroNombre = '';
  productoTexto = '';
  productoSugerencias: DashboardFiltroCatalogoItem[] = [];
  productoListaAbierta = false;
  productoBuscando = false;
  productoHayMas = false;
  private productoQueryActiva = '';
  clienteFiltroId = 'todos';
  clienteFiltroNombre = '';
  clienteTexto = '';
  clienteSugerencias: DashboardFiltroCatalogoItem[] = [];
  clienteListaAbierta = false;
  clienteBuscando = false;
  clienteHayMas = false;
  private clienteQueryActiva = '';
  proveedorFiltroNombre = '';
  proveedorTexto = '';
  proveedorListaAbierta = false;
  readonly proveedorColumnas: {
    col: 'nombre' | 'compro' | 'parteComprasPct' | 'ganoAlVender' | 'ganaPorDolar';
    label: string;
    num?: boolean;
  }[] = [
    { col: 'nombre', label: 'Proveedor' },
    { col: 'compro', label: 'Total compras', num: true },
    { col: 'parteComprasPct', label: 'Porcentaje compras', num: true },
    { col: 'ganoAlVender', label: 'Margen', num: true },
    { col: 'ganaPorDolar', label: 'Ganancia', num: true },
  ];
  readonly ordenPorDimension: Record<RentabilidadDimensionId, EstadoOrden> = {
    sucursal: { col: null, dir: 'asc' },
    producto: { col: null, dir: 'asc' },
    cliente: { col: null, dir: 'asc' },
    proveedor: { col: null, dir: 'asc' },
    proyecto: { col: null, dir: 'asc' },
  };
  proyectoFiltroNombre = '';
  proyectoTexto = '';
  proyectoListaAbierta = false;
  sucursalAbierta: RentabilidadSucursalFila | null = null;
  productoAbierto: RentabilidadProductoTablaFila | null = null;
  clienteAbierto: RentabilidadClienteFila | null = null;
  proveedorAbierto: RentabilidadProveedorFila | null = null;
  proyectoAbierto: RentabilidadProyectoFila | null = null;

  ngOnInit(): void {
    this.cargar();
    this.buscarProducto$
      .pipe(
        debounceTime(250),
        map((value) => value.trim()),
        distinctUntilChanged(),
        switchMap((q) => {
          this.productoQueryActiva = q;
          const seq = ++this.busquedaSeq;
          if (q.length < 2) {
            return of({ seq, list: [] as DashboardFiltroCatalogoItem[] });
          }
          this.productoBuscando = true;
          this.cdr.markForCheck();
          return this.filtrosCatalogo.buscarProductos(q, 0).pipe(map((list) => ({ seq, list })));
        }),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe(({ seq, list }) => {
        if (seq !== this.busquedaSeq) {
          return;
        }
        const term = this.productoQueryActiva;
        const page =
          term.length < 2
            ? []
            : list.filter((p) => p.nombre.toLowerCase().includes(term.toLowerCase())).slice(0, 20);
        this.productoSugerencias = page;
        this.productoHayMas = list.length === 20;
        this.productoBuscando = false;
        this.cdr.markForCheck();
      });
    this.buscarCliente$
      .pipe(
        debounceTime(250),
        map((value) => value.trim()),
        distinctUntilChanged(),
        switchMap((q) => {
          this.clienteQueryActiva = q;
          const seq = ++this.clienteSeq;
          if (q.length < 2) {
            return of({ seq, list: [] as DashboardFiltroCatalogoItem[] });
          }
          this.clienteBuscando = true;
          this.cdr.markForCheck();
          return this.filtrosCatalogo.buscarClientes(q, 0).pipe(map((list) => ({ seq, list })));
        }),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe(({ seq, list }) => {
        if (seq !== this.clienteSeq) {
          return;
        }
        const term = this.clienteQueryActiva;
        const page =
          term.length < 2
            ? []
            : list.filter((p) => p.nombre.toLowerCase().includes(term.toLowerCase())).slice(0, 20);
        this.clienteSugerencias = page;
        this.clienteHayMas = list.length === 20;
        this.clienteBuscando = false;
        this.cdr.markForCheck();
      });
  }

  ngOnChanges(changes: SimpleChanges): void {
    if (
      (changes['anio'] || changes['mes'] || changes['sucursalId'] || changes['horizonte']) &&
      !this.esPrimerCambio(changes)
    ) {
      this.cargar();
    }
  }

  sinFilas(): boolean {
    switch (this.dimension) {
      case 'sucursal':
        return this.data.filasSucursal.length === 0;
      case 'producto':
        return this.data.filasProducto.length === 0;
      case 'cliente':
        return this.data.filasCliente.length === 0;
      case 'proveedor':
        return this.data.filasProveedor.length === 0;
      case 'proyecto':
        return this.data.filasProyecto.length === 0;
    }
  }

  private esPrimerCambio(changes: SimpleChanges): boolean {
    return (
      !!changes['anio']?.firstChange &&
      !!changes['mes']?.firstChange &&
      (changes['sucursalId'] == null || changes['sucursalId'].firstChange) &&
      (changes['horizonte'] == null || changes['horizonte'].firstChange)
    );
  }

  private cargar(): void {
    if (!this.anio || !this.mes) {
      return;
    }
    const seq = ++this.loadSeq;
    this.cargando = true;
    this.errorMensaje = null;
    this.cdr.markForCheck();
    this.rentabilidadData
      .obtener({
        anio: this.anio,
        mes: this.mes,
        horizonte: this.horizonte,
        sucursalId: this.sucursalId,
      })
      .pipe(
        take(1),
        finalize(() => {
          if (seq === this.loadSeq) {
            this.cargando = false;
            this.cdr.markForCheck();
          }
        }),
      )
      .subscribe({
        next: (data) => {
          if (seq !== this.loadSeq) {
            return;
          }
          if (!data) {
            if (!this.contenidoListo) {
              this.data = RENTABILIDAD_VACIA;
              this.cerrarFilas();
            }
            this.errorMensaje =
              'No se pudo cargar la rentabilidad. Comprueba que sp-analytics esté en marcha y que hayas corrido el ETL de rentabilidad.';
            return;
          }
          this.data = data;
          this.contenidoListo = true;
          this.abrirPrimeras();
          this.aplicarFiltroProducto();
          this.aplicarFiltroCliente();
          this.cdr.markForCheck();
        },
        error: () => {
          if (seq !== this.loadSeq) {
            return;
          }
          if (!this.contenidoListo) {
            this.data = RENTABILIDAD_VACIA;
            this.cerrarFilas();
          }
          this.errorMensaje =
            'Error al consultar la rentabilidad. Revisa la consola del navegador y los logs de sp-analytics.';
        },
      });
  }

  private cerrarFilas(): void {
    this.sucursalAbierta = null;
    this.productoAbierto = null;
    this.clienteAbierto = null;
    this.proveedorAbierto = null;
    this.proyectoAbierto = null;
  }

  private abrirPrimeras(): void {
    this.sucursalAbierta = this.data.filasSucursal[0] ?? null;
    this.productoAbierto = this.data.filasProducto[0] ?? null;
    this.clienteAbierto = this.data.filasCliente[0] ?? null;
    this.proveedorAbierto = this.data.filasProveedor[0] ?? null;
    this.proyectoAbierto = this.data.filasProyecto[0] ?? null;
  }

  kpiChartItems(): RentKpiChartItem[] {
    return this.kpisActivos().map((kpi) => ({
      metric: { title: kpi.titulo, value: kpi.valor },
      linea: kpi.linea,
      sublinea: kpi.sublinea,
      sublineaTono: kpi.sublineaTono,
      alerta: kpi.alerta,
    }));
  }

  kpisActivos(): RentabilidadKpi[] {
    if (this.kpiModo === 'alertas') {
      return this.data.kpis;
    }

    const resumen =
      this.data.resumen ??
      resumenDesdeFilas(this.data.filasSucursal, this.etiquetaMesResumen(), this.etiquetaComparacionResumen());
    return resumen
      ? kpisDesdeResumen(resumen, (n) => this.formatMonto(n), (n, d) => this.formatPct(n, d))
      : [];
  }

  seleccionarDimension(id: RentabilidadDimensionId): void {
    this.dimension = id;
    if (id === 'sucursal' && !this.sucursalAbierta) {
      this.sucursalAbierta = this.data.filasSucursal[0] ?? null;
    }
    if (id === 'producto') {
      if (!this.productoAbierto) {
        this.productoAbierto = this.data.filasProducto[0] ?? null;
      }
      this.aplicarFiltroProducto();
    }
    if (id === 'cliente') {
      if (!this.clienteAbierto) {
        this.clienteAbierto = this.data.filasCliente[0] ?? null;
      }
      this.aplicarFiltroCliente();
    }
    if (id === 'proveedor' && !this.proveedorAbierto) {
      this.proveedorAbierto = this.data.filasProveedor[0] ?? null;
    }
    if (id === 'proyecto' && !this.proyectoAbierto) {
      this.proyectoAbierto = this.data.filasProyecto[0] ?? null;
    }
  }

  abrirSucursal(fila: RentabilidadSucursalFila): void {
    this.sucursalAbierta = fila;
    setTimeout(() => {
      this.detalleSucursal?.nativeElement.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
  }

  escribirProducto(event: Event): void {
    this.productoTexto = (event.target as HTMLInputElement).value;
    this.productoListaAbierta = true;
    if (this.productoFiltroId !== 'todos') {
      this.productoFiltroId = 'todos';
      this.productoFiltroNombre = '';
      this.productoAbierto = this.data.filasProducto[0] ?? null;
    }
    this.buscarProducto$.next(this.productoTexto);
  }

  abrirBusquedaProducto(): void {
    this.productoListaAbierta = true;
    if (this.productoTexto.trim().length >= 2 && this.productoSugerencias.length === 0) {
      this.buscarProducto$.next(this.productoTexto);
    }
  }

  cerrarBusquedaProducto(event: FocusEvent): void {
    const next = event.relatedTarget as Node | null;
    if (next && (event.currentTarget as HTMLElement).contains(next)) {
      return;
    }
    this.productoListaAbierta = false;
  }

  elegirProducto(item: DashboardFiltroCatalogoItem): void {
    this.productoFiltroId = item.id;
    this.productoFiltroNombre = item.nombre.trim();
    this.productoTexto = item.nombre;
    this.productoListaAbierta = false;
    this.productoAbierto = this.filasProductoVisibles()[0] ?? null;
  }

  limpiarProducto(): void {
    this.productoFiltroId = 'todos';
    this.productoFiltroNombre = '';
    this.productoTexto = '';
    this.productoSugerencias = [];
    this.productoHayMas = false;
    this.productoListaAbierta = false;
    this.productoAbierto = this.data.filasProducto[0] ?? null;
  }

  masProductos(event: Event): void {
    const el = event.target as HTMLElement;
    if (!this.productoHayMas || this.productoBuscando) {
      return;
    }
    if (el.scrollTop + el.clientHeight < el.scrollHeight - 32) {
      return;
    }
    this.cargarMasProductos();
  }

  private cargarMasProductos(): void {
    const term = this.productoQueryActiva;
    if (term.length < 2 || this.productoBuscando || !this.productoHayMas) {
      return;
    }
    const seq = this.busquedaSeq;
    const offset = this.productoSugerencias.length;
    this.productoBuscando = true;
    this.filtrosCatalogo
      .buscarProductos(term, offset)
      .pipe(take(1))
      .subscribe((list) => {
        if (seq !== this.busquedaSeq) {
          return;
        }
        const page = list
          .filter((p) => p.nombre.toLowerCase().includes(term.toLowerCase()))
          .slice(0, 20);
        this.productoSugerencias = this.productoSugerencias.concat(page);
        this.productoHayMas = list.length === 20;
        this.productoBuscando = false;
        this.cdr.markForCheck();
      });
  }

  filasSucursalVisibles(): RentabilidadSucursalFila[] {
    const st = this.ordenPorDimension.sucursal;
    if (!st.col) {
      return this.data.filasSucursal;
    }
    const col = st.col as keyof Pick<
      RentabilidadSucursalFila,
      'nombre' | 'ventas' | 'gastos' | 'utilidad' | 'margenPct' | 'cxc' | 'cxp' | 'ventasVsAnioPct'
    >;
    return ordenarFilas(this.data.filasSucursal, st.dir, (fila) => fila[col]);
  }

  filasProductoVisibles(): RentabilidadProductoTablaFila[] {
    const base = this.productoFiltroId === 'todos'
      ? this.data.filasProducto
      : (this.productoFiltroNombre.trim()
          ? this.data.filasProducto.filter((fila) => fila.nombre.trim() === this.productoFiltroNombre.trim())
          : []);
    const st = this.ordenPorDimension.producto;
    if (!st.col) {
      return base;
    }
    return ordenarFilas(base, st.dir, (fila) => {
      if (st.col === 'inventario') {
        return fila.inventario.tipo === 'sinVenta' ? null : fila.inventario.dias;
      }
      const col = st.col as keyof Pick<
        RentabilidadProductoTablaFila,
        'nombre' | 'unidades' | 'ventas' | 'ventasVsPct' | 'utilidad' | 'margenPct' | 'margenAnioPct' | 'precioVsPct'
      >;
      return fila[col];
    });
  }

  private aplicarFiltroProducto(): void {
    if (this.dimension !== 'producto' || this.productoFiltroId === 'todos') {
      return;
    }
    this.productoAbierto = this.filasProductoVisibles()[0] ?? null;
  }

  escribirCliente(event: Event): void {
    this.clienteTexto = (event.target as HTMLInputElement).value;
    this.clienteListaAbierta = true;
    if (this.clienteFiltroId !== 'todos') {
      this.clienteFiltroId = 'todos';
      this.clienteFiltroNombre = '';
      this.clienteAbierto = this.data.filasCliente[0] ?? null;
    }
    this.buscarCliente$.next(this.clienteTexto);
  }

  abrirBusquedaCliente(): void {
    this.clienteListaAbierta = true;
    if (this.clienteTexto.trim().length >= 2 && this.clienteSugerencias.length === 0) {
      this.buscarCliente$.next(this.clienteTexto);
    }
  }

  cerrarBusquedaCliente(event: FocusEvent): void {
    const next = event.relatedTarget as Node | null;
    if (next && (event.currentTarget as HTMLElement).contains(next)) {
      return;
    }
    this.clienteListaAbierta = false;
  }

  elegirCliente(item: DashboardFiltroCatalogoItem): void {
    this.clienteFiltroId = item.id;
    this.clienteFiltroNombre = item.nombre.trim();
    this.clienteTexto = item.nombre;
    this.clienteListaAbierta = false;
    this.clienteAbierto = this.filasClienteVisibles()[0] ?? null;
  }

  limpiarCliente(): void {
    this.clienteFiltroId = 'todos';
    this.clienteFiltroNombre = '';
    this.clienteTexto = '';
    this.clienteSugerencias = [];
    this.clienteHayMas = false;
    this.clienteListaAbierta = false;
    this.clienteAbierto = this.data.filasCliente[0] ?? null;
  }

  masClientes(event: Event): void {
    const el = event.target as HTMLElement;
    if (!this.clienteHayMas || this.clienteBuscando) {
      return;
    }
    if (el.scrollTop + el.clientHeight < el.scrollHeight - 32) {
      return;
    }
    this.cargarMasClientes();
  }

  private cargarMasClientes(): void {
    const term = this.clienteQueryActiva;
    if (term.length < 2 || this.clienteBuscando || !this.clienteHayMas) {
      return;
    }
    const seq = this.clienteSeq;
    const offset = this.clienteSugerencias.length;
    this.clienteBuscando = true;
    this.filtrosCatalogo
      .buscarClientes(term, offset)
      .pipe(take(1))
      .subscribe((list) => {
        if (seq !== this.clienteSeq) {
          return;
        }
        const page = list
          .filter((p) => p.nombre.toLowerCase().includes(term.toLowerCase()))
          .slice(0, 20);
        this.clienteSugerencias = this.clienteSugerencias.concat(page);
        this.clienteHayMas = list.length === 20;
        this.clienteBuscando = false;
        this.cdr.markForCheck();
      });
  }

  filasClienteVisibles(): RentabilidadClienteFila[] {
    const base = this.clienteFiltroId === 'todos'
      ? this.data.filasCliente
      : (this.clienteFiltroNombre.trim()
          ? this.data.filasCliente.filter((fila) => fila.nombre.trim() === this.clienteFiltroNombre.trim())
          : []);
    const st = this.ordenPorDimension.cliente;
    if (!st.col) {
      return base;
    }
    return ordenarFilas(base, st.dir, (fila) => {
      if (st.col === 'pagoDias') {
        return fila.pagoContado ? 0 : fila.pagoDias;
      }
      if (st.col === 'ultimaCompra') {
        return fila.ultimaCompra || null;
      }
      const col = st.col as keyof Pick<
        RentabilidadClienteFila,
        'nombre' | 'compro' | 'ganancia' | 'deCada100' | 'alFinalQuedan' | 'leDebe' | 'parteVentasPct'
      >;
      return fila[col];
    });
  }

  private aplicarFiltroCliente(): void {
    if (this.dimension !== 'cliente' || this.clienteFiltroId === 'todos') {
      return;
    }
    this.clienteAbierto = this.filasClienteVisibles()[0] ?? null;
  }

  escribirProveedor(event: Event): void {
    this.proveedorTexto = (event.target as HTMLInputElement).value;
    this.proveedorListaAbierta = true;
    if (this.proveedorTexto.trim() !== this.proveedorFiltroNombre) {
      this.proveedorFiltroNombre = '';
      this.proveedorAbierto = this.data.filasProveedor[0] ?? null;
    }
  }

  cerrarBusquedaProveedor(event: FocusEvent): void {
    const next = event.relatedTarget as Node | null;
    if (next && (event.currentTarget as HTMLElement).contains(next)) {
      return;
    }
    this.proveedorListaAbierta = false;
  }

  proveedorSugerencias(): RentabilidadProveedorFila[] {
    const q = this.proveedorTexto.trim().toLowerCase();
    const filas = this.data.filasProveedor;
    if (q.length < 2 || q === this.proveedorFiltroNombre.trim().toLowerCase()) {
      return filas.slice(0, 20);
    }
    return filas.filter((fila) => fila.nombre.toLowerCase().includes(q)).slice(0, 20);
  }

  elegirProveedor(fila: RentabilidadProveedorFila): void {
    this.proveedorFiltroNombre = fila.nombre.trim();
    this.proveedorTexto = fila.nombre;
    this.proveedorListaAbierta = false;
    this.proveedorAbierto = fila;
  }

  limpiarProveedor(): void {
    this.proveedorFiltroNombre = '';
    this.proveedorTexto = '';
    this.proveedorListaAbierta = false;
    this.proveedorAbierto = this.data.filasProveedor[0] ?? null;
  }

  escribirProyecto(event: Event): void {
    this.proyectoTexto = (event.target as HTMLInputElement).value;
    this.proyectoListaAbierta = true;
    if (this.proyectoTexto.trim() !== this.proyectoFiltroNombre) {
      this.proyectoFiltroNombre = '';
      this.proyectoAbierto = this.data.filasProyecto[0] ?? null;
    }
  }

  cerrarBusquedaProyecto(event: FocusEvent): void {
    const next = event.relatedTarget as Node | null;
    if (next && (event.currentTarget as HTMLElement).contains(next)) {
      return;
    }
    this.proyectoListaAbierta = false;
  }

  proyectoSugerencias(): RentabilidadProyectoFila[] {
    const q = this.proyectoTexto.trim().toLowerCase();
    const filas = this.data.filasProyecto;
    if (q.length < 2 || q === this.proyectoFiltroNombre.trim().toLowerCase()) {
      return filas.slice(0, 20);
    }
    return filas.filter((fila) => fila.nombre.toLowerCase().includes(q)).slice(0, 20);
  }

  elegirProyecto(fila: RentabilidadProyectoFila): void {
    this.proyectoFiltroNombre = fila.nombre.trim();
    this.proyectoTexto = fila.nombre;
    this.proyectoListaAbierta = false;
    this.proyectoAbierto = fila;
  }

  limpiarProyecto(): void {
    this.proyectoFiltroNombre = '';
    this.proyectoTexto = '';
    this.proyectoListaAbierta = false;
    this.proyectoAbierto = this.data.filasProyecto[0] ?? null;
  }

  filasProyectoVisibles(): RentabilidadProyectoFila[] {
    const nombre = this.proyectoFiltroNombre.trim();
    const base = nombre
      ? this.data.filasProyecto.filter((fila) => fila.nombre.trim() === nombre)
      : this.data.filasProyecto;
    const st = this.ordenPorDimension.proyecto;
    if (!st.col) {
      return base;
    }
    const col = st.col as keyof Pick<
      RentabilidadProyectoFila,
      | 'nombre'
      | 'contrato'
      | 'facturadoPct'
      | 'presupuestoGastadoPct'
      | 'margenPlaneadoPct'
      | 'margenFechaPct'
      | 'porCobrar'
    >;
    return ordenarFilas(base, st.dir, (fila) => fila[col]);
  }

  filasProveedorVisibles(): RentabilidadProveedorFila[] {
    const nombre = this.proveedorFiltroNombre.trim();
    const base = nombre
      ? this.data.filasProveedor.filter((fila) => fila.nombre.trim() === nombre)
      : this.data.filasProveedor;
    const st = this.ordenPorDimension.proveedor;
    if (!st.col) {
      return base;
    }
    const col = st.col as keyof Pick<
      RentabilidadProveedorFila,
      'nombre' | 'compro' | 'parteComprasPct' | 'ganoAlVender' | 'ganaPorDolar'
    >;
    return ordenarFilas(base, st.dir, (fila) => fila[col]);
  }

  ordenarTabla(dim: RentabilidadDimensionId, col: string): void {
    this.ordenPorDimension[dim] = alternarOrden(this.ordenPorDimension[dim], col);
  }

  ariaSortTabla(dim: RentabilidadDimensionId, col: string): 'ascending' | 'descending' | 'none' {
    const st = this.ordenPorDimension[dim];
    if (st.col !== col) {
      return 'none';
    }
    return st.dir === 'asc' ? 'ascending' : 'descending';
  }

  iconoOrden(dim: RentabilidadDimensionId, col: string): string {
    const st = this.ordenPorDimension[dim];
    if (st.col !== col) {
      return '↕';
    }
    return st.dir === 'asc' ? '↑' : '↓';
  }

  tonoGananciaCompra(fila: RentabilidadProveedorFila): string {
    return fila.ganaPorDolar >= 0.45 ? 'rent-text-pos' : '';
  }

  abrirProducto(fila: RentabilidadProductoTablaFila): void {
    this.productoAbierto = fila;
    setTimeout(() => {
      this.detalleProducto?.nativeElement.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
  }

  montoHorizonte(value: number): number {
    return Math.round(value * factorHorizonte(this.horizonte));
  }

  unidadesHorizonte(value: number): number {
    return Math.round(value * factorHorizonte(this.horizonte));
  }

  proyectoDetalle(proy: RentabilidadProyectoFila): RentabilidadProyectoDetalle {
    const base = detalleProyectoDesdeFila(proy);
    const f = factorHorizonte(this.horizonte);
    if (f === 1) {
      return base;
    }
    const escalaM = (n: number) => Math.round(n * f);
    return {
      ...base,
      porFacturar: escalaM(base.porFacturar),
      facturado: escalaM(base.facturado),
      cobrado: escalaM(base.cobrado),
      gastado: escalaM(base.gastado),
      pagadoObra: escalaM(base.pagadoObra),
      utilidadFecha: escalaM(base.utilidadFecha),
      resultado: base.resultado.map((row) =>
        row.formato === 'monto'
          ? { ...row, fecha: escalaM(row.fecha) }
          : row,
      ),
      costosCategoria: base.costosCategoria.map((c) => ({
        ...c,
        monto: escalaM(c.monto),
      })),
      presupuesto: base.presupuesto.map((p) => ({
        ...p,
        gastado: escalaM(p.gastado),
        disponible: escalaM(p.disponible),
      })),
      presupuestoTotal: {
        ...base.presupuestoTotal,
        gastado: escalaM(base.presupuestoTotal.gastado),
        disponible: escalaM(base.presupuestoTotal.disponible),
      },
      materiales: base.materiales.map((m) => ({
        ...m,
        difPrecio: m.difPrecio === null ? null : escalaM(m.difPrecio),
      })),
      chartGastado: base.chartGastado.map(escalaM),
      chartFacturado: base.chartFacturado.map(escalaM),
      chartCobrado: base.chartCobrado.map(escalaM),
    };
  }

  proveedorDetalle(prov: RentabilidadProveedorFila): RentabilidadProveedorDetalle {
    const base = detalleProveedorDesdeFila(prov);
    const f = factorHorizonte(this.horizonte);
    if (f === 1) {
      return base;
    }
    const escalaResumen = (filas: RentabilidadProveedorResumenFila[]) =>
      filas.map((fila) =>
        fila.formato === 'monto'
          ? {
              ...fila,
              anual: Math.round(fila.anual * f),
              promedio: Math.round(fila.promedio * f),
              mes: Math.round(fila.mes * f),
            }
          : fila,
      );
    return {
      ...base,
      resumen: escalaResumen(base.resumen),
      chartCompras: base.chartCompras.map((v) => Math.round(v * f)),
      chartVentas: base.chartVentas.map((v) => Math.round(v * f)),
      productoCompras: Math.round(base.productoCompras * f),
      porPagar: base.porPagar.map((p) => ({ ...p, monto: Math.round(p.monto * f) })),
      clientes: base.clientes.map((c) => ({
        ...c,
        ventas: Math.round(c.ventas * f),
      })),
    };
  }

  clienteDetalle(cli: RentabilidadClienteFila): RentabilidadClienteDetalle {
    const base = detalleClienteDesdeFila(cli);
    const f = factorHorizonte(this.horizonte);
    if (f === 1) {
      return base;
    }
    return {
      ...base,
      descuentoMonto: Math.round(base.descuentoMonto * f),
      cxcVencido: Math.round(base.cxcVencido * f),
      puente: base.puente.map((paso) => ({
        ...paso,
        monto: Math.round(paso.monto * f),
      })),
      facturas: base.facturas.map((fac) => ({
        ...fac,
        monto: Math.round(fac.monto * f),
      })),
      productos: base.productos.map((prod) => ({
        ...prod,
        compro: Math.round(prod.compro * f),
      })),
      sucursales: base.sucursales.map((suc) => ({
        ...suc,
        monto: Math.round(suc.monto * f),
      })),
    };
  }

  productoDetalle(prod: RentabilidadProductoTablaFila): RentabilidadProductoDetalle {
    const base = detalleProductoDesdeFila(prod);
    const f = factorHorizonte(this.horizonte);
    if (f === 1) {
      return base;
    }
    return {
      ...base,
      sucursales: base.sucursales.map((s) => ({
        ...s,
        monto: Math.round(s.monto * f),
      })),
      clientes: base.clientes.map((c) => ({
        ...c,
        unidades: Math.round(c.unidades * f),
      })),
    };
  }

  abrirCliente(fila: RentabilidadClienteFila): void {
    this.clienteAbierto = fila;
    setTimeout(() => {
      this.detalleCliente?.nativeElement.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
  }

  abrirProveedor(fila: RentabilidadProveedorFila): void {
    this.proveedorAbierto = fila;
    setTimeout(() => {
      this.detalleProveedor?.nativeElement.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
  }

  abrirProyecto(fila: RentabilidadProyectoFila): void {
    this.proyectoAbierto = fila;
    setTimeout(() => {
      this.detalleProyecto?.nativeElement.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
  }

  claseBarraProyecto(tono: RentabilidadProyectoBarTono): string {
    if (tono === 'rojo') {
      return 'rent-barra-fill--rojo';
    }
    if (tono === 'verde') {
      return 'rent-barra-fill--verde';
    }
    return 'rent-barra-fill--azul';
  }

  claseBarraPresupuestoUsado(tono: 'azul' | 'naranja'): string {
    return tono === 'naranja' ? 'rent-barra-fill--naranja' : 'rent-barra-fill--azul';
  }

  formatResultadoProyecto(
    fila: RentabilidadProyectoResultadoFila,
    columna: 'planeado' | 'fecha',
  ): string {
    const value = fila[columna];
    return fila.formato === 'pct' ? this.formatPct(value) : this.formatMonto(value);
  }

  anchoRelativoCategoriaGasto(
    pct: number,
    filas: { pctGasto: number }[],
  ): number {
    const max = Math.max(...filas.map((f) => f.pctGasto), 1);
    return (pct / max) * 100;
  }

  formatResumenValor(
    fila: RentabilidadProveedorResumenFila,
    columna: 'anual' | 'promedio' | 'mes',
  ): string {
    const value = fila[columna];
    if (fila.formato === 'pct' && columna === 'promedio' && value === 0) {
      return '—';
    }
    if (!value) {
      return '';
    }
    return fila.formato === 'pct' ? this.formatPct(value) : this.formatMonto(value);
  }

  maxProveedorChart(detalle: RentabilidadProveedorDetalle): number {
    return Math.max(...detalle.chartCompras, ...detalle.chartVentas, 1);
  }

  alturaProveedorBarra(valor: number, max: number): number {
    return Math.round((valor / max) * 100);
  }

  margenProveedorPath(detalle: RentabilidadProveedorDetalle): string {
    return this.seriePath(detalle.chartMargen, 280, 56);
  }

  ejeMonto(value: number): string {
    if (value <= 0) {
      return '$0';
    }
    if (value >= 1000) {
      const miles = value / 1000;
      const texto = Number.isInteger(miles) ? miles.toFixed(0) : miles.toFixed(1);
      return `$${texto}k`;
    }
    return this.formatMonto(value);
  }

  comboProveedor(detalle: RentabilidadProveedorDetalle): {
    left: { y: number; text: string }[];
    right: { y: number; text: string }[];
    bars: { compraY: number; compraH: number; ventaY: number; ventaH: number; x: number; label: string }[];
    line: string;
  } | null {
    const n = detalle.chartLabels.length;
    if (!n) {
      return null;
    }
    const w = 720;
    const h = 220;
    const padL = 46;
    const padR = 42;
    const padT = 12;
    const padB = 26;
    const innerH = h - padT - padB;
    const slot = (w - padL - padR) / n;
    const maxV = Math.max(...detalle.chartCompras, ...detalle.chartVentas, 1);
    const margenes = detalle.chartMargen.length ? detalle.chartMargen : [0];
    let minM = Math.min(...margenes);
    let maxM = Math.max(...margenes);
    if (maxM - minM < 1) {
      minM -= 1;
      maxM += 1;
    }
    const yMonto = (valor: number) => padT + innerH - (valor / maxV) * innerH;
    const yMargen = (valor: number) => padT + innerH - ((valor - minM) / (maxM - minM)) * innerH;
    const left = [0, 1, 2, 3].map((i) => {
      const valor = (maxV / 3) * i;
      return { y: yMonto(valor), text: this.ejeMonto(valor) };
    });
    const right = [0, 1, 2, 3].map((i) => {
      const valor = minM + ((maxM - minM) / 3) * i;
      return { y: yMargen(valor), text: `${Math.round(valor)}%` };
    });
    const bars = detalle.chartLabels.map((label, i) => {
      const compra = detalle.chartCompras[i] ?? 0;
      const venta = detalle.chartVentas[i] ?? 0;
      const compraH = (compra / maxV) * innerH;
      const ventaH = (venta / maxV) * innerH;
      const x = padL + slot * i + slot / 2;
      return {
        x,
        label,
        compraY: padT + innerH - compraH,
        compraH,
        ventaY: padT + innerH - ventaH,
        ventaH,
      };
    });
    const line = detalle.chartMargen
      .map((valor, i) => {
        const x = padL + slot * i + slot / 2;
        return `${i === 0 ? 'M' : 'L'} ${x.toFixed(1)} ${yMargen(valor).toFixed(1)}`;
      })
      .join(' ');
    return { left, right, bars, line };
  }

  tonoPuenteCliente(etiqueta: string): string {
    if (
      etiqueta.startsWith('Descuentos') ||
      etiqueta === 'Mercadería' ||
      etiqueta === 'Esperar su pago' ||
      etiqueta === 'Costo'
    ) {
      return 'naranja';
    }
    return 'azul';
  }

  tituloTabla(): string {
    const dim = this.dimensionOpciones.find((d) => d.id === this.dimension);
    if (this.dimension === 'proyecto') {
      return 'Rentabilidad por proyecto';
    }
    const nombre = dim?.label?.toLowerCase() ?? 'sucursal';
    return `Rentabilidad por ${nombre} — ${this.periodoTitulo}`;
  }

  formatDeCada100(value: number): string {
    return `$${value}`;
  }

  formatGanaPorDolar(value: number): string {
    return this.formatPrecio(value);
  }

  urlFacturaProveedor(fac: { id?: number; tipo?: 'Compra' | 'Gasto' }): string | null {
    if (!fac.id) {
      return null;
    }
    return fac.tipo === 'Gasto' ? `/gasto/detalles/${fac.id}` : `/compra/${fac.id}`;
  }

  claseValorTono(tono: 'normal' | 'naranja' | 'verde' | 'rojo'): string {
    if (tono === 'verde') {
      return 'rent-text-pos';
    }
    if (tono === 'rojo' || tono === 'naranja') {
      return tono === 'rojo' ? 'rent-text-neg' : 'rent-text-warn';
    }
    return '';
  }

  textoPago(fila: RentabilidadClienteFila): string {
    if (fila.pagoContado) {
      return 'contado';
    }
    const pacto = fila.pagoPactado === null ? '' : ` (pactó ${fila.pagoPactado})`;
    if (fila.pagoDias === null) {
      return fila.pagoPactado === null ? '—' : `pactó ${fila.pagoPactado}`;
    }
    return `${fila.pagoDias}${pacto}`;
  }

  tonoPago(fila: RentabilidadClienteFila): 'pos' | 'warn' | 'neg' | 'normal' {
    if (fila.pagoContado) {
      return 'pos';
    }
    const dias = fila.pagoDias ?? 0;
    if (dias <= 35) {
      return 'pos';
    }
    if (dias <= 50) {
      return 'warn';
    }
    return 'neg';
  }

  private etiquetaMesResumen(): string {
    if (this.horizonte === 'anio') {
      return 'año';
    }
    if (this.horizonte === 'trimestre') {
      return 'trim.';
    }
    const abr = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    return abr[this.mes - 1] || 'mes';
  }

  private etiquetaComparacionResumen(): string {
    if (this.horizonte === 'anio') {
      return `año ${this.anio - 1}`;
    }
    if (this.horizonte === 'trimestre') {
      return `trim. ${this.anio - 1}`;
    }
    return `${this.etiquetaMesResumen()} ${this.anio - 1}`;
  }

  etiquetaComparacion(): string {
    const partes = this.periodoTitulo.trim().split(/\s+/);
    const mes = (partes[0] || 'sep').slice(0, 3).toUpperCase();
    const anio = Number(partes[1]);
    const previo = Number.isFinite(anio) ? anio - 1 : new Date().getFullYear() - 1;
    return `${mes} ${previo}`;
  }

  formatMonto(value: number): string {
    const empresa = this.api.auth_user()?.empresa;
    const abs = formatEmpresaCurrencyInt(Math.abs(value), empresa);
    return value < 0 ? `-${abs}` : abs;
  }

  formatPrecio(value: number): string {
    return formatEmpresaCurrency(value, this.api.auth_user()?.empresa);
  }

  etiquetaComparacionCorta(): string {
    return this.etiquetaComparacion().toLowerCase();
  }

  mesNombre(): string {
    const nombres = ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    return nombres[this.mes] ?? '';
  }

  formatPct(value: number | null | undefined, digits = 1): string {
    return `${(value ?? 0).toFixed(digits)}%`;
  }

  formatVs(value: number): string {
    const n = value.toFixed(1);
    return value > 0 ? `+${n}%` : `${n}%`;
  }

  gastosPctVentas(fila: RentabilidadSucursalFila): string {
    if (!fila.ventas) {
      return '0%';
    }
    return this.formatPct((fila.gastos / fila.ventas) * 100);
  }

  formatUnidades(value: number): string {
    return new Intl.NumberFormat('en-US').format(value);
  }

  textoInventario(fila: RentabilidadProductoTablaFila): string {
    const inv = fila.inventario;
    if (inv.tipo === 'dias') {
      return `${inv.dias} días`;
    }
    if (inv.tipo === 'agota') {
      return `se agota en ~${inv.dias} días`;
    }
    return `${inv.dias} días sin venta · ${this.formatMonto(inv.monto)}`;
  }

  seriePath(values: number[], w: number, h: number, min?: number, max?: number): string {
    if (!values.length) {
      return '';
    }
    const lo = min ?? Math.min(...values);
    const hi = max ?? Math.max(...values);
    const range = hi - lo || 1;
    const yOf = (v: number) => h - 4 - ((v - lo) / range) * (h - 10);
    if (values.length === 1) {
      const y = yOf(values[0]).toFixed(1);
      return `M 0,${y} L ${w},${y}`;
    }
    const coords = values.map((v, i) => {
      const x = (i / (values.length - 1)) * w;
      return `${x.toFixed(1)},${yOf(v).toFixed(1)}`;
    });
    return `M ${coords.join(' L ')}`;
  }

  precioCostoChart(detalle: NonNullable<RentabilidadProductoTablaFila['detalle']>): {
    precio: string;
    costo: string;
    puntos: { left: number; ancho: number; label: string; precio: number; costo: number }[];
  } {
    const w = 280;
    const h = 120;
    const all = [...detalle.precioSerie, ...detalle.costoSerie];
    if (!all.length) {
      return { precio: '', costo: '', puntos: [] };
    }
    const min = Math.min(...all) - 0.5;
    const max = Math.max(...all) + 0.5;
    const n = detalle.precioSerie.length;
    const puntos = detalle.precioCostoLabels.map((label, i) => ({
      left: n <= 1 ? 50 : (i / (n - 1)) * 100,
      ancho: n <= 1 ? 100 : 100 / (n - 1),
      label,
      precio: detalle.precioSerie[i] ?? 0,
      costo: detalle.costoSerie[i] ?? 0,
    }));
    return {
      precio: this.seriePath(detalle.precioSerie, w, h, min, max),
      costo: this.seriePath(detalle.costoSerie, w, h, min, max),
      puntos,
    };
  }

  sparklinePath(values: number[]): string {
    const w = 56;
    const h = 26;
    if (!values.length) {
      return '';
    }
    const min = Math.min(...values);
    const max = Math.max(...values);
    const range = max - min || 1;
    const coords = values.map((v, i) => {
      const x = values.length === 1 ? 0 : (i / (values.length - 1)) * w;
      const y = h - 2 - ((v - min) / range) * (h - 6);
      return `${x.toFixed(1)},${y.toFixed(1)}`;
    });
    return `M ${coords.join(' L ')}`;
  }

  anchoBarra(pct: number): number {
    return Math.min(100, Math.abs(pct));
  }

  anchoPuenteCliente(pct: number, pasos: { pct: number }[]): number {
    const max = Math.max(100, ...pasos.map((p) => Math.abs(p.pct)));
    return (Math.abs(pct) / max) * 100;
  }

  anchoRelativo(pct: number, filas: RentabilidadMontoPct[]): number {
    const max = Math.max(...filas.map((f) => f.pct), 1);
    return (pct / max) * 100;
  }

  anchoRelativoMonto(monto: number, filas: { ventas?: number; compro?: number; compras?: number }[]): number {
    const max = Math.max(...filas.map((f) => f.ventas ?? f.compro ?? f.compras ?? 0), 1);
    return (Math.abs(monto) / max) * 100;
  }

  tonoPuente(fila: RentabilidadMontoPct, index: number, total: number): string {
    if (index === 0) {
      return 'azul';
    }
    if (index === total - 1) {
      return fila.monto < 0 ? 'rojo' : 'azul';
    }
    return 'naranja';
  }

}
