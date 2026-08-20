import { Component, EventEmitter, Input, Output } from '@angular/core';
import { CommonModule } from '@angular/common';
import { CurrencyPipe } from '@pipes/currency-format.pipe';
import { DecimalPipe } from '@angular/common';

@Component({
  selector: 'app-pos-ticket-ventas',
  standalone: true,
  imports: [CommonModule, CurrencyPipe, DecimalPipe],
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
}
