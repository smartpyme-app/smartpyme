import { Component, Input } from '@angular/core';
import { CommonModule } from '@angular/common';
import { MetricCard } from '../../models/chart-config.model';
import { CurrencyFormatService } from '@services/currency-format.service';

@Component({
  selector: 'app-finanzas-kpi-card',
  standalone: true,
  imports: [CommonModule],
  template: `
    <div class="sf-kpi fin-kpi-card">
      <div class="sf-kpi__label fin-kpi-label">{{ data.title }}</div>
      <div
        class="sf-kpi__value fin-kpi-value"
        [class.sf-kpi__value--neg]="negativo || alerta"
        [class.fin-kpi-value--neg]="negativo || alerta">
        @if (muestraMoneda) {
          <span class="fin-kpi-moneda">{{ moneda }}</span>
        }
        {{ texto }}
      </div>
      <div class="fin-kpi-slot">
        <ng-content></ng-content>
      </div>
    </div>
  `,
  styles: `
    :host {
      display: flex;
      flex-direction: column;
      flex: 1 1 auto;
      width: 100%;
      height: 100%;
    }

    .fin-kpi-card {
      height: 100%;
      min-width: 0;
    }

    .fin-kpi-label {
      white-space: normal;
      overflow: visible;
    }

    .fin-kpi-moneda {
      font-weight: 700;
    }

    .fin-kpi-value {
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .fin-kpi-slot:empty {
      display: none;
    }
  `,
})
export class FinanzasKpiCardComponent {
  @Input() data!: MetricCard;
  @Input() alerta = false;

  constructor(private currencyFormat: CurrencyFormatService) {}

  get moneda(): string {
    return this.currencyFormat.getSymbol();
  }

  get muestraMoneda(): boolean {
    return this.data?.type === 'currency' || this.data?.type === 'currency-int';
  }

  get negativo(): boolean {
    const val = this.data?.value;
    if (typeof val === 'number') {
      return val < 0;
    }
    if (typeof val === 'string') {
      return val.trim().startsWith('-');
    }
    return false;
  }

  get texto(): string {
    const value = this.data?.value;
    if (typeof value === 'string') {
      return value;
    }
    if (typeof value !== 'number') {
      return '';
    }
    if (this.data.type === 'percentage') {
      return `${value < 0 ? '-' : ''}${Math.abs(value).toFixed(2)}%`;
    }
    if (this.data.type === 'percentage-int') {
      return `${value < 0 ? '-' : ''}${Math.round(Math.abs(value))}%`;
    }
    if (this.data.type === 'number' || this.data.type === 'currency-int') {
      return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
      }).format(this.data.type === 'currency-int' ? Math.abs(value) : value);
    }
    if (this.data.type === 'currency') {
      return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      }).format(Math.abs(value));
    }
    return new Intl.NumberFormat('en-US', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    }).format(value);
  }
}
