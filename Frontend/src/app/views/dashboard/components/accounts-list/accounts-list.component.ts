import { Component, Input, OnInit, OnChanges, SimpleChanges } from '@angular/core';

export interface AccountItem {
  name: string;
  amount: number;
}

@Component({
  selector: 'app-accounts-list',
  templateUrl: './accounts-list.component.html',
  styleUrls: ['./accounts-list.component.css']
})
export class AccountsListComponent implements OnInit, OnChanges {
  @Input() title: string = '';
  @Input() accounts: AccountItem[] = [];
  @Input() type: 'receivable' | 'payable' = 'receivable';

  chartOption: any = {};
  echartsInstance: any;

  ngOnInit(): void {
    this.initChart();
  }

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['accounts'] && !changes['accounts'].firstChange) {
      this.initChart();
    }
  }

  get sortedAccounts(): AccountItem[] {
    return [...this.accounts].sort((a, b) => Math.abs(b.amount) - Math.abs(a.amount));
  }

  initChart(): void {
    if (!this.accounts || this.accounts.length === 0) {
      return;
    }

    const sorted = this.sortedAccounts;
    const labels = sorted.map(a => a.name);
    const data = sorted.map(a => Math.abs(a.amount));
    const color = this.type === 'payable' ? '#ff9800' : '#4a90e2';

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
        left: '30%',
        right: '8%',
        bottom: '3%',
        top: '3%',
        containLabel: false
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
      series: [{
        type: 'bar',
        data: data,
        barWidth: '80%',
        itemStyle: {
          color: color,
          borderRadius: [0, 4, 4, 0]
        },
        label: {
          show: true,
          position: 'right',
          formatter: (params: any) => {
            const value = params.value;
            if (value >= 1000000) {
              return `$${(value / 1000000).toFixed(1)}M`;
            } else if (value >= 1000) {
              return `$${(value / 1000).toFixed(0)}K`;
            }
            return `$${value.toLocaleString('es-GT')}`;
          },
          color: '#333',
          fontSize: 11,
          fontWeight: 'bold'
        }
      }]
    };
  }

  onChartInit(ec: any): void {
    this.echartsInstance = ec;
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
