import {
  ChangeDetectorRef,
  Component,
  Input,
  OnChanges,
  OnInit,
  SimpleChanges,
  inject,
} from '@angular/core';
import { CommonModule } from '@angular/common';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { ApiService } from '@services/api.service';
import { PlEstadoResultadosFila, PlGastosPctVentasMock, PlVistaEstadoId } from './pl-gerencial.model';
import {
  buildFilasVistaAnual,
  PlFilaVistaAnual,
} from './pl-gerencial-anual.helper';
import { formatEmpresaCurrencyInt } from '@helpers/currency-format.helper';
import { PlGastosPctVentasComponent } from './pl-gastos-pct-ventas.component';
import {
  FinanzasPlDataService,
  PlGerencialQuery,
} from '../../../services/finanzas-pl-data.service';
import { finalize, take } from 'rxjs/operators';
import {
  alternarOrden,
  EstadoOrden,
  ordenarFilas,
} from '../finanzas-table-sort.helper';

type PlMesSortCol = 'concepto' | 'mes' | 'pctVentasMes' | 'vsAbs' | 'acum' | 'pctVentasAcum';
type PlAnualSortCol = 'concepto' | 'total' | `mes_${number}`;

const PL_GASTOS_PCT_VACIO: PlGastosPctVentasMock = { filas: [] };

@Component({
  selector: 'app-pl-gerencial',
  templateUrl: './pl-gerencial.component.html',
  styleUrls: ['./pl-gerencial.component.css'],
  standalone: true,
  imports: [CommonModule, TooltipModule, PlGastosPctVentasComponent],
})
export class PlGerencialComponent implements OnInit, OnChanges {
  private readonly api = inject(ApiService);
  private readonly plData = inject(FinanzasPlDataService);
  private readonly cdr = inject(ChangeDetectorRef);

  @Input() query!: PlGerencialQuery;
  @Input() periodoTitulo = '';
  @Input() comparacionTitulo = '';
  @Input() anioAcumulado = new Date().getFullYear();

  filas: PlEstadoResultadosFila[] = [];
  filasVistaAnual: PlFilaVistaAnual[] = [];
  gastosPctVentas: PlGastosPctVentasMock = PL_GASTOS_PCT_VACIO;

  cargando = false;
  errorMensaje: string | null = null;
  vistaEstado: PlVistaEstadoId = 'mes';

  readonly mesesAbr = ['ENE', 'FEB', 'MAR', 'ABR', 'MAY', 'JUN', 'JUL', 'AGO', 'SEP', 'OCT', 'NOV', 'DIC'] as const;

  ordenMes: EstadoOrden<PlMesSortCol> = { col: null, dir: 'asc' };
  ordenAnual: EstadoOrden<PlAnualSortCol> = { col: null, dir: 'asc' };

  private loadSeq = 0;

