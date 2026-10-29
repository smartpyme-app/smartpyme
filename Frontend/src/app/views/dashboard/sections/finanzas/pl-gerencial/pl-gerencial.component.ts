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
import { ApiService } from '@services/api.service';
import { formatEmpresaCurrency } from '@helpers/currency-format.helper';
import { MetricCard } from '../../../models/chart-config.model';
import { FinanzasKpiCardComponent } from '../finanzas-kpi-card.component';
import { PlEstadoResultadosFila, PlGastosPctVentasMock } from './pl-gerencial.model';
import { PlGastosPctVentasComponent } from './pl-gastos-pct-ventas.component';
import {
  FinanzasPlDataService,
  PlGerencialQuery,
} from '../../../services/finanzas-pl-data.service';
import { finalize, take } from 'rxjs/operators';

export interface PlKpiChartItem {
  metric: MetricCard;
  sublinea?: string;
}

const PL_GASTOS_PCT_VACIO: PlGastosPctVentasMock = { filas: [] };

@Component({
  selector: 'app-pl-gerencial',
  templateUrl: './pl-gerencial.component.html',
  styleUrls: ['./pl-gerencial.component.css'],
  standalone: true,
  imports: [CommonModule, FinanzasKpiCardComponent, PlGastosPctVentasComponent],
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
  gastosPctVentas: PlGastosPctVentasMock = PL_GASTOS_PCT_VACIO;
  kpiItems: PlKpiChartItem[] = [];

  cargando = false;
  errorMensaje: string | null = null;

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
    if (!this.query?.anio || !this.query?.mes) {
      return;
    }

    const seq = ++this.loadSeq;
    this.cargando = true;
    this.errorMensaje = null;
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
          this.gastosPctVentas = data.gastosPctVentas;
          this.kpiItems = data.kpis.map((kpi) => ({
            metric: { title: kpi.titulo, value: kpi.valor },
            sublinea: kpi.sublinea,
          }));
          if (data.meta.periodoTitulo) {
            this.periodoTitulo = data.meta.periodoTitulo;
          }
          if (data.meta.comparacionTitulo) {
            this.comparacionTitulo = data.meta.comparacionTitulo;
          }
          if (data.meta.anioAcumulado) {
            this.anioAcumulado = data.meta.anioAcumulado;
          }
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

  formatMonto(value: number): string {
    const empresa = this.api.auth_user()?.empresa;
    const abs = formatEmpresaCurrency(Math.abs(value), empresa);
    if (value < 0) {
      return `-${abs}`;
    }
    return abs;
  }

  formatPct(value: number | null): string {
    if (value === null || value === undefined) {
      return '—';
    }
    return `${value}%`;
  }

  formatVariacion(fila: PlEstadoResultadosFila): string {
    if (fila.vsAbs === 0 && fila.vsPct === 0) {
      return '$0 (0%)';
    }
    const empresa = this.api.auth_user()?.empresa;
    const abs = formatEmpresaCurrency(Math.abs(fila.vsAbs), empresa);
    const sign = fila.vsAbs > 0 ? '+' : fila.vsAbs < 0 ? '-' : '';
    const pctSign = fila.vsPct > 0 ? '+' : '';
    return `${sign}${abs} (${pctSign}${fila.vsPct}%)`;
  }

  variacionPositiva(fila: PlEstadoResultadosFila): boolean {
    return fila.vsAbs > 0;
  }

  variacionNegativa(fila: PlEstadoResultadosFila): boolean {
    return fila.vsAbs < 0;
  }

  estiloFila(fila: PlEstadoResultadosFila): PlEstadoResultadosFila['estilo'] {
    return fila.estilo ?? 'normal';
  }

  etiquetaPeriodoColumna(): string {
    return this.periodoTitulo.split(' ')[0]?.toUpperCase() ?? 'MES';
  }

  etiquetaMesTitulo(): string {
    return this.periodoTitulo;
  }
}
