import { Component, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ApiService } from '@services/api.service';
import { formatEmpresaCurrency } from '@helpers/currency-format.helper';
import { FlujoSegmentoBarra } from './flujo-caja.model';
import { FLUJO_POSICION_MOCK } from './flujo-caja.mock';

@Component({
  selector: 'app-flujo-caja-posicion',
  templateUrl: './flujo-caja-posicion.component.html',
  styleUrls: ['./flujo-caja-posicion.component.css'],
  standalone: true,
  imports: [CommonModule],
})
export class FlujoCajaPosicionComponent {
  private readonly api = inject(ApiService);

  readonly data = FLUJO_POSICION_MOCK;

  formatMonto(value: number): string {
    return formatEmpresaCurrency(value, this.api.auth_user()?.empresa);
  }

  anchoSegmento(segmento: FlujoSegmentoBarra, total: number): number {
    if (!total) {
      return 0;
    }
    return (segmento.monto / total) * 100;
  }

  claseTono(tono: FlujoSegmentoBarra['tono']): string {
    return `flujo-bar--${tono}`;
  }
}
