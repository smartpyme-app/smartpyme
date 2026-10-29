import {
  Component,
  Input,
  OnChanges,
  SimpleChanges,
  inject,
} from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ApiService } from '@services/api.service';
import { formatEmpresaCurrency } from '@helpers/currency-format.helper';
import {
  FlujoSemanaDesglose,
  FlujoSemanaDetalle,
  FlujoSemanaDetalleFila,
} from './flujo-caja.model';

@Component({
  selector: 'app-flujo-caja-semanal',
  templateUrl: './flujo-caja-semanal.component.html',
  styleUrls: ['./flujo-caja-semanal.component.css'],
  standalone: true,
  imports: [CommonModule, FormsModule],
})
export class FlujoCajaSemanalComponent implements OnChanges {
  private readonly api = inject(ApiService);

  @Input() detalle: FlujoSemanaDetalle | null = null;
  @Input() desgloses: FlujoSemanaDesglose[] = [];
  @Input() pctContado = 0;
  @Input() semanaInicial = '';

  filas: FlujoSemanaDetalleFila[] = [];
  semanaSeleccionada = '';

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['detalle'] || changes['semanaInicial']) {
      this.filas = (this.detalle?.filas ?? []).map((f) => ({ ...f }));
      const pick =
        this.semanaInicial && this.filas.some((f) => f.id === this.semanaInicial)
          ? this.semanaInicial
          : this.filas[0]?.id ?? '';
      if (!this.filas.some((f) => f.id === this.semanaSeleccionada)) {
        this.semanaSeleccionada = pick;
      }
    }
  }

  get notaPie(): string {
    return this.detalle?.notaPie ?? '';
  }

  seleccionarSemana(id: string): void {
    this.semanaSeleccionada = id;
  }

  desgloseSemana(): FlujoSemanaDesglose | null {
    const fila = this.filas.find((f) => f.id === this.semanaSeleccionada);
    if (!fila) {
      return null;
    }
    const api = this.desgloses.find((d) => d.id === fila.id);
    if (api) {
      return api;
    }
    return this.desgloseDesdeFila(fila);
  }

  onVentasChange(index: number): void {
    const ventas = Number(this.filas[index].ventasSinIva);
    this.filas[index].ventasSinIva = Number.isFinite(ventas) ? ventas : 0;
    this.recalcularDesde(index);
  }

  formatMonto(value: number): string {
    return formatEmpresaCurrency(value, this.api.auth_user()?.empresa);
  }

  formatCobros(value: number | null | undefined): string {
    if (value === null || value === undefined || Number.isNaN(value)) {
      return '—';
    }
    return this.formatMonto(value);
  }

  private desgloseDesdeFila(fila: FlujoSemanaDetalleFila): FlujoSemanaDesglose {
    const salidas: FlujoSemanaDesglose['salidas'] = [];
    if (fila.fijos > 0) {
      salidas.push({ concepto: 'Gastos fijos', monto: fila.fijos });
    }
    if (fila.impuestos > 0) {
      salidas.push({ concepto: 'Impuestos', monto: fila.impuestos });
    }
    if (fila.proveedores > 0) {
      salidas.push({ concepto: 'Proveedores', monto: fila.proveedores });
    }
    if (fila.otros > 0) {
      salidas.push({ concepto: 'Gastos variables', monto: fila.otros });
    }
    const cobros = fila.cobros ?? 0;
    const entradas = [{ concepto: 'Ventas de contado', monto: fila.deContado }];
    if (cobros > 0) {
      entradas.push({ concepto: 'Cobros', monto: cobros });
    }
    return {
      id: fila.id,
      rango: fila.rango,
      entradas,
      salidas,
      neto: fila.neto,
    };
  }

  private recalcularDesde(startIndex: number): void {
    const pct = this.pctContado / 100;
    for (let i = startIndex; i < this.filas.length; i++) {
      const fila = this.filas[i];
      if (i > 0) {
        fila.inicial = this.filas[i - 1].final;
      }
      fila.deContado = Math.round(fila.ventasSinIva * pct);
      const cobros = fila.cobros ?? 0;
      fila.neto =
        fila.deContado +
        cobros -
        fila.fijos -
        fila.impuestos -
        fila.proveedores -
        fila.otros;
      fila.final = fila.inicial + fila.neto;
    }
  }
}
