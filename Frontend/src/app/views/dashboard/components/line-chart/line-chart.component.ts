import { Component, Input, OnInit, OnChanges, SimpleChanges } from '@angular/core';
import { ChartConfig } from '../../models/chart-config.model';

@Component({
  selector: 'app-line-chart',
  templateUrl: './line-chart.component.html',
  styleUrls: ['./line-chart.component.css']
})
export class LineChartComponent implements OnInit, OnChanges {
  @Input() config!: ChartConfig;
  
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
        data: this.config.labels || []
      },
      yAxis: {
        type: 'value'
      },
      series: [
        {
          name: this.config.title || 'Datos',
          type: 'line',
          data: this.config.data,
          smooth: true,
          itemStyle: {
            color: this.config.colors?.[0] || '#5470c6'
          },
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
                  color: this.config.colors?.[0] || '#5470c6'
                },
                {
                  offset: 1,
                  color: 'rgba(84, 112, 198, 0.1)'
                }
              ]
            }
          }
        }
      ]
    };
  }

  onChartInit(ec: any): void {
    this.echartsInstance = ec;
  }
}

