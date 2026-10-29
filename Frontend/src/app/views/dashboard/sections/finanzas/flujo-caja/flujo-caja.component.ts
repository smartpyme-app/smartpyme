import {
  ChangeDetectorRef,
  Component,
  Input,
  OnChanges,
  OnDestroy,
  OnInit,
  SimpleChanges,
  inject,
} from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { NgxEchartsModule } from 'ngx-echarts';
import { EChartsOption } from 'echarts';
import { finalize, take } from 'rxjs/operators';
import { ApiService } from '@services/api.service';
import {
  formatEmpresaCurrency,
  getEmpresaCurrencySymbol,
} from '@helpers/currency-format.helper';
import { FlujoVistaId } from './flujo-caja.model';
import { FlujoCajaPosicionComponent } from './flujo-caja-posicion.component';
import { FlujoCajaSemanalComponent } from './flujo-caja-semanal.component';
import { FlujoCajaSemanaDesgloseComponent } from './flujo-caja-semana-desglose.component';
import { MetricCard } from '../../../models/chart-config.model';
import { FinanzasKpiCardComponent } from '../finanzas-kpi-card.component';
import {
  FLUJO_CAJA_VACIA,
  FinanzasFlujoCajaDataService,
} from '../../../services/finanzas-flujo-caja-data.service';

export interface FlujoKpiChartItem {
  metric: MetricCard;
  sublinea?: string;
}

@Component({
  selector: 'app-flujo-caja',
  templateUrl: './flujo-caja.component.html',
  styleUrls: ['./flujo-caja.component.css'],
  standalone: true,
  imports: [
    CommonModule,
    FormsModule,
    NgxEchartsModule,
    FinanzasKpiCardComponent,
    FlujoCajaPosicionComponent,
    FlujoCajaSemanalComponent,
    FlujoCajaSemanaDesgloseComponent,
  ],
})
export class FlujoCajaComponent implements OnInit, OnChanges, OnDestroy {
  private readonly api = inject(ApiService);
  private readonly flujoData = inject(FinanzasFlujoCajaDataService);
  private readonly cdr = inject(ChangeDetectorRef);

  @Input() sucursalId = 'consolidado';

  data = FLUJO_CAJA_VACIA;
  cargando = false;
  contenidoListo = false;
  errorMensaje: string | null = null;
  private loadSeq = 0;
  private supuestosTimer: ReturnType<typeof setTimeout> | null = null;

  readonly vistaOpciones: { id: FlujoVistaId; label: string }[] = [
    { id: 'semanal', label: 'Semanal' },
    { id: 'mensual', label: 'Mensual' },
  ];

  vista: FlujoVistaId = 'semanal';
  cobroCxCPct = 90;
  cumplimientoMetaPct = 100;

  chartOption: EChartsOption = {};

