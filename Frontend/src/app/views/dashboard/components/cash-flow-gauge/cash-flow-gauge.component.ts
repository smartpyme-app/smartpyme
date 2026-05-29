import { Component, Input, OnInit, OnChanges, SimpleChanges } from '@angular/core';

@Component({
  selector: 'app-cash-flow-gauge',
  templateUrl: './cash-flow-gauge.component.html',
  styleUrls: ['./cash-flow-gauge.component.css']
})
export class CashFlowGaugeComponent implements OnInit, OnChanges {
  @Input() minRequired: number = 0;
  @Input() current: number = 0;
  
  gaugeOption: any = {};

  ngOnInit(): void {
    this.initGauge();
  }

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['minRequired'] || changes['current']) {
      this.initGauge();
    }
  }

  initGauge(): void {
    const maxValue = this.minRequired || 100;
    const percentage = maxValue > 0 ? Math.min((this.current / maxValue) * 100, 100) : 0;
    const remaining = 100 - percentage;
    
    this.gaugeOption = {
      tooltip: {
        trigger: 'item',
        formatter: '{b}: {c} ({d}%)'
      },
      series: [
        {
          type: 'pie',
          radius: ['40%', '70%'],
          center: ['50%', '70%'],
          startAngle: 180,
          endAngle: 360,
          data: [
            {
              value: percentage,
              name: 'Disponible',
              itemStyle: {
                color: '#91cc75'
              },
              label: {
                show: false
              }
            },
            {
              value: remaining,
              name: 'Restante',
              itemStyle: {
                color: '#e0e0e0'
              },
              label: {
                show: false
              }
            }
          ],
          labelLine: {
            show: false
          },
          label: {
            show: false
          }
        }
      ],
      graphic: [
        {
          type: 'text',
          left: 'center',
          top: '60%',
          style: {
            text: '$' + this.current.toLocaleString('es-GT', { minimumFractionDigits: 2, maximumFractionDigits: 2 }),
            fontSize: 14,
            fontWeight: 'bold',
            fill: '#333'
          }
        },
        {
          type: 'text',
          left: 'center',
          top: '75%',
          style: {
            text: '$' + maxValue.toLocaleString('es-GT', { maximumFractionDigits: 0 }),
            fontSize: 11,
            fill: '#666'
          }
        }
      ]
    };
  }
}
