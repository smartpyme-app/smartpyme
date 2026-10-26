import { Component, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ApiService } from '@services/api.service';
import { formatEmpresaCurrency } from '@helpers/currency-format.helper';
import {
  CierreMesDetalle,
  CierreMesItem,
  CierreVarTono,
} from './cierre-mes.model';
import { CIERRE_MES_MOCK } from './cierre-mes.mock';

@Component({
  selector: 'app-cierre-mes',
  templateUrl: './cierre-mes.component.html',
  styleUrls: ['./cierre-mes.component.css'],
  standalone: true,
  imports: [CommonModule],
})
export class CierreMesComponent {
  private readonly api = inject(ApiService);

  readonly data = CIERRE_MES_MOCK;
  mesSeleccionadoId = this.data.mesActivoId;

  seleccionarMes(mes: CierreMesItem): void {
    if (mes.estado === 'pendiente') {
      return;
    }
    this.mesSeleccionadoId = mes.id;
  }

  mesesCerradosConteo(): number {
    return this.data.mesesCerrados;
  }

  detalleActivo(): CierreMesDetalle | null {
    return this.data.detallesPorMesId[this.mesSeleccionadoId] ?? null;
  }

  pendientesChecklist(detalle: CierreMesDetalle): number {
    return detalle.checklist.filter((i) => i.estado !== 'ok').length;
  }

  formatMonto(value: number): string {
    return formatEmpresaCurrency(value, this.api.auth_user()?.empresa);
  }

  formatVarMes(pct: number | null): string {
    if (pct === null) {
      return '—';
    }
    if (pct > 0) {
      return `+${pct}%`;
    }
    if (pct < 0) {
      return `${pct}%`;
    }
    return '0%';
  }

  claseVarTono(tono: CierreVarTono): string {
    switch (tono) {
      case 'pos':
        return 'cierre-text-pos';
      case 'neg':
        return 'cierre-text-neg';
      case 'warn':
        return 'cierre-text-warn';
      default:
        return 'cierre-text-muted';
    }
  }
}
