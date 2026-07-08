import { Component, Input, OnInit, OnChanges, SimpleChanges, Output, EventEmitter } from '@angular/core';
import { ChartConfig } from '../../models/chart-config.model';

import { CommonModule } from '@angular/common';
import { NgxEchartsModule } from 'ngx-echarts';

@Component({
  selector: 'app-treemap-chart',
  templateUrl: './treemap-chart.component.html',
  styleUrls: ['./treemap-chart.component.css'],
  standalone: true,
  imports: [CommonModule, NgxEchartsModule]
})
export class TreemapChartComponent implements OnInit, OnChanges {
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
    if (!this.config || !this.config.data) {
      return;
    }

    // Preparar datos para el treemap
    // Los datos pueden venir como array de objetos {name, value, children} o como array simple
    let treemapData: any[] = [];
    const colors = this.config.colors || ['#F19447', '#C9732F', '#A0521F'];

    if (this.config.data && this.config.data.length > 0) {
      treemapData = this.config.data.map((item: any, index: number) => {
        let name = '';
        let value = 0;
        let originalChildren: any[] = [];

        if (typeof item === 'object' && item !== null) {
          name = item.name || (this.config.labels && this.config.labels[index]) || '';
          value = item.value !== undefined ? item.value : (item.amount !== undefined ? item.amount : 0);
          originalChildren = item.children || [];
        } else {
          name = (this.config.labels && this.config.labels[index]) || `Item ${item}`;
          value = Number(item) || 0;
        }

        const itemColor = colors[index % colors.length];

        const result: any = {
          name: name,
          value: value,
          itemStyle: {
            color: itemColor
          }
        };

        if (originalChildren.length > 0) {
          result.children = originalChildren.map((child: any, childIndex: number) => {
            const childVal = child.value !== undefined ? child.value : (child.amount !== undefined ? child.amount : 0);
            return {
              name: child.name || '',
              value: childVal,
              itemStyle: {
                color: colors[(index + childIndex + 1) % colors.length]
              }
            };
          });
        }

        return result;
      });
    }

    this.chartOption = {
      title: this.config.title ? {
        text: this.config.title,
        left: 'left',
        textStyle: {
          fontSize: 14,
          fontWeight: 'normal'
        }
      } : undefined,
      tooltip: {
        trigger: 'item',
        formatter: (params: any) => {
          const value = params.value || params.data?.value || 0;
          const formattedValue = '$' + new Intl.NumberFormat('es-GT', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
          }).format(value);
          return `${params.name}<br/>${formattedValue}`;
        }
      },
      series: [
        {
          name: this.config.title || 'Gastos',
          type: 'treemap',
          data: treemapData,
          roam: false,
          nodeClick: false,
          breadcrumb: {
            show: false
          },
          label: {
            show: true,
            formatter: (params: any) => {
              const name = params.name || '';
              const value = params.value || params.data?.value || 0;
              const formattedValue = '$' + new Intl.NumberFormat('es-GT', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
              }).format(value);
              
              if (name.length > 15) {
                return name.substring(0, 15) + '...\n' + formattedValue;
              }
              return name + '\n' + formattedValue;
            },
            fontSize: 12,
            fontWeight: 'bold',
            color: '#fff'
          },
          itemStyle: {
            borderColor: '#fff',
            borderWidth: 2,
            gapWidth: 2
          },
          emphasis: {
            itemStyle: {
              shadowBlur: 10,
              shadowOffsetX: 0,
              shadowColor: 'rgba(0, 0, 0, 0.5)'
            }
          },
          visualMin: 0,
          visualMax: this.getMaxValue(treemapData)
        }
      ]
    };

    this.attachItemClickHandler();
  }

  private attachItemClickHandler(): void {
    if (!this.echartsInstance) {
      return;
    }
    this.echartsInstance.off('click');
    this.echartsInstance.on('click', (params: any) => {
      if (params && params.name !== undefined) {
        this.itemClick.emit({
          name: params.name,
          value: params.value || params.data?.value,
          index: params.dataIndex || 0,
        });
      }
    });
  }

  getMaxValue(data: any[]): number {
    let max = 0;
    const traverse = (items: any[]) => {
      items.forEach((item: any) => {
        if (item.value && item.value > max) {
          max = item.value;
        }
        if (item.children && item.children.length > 0) {
          traverse(item.children);
        }
      });
    };
    traverse(data);
    return max || 100;
  }

  onChartInit(ec: any): void {
    this.echartsInstance = ec;
    if (this.echartsInstance && this.chartOption) {
      this.attachItemClickHandler();
    }
  }
}
