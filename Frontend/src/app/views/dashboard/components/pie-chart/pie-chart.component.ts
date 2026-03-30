import { Component, Input, OnInit, OnChanges, SimpleChanges, Output, EventEmitter } from '@angular/core';
import { ChartConfig } from '../../models/chart-config.model';

@Component({
  selector: 'app-pie-chart',
  templateUrl: './pie-chart.component.html',
  styleUrls: ['./pie-chart.component.css']
})
export class PieChartComponent implements OnInit, OnChanges {
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

    // Preparar datos: objetos { name, value } o arrays paralelos labels[] + data[] (p. ej. CXC vigencia)
    const labels = this.config.labels ?? [];

    const pieData = this.config.data.map((item: any, index: number) => {
      const labelAt = labels[index];
      const nameFromLabels =
        labelAt != null && String(labelAt).trim() !== ''
          ? String(labelAt)
          : '';

      if (item !== null && typeof item === 'object' && !Array.isArray(item)) {
        const name =
          item.name != null && String(item.name).trim() !== ''
            ? String(item.name)
            : item.estadoVigencia != null &&
                String(item.estadoVigencia).trim() !== ''
              ? String(item.estadoVigencia)
              : nameFromLabels || `Item ${index + 1}`;
        const valueRaw =
          item.value ?? item.amount ?? item.total ?? item.y ?? 0;
        const value =
          typeof valueRaw === 'number'
            ? valueRaw
            : Number(valueRaw) || 0;
        return { name, value };
      }

      const value =
        typeof item === 'number'
          ? item
          : typeof item === 'string'
            ? Number(item) || 0
            : Number(item) || 0;

      return {
        name: nameFromLabels || `Item ${index + 1}`,
        value,
      };
    });

    const colors = this.config.colors || [
      '#5470c6', '#91cc75', '#fac858', '#ee6666',
      '#73c0de', '#3ba272', '#fc8452', '#9a60b4'
    ];

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
        formatter: '{a} <br/>{b}: {c} ({d}%)'
      },
      legend: {
        orient: 'vertical',
        left: 'left',
        top: 'middle'
      },
      series: [
        {
          name: this.config.title || 'Distribución',
          type: 'pie',
          radius: ['40%', '70%'],
          avoidLabelOverlap: false,
          itemStyle: {
            borderRadius: 10,
            borderColor: '#fff',
            borderWidth: 2
          },
          label: {
            show: false,
            formatter: '{b}: {d}%'
          },
          emphasis: {
            label: {
              show: false,
              fontSize: 14,
              fontWeight: 'bold'
            }
          },
          data: pieData,
          color: colors
        }
      ]
    };
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

