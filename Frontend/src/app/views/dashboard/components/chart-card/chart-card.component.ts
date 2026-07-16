import { Component, Input, OnInit } from '@angular/core';
import { MetricCard } from '../../models/chart-config.model';
import { CurrencyFormatService } from '@services/currency-format.service';

@Component({
  selector: 'app-chart-card',
  templateUrl: './chart-card.component.html',
  styleUrls: ['./chart-card.component.css']
})
export class ChartCardComponent implements OnInit {
  @Input() data!: MetricCard;

  constructor(private currencyFormat: CurrencyFormatService) {}

  get trendIcon(): string {
    if (!this.data.trend) return '';
    switch (this.data.trend.direction) {
      case 'up':
        return 'fa-arrow-up';
      case 'down':
        return 'fa-arrow-down';
      default:
        return 'fa-minus';
    }
  }

  get trendColor(): string {
    if (!this.data.trend) return '';
    switch (this.data.trend.direction) {
      case 'up':
        return 'text-success';
      case 'down':
        return 'text-danger';
      default:
        return 'text-muted';
    }
  }

  formatValue(value: number | string): string {
    if (typeof value === 'string') {
      // Si ya viene formateado como string (ej: "+ 12.58%")
      return value;
    }
    
    // Si es número, formatearlo según el tipo
    if (typeof value === 'number') {
      if (this.data.type === 'percentage') {
        // Valor ya en puntos porcentuales (ej. 0.26 → 0.26%, 12.5 → 12.50%)
        const sign = value >= 0 ? '' : '-';
        return `${sign}${Math.abs(value).toFixed(2)}%`;
      }

      if (this.data.type === 'percentage-int') {
        // Valor ya en puntos porcentuales (ej. 12.4 → 12%)
        const sign = value >= 0 ? '' : '-';
        const rounded = Math.round(Math.abs(value));
        return `${sign}${rounded}%`;
      }

      if (this.data.type === 'number') {
        // Formatear números enteros (sin decimales)
        return new Intl.NumberFormat('es-GT', {
          minimumFractionDigits: 0,
          maximumFractionDigits: 0
        }).format(value);
      }

      if (this.data.type === 'currency-int') {
        return new Intl.NumberFormat('en-US', {
          minimumFractionDigits: 0,
          maximumFractionDigits: 0,
        }).format(Math.abs(value));
      }

      if (this.data.type === 'currency' || this.shouldShowCurrency()) {
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
    
    // Fallback para cualquier otro caso
    return String(value);
  }

  formatTrendValue(value: number): string {
    const sign = value >= 0 ? '+' : '';
    return `${sign}${Math.abs(value).toFixed(2)}%`;
  }

  get currencySymbol(): string {
    return this.currencyFormat.getSymbol();
  }

  shouldShowCurrency(): boolean {
    // Mostrar $ solo si el tipo es 'currency' o 'currency-int'
    return this.data.type === 'currency' || this.data.type === 'currency-int';
  }

  isNegativeValue(): boolean {
    if (!this.data || this.data.value === undefined || this.data.value === null) {
      return false;
    }
    const val = this.data.value;
    if (typeof val === 'number') {
      return val < 0;
    }
    if (typeof val === 'string') {
      const cleaned = val.replace(/[^0-9.-]/g, '');
      const parsed = parseFloat(cleaned);
      if (!isNaN(parsed)) {
        return parsed < 0;
      }
      return val.trim().startsWith('-');
    }
    return false;
  }

  ngOnInit(): void {
  }
}

