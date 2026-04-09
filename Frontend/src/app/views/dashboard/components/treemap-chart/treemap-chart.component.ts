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
    let treemapData: any;
    
    const colors = this.config.colors || ['#F19447', '#C9732F', '#A0521F'];
    
    // Si el primer elemento tiene children, es una estructura jerárquica
    if (this.config.data.length > 0 && 
        typeof this.config.data[0] === 'object' && 
        this.config.data[0].children) {
      // Estructura jerárquica completa - asignar colores específicos
      treemapData = this.config.data.map((item: any, index: number) => {
        const result: any = {
          name: item.name,
          value: item.value,
          itemStyle: {
            color: colors[index % colors.length]
          }
        };
        
        if (item.children && item.children.length > 0) {
          result.children = item.children.map((child: any, childIndex: number) => ({
            ...child,
            itemStyle: {
              color: colors[(index + childIndex + 1) % colors.length]
            }
          }));
        }
        
        return result;
      });
    } else {
      // Estructura plana - convertir a jerárquica si es necesario
      treemapData = this.config.data.map((item: any, index: number) => {
        if (typeof item === 'object' && item.name && item.value !== undefined) {
          const result: any = {
            name: item.name,
            value: item.value,
            itemStyle: {
              color: colors[index % colors.length]
            }
          };
          
          if (item.children && item.children.length > 0) {
            result.children = item.children.map((child: any, childIndex: number) => ({
              ...child,
              itemStyle: {
                color: colors[(index + childIndex + 1) % colors.length]
              }
            }));
          }
          
          return result;
        }
        return {
          name: `Item ${item}`,
          value: item,
          itemStyle: {
            color: colors[index % colors.length]
          }
        };
      });
    }

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
          label: {
            show: true,
            formatter: (params: any) => {
              const name = params.name || '';
              const value = params.value || params.data?.value || 0;
              const formattedValue = new Intl.NumberFormat('es-GT', {
                style: 'currency',
                currency: 'USD',
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
              }).format(value);
              
              // Mostrar nombre y valor en diferentes líneas si hay espacio
              if (name.length > 15) {
                return name.substring(0, 15) + '...\n' + formattedValue;
              }
              return name + '\n' + formattedValue;
            },
            fontSize: 12,
            fontWeight: 'bold',
            color: '#333'
          },
          upperLabel: {
            show: true,
            height: 30
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
          // No usar colorMappingBy, los colores ya están asignados en itemStyle de cada elemento
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