  ngOnInit(): void {
    this.cargar();
  }

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['query'] && !changes['query'].firstChange) {
      this.cargar();
    }
  }

  private cargar(): void {
    if (
      !this.query?.anio ||
      (this.query.periodo === 'mes' &&
        (this.query.mes < 1 || this.query.mes > 12))
    ) {
      return;
    }

    const seq = ++this.loadSeq;
    this.cargando = true;
    this.errorMensaje = null;
    this.comparacionTitulo = '';
    this.cdr.markForCheck();

    this.plData
      .obtenerPlGerencial(this.query)
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
            this.errorMensaje =
              'No se pudo cargar el P&L gerencial. Comprueba que sp-analytics esté en marcha y que el ETL de pl_gerencial haya corrido.';
            return;
          }
          this.filas = data.estadoResultados;
          this.filasVistaAnual = buildFilasVistaAnual(this.filas);
          this.gastosPctVentas = data.gastosPctVentas;
          if (data.meta.periodoTitulo) {
            this.periodoTitulo = data.meta.periodoTitulo;
          }
          // Título vs. solo del API: evita el flash de etiquetas del padre (hoy vs. periodo).
          this.comparacionTitulo = (data.meta.comparacionTitulo || '').trim();
          if (data.meta.anioAcumulado) {
            this.anioAcumulado = data.meta.anioAcumulado;
          }
          this.vistaEstado =
            data.meta.vistaEstado ??
            (this.query.periodo === 'anio_completo' ? 'anio_mensual' : 'mes');
        },
        error: () => {
          if (seq !== this.loadSeq) {
            return;
          }
          this.errorMensaje =
            'Error al consultar el P&L gerencial. Revisa la consola del navegador y los logs de sp-analytics.';
        },
      });
  }

  formatPct(value: number | null): string {
    if (value === null || value === undefined) {
      return '—';
    }
    return `${Math.trunc(value)}%`;
  }

  formatVariacion(fila: PlEstadoResultadosFila): string {
    if (fila.vsAbs === 0 && fila.vsPct === 0) {
      return '$0';
    }
    const empresa = this.api.auth_user()?.empresa;
    const abs = formatEmpresaCurrencyInt(Math.abs(fila.vsAbs), empresa);
    const sign = fila.vsAbs > 0 ? '+' : fila.vsAbs < 0 ? '-' : '';
    return `${sign}${abs}`;
  }

  /** Porcentaje de variación (tooltip en columna vs.). */
  tooltipVariacionPct(fila: PlEstadoResultadosFila): string {
    const pct = fila.vsPct ?? 0;
    if (Number.isNaN(pct)) {
      return 'Variación % no disponible';
    }
    if (Math.abs(pct) < 0.005 && Math.abs(fila.vsAbs) > 0.005) {
      return 'Variación %: no aplica (comparación en cero)';
    }
    const sign = pct > 0 ? '+' : pct < 0 ? '-' : '';
    const abs = Math.abs(pct);
    const cuerpo =
      Math.abs(abs - Math.round(abs)) < 0.05 ? String(Math.round(abs)) : abs.toFixed(1);
    return `Variación: ${sign}${cuerpo}%`;
  }

  variacionPositiva(fila: PlEstadoResultadosFila): boolean {
    return fila.vsAbs > 0.005;
  }

  variacionNegativa(fila: PlEstadoResultadosFila): boolean {
    return fila.vsAbs < -0.005;
  }

  estiloFila(fila: PlEstadoResultadosFila): PlEstadoResultadosFila['estilo'] {
    return fila.estilo ?? 'normal';
  }

  tituloEstadoMes(): string {
    return this.periodoTitulo;
  }

  etiquetaColumnaMes(): string {
    const mes = this.periodoTitulo.split(' ')[0]?.trim();
    return mes ? mes.toUpperCase() : 'MES';
  }

  /** Clases visuales del mock v4 (vista mes). */
  claseFilaMes(fila: PlEstadoResultadosFila): 'subtotal' | 'destacado' | 'normal' {
    const id = fila.lineId;
    if (id === 'resultado_neto') {
      return 'destacado';
    }
    if (id === 'utilidad_bruta' || id === 'utilidad_operativa') {
      return 'subtotal';
    }
    return 'normal';
  }

  etiquetaPeriodoColumna(): string {
    return this.periodoTitulo.split(' ')[0]?.toUpperCase() ?? 'MES';
  }

  etiquetaMesTitulo(): string {
    return this.periodoTitulo;
  }

  /** «OCT 2026» → «octubre 2026». El resto (presupuesto, YTD) va en minúsculas. */
  etiquetaComparacion(): string {
    const raw = (this.comparacionTitulo || '').trim();
    if (!raw) {
      return 'comparación';
    }
    const meses: Record<string, string> = {
      ene: 'enero',
      feb: 'febrero',
      mar: 'marzo',
      abr: 'abril',
      may: 'mayo',
      jun: 'junio',
      jul: 'julio',
      ago: 'agosto',
      sep: 'septiembre',
      oct: 'octubre',
      nov: 'noviembre',
      dic: 'diciembre',
    };
    const m = raw.match(/^([A-Za-z]{3,})\s+(\d{4})$/);
    if (m) {
      const nombre = meses[m[1].toLowerCase().slice(0, 3)];
      if (nombre) {
        return `${nombre} ${m[2]}`;
      }
    }
    return raw.toLowerCase();
  }

  esVistaAnualMensual(): boolean {
    return this.vistaEstado === 'anio_mensual' || this.query?.periodo === 'anio_completo';
  }

  montoMesFila(fila: PlEstadoResultadosFila, indiceMes: number): number {
    return fila.meses?.[indiceMes] ?? 0;
  }

  totalAnualFila(fila: PlEstadoResultadosFila): number {
    if (fila.totalAnual != null && !Number.isNaN(fila.totalAnual)) {
      return fila.totalAnual;
    }
    return fila.mes;
  }

  tituloEstadoAnual(): string {
    const base =
      this.query?.base === 'caja' ? 'Caja, con IVA' : 'Gerencial, sin IVA';
    return `${this.anioAcumulado} (${base})`;
  }

  formatMontoAnual(value: number): string {
    const empresa = this.api.auth_user()?.empresa;
    const abs = formatEmpresaCurrencyInt(Math.abs(value), empresa);
    if (value < 0) {
      return `-${abs}`;
    }
    return abs;
  }

  formatMargenAnual(value: number | null | undefined): string {
    if (value === null || value === undefined) {
      return '—';
    }
    return `${Math.trunc(value)}%`;
  }

  montoMesVista(row: PlFilaVistaAnual, indiceMes: number): number {
    if (!row.fuente) {
      return 0;
    }
    return this.montoMesFila(row.fuente, indiceMes);
  }

  totalAnualVista(row: PlFilaVistaAnual): number {
    if (!row.fuente) {
      return 0;
    }
    return this.totalAnualFila(row.fuente);
  }

  ordenarMes(col: PlMesSortCol): void {
    this.ordenMes = alternarOrden(this.ordenMes, col, true);
  }

  ariaSortMes(col: PlMesSortCol): 'ascending' | 'descending' | 'none' {
    return this.ordenMes.col === col
      ? (this.ordenMes.dir === 'asc' ? 'ascending' : 'descending')
      : 'none';
  }

  filasMesVisibles(): PlEstadoResultadosFila[] {
    const col = this.ordenMes.col;
    if (!col) {
      return this.filas;
    }
    return ordenarFilas(this.filas, this.ordenMes.dir, (f) => f[col]);
  }

  colMesAnual(idx: number): PlAnualSortCol {
    return `mes_${idx}`;
  }

  ordenarAnual(col: PlAnualSortCol): void {
    this.ordenAnual = alternarOrden(this.ordenAnual, col, true);
  }

  ariaSortAnual(col: PlAnualSortCol): 'ascending' | 'descending' | 'none' {
    return this.ordenAnual.col === col
      ? (this.ordenAnual.dir === 'asc' ? 'ascending' : 'descending')
      : 'none';
  }

  filasVistaAnualVisibles(): PlFilaVistaAnual[] {
    const col = this.ordenAnual.col;
    if (!col) {
      return this.filasVistaAnual;
    }
    return ordenarFilas(this.filasVistaAnual, this.ordenAnual.dir, (row) => {
      if (col === 'concepto') {
        return row.concepto;
      }
      if (col === 'total') {
        return row.estilo === 'margen' ? row.margenTotal ?? null : this.totalAnualVista(row);
      }
      const idx = Number(col.slice(4));
      return row.estilo === 'margen' ? row.margenMeses?.[idx] ?? null : this.montoMesVista(row, idx);
    });
  }
}
