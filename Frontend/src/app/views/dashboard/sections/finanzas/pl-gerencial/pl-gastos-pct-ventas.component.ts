import { Component, Input } from '@angular/core';
import { CommonModule } from '@angular/common';
import { PlGastoPctVentaFila } from './pl-gerencial.model';
@Component({
  selector: 'app-pl-gastos-pct-ventas',
  templateUrl: './pl-gastos-pct-ventas.component.html',
  styleUrls: ['./pl-gastos-pct-ventas.component.css'],
  standalone: true,
  imports: [CommonModule],
})
export class PlGastosPctVentasComponent {
  @Input() mesEtiqueta = 'septiembre';
  @Input() data: { filas: PlGastoPctVentaFila[] } = { filas: [] };
  /** ponytail: escala fija del mock; subir si algún % supera 70 */
  readonly escalaMaxPct = 70;

  etiquetaMes(): string {
    const raw = this.mesEtiqueta.trim().toLowerCase();
    if (raw.startsWith('año')) {
      return raw;
    }
    return raw.split(' ')[0] || 'mes';
  }

  anchoBarra(pct: number): number {
    return Math.min(100, Math.max(0, (pct / this.escalaMaxPct) * 100));
  }

  formatoDelta(delta: number): string {
    if (delta === 0) {
      return '-0.0';
    }
    const sign = delta > 0 ? '+' : '';
    return `${sign}${delta.toFixed(1)}`;
  }

  sparklinePath(fila: PlGastoPctVentaFila): string {
    const w = 76;
    const h = 30;
    const values = fila.tendencia;
    if (!values.length) {
      return '';
    }
    const min = Math.min(...values);
    const max = Math.max(...values);
    const range = max - min || 1;
    const coords = values.map((v, i) => {
      const x = values.length === 1 ? 0 : (i / (values.length - 1)) * w;
      const y = h - 2 - ((v - min) / range) * (h - 6);
      return `${x.toFixed(1)},${y.toFixed(1)}`;
    });
    return `M ${coords.join(' L ')}`;
  }
}
