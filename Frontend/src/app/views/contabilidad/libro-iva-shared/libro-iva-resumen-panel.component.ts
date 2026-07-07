import { Component, Input, ChangeDetectionStrategy } from '@angular/core';
import { CommonModule } from '@angular/common';
import {
  pagoCuentaIvaResumenLibroIva,
  resumenIvaLibroIva,
  resumenPeriodoSinMovimientosLibroIva,
  resumenTotalesLibroIva,
  comprasPorImpuestoResumenLibroIva,
  sumaBaseDesgloseLibroIva,
  sumaComprasDesgloseLibroIva,
  sumaImpuestoDesgloseLibroIva,
  sumaVentasDesgloseLibroIva,
  totalFilaDesgloseLibroIva,
  ventasPorImpuestoResumenLibroIva,
  ventasResumenContableLibroIva,
  mostrarVentasResumenContableLibroIva,
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

  readonly totalFilaDesglose = totalFilaDesgloseLibroIva;

  get sinMovimientos(): boolean {
    return resumenPeriodoSinMovimientosLibroIva(this.fiscalResumen);
  }

  get totales(): { ventas: number; compras: number; compras_sin_devoluciones: number; gastos: number } {
    return resumenTotalesLibroIva(this.fiscalResumen);
  }

  get ventasPorImpuesto(): { tarifa: string; etiqueta: string; base: number; iva: number }[] {
    return ventasPorImpuestoResumenLibroIva(this.fiscalResumen);
  }

  get sumaBaseVentas(): number {
    return sumaBaseDesgloseLibroIva(this.ventasPorImpuesto);
  }

  get sumaImpuestoVentas(): number {
    return sumaImpuestoDesgloseLibroIva(this.ventasPorImpuesto);
  }

  get sumaDesglose(): number {
    return sumaVentasDesgloseLibroIva(this.ventasPorImpuesto);
  }

  get comprasPorImpuesto(): { tarifa: string; etiqueta: string; base: number; iva: number }[] {
    return comprasPorImpuestoResumenLibroIva(this.fiscalResumen);
  }

  get sumaBaseCompras(): number {
    return sumaBaseDesgloseLibroIva(this.comprasPorImpuesto);
  }

  get sumaImpuestoCompras(): number {
    return sumaImpuestoDesgloseLibroIva(this.comprasPorImpuesto);
  }

  get sumaComprasDesglose(): number {
    return sumaComprasDesgloseLibroIva(this.comprasPorImpuesto);
  }

  get iva() {
    return resumenIvaLibroIva(this.fiscalResumen);
  }

  get pagoCuenta() {
    return pagoCuentaIvaResumenLibroIva(this.fiscalResumen);
  }

  get ventasResumenContable() {
    return ventasResumenContableLibroIva(this.fiscalResumen);
  }

  get mostrarVentasResumenContable(): boolean {
    return mostrarVentasResumenContableLibroIva(this.fiscalResumen);
  }
}
