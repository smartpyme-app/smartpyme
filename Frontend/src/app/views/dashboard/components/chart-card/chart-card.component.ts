import { Component, Input, OnInit } from '@angular/core';
import { MetricCard } from '../../models/chart-config.model';

@Component({
  selector: 'app-chart-card',
  templateUrl: './chart-card.component.html',
  styleUrls: ['./chart-card.component.css']
})
export class ChartCardComponent implements OnInit {
  @Input() data!: MetricCard;
  barChartData: Array<{x: number, y: number, width: number, height: number}> = [];

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
    
    // Si es número, formatearlo
    if (typeof value === 'number') {
      // Si el valor es muy pequeño, podría ser un porcentaje
      if (Math.abs(value) < 1 && value !== 0) {
        const sign = value >= 0 ? '+' : '';
        return `${sign}${(Math.abs(value) * 100).toFixed(2)}%`;
      }
      
      // Formatear números grandes con comas
      return new Intl.NumberFormat('es-GT', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
      }).format(value);
    }
    
    // Fallback para cualquier otro caso
    return String(value);
  }

  formatTrendValue(value: number): string {
    const sign = value >= 0 ? '+' : '';
    return `${sign}${Math.abs(value).toFixed(2)}%`;
  }

  getChartColor(): string {
    // Colores para los gráficos según el tipo de métrica
    if (this.data.color) {
      return this.data.color;
    }
    // Colores por defecto según la dirección de la tendencia
    if (this.data.trend) {
      return this.data.trend.direction === 'up' ? '#28a745' : 
             this.data.trend.direction === 'down' ? '#dc3545' : '#6c757d';
    }
    return '#4a90e2';
  }

  shouldShowCurrency(): boolean {
    // Mostrar $ solo si el valor es numérico y el título no es "Growth"
    return typeof this.data.value === 'number' && 
           this.data.title !== 'Growth' && 
           !this.data.title.toLowerCase().includes('growth');
  }

  ngOnInit(): void {
    this.generateBarChart();
  }

  generateBarChart(): void {
    // Genera datos para el gráfico de barras
    const numBars = 7;
    this.barChartData = [];
    
    for (let i = 0; i < numBars; i++) {
      const height = Math.random() * 25 + 8; // Altura entre 8 y 33
      const x = i * 10 + 2;
      const y = 40 - height;
      this.barChartData.push({ x, y, width: 6, height });
    }
  }
}

