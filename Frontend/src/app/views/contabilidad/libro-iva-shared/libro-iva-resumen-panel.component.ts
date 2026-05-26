import { Component, Input, ChangeDetectionStrategy } from '@angular/core';
import { CommonModule } from '@angular/common';
import {
  pagoCuentaIvaResumenLibroIva,
  resumenIvaLibroIva,
  resumenPeriodoSinMovimientosLibroIva,
  resumenTotalesLibroIva,
  sumaVentasDesgloseLibroIva,
  ventasPorImpuestoResumenLibroIva,
} from './libro-iva-resumen.util';

@Component({
  selector: 'app-libro-iva-resumen-panel',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './libro-iva-resumen-panel.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class LibroIvaResumenPanelComponent {
  @Input() fiscalResumen: unknown = null;
  @Input() loading = false;

  get sinMovimientos(): boolean {
    return resumenPeriodoSinMovimientosLibroIva(this.fiscalResumen);
  }

  get totales(): { ventas: number; compras: number; gastos: number } {
    return resumenTotalesLibroIva(this.fiscalResumen);
  }

  get ventasPorImpuesto(): { tarifa: string; etiqueta: string; base: number; iva: number }[] {
    return ventasPorImpuestoResumenLibroIva(this.fiscalResumen);
  }

  get sumaDesglose(): number {
    return sumaVentasDesgloseLibroIva(this.ventasPorImpuesto);
  }

  get desgloseCuadra(): boolean {
    return Math.abs(this.sumaDesglose - this.totales.ventas) < 0.02;
  }

  get iva() {
    return resumenIvaLibroIva(this.fiscalResumen);
  }

  get pagoCuenta() {
    return pagoCuentaIvaResumenLibroIva(this.fiscalResumen);
  }
}
