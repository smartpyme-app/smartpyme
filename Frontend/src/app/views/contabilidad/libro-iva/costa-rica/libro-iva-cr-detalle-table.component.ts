import { Component, Input, ChangeDetectionStrategy } from '@angular/core';
import { CommonModule } from '@angular/common';

/** Libro detalle IVA Costa Rica (plantillas Reporte_Detalle_IVA / Compras). Solo presentación. */
export type LibroIvaCrDetalleLibroTipo = 'ventas' | 'compras';

@Component({
  selector: 'app-libro-iva-cr-detalle-table',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './libro-iva-cr-detalle-table.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class LibroIvaCrDetalleTableComponent {
  @Input({ required: true }) libroTipo!: LibroIvaCrDetalleLibroTipo;
  @Input() filas: Record<string, unknown>[] = [];
  @Input() totales: Record<string, number> | null = null;
  @Input() loading = false;

  esVentas(): boolean {
    return this.libroTipo === 'ventas';
  }

  /** Columnas totales de la tabla (29 compras, 30 ventas por ExoPorc). */
  colCount(): number {
    return this.esVentas() ? 30 : 29;
  }

  /** Columnas unidas para la etiqueta TOTALES antes de Retenciones. */
  colspanLabelTotales(): number {
    return this.esVentas() ? 10 : 9;
  }

  total(k: string): number {
    const v = Number(this.totales?.[k]);
    return Number.isFinite(v) ? v : 0;
  }
}
