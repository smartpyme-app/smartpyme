import { Component, Input, OnInit, OnChanges, SimpleChanges, Output, EventEmitter, OnDestroy } from '@angular/core';
import { ChartConfig } from '../../models/chart-config.model';

import { CommonModule } from '@angular/common';
import { NgxEchartsModule } from 'ngx-echarts';

@Component({
  selector: 'app-bar-chart',
  templateUrl: './bar-chart.component.html',
  styleUrls: ['./bar-chart.component.css'],
  standalone: true,
  imports: [CommonModule, NgxEchartsModule]
})
export class BarChartComponent implements OnInit, OnChanges, OnDestroy {
  @Input() config!: ChartConfig;
  @Output() itemClick = new EventEmitter<{ name: string; value: any; index: number }>();

  chartOption: any = {};
  echartsInstance: any;

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

  private hexToRgba(hex: string, alpha: number): string {
    if (!hex || !hex.startsWith('#')) return hex;
    const r = parseInt(hex.slice(1, 3), 16);
    const g = parseInt(hex.slice(3, 5), 16);
    const b = parseInt(hex.slice(5, 7), 16);
    return `rgba(${r}, ${g}, ${b}, ${alpha})`;
  }

  private buildTooltipStyle(): Record<string, any> {
    const primary = this.config?.colors?.[0];
    if (!primary || !primary.startsWith('#')) return {};
    return {
      backgroundColor: '#ffffff',
      borderColor: primary,
      borderWidth: 2,
      textStyle: { color: '#333' }
    };
  }