  ngOnInit(): void {
    this.cargar();
  }

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['sucursalId'] && !changes['sucursalId'].firstChange) {
      this.cargar();
    }
  }

  ngOnDestroy(): void {
    if (this.supuestosTimer) {
      clearTimeout(this.supuestosTimer);
    }
  }

  seleccionarVista(id: FlujoVistaId): void {
    this.vista = id;
  }

  onSupuestosChange(): void {
    if (this.supuestosTimer) {
      clearTimeout(this.supuestosTimer);
    }
    this.supuestosTimer = setTimeout(() => this.cargar(), 350);
  }

  kpiChartItems(): FlujoKpiChartItem[] {
    const k = this.data.kpis;
    const empresa = this.api.auth_user()?.empresa;
    const monto = (n: number) => formatEmpresaCurrency(n, empresa);
    const montoSigned = (n: number) => {
      const abs = formatEmpresaCurrency(Math.abs(n), empresa);
      return n < 0 ? `-${abs}` : abs;
    };

    return [
      {
        metric: { title: 'Punto más bajo - 4 semanas', value: monto(k.puntoBajo4Semanas) },
        sublinea: k.puntoBajo4SemanasSub,
      },
      {
        metric: { title: 'vs. Colchón', value: montoSigned(k.vsColchon) },
        sublinea: `colchón ${monto(this.data.colchon)}`,
      },
      {
        metric: { title: 'Punto más bajo - 12 semanas', value: monto(k.puntoBajo12Semanas) },
        sublinea: k.puntoBajo12SemanasSub,
      },
      {
        metric: {
          title: 'Cobro necesario',
          value: `${k.cobroNecesarioPct}%`,
          type: 'percentage-int',
        },
        sublinea: `supuesto ${this.cobroCxCPct}%`,
      },
      {
        metric: {
          title: 'Meta necesaria',
          value: `${k.metaNecesariaPct}%`,
          type: 'percentage-int',
        },
        sublinea: `supuesto ${this.cumplimientoMetaPct}%`,
      },
      {
        metric: {
          title: 'Semanas bajo colchón',
          value: k.semanasBajoColchon,
          type: 'number',
        },
        sublinea: `de ${k.totalSemanasProyeccion}`,
      },
    ];
  }

  sliderMarkerLeft(value: number, min: number, max: number): number {
    if (max <= min) {
      return 0;
    }
    const pct = ((value - min) / (max - min)) * 100;
    return Math.min(100, Math.max(0, pct));
  }

  formatMonto(value: number): string {
    const empresa = this.api.auth_user()?.empresa;
    return formatEmpresaCurrency(value, empresa);
  }

  cicloEfectivoTexto(): string {
    const d = this.data.cicloEfectivoDias;
    return d == null ? '—' : `${d}`;
  }

  sinProyeccion(): boolean {
    return !this.data.semanas.labels.length;
  }

  private cargar(): void {
    const seq = ++this.loadSeq;
    this.cargando = true;
    this.errorMensaje = null;
    this.cdr.markForCheck();
    this.flujoData
      .obtener({
        sucursalId: this.sucursalId,
        cobroPct: this.cobroCxCPct,
        metaPct: this.cumplimientoMetaPct,
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
      .subscribe((payload) => {
        if (seq !== this.loadSeq) {
          return;
        }
        if (!payload) {
          if (!this.contenidoListo) {
            this.data = FLUJO_CAJA_VACIA;
          }
          this.errorMensaje =
            'No se pudo cargar el flujo de caja. Revisa la conexión con analytics.';
          this.chartOption = this.buildChartOption();
          return;
        }
        this.data = payload;
        this.contenidoListo = true;
        this.errorMensaje = null;
        this.chartOption = this.buildChartOption();
      });
  }

  private buildChartOption(): EChartsOption {
    const { labels, neto, saldo, saldoBajo, saldoAlto } = this.data.semanas;
    const colchon = this.data.colchon;
    const empresa = this.api.auth_user()?.empresa;
    const semCritica = this.data.kpis.semanaBajo4;

    const netoPos = neto.map((v) => (v >= 0 ? v : 0));
    const netoNeg = neto.map((v) => (v < 0 ? v : 0));

    const vals = [...neto, ...saldo, ...saldoBajo, ...saldoAlto, colchon];
    let yMin = vals.length ? Math.min(...vals) : 0;
    let yMax = vals.length ? Math.max(...vals) : 0;
    const pad = Math.max(500, (yMax - yMin) * 0.12);
    yMin = Math.floor((yMin - pad) / 1000) * 1000;
    yMax = Math.ceil((yMax + pad) / 1000) * 1000;
    if (yMin === yMax) {
      yMax = yMin + 1000;
    }
    const interval = Math.max(1000, Math.ceil((yMax - yMin) / 4 / 1000) * 1000);

    return {
      grid: { left: 8, right: 8, top: 36, bottom: 48, containLabel: true },
      legend: {
        top: 0,
        left: 'center',
        itemWidth: 12,
        itemHeight: 12,
        textStyle: { fontSize: 11, color: '#64748b' },
        data: ['Neto +', 'Neto −', 'Saldo', 'Rango de escenarios'],
      },
      tooltip: { trigger: 'axis', axisPointer: { type: 'cross' } },
      xAxis: {
        type: 'category',
        data: labels,
        axisLine: { show: false },
        axisTick: { show: false },
        axisLabel: { color: '#94a3b8', fontSize: 11 },
      },
      yAxis: {
        type: 'value',
        min: yMin,
        max: yMax,
        interval,
        axisLabel: {
          color: '#94a3b8',
          fontSize: 11,
          formatter: (v: number) => {
            const sym = getEmpresaCurrencySymbol(empresa);
            return `${sym}${v / 1000}k`;
          },
        },
        splitLine: { lineStyle: { color: '#f1f5f9', type: 'dashed' } },
      },
      series: [
        {
          name: 'Rango de escenarios',
          type: 'line',
          data: saldoBajo,
          lineStyle: { opacity: 0 },
          stack: 'band',
          symbol: 'none',
          silent: true,
        },
        {
          name: 'Rango de escenarios',
          type: 'line',
          data: saldoAlto.map((hi, i) => hi - saldoBajo[i]),
          lineStyle: { opacity: 0 },
          areaStyle: { color: 'rgba(147, 197, 253, 0.35)' },
          stack: 'band',
          symbol: 'none',
          silent: true,
        },
        {
          name: 'Neto +',
          type: 'bar',
          data: netoPos,
          barMaxWidth: 18,
          itemStyle: { color: '#1e3a5f', borderRadius: [3, 3, 0, 0] },
        },
        {
          name: 'Neto −',
          type: 'bar',
          data: netoNeg,
          barMaxWidth: 18,
          itemStyle: {
            color: (params: { dataIndex: number }) =>
              labels[params.dataIndex] === semCritica ? '#ef4444' : '#fb923c',
            borderRadius: [0, 0, 3, 3],
          },
        },
        {
          name: 'Saldo',
          type: 'line',
          data: saldo,
          smooth: 0.25,
          symbol: 'circle',
          symbolSize: 8,
          lineStyle: { width: 2.5, color: '#3b82f6' },
          itemStyle: {
            color: '#fff',
            borderColor: '#3b82f6',
            borderWidth: 2,
          },
          markLine: colchon
            ? {
                silent: true,
                symbol: 'none',
                lineStyle: { type: 'dashed', color: '#f97316', width: 1.5 },
                label: {
                  formatter: 'colchón',
                  color: '#f97316',
                  fontSize: 11,
                },
                data: [{ yAxis: colchon }],
              }
            : undefined,
        },
      ],
    };
  }
}
