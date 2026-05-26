import { Component, EventEmitter, Input, Output } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';

@Component({
  selector: 'app-libro-iva-periodo-filtros',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './libro-iva-periodo-filtros.component.html',
})
export class LibroIvaPeriodoFiltrosComponent {
  @Input({ required: true }) filtros: any;
  @Input() years: number[] = [];
  @Input() sucursales: any[] = [];
  @Output() periodoChange = new EventEmitter<void>();
}
