import { Component, EventEmitter, Input, Output } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ApiService } from '@services/api.service';

@Component({
  selector: 'app-pos-linea-venta-sheet',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './pos-linea-venta-sheet.component.html',
  styleUrls: ['./pos-linea-venta-sheet.component.css'],
})
export class PosLineaVentaSheetComponent {
  @Input() detalle: any = null;
  @Input() apiService!: ApiService;
  @Input() mostrarTipoGravado = false;
  @Input() mostrarLote = false;
  @Output() guardar = new EventEmitter<void>();
  @Output() elegirLote = new EventEmitter<void>();
  @Output() cerrar = new EventEmitter<void>();
}
