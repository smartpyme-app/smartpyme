import { Component, Input, OnInit, OnChanges, SimpleChanges, Output, EventEmitter } from '@angular/core';

export interface AccountItem {
  name: string;
  amount: number;
}

import { CommonModule } from '@angular/common';
import { NgxEchartsModule } from 'ngx-echarts';

@Component({
  selector: 'app-accounts-list',
  templateUrl: './accounts-list.component.html',
  styleUrls: ['./accounts-list.component.css'],
  standalone: true,
  imports: [CommonModule, NgxEchartsModule]
})
export class AccountsListComponent implements OnInit, OnChanges {
  @Input() title: string = '';
  @Input() accounts: AccountItem[] = [];
  @Input() type: 'receivable' | 'payable' = 'receivable';
  @Output() itemClick = new EventEmitter<{ name: string; amount: number }>();

  chartOption: any = {};
  echartsInstance: any;

  private hexToRgba(hex: string, alpha: number): string {
    const r = parseInt(hex.slice(1, 3), 16);
    const g = parseInt(hex.slice(3, 5), 16);
    const b = parseInt(hex.slice(5, 7), 16);
    return `rgba(${r}, ${g}, ${b}, ${alpha})`;
  }

  ngOnInit(): void {
    this.initChart();
  }

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['accounts'] && !changes['accounts'].firstChange) {
      this.initChart();
    }
  }

  get sortedAccounts(): AccountItem[] {
    if (!this.accounts || !Array.isArray(this.accounts)) return []; // ← agregar
    return [...this.accounts].sort((a, b) => Math.abs(b.amount) - Math.abs(a.amount));
  }

  initChart(): void {
    if (!this.accounts || !Array.isArray(this.accounts) || this.accounts.length === 0) {
      return;
    }

    const sorted = this.sortedAccounts;
    const labels = sorted.map(a => a.name);
    const data = sorted.map(a => Math.abs(a.amount));
    const color = this.type === 'payable' ? '#F19447' : '#7CABFF';

    const showZoom = labels.length > 10;

    this.chartOption = {
      tooltip: {
        trigger: 'axis',
        axisPointer: {
          type: 'shadow'
        },
        formatter: (params: any) => {
          if (Array.isArray(params) && params.length > 0) {
            const value = params[0].value;
            const formattedValue = value.toLocaleString('es-GT', {
              minimumFractionDigits: 2,
              maximumFractionDigits: 2
            });
            return `${params[0].name}<br/>$${formattedValue}`;
          }
          return '';
        }
      },
      grid: {
        left: '3%',
        right: showZoom ? '15%' : '8%',
        bottom: '3%',
        top: '3%',
        containLabel: true
      },
      xAxis: {
        type: 'value',
        axisLabel: {
          show: false
        },
        splitLine: {
          show: false
        }
      },
      yAxis: {
        type: 'category',
        data: labels,
        inverse: true,
        axisLabel: {
          interval: 0,
          fontSize: 11,
          formatter: (value: string) => {
            if (value.length > 25) {
              return value.substring(0, 25) + '...';
            }
            return value;
          }
        }
      },
      dataZoom: showZoom ? [
        {
          type: 'slider',
          yAxisIndex: 0,
          startValue: 0,
          endValue: 9,
          right: '2%',
          width: 15,
          borderColor: 'transparent',
          fillerColor: '#e2e8f0',
          handleSize: 0,
          showDetail: false,
          zoomLock: true,
          brushSelect: false
        },
        {
          type: 'inside',
          yAxisIndex: 0,
          startValue: 0,
          endValue: 9,
          zoomOnMouseWheel: false,
          moveOnMouseMove: true,
          moveOnMouseWheel: true
        }
      ] : undefined,
      series: [{
        type: 'bar',
        data: data,
        barWidth: '80%',
        itemStyle: {
          color: (params: any) => {
            return params.dataIndex === 0 ? color : this.hexToRgba(color, 0.4);
          },
          borderRadius: [0, 4, 4, 0]
        },
        label: {
          show: true,
          position: 'right',
          formatter: (params: any) => {
            const value = params.value;
            const absValue = Math.abs(value);
            let formatted: string;
            if (absValue >= 1000000) {
              formatted = `${(Math.floor((absValue / 1000000) * 10) / 10).toFixed(1)}M`;
            } else if (absValue >= 1000) {
              formatted = `${(Math.floor((absValue / 1000) * 10) / 10).toFixed(1)}K`;
            } else {
              formatted = absValue.toLocaleString('es-GT', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
            }
            return `$${formatted}`;
          },
          color: '#000',
          fontSize: 11,
          fontWeight: 'normal'
        }
      }]
    };

    // Agregar evento de clic
    if (this.echartsInstance) {
      this.echartsInstance.off('click');
      this.echartsInstance.on('click', (params: any) => {
        if (params && params.name !== undefined) {
          const account = sorted.find(a => a.name === params.name);
          if (account) {
            this.itemClick.emit({
              name: account.name,
              amount: account.amount
            });
          }
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
          const sorted = this.sortedAccounts;
          const account = sorted.find(a => a.name === params.name);
          if (account) {
            this.itemClick.emit({
              name: account.name,
              amount: account.amount
            });
          }
        }
      });
    }
  }

  formatAmount(amount: number): string {
    return new Intl.NumberFormat('es-GT', {
      style: 'currency',
      currency: 'USD',
      minimumFractionDigits: 2,
      maximumFractionDigits: 2
    }).format(amount);
  }
}
