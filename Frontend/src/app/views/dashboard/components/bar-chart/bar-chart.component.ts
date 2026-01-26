import { Component, Input, OnInit, OnChanges, SimpleChanges, Output, EventEmitter } from '@angular/core';
import { ChartConfig } from '../../models/chart-config.model';

@Component({
  selector: 'app-bar-chart',
  templateUrl: './bar-chart.component.html',
  styleUrls: ['./bar-chart.component.css']
})
export class BarChartComponent implements OnInit, OnChanges {
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

  initChart(): void {
    if (!this.config) {
      return;
    }

    // Verificar si es un gráfico de múltiples series (ventas y gastos)
    const isMultiSeries = Array.isArray(this.config.data) && 
                         this.config.data.length > 0 && 
                         typeof this.config.data[0] === 'object' && 
                         this.config.data[0].hasOwnProperty('name') &&
                         this.config.data[0].hasOwnProperty('data');

    let series: any[] = [];

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
        label: {
          show: true,
          position: 'top',
          rotate: 0,
          formatter: (params: any) => {
            const value = params.value;
            const absValue = Math.abs(value);
            
            // Formatear con abreviaciones para valores grandes
            let formatted: string;
            if (absValue >= 1000000) {
              formatted = `${(absValue / 1000000).toFixed(1)}M`;
            } else if (absValue >= 1000) {
              formatted = `${(absValue / 1000).toFixed(1)}K`;
            } else {
              formatted = absValue.toFixed(0);
            }
            
            return value < 0 ? `(${formatted})` : formatted;
          },
          color: '#333',
          fontSize: 12,
          fontWeight: 'medium',
          offset: [0, -10],
          align: 'center',
          verticalAlign: 'middle',
          padding: [4, 6, 4, 6]
        },
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
          borderRadius: [4, 4, 0, 0]
        } : {
          color: this.config.colors?.[0] || '#5470c6',
          borderRadius: [4, 4, 0, 0]
        },
        label: {
          show: true,
          position: 'top',
          rotate: 0,
          formatter: (params: any) => {
            const value = params.value;
            const absValue = Math.abs(value);
            
            // Formatear con abreviaciones para valores grandes
            let formatted: string;
            if (absValue >= 1000000) {
              formatted = `${(absValue / 1000000).toFixed(1)}M`;
            } else if (absValue >= 1000) {
              formatted = `${(absValue / 1000).toFixed(1)}K`;
            } else {
              formatted = absValue.toFixed(0);
            }
            
            return value < 0 ? `(${formatted})` : formatted;
          },
          color: '#333',
          fontSize: 12,
          fontWeight: 'medium',
          offset: [0, -10],
          align: 'center',
          verticalAlign: 'middle',
          padding: [4, 6, 4, 6]
        },
        emphasis: {
          itemStyle: {
            shadowBlur: 10,
            shadowOffsetX: 0,
            shadowColor: 'rgba(0, 0, 0, 0.5)'
          }
        }
      }];
    }

    this.chartOption = {
      title: this.config.title ? {
        text: this.config.title,
        left: 'left',
        textStyle: {
          fontSize: 16,
          fontWeight: 'normal'
        }
      } : undefined,
      tooltip: {
        trigger: 'axis',
        axisPointer: {
          type: 'shadow'
        },
        formatter: (params: any) => {
          if (Array.isArray(params)) {
            let result = params[0].name + '<br/>';
            params.forEach((item: any) => {
              const value = item.value;
              const formattedValue = value < 0 
                ? `(${Math.abs(value).toLocaleString('es-GT', { minimumFractionDigits: 2, maximumFractionDigits: 2 })})`
                : value.toLocaleString('es-GT', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
              result += `${item.marker} ${item.seriesName}: $${formattedValue}<br/>`;
            });
            return result;
          }
          return '';
        }
      },
      legend: isMultiSeries ? {
        data: (this.config.data as any[]).map((s: any) => s.name),
        top: this.config.title ? 30 : 10,
        left: 'left'
      } : undefined,
      grid: {
        left: (this.config as any).horizontal ? '15%' : '3%',
        right: '4%',
        bottom: '3%',
        top: isMultiSeries ? (this.config.title ? '20%' : '15%') : '10%',
        containLabel: true
      },
      xAxis: (this.config as any).horizontal ? {
        type: 'value',
        axisLabel: {
          show: true,
          formatter: (value: number) => {
            if (value >= 1000000) {
              return `$${(value / 1000000).toFixed(1)}M`;
            } else if (value >= 1000) {
              return `$${(value / 1000).toFixed(1)}K`;
            }
            return `$${value.toFixed(0)}`;
          }
        },
        splitLine: {
          show: false
        }
      } : {
        type: 'category',
        data: this.config.labels || [],
        axisLabel: {
          rotate: this.config.rotateLabels !== undefined ? this.config.rotateLabels : 0,
          interval: 0
        }
      },
      yAxis: (this.config as any).horizontal ? {
        type: 'category',
        data: this.config.labels || [],
        axisLabel: {
          show: true,
          interval: 0
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
          // Para barras horizontales, ajustar el label position
          return {
            ...s,
            label: {
              ...s.label,
              position: 'right'
            }
          };
        }
        return s;
      })
    };

    // Agregar evento de clic
    if (this.echartsInstance) {
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
    if (this.echartsInstance && this.chartOption) {
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