  initChart(): void {
    if (!this.config) {
      return;
    }

    if (this.config.type === 'doughnut' || this.config.type === 'pie') {
      this.initPieLikeChart();
      this.attachItemClickHandler();
      return;
    }

    // Verificar si es un gráfico de múltiples series (ventas y gastos)
    const isMultiSeries = Array.isArray(this.config.data) &&
      this.config.data.length > 0 &&
      typeof this.config.data[0] === 'object' &&
      this.config.data[0].hasOwnProperty('name') &&
      this.config.data[0].hasOwnProperty('data');

    let series: any[] = [];
    const showBarLabels = this.config.showBarLabels !== false;
    const barLabelPosition = this.config.barLabelPosition || 'top';
    const isInsidePosition = barLabelPosition.startsWith('inside');

    const barValueLabel = showBarLabels ? {
      show: true,
      position: barLabelPosition,
      rotate: 0,
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
      color: isInsidePosition ? '#fff' : '#878c94ff',
      fontSize: 12,
      fontWeight: 'medium',
      offset: isInsidePosition ? [0, 0] : [0, -5],
      align: 'center',
      verticalAlign: 'middle',
      padding: [4, 6, 4, 6]
    } : { show: false };

    if (isMultiSeries) {
      // Gráfico de múltiples series (barras agrupadas)
      series = (this.config.data as any[]).map((serie, index) => ({
        name: serie.name,
        type: 'bar',
        data: serie.data,
        itemStyle: {
          color: this.config.colors?.[index] || (index === 0 ? '#5470c6' : '#ff9800'),
          borderRadius: [4, 4, 0, 0]
        },
        label: barValueLabel,
        barMaxWidth: 60,
        emphasis: {
          itemStyle: {
            shadowBlur: 10,
            shadowOffsetX: 0,
            shadowColor: 'rgba(0, 0, 0, 0.5)'
          }
        }
      }));
    } else {
      // Gráfico de una sola serie
      const data = this.config.data as number[];
      const hasConditionalColors = (this.config as any).conditionalColors === true;
      const originalValues = (this.config as any).originalValues as number[] | undefined;

      series = [{
        name: this.config.title || 'Datos',
        type: 'bar',
        data: data,
        itemStyle: hasConditionalColors ? {
          color: (params: any) => {
            // Si hay valores originales, usarlos para determinar el color
            // Si no, usar el valor mostrado (que ya es absoluto)
            const originalValue = originalValues && originalValues[params.dataIndex] !== undefined
              ? originalValues[params.dataIndex]
              : params.value;
            // Verde para valores positivos, rojo para negativos
            return originalValue >= 0 ? '#4caf50' : '#f44336';
          },
        } : (this.config.graduatedOpacity ? {
          color: (params: any) => {
            const baseColor = this.config.colors?.[0] || '#5470c6';
            const maxVal = Math.max(...data);
            if (maxVal <= 0) return baseColor;
            const ratio = params.value / maxVal;
            if (baseColor.toUpperCase() === '#F19447') {
              const r = Math.round(246 + (241 - 246) * ratio);
              const g = Math.round(193 + (148 - 193) * ratio);
              const b = Math.round(146 + (71 - 146) * ratio);
              return `rgb(${r}, ${g}, ${b})`;
            }
            const opacity = 0.35 + 0.65 * ratio;
            return this.hexToRgba(baseColor, opacity);
          },
          borderRadius: (this.config as any).horizontal ? [0, 4, 4, 0] : [4, 4, 0, 0]
        } : (this.config.highlightMaxBar ? {
          color: (params: any) => {
            const baseColor = this.config.colors?.[0] || '#5470c6';
            const maxVal = Math.max(...data);
            return params.value === maxVal ? baseColor : this.hexToRgba(baseColor, 0.4);
          },
          borderRadius: (this.config as any).horizontal ? [0, 4, 4, 0] : [4, 4, 0, 0]
        } : {
          color: this.config.colors?.[0] || '#5470c6',
          borderRadius: (this.config as any).horizontal ? [0, 4, 4, 0] : [4, 4, 0, 0]
        })),
        label: barValueLabel,
        barMaxWidth: 60,
        emphasis: {
          itemStyle: {
            shadowBlur: 10,
            shadowOffsetX: 0,
            shadowColor: 'rgba(0, 0, 0, 0.5)'
          }
        }
      }];
    }

    const formatBarTooltipValue = (value: number) =>
      value < 0
        ? `(${Math.abs(value).toLocaleString('es-GT', { minimumFractionDigits: 2, maximumFractionDigits: 2 })})`
        : value.toLocaleString('es-GT', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    const labelCount = this.config.labels?.length ?? 0;
    const rotateLabels = this.config.rotateLabels ?? 0;
    const isHorizontal = !!(this.config as any).horizontal;
    const needsDataZoom = !isHorizontal && labelCount > 6;
    const dataZoomBottom = needsDataZoom ? '20%' : undefined;
    const gridBottom = this.config.gridBottom ?? dataZoomBottom ?? (
      rotateLabels >= 45 ? '16%' : rotateLabels > 0 ? '10%' : '8%'
    );

    const primaryColor = this.config.colors?.[0] || '#5470c6';
    const dataZoom = needsDataZoom ? [
      {
        type: 'slider',
        xAxisIndex: 0,
        start: 0,
        end: Math.round((6 / labelCount) * 100),
        bottom: 5,
        height: 14,
        borderColor: primaryColor,
        fillerColor: this.hexToRgba(primaryColor, 0.15),
        handleStyle: { color: primaryColor, borderColor: primaryColor },
        moveHandleStyle: { color: primaryColor },
        emphasis: { handleStyle: { color: primaryColor } },
        textStyle: { color: '#666', fontSize: 10 },
        brushSelect: false,
        zoomLock: true
      },
      {
        type: 'inside',
        xAxisIndex: 0,
        start: 0,
        end: Math.round((6 / labelCount) * 100),
        zoomLock: true,
        zoomOnMouseWheel: false,
        moveOnMouseMove: true,
        moveOnMouseWheel: true
      }
    ] : undefined;

    const barTooltipFormatter = (params: any) => {
      const resolveLabel = (item: any) =>
        this.config.tooltipLabels?.[item.dataIndex] ?? item.name;

      if (Array.isArray(params)) {
        const label = resolveLabel(params[0]);
        let result = label + '<br/>';
        params.forEach((item: any) => {
          const value = item.value;
          const formattedValue = formatBarTooltipValue(value);
          result += `${item.marker} ${item.seriesName}: $${formattedValue}<br/>`;
        });
        return result;
      }
      const item = params;
      const formattedValue = formatBarTooltipValue(item.value);
      const label = resolveLabel(item);
      return `${label}<br/>${item.marker} ${item.seriesName}: $${formattedValue}`;
    };

    this.chartOption = {
      title: this.config.title ? {
        text: this.config.title,
        left: 'left',
        textStyle: {
          fontSize: 16,
          fontWeight: 'normal'
        }
      } : undefined,
      tooltip: isMultiSeries
        ? {
          trigger: 'axis',
          axisPointer: { type: 'shadow' },
          formatter: barTooltipFormatter,
          ...this.buildTooltipStyle()
        }
        : {
          trigger: 'item',
          axisPointer: { type: 'none' },
          formatter: barTooltipFormatter,
          ...this.buildTooltipStyle()
        },
      legend: isMultiSeries ? {
        data: (this.config.data as any[]).map((s: any) => s.name),
        top: this.config.title ? 30 : 0,
        left: 'left',
        icon: 'circle',
        itemWidth: 10,
        itemHeight: 10,
        itemGap: 24,
        textStyle: {
          fontSize: 13,
          color: '#4a5568',
          fontWeight: 'normal'
        }
      } : undefined,
      grid: {
        left: (this.config as any).horizontal ? '15%' : '3%',
        right: '4%',
        bottom: gridBottom,
        top: isMultiSeries ? (this.config.title ? 60 : 40) : 30,
        containLabel: true
      },
      xAxis: (this.config as any).horizontal ? {
        type: 'value',
        axisLabel: {
          show: this.config.showXAxisLabels !== false,
          color: '#878c94ff',
          formatter: (value: number) => {
            const absValue = Math.abs(value);
            const sign = value >= 0 ? '' : '-';
            if (absValue >= 1000000) {
              return `${sign}$${(Math.floor((absValue / 1000000) * 10) / 10).toFixed(1)}M`;
            } else if (absValue >= 1000) {
              return `${sign}$${(Math.floor((absValue / 1000) * 10) / 10).toFixed(1)}K`;
            }
            return `${sign}$${absValue.toFixed(0)}`;
          }
        },
        splitLine: {
          show: false
        },
        axisLine: {
          show: false
        },
        axisTick: {
          show: false
        }
      } : {
        type: 'category',
        data: this.config.labels || [],
        axisLabel: {
          show: this.config.showXAxisLabels !== false,
          color: '#878c94ff',
          rotate: rotateLabels,
          interval: 0,
          fontSize: labelCount > 6 ? 10 : 11,
          hideOverlap: false,
          formatter: (value: string) => {
            const maxLen = 12;
            return value && value.length > maxLen ? value.slice(0, maxLen) + '…' : value;
          }
        },
        axisLine: {
          show: false
        },
        axisTick: {
          show: false
        }
      },
      yAxis: (this.config as any).horizontal ? {
        type: 'category',
        data: this.config.labels || [],
        axisLabel: {
          show: true,
          color: '#8e949dff',
          interval: 0,
          formatter: (value: string) => {
            const maxLen = 14;
            return value && value.length > maxLen ? value.slice(0, maxLen) + '…' : value;
          }
        },
        axisLine: {
          show: false
        },
        axisTick: {
          show: false
        },
        inverse: true
      } : {
        type: 'value',
        axisLabel: {
          show: false
        },
        splitLine: {
          show: false
        }
      },
      series: series.map(s => {
        if ((this.config as any).horizontal) {
          // Para barras horizontales, ajustar el label position y rotación
          const finalPosition = this.config.barLabelPosition || 'right';
          const isInside = finalPosition.startsWith('inside');
          return {
            ...s,
            label: {
              ...s.label,
              position: finalPosition,
              rotate: 0,
              align: isInside ? 'center' : 'left',
              color: isInside ? '#fff' : '#878c94ff',
              offset: isInside ? [0, 0] : [5, 0]
            }
          };
        }
        return s;
      }),
      ...(dataZoom ? { dataZoom } : {})
    };

    this.attachItemClickHandler();
    this.refreshChartSize();
  }

  private refreshChartSize(): void {
    if (!this.echartsInstance || this.echartsInstance.isDisposed()) {
      return;
    }
    requestAnimationFrame(() => {
      if (this.echartsInstance && !this.echartsInstance.isDisposed()) {
        this.echartsInstance.resize();
      }
    });
  }

  /**
   * Rosca / pastel: `labels` + `data` numéricos; `porcentajes` opcional (p. ej. desde API).
   */
  private initPieLikeChart(): void {
    const labels = this.config.labels || [];
    const rawData = (this.config.data || []) as number[];
    const porcentajes = this.config.porcentajes;
    const colors = this.config.colors || [
      '#5470c6', '#91cc75', '#fac858', '#ee6666',
      '#73c0de', '#3ba272', '#fc8452', '#9a60b4'
    ];

    const pieData = labels.map((name, index) => ({
      name: (name && String(name).trim()) ? name : `Ítem ${index + 1}`,
      value: rawData[index] ?? 0,
    }));

    const formatMoney = (value: number) => {
      const v = Number(value);
      const formatted = Math.abs(v).toLocaleString('es-GT', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      });
      return v < 0 ? `(${formatted})` : formatted;
    };

    const pctLabel = (dataIndex: number, params: any): string => {
      const p = porcentajes?.[dataIndex];
      if (p != null && Number.isFinite(p)) {
        return `${Number(p).toFixed(1)}%`;
      }
      const d = params?.percent;
      if (d != null && Number.isFinite(d)) {
        return `${Number(d).toFixed(1)}%`;
      }
      return '';
    };

    this.chartOption = {
      title: this.config.title ? {
        text: this.config.title,
        left: 'center',
        top: 0,
        textStyle: { fontSize: 14, fontWeight: 'normal' },
      } : undefined,
      tooltip: {
        trigger: 'item',
        triggerOn: 'mousemove',
        axisPointer: { type: 'none' },
        formatter: (params: any) => {
          const idx = params.dataIndex;
          const pct = pctLabel(idx, params);
          const val = formatMoney(params.value);
          const pctLine = pct ? `Participación: ${pct}` : '';
          return [
            `${params.marker} <b>${params.name}</b>`,
            `Importe: $${val}`,
            pctLine,
          ].filter(Boolean).join('<br/>');
        },
      },
      legend: {
        orient: 'vertical',
        left: 'left',
        top: 'middle',
      },
      series: [
        {
          name: this.config.title || 'Distribución',
          type: 'pie',
          radius:
            this.config.type === 'doughnut' ? ['42%', '72%'] : [0, '72%'],
          avoidLabelOverlap: true,
          itemStyle: {
            borderRadius: 8,
            borderColor: '#fff',
            borderWidth: 2,
          },
          label: {
            show: false,
          },
          labelLine: {
            show: false,
          },
          emphasis: {
            scale: true,
            scaleSize: 4,
            label: {
              show: false,
            },
            labelLine: {
              show: false,
            },
          },
          data: pieData,
          color: colors,
        },
      ],
    };
  }

  private attachItemClickHandler(): void {
    if (!this.echartsInstance || this.echartsInstance.isDisposed()) {
      return;
    }
    this.echartsInstance.off('click');
    this.echartsInstance.on('click', (params: any) => {
      if (params && params.name !== undefined) {
        this.itemClick.emit({
          name: params.name,
          value: params.value,
          index: params.dataIndex,
        });
      }
    });
  }

  onChartInit(ec: any): void {
    this.echartsInstance = ec;
    if (this.echartsInstance && !this.echartsInstance.isDisposed() && this.chartOption) {
      this.attachItemClickHandler();
    }
    setTimeout(() => this.refreshChartSize(), 0);
  }
}