import { Component, Input, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ApiService } from '@services/api.service';
import { formatEmpresaCurrency } from '@helpers/currency-format.helper';
import { FlujoSemanaDesglose } from './flujo-caja.model';

@Component({
  selector: 'app-flujo-caja-semana-desglose',
  templateUrl: './flujo-caja-semana-desglose.component.html',
  styleUrls: ['./flujo-caja-semana-desglose.component.css'],
  standalone: true,
  imports: [CommonModule],
})
export class FlujoCajaSemanaDesgloseComponent {
  private readonly api = inject(ApiService);

  @Input({ required: true }) desglose!: FlujoSemanaDesglose;

  formatMonto(value: number): string {
    const empresa = this.api.auth_user()?.empresa;
    const abs = formatEmpresaCurrency(Math.abs(value), empresa);
    if (value < 0) {
      return `-${abs}`;
    }
    return abs;
  }
}
