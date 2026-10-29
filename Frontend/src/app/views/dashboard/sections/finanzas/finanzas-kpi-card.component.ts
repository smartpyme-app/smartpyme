import { Component, Input } from '@angular/core';
import { CommonModule } from '@angular/common';
import { MetricCard } from '../../models/chart-config.model';
import { CurrencyFormatService } from '@services/currency-format.service';

@Component({
  selector: 'app-finanzas-kpi-card',
  standalone: true,
  imports: [CommonModule],
  template: `
    <div class="card fin-kpi-card">
      <div class="card-body pt-3 ps-3 pe-3 pb-3">
        <div class="fin-kpi-label text-muted mb-1">{{ data.title }}</div>
        <div class="fin-kpi-value mb-1" [class.text-danger]="negativo" [class.text-dark]="!negativo">
          @if (muestraMoneda) {
            <span>{{ moneda }}</span>
          }
          {{ texto }}
        </div>
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
      background-color: #fff;
      border: 1px solid rgba(0, 118, 227, 0.15);
      border-radius: 8px;
      box-shadow: none;
      min-width: 0;
      overflow: hidden;
    }

    .fin-kpi-card .card-body {
      min-width: 0;
    }

    .fin-kpi-label {
      font-size: 0.62rem;
      font-weight: 600;
      color: #64748b;
      text-transform: uppercase;
      letter-spacing: 0.06em;
      line-height: 1.3;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .fin-kpi-value {
      font-size: clamp(1rem, 2.8vw, 1.45rem);
      font-weight: 400;
      line-height: 1.2;
      letter-spacing: -0.02em;
      color: #1e293b;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
  `,
})
export class FinanzasKpiCardComponent {
  @Input() data!: MetricCard;

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
