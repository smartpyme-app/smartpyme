import { Component, Input, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ApiService } from '@services/api.service';
import { formatEmpresaCurrency } from '@helpers/currency-format.helper';
import { MetricCard } from '../../../models/chart-config.model';
import { ChartCardComponent } from '../../../components/chart-card/chart-card.component';
import { PlEstadoResultadosFila } from './pl-gerencial.model';
import { PL_ESTADO_RESULTADOS_MOCK, PL_KPIS_MOCK } from './pl-gerencial.mock';
import { PlGastosPctVentasComponent } from './pl-gastos-pct-ventas.component';

export interface PlKpiChartItem {
  metric: MetricCard;
  sublinea?: string;
}

@Component({
  selector: 'app-pl-gerencial',
  templateUrl: './pl-gerencial.component.html',
  styleUrls: ['./pl-gerencial.component.css'],
  standalone: true,
  imports: [CommonModule, ChartCardComponent, PlGastosPctVentasComponent],
})
export class PlGerencialComponent {
  private readonly api = inject(ApiService);

  @Input() periodoTitulo = 'septiembre 2026';
  @Input() comparacionTitulo = 'SEP 2025';
  @Input() anioAcumulado = new Date().getFullYear();

  readonly filas: PlEstadoResultadosFila[] = PL_ESTADO_RESULTADOS_MOCK;

  readonly kpiItems: PlKpiChartItem[] = PL_KPIS_MOCK.map((kpi) => ({
    metric: { title: kpi.titulo, value: kpi.valor },
    sublinea: kpi.sublinea,
  }));

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
