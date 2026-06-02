import { Component, Input, OnInit, OnChanges, SimpleChanges, Output, EventEmitter } from '@angular/core';
import { ChartConfig } from '../../models/chart-config.model';

@Component({
  selector: 'app-treemap-chart',
  templateUrl: './treemap-chart.component.html',
  styleUrls: ['./treemap-chart.component.css']
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

        // Si no tiene children, creamos un dummy child para poder usar upperLabel y label en esquinas distintas
        if (originalChildren.length === 0) {
          result.children = [
            {
              name: name,
              value: value,
              itemStyle: {
                color: itemColor
              }
            }
          ];
        } else {
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
          const formattedValue = new Intl.NumberFormat('es-GT', {
            style: 'currency',
            currency: 'USD',
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
          levels: [
            {
              // Level 0 (root)
            },
            {
              // Level 1: Parent nodes (e.g. "Transferencia")
              itemStyle: {
                borderColor: '#fff',
                borderWidth: 2,
                gapWidth: 2
              },
              upperLabel: {
                show: true,
                position: 'insideTopLeft',
                color: '#000',
                fontSize: 14,
                fontWeight: 'normal',
                offset: [10, 10],
                formatter: '{b}',
                backgroundColor: 'transparent'
              },
              label: {
                show: false
              }
            },
            {
              // Level 2: Child nodes (holding the actual value)
              itemStyle: {
                borderWidth: 0,
                gapWidth: 0
              },
              label: {
                show: true,
                position: 'insideBottomLeft',
                color: '#fff',
                fontSize: 12,
                fontWeight: 'normal',
                offset: [10, -10],
                formatter: (params: any) => {
                  const val = params.value || params.data?.value || 0;
                  return new Intl.NumberFormat('es-GT', {
                    style: 'currency',
                    currency: 'USD',
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                  }).format(val);
                }
              }
            }
          ],
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
    // Configurar evento de clic después de inicializar
    if (this.echartsInstance && this.chartOption) {
      this.echartsInstance.on('click', (params: any) => {
        if (params && params.name !== undefined) {
          this.itemClick.emit({
            name: params.name,
            value: params.value || params.data?.value,
            index: params.dataIndex || 0
          });
        }
      });
    }
  }
}
