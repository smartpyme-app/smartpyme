import { Component, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ApiService } from '@services/api.service';
import { formatEmpresaCurrency } from '@helpers/currency-format.helper';
import {
  ModeladoCalendarioCostoFila,
  ModeladoCalendarioPendiente,
  ModeladoGastoFila,
  ModeladoGastoTipo,
  ModeladoPresupuestoFila,
  ModeladoPresupuestoReparto,
  ModeladoPresupuestoVista,
} from './modelado.model';
import { MODELADO_MOCK } from './modelado.mock';

@Component({
  selector: 'app-modelado',
  templateUrl: './modelado.component.html',
  styleUrls: ['./modelado.component.css'],
  standalone: true,
  imports: [CommonModule, FormsModule],
})
export class ModeladoComponent {
  private readonly api = inject(ApiService);

  readonly data = MODELADO_MOCK;
  gastos: ModeladoGastoFila[] = this.data.gastosFijosVariables.map((g) => ({ ...g }));
  calendarioFilas: ModeladoCalendarioCostoFila[] = this.data.calendarioCostosFijos.map(
    (f) => ({ ...f }),
  );
  calendarioPendiente: ModeladoCalendarioPendiente | null = this.data.calendarioPendiente
    ? { ...this.data.calendarioPendiente }
    : null;

  readonly presupuestoVistas: { id: ModeladoPresupuestoVista; label: string }[] = [
    { id: 'empresa', label: 'Empresa' },
    { id: 'sucursales', label: 'Sucursales' },
    { id: 'areas', label: 'Áreas' },
  ];
  presupuestoVista: ModeladoPresupuestoVista = 'empresa';
  presupuestoReparto: ModeladoPresupuestoReparto = 'estacionalidad';
  presupuestoFilas: ModeladoPresupuestoFila[] = this.data.presupuesto.filas.map((f) => ({
    ...f,
  }));
  metaVentasAnual = this.data.presupuesto.metaVentas.montoAnual;

  pronostico = { ...this.data.parametrosPronostico };

  formatMonto(value: number): string {
    return formatEmpresaCurrency(value, this.api.auth_user()?.empresa);
  }

  gastoSubtitulo(gasto: ModeladoGastoFila): string {
    return `${gasto.categoria} · ${this.formatMonto(gasto.montoMensual)}/mes`;
  }

  seleccionarTipo(gasto: ModeladoGastoFila, tipo: ModeladoGastoTipo): void {
    gasto.tipo = tipo;
  }

  sinClasificarTotal(): number {
    return this.gastos
      .filter((g) => g.tipo === null)
      .reduce((sum, g) => sum + g.montoMensual, 0);
  }

  ejecutadoPresupuestoTexto(fila: ModeladoPresupuestoFila): string {
    return `${this.formatMonto(fila.ejecutadoMonto)} de ${this.formatMonto(fila.ejecutadoPresupuesto)}`;
  }

  vsPresupuestoLabel(pct: number): string {
    const sign = pct > 0 ? '+' : '';
    return `${sign}${pct}%`;
  }

  agregarCalendarioPendiente(): void {
    if (!this.calendarioPendiente) {
      return;
    }
    const p = this.calendarioPendiente;
    this.calendarioFilas.push({
      id: `manual-${Date.now()}`,
      nombre: p.nombre,
      monto: p.monto,
      bloqueado: false,
      frecuencia: 'Mensual',
      dia: String(p.dia),
      proximo: `${p.dia} nov`,
      editableMonto: true,
      editableDia: true,
    });
    this.calendarioPendiente = null;
  }
}
