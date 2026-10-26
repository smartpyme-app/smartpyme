import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ApiService } from '@services/api.service';
import { formatEmpresaCurrency } from '@helpers/currency-format.helper';
import { FlujoSemanaDesglose, FlujoSemanaDetalleFila } from './flujo-caja.model';
import {
  FLUJO_SEMANA_DESGLOSE_MOCK,
  FLUJO_SEMANAL_DETALLE_MOCK,
} from './flujo-caja.mock';

@Component({
  selector: 'app-flujo-caja-semanal',
  templateUrl: './flujo-caja-semanal.component.html',
  styleUrls: ['./flujo-caja-semanal.component.css'],
  standalone: true,
  imports: [CommonModule, FormsModule],
})
export class FlujoCajaSemanalComponent implements OnInit {
  private readonly api = inject(ApiService);

  readonly meta = FLUJO_SEMANAL_DETALLE_MOCK;
  filas: FlujoSemanaDetalleFila[] = [];
  semanaSeleccionada = 'S3';

  ngOnInit(): void {
    this.filas = this.meta.filas.map((f) => ({ ...f }));
  }

  seleccionarSemana(id: string): void {
    this.semanaSeleccionada = id;
  }

  desgloseSemana(): FlujoSemanaDesglose | null {
    const fila = this.filas.find((f) => f.id === this.semanaSeleccionada);
    if (!fila) {
      return null;
    }
    const mock = FLUJO_SEMANA_DESGLOSE_MOCK[fila.id];
    if (mock) {
      return {
        ...mock,
        neto: fila.neto,
        entradas: mock.entradas.map((e, i) =>
          i === 0 ? { ...e, monto: fila.deContado } : e,
        ),
      };
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
    const pct = this.meta.pctContado / 100;
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
