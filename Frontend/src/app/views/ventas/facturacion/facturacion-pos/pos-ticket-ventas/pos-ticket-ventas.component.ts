import { Component, EventEmitter, Input, Output } from '@angular/core';
import { CommonModule, DecimalPipe } from '@angular/common';
import { TranslatePipe } from '@ngx-translate/core';
import { CurrencyPipe } from '@pipes/currency-format.pipe';

@Component({
  selector: 'app-pos-ticket-ventas',
  standalone: true,
  imports: [CommonModule, CurrencyPipe, DecimalPipe, TranslatePipe],
  templateUrl: './pos-ticket-ventas.component.html',
  styleUrls: ['./pos-ticket-ventas.component.css'],
})
export class PosTicketVentasComponent {
  @Input() venta: any = {};
  @Input() descuentoPuntos = 0;
  @Input() puntosCanjeados = 0;
  @Input() tieneAccesoPropina = false;
  @Input() tieneMultimoneda = false;
  @Input() monedaVenta = '';
  @Input() usdEquivalentTotal: number | null = null;
  @Input() totalConPropina = 0;
  @Input() formatNumber: (v: number) => string = (v) => String(v);
  @Input() esLineaConLote: (detalle: any) => boolean = () => false;

  @Output() cantidadDelta = new EventEmitter<{ detalle: any; delta: number }>();
  @Output() eliminarLinea = new EventEmitter<any>();
  @Output() editarLinea = new EventEmitter<any>();
  @Output() editarLote = new EventEmitter<any>();

  unitPrecioConIva(detalle: any): number {
    return parseFloat(detalle?.precio_iva) || 0;
  }

  unitPrecioSinIva(detalle: any): number {
    return parseFloat(detalle?.precio) || 0;
  }

  lineTotalConIva(detalle: any): number {
    const v = detalle?.total_iva != null ? detalle.total_iva : detalle?.total;
    return parseFloat(v) || 0;
  }

  mostrarSinIva(detalle: any): boolean {
    return !!this.venta?.cobrar_impuestos && this.unitPrecioSinIva(detalle) > 0;
  }

  cantidadLinea(detalle: any): number {
    return parseFloat(detalle?.cantidad) || 0;
  }
}
