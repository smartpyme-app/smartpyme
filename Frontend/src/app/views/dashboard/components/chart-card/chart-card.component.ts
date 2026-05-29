import { Component, Input, OnInit } from '@angular/core';
import { MetricCard } from '../../models/chart-config.model';

@Component({
  selector: 'app-chart-card',
  templateUrl: './chart-card.component.html',
  styleUrls: ['./chart-card.component.css']
})
export class ChartCardComponent implements OnInit {
  @Input() data!: MetricCard;

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
        // Si el valor viene como decimal (0.125) → convertir a porcentaje
        // Si viene como entero/flotante directo (12.5) → usarlo directo
        const pct = Math.abs(value) <= 1 && value !== 0 ? value * 100 : value;
        const sign = pct >= 0 ? '' : '-';
        return `${sign}${Math.abs(pct).toFixed(2)}%`;
      }

      // Formatear números de moneda con 2 decimales (nunca redondear)
      return new Intl.NumberFormat('es-GT', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
      }).format(value);
    }
    
    // Fallback para cualquier otro caso
    return String(value);
  }

  formatTrendValue(value: number): string {
    const sign = value >= 0 ? '+' : '';
    return `${sign}${Math.abs(value).toFixed(2)}%`;
  }

  shouldShowCurrency(): boolean {
    // Mostrar $ solo si el tipo es 'currency'
    return this.data.type === 'currency';
  }

  ngOnInit(): void {
  }
}

