import { ChangeDetectionStrategy, Component, EventEmitter, Input, OnChanges, Output, SimpleChanges } from '@angular/core';

@Component({
  standalone: false,
  selector: 'app-pos-sheet-agregar',
  templateUrl: './pos-sheet-agregar.component.html',
  styleUrls: ['./pos-sheet-agregar.component.css'],
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class PosSheetAgregarComponent implements OnChanges {
  @Input() producto: any = null;
  @Input() visible = false;
  @Input() enviando = false;

  @Output() confirmar = new EventEmitter<{ producto_id: number; cantidad: number; notas: string }>();
  @Output() cancelar = new EventEmitter<void>();

  cantidad = 1;
  notas = '';

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['visible'] && this.visible) {
      this.cantidad = 1;
      this.notas = '';
    }
  }

  incrementar(): void {
    this.cantidad = this.roundQty(this.cantidad + 1);
  }

  decrementar(): void {
    this.cantidad = Math.max(0.01, this.roundQty(this.cantidad - 1));
  }

  onCantidadBlur(): void {
    const v = Number(this.cantidad);
    this.cantidad = Number.isFinite(v) && v >= 0.01 ? this.roundQty(v) : 0.01;
  }

  onConfirmar(): void {
    if (this.enviando) {
      return;
    }
    if (!this.producto?.id || !Number.isFinite(this.cantidad) || this.cantidad < 0.01) {
      return;
    }
    this.confirmar.emit({
      producto_id: this.producto.id,
      cantidad: this.cantidad,
      notas: this.notas.trim()
    });
  }

  onCancelar(): void {
    if (this.enviando) {
      return;
    }
    this.cancelar.emit();
  }

  private roundQty(x: number): number {
    return Math.round(x * 100) / 100;
  }
}
