import { Component, Input, OnInit, OnChanges, SimpleChanges } from '@angular/core';
import { BudgetMetric } from '../../models/chart-config.model';

@Component({
  selector: 'app-budget-card',
  templateUrl: './budget-card.component.html',
  styleUrls: ['./budget-card.component.css']
})
export class BudgetCardComponent implements OnInit, OnChanges {
  @Input() data!: BudgetMetric;
  
  chartOption: any = {};

  ngOnInit(): void {
    this.initChart();
  }

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['data']) {
      this.initChart();
    }
  }

  initChart(): void {
    if (!this.data.chartData || this.data.chartData.length === 0) {
      this.chartOption = {};
      return;
    }

    const chartColor = this.getChartColor();
    const dataPoints = this.data.chartData.map((value, index) => [index, value]);

    this.chartOption = {
      grid: {
        left: 0,
        right: 0,
        top: 5,
        bottom: 0,
        containLabel: false
      },
      xAxis: {
        type: 'value',
        show: false
      },
      yAxis: {
        type: 'category',
        show: false,
        inverse: true
      },
      series: [
        {
          type: 'line',
          data: dataPoints,
          smooth: true,
          areaStyle: {
            color: {
              type: 'linear',
              x: 0,
              y: 0,
              x2: 0,
              y2: 1,
              colorStops: [
                {
                  offset: 0,
                  color: chartColor
                },
                {
                  offset: 1,
                  color: 'transparent'
                }
              ]
            }
          },
          lineStyle: {
            color: chartColor,
            width: 2
          },
          symbol: 'none',
          emphasis: {
            disabled: true
          }
        }
      ],
      tooltip: {
        show: true,
        trigger: 'axis',
        formatter: (params: any) => {
          const param = params[0];
          return `Valor: ${this.formatCurrency(param.value[1])}`;
        },
        backgroundColor: 'rgba(0, 0, 0, 0.12)',
        borderColor: 'transparent',
        textStyle: {
          color: '#fff',
          fontSize: 11
        }
      }
    };
  }

  formatCurrency(value: number): string {
    return new Intl.NumberFormat('es-GT', {
      style: 'currency',
      currency: 'USD',
      minimumFractionDigits: 2,
      maximumFractionDigits: 2
    }).format(value);
  }

  formatPercentage(value: number): string {
    const sign = value >= 0 ? '+' : '';
    return `${sign}${value.toFixed(2)}%`;
  }

  getColorClass(): string {
    switch (this.data.color) {
      case 'green':
        return 'text-success';
      case 'orange':
        return 'text-warning';
      case 'gray':
        return 'text-secondary';
      default:
        return 'text-success';
    }
  }

  getChartColor(): string {
    switch (this.data.color) {
      case 'green':
        return '#28a745';
      case 'orange':
        return '#ff9800';
      case 'gray':
        return '#6c757d';
      default:
        return '#28a745';
    }
  }
}
