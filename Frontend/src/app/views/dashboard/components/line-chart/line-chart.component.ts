import { Component, Input, OnInit, OnChanges, SimpleChanges, Output, EventEmitter, OnDestroy } from '@angular/core';
import { ChartConfig } from '../../models/chart-config.model';
import { CurrencyFormatService } from '@services/currency-format.service';

import { CommonModule } from '@angular/common';
import { NgxEchartsModule } from 'ngx-echarts';

@Component({
  selector: 'app-line-chart',
  templateUrl: './line-chart.component.html',
  styleUrls: ['./line-chart.component.css'],
  standalone: true,
  imports: [CommonModule, NgxEchartsModule]
})
export class LineChartComponent implements OnInit, OnChanges, OnDestroy {
  @Input() config!: ChartConfig;
  @Output() itemClick = new EventEmitter<{ name: string; value: any; index: number }>();

  chartOption: any = {};
  echartsInstance: any;

  constructor(private currencyFormat: CurrencyFormatService) {}

  private hexToRgba(hex: string, alpha: number): string {
    if (!hex || !hex.startsWith('#')) return hex;
    const r = parseInt(hex.slice(1, 3), 16);
    const g = parseInt(hex.slice(3, 5), 16);
    const b = parseInt(hex.slice(5, 7), 16);
    return `rgba(${r}, ${g}, ${b}, ${alpha})`;
  }

  ngOnInit(): void {
    this.initChart();
  }

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['config'] && !changes['config'].firstChange) {
      this.initChart();
    }
  }

  ngOnDestroy(): void {
    this.echartsInstance = null;
  }

  formatValue(value: number): string {
    let formatted = '';
    const absValue = Math.abs(value);
    const sign = value >= 0 ? '' : '-';
    if (absValue >= 1000000) {
      formatted = sign + (Math.floor((absValue / 1000000) * 10) / 10).toFixed(1) + 'M';
    } else if (absValue >= 1000) {
      formatted = sign + (Math.floor((absValue / 1000) * 10) / 10).toFixed(1) + 'K';
    } else {
      formatted = value.toString();
    }
    return `${this.currencyFormat.getSymbol()}${formatted}`;
  }

  initChart(): void {
    if (!this.config) {
      return;
    }

    const formatLineTooltipValue = (value: number) => {
      const v = Number(value);
      if (Number.isNaN(v)) return '';
      const formatted = Math.abs(v).toLocaleString('es-GT', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
      return v < 0 ? `($${formatted})` : `$${formatted}`;
    };

    this.chartOption = {
      title: this.config.title ? {
        text: this.config.title,
        left: 'center',
        textStyle: {
          fontSize: 14,
          fontWeight: 'normal'
        }
      } : undefined,
      tooltip: {
        trigger: 'axis',
        axisPointer: {
          type: 'cross'
        },
        ...(this.config.colors?.[0] ? {
          backgroundColor: '#ffffff',
          borderColor: this.config.colors[0],
          borderWidth: 2,
          textStyle: { color: '#333' }
        } : {}),
        formatter: (params: any) => {
          const resolveParams = Array.isArray(params) ? params : [params];
          if (resolveParams.length === 0) return '';
          const name = resolveParams[0].name;
          let html = `<b>${name}</b><br/>`;
          resolveParams.forEach((p: any) => {
            const val = formatLineTooltipValue(p.value);
            html += `${p.marker} ${p.seriesName}: ${val}<br/>`;
          });
          return html;
        }
      },
      grid: {
        left: '3%',
        right: '4%',
        bottom: '3%',
        containLabel: true
      },
      xAxis: {
        type: 'category',
        boundaryGap: false,
        data: this.config.labels || [],
        axisLine: {
          show: false
        },
        axisTick: {
          show: false
        },
        axisLabel: {
          color: '#878c94ff'
        }
      },
      yAxis: {
        type: 'value',
        splitLine: {
          show: false
        },
        axisLabel: {
          show: this.config.showYAxisLabels !== false,
          color: '#878c94ff',
          formatter: (value: number) => this.formatValue(value)
        },
        axisLine: {
          show: false
        },
        axisTick: {
          show: false
        }
      },
      series: [
        {
          name: this.config.title || 'Datos',
          type: 'line',
          data: this.config.data,
          smooth: this.config.smooth !== false,
          label: {
            show: this.config.showLineLabels !== false,
            position: 'top',
            formatter: (params: any) => {
              const value = params.value;
              const absValue = Math.abs(value);

              let formatted: string;
              if (absValue >= 1000000) {
                formatted = `${(Math.floor((absValue / 1000000) * 10) / 10).toFixed(1)}M`;
              } else if (absValue >= 1000) {
                formatted = `${(Math.floor((absValue / 1000) * 10) / 10).toFixed(1)}K`;
              } else {
                formatted = absValue.toFixed(0);
              }

              return value < 0 ? `(${formatted})` : formatted;
            },
            color: '#878c94ff',
            fontSize: 11,
            fontWeight: 'medium'
          },
          itemStyle: {
            color: this.config.colors?.[0] || '#5470c6'
          },
          areaStyle: this.config.showArea !== false ? {
            color: {
              type: 'linear',
              x: 0,
              y: 0,
              x2: 0,
              y2: 1,
              colorStops: [
                {
                  offset: 0,
                  color: this.config.colors?.[0] || '#5470c6'
                },
                {
                  offset: 1,
                  color: 'rgba(84, 112, 198, 0.1)'
                }
              ]
            }
          } : undefined
        }
      ]
    };

    // Agregar evento de clic
    if (this.echartsInstance && !this.echartsInstance.isDisposed()) {
      this.echartsInstance.off('click');
      this.echartsInstance.on('click', (params: any) => {
        if (params && params.name !== undefined) {
          this.itemClick.emit({
            name: params.name,
            value: params.value,
            index: params.dataIndex
          });
        }
      });
    }
  }

  onChartInit(ec: any): void {
    this.echartsInstance = ec;
    // Configurar evento de clic después de inicializar
    if (this.echartsInstance && !this.echartsInstance.isDisposed() && this.chartOption) {
      this.echartsInstance.on('click', (params: any) => {
        if (params && params.name !== undefined) {
          this.itemClick.emit({
            name: params.name,
            value: params.value,
            index: params.dataIndex
          });
        }
      });
    }
  }
}

