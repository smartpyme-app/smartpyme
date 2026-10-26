import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { NgxEchartsModule } from 'ngx-echarts';
import { EChartsOption } from 'echarts';
import { ApiService } from '@services/api.service';
import {
  formatEmpresaCurrency,
  getEmpresaCurrencySymbol,
} from '@helpers/currency-format.helper';
import { FlujoVistaId } from './flujo-caja.model';
import { FLUJO_CAJA_MOCK } from './flujo-caja.mock';
import { FlujoCajaPosicionComponent } from './flujo-caja-posicion.component';
import { FlujoCajaSemanalComponent } from './flujo-caja-semanal.component';
import { FlujoCajaSemanaDesgloseComponent } from './flujo-caja-semana-desglose.component';
import { MetricCard } from '../../../models/chart-config.model';
import { ChartCardComponent } from '../../../components/chart-card/chart-card.component';

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
    ChartCardComponent,
    FlujoCajaPosicionComponent,
    FlujoCajaSemanalComponent,
    FlujoCajaSemanaDesgloseComponent,
  ],
})
export class FlujoCajaComponent implements OnInit {
  private readonly api = inject(ApiService);

  readonly data = FLUJO_CAJA_MOCK;

  readonly vistaOpciones: { id: FlujoVistaId; label: string }[] = [
    { id: 'semanal', label: 'Semanal' },
    { id: 'mensual', label: 'Mensual' },
  ];

  vista: FlujoVistaId = 'semanal';
  cobroCxCPct = this.data.cobroCxCHistoricoPct;
  cumplimientoMetaPct = this.data.metaRitmoPct;

  chartOption: EChartsOption = {};

  ngOnInit(): void {
    this.chartOption = this.buildChartOption();
  }

  seleccionarVista(id: FlujoVistaId): void {
    this.vista = id;
  }

  onSupuestosChange(): void {
    // ponytail: sliders solo UI; recálculo real cuando exista API de pronóstico
    this.chartOption = this.buildChartOption();
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

  metasVentasTexto(): string {
    return this.data.metasVentas
      .map((m) => `${m.mes} (${this.formatMonto(m.monto)})`)
      .join(', ');
  }

  private buildChartOption(): EChartsOption {
    const { labels, neto, saldo, saldoBajo, saldoAlto } = this.data.semanas;
    const colchon = this.data.colchon;
    const empresa = this.api.auth_user()?.empresa;

    const netoPos = neto.map((v) => (v >= 0 ? v : 0));
    const netoNeg = neto.map((v) => (v < 0 ? v : 0));

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
        min: -15000,
        max: 45000,
        interval: 15000,
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
              labels[params.dataIndex] === 'S3' ? '#ef4444' : '#fb923c',
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
          markLine: {
            silent: true,
            symbol: 'none',
            lineStyle: { type: 'dashed', color: '#f97316', width: 1.5 },
            label: {
              formatter: 'colchón',
              color: '#f97316',
              fontSize: 11,
            },
            data: [{ yAxis: colchon }],
          },
        },
      ],
    };
  }
}
