import { Component, EventEmitter, Input, OnInit, Output } from '@angular/core';

@Component({
  selector: 'app-filtro-fecha',
  templateUrl: './filtro-fecha.component.html',
  styleUrls: ['./filtro-fecha.component.css']
})
export class FiltroFechaComponent implements OnInit {
  /** Valor del año (string, p. ej. año actual). Vacío = opción "Seleccionar". */
  @Input() anio = '';
  @Output() anioChange = new EventEmitter<string>();

  /** Mes 1–12 o vacío = "Todos". */
  @Input() mes = '';
  @Output() mesChange = new EventEmitter<string>();

  /** Se emite tras cambiar año o mes (para recargar datos en el padre). */
  @Output() filtroChange = new EventEmitter<void>();

  /** Prefijo único para `id`/`for` cuando hay varias instancias en la misma vista. */
  @Input() controlId = 'filtro-fecha';

  /** Primer año incluido en el listado (inclusive). */
  @Input() anioMin = 2023;

  /** Clases del label (Bootstrap u otras). */
  @Input() labelClass = 'text-primary fw-medium small mb-1';

  /** Color CSS opcional de los labels (p. ej. `#F19447` para variantes por sección). */
  @Input() labelColor = '';

  aniosDisponibles: number[] = [];

  ngOnInit(): void {
    const anioActual = new Date().getFullYear();
    for (let a = this.anioMin; a <= anioActual; a++) {
      this.aniosDisponibles.push(a);
    }
  }

  get idAnio(): string {
    return `${this.controlId}-anio`;
  }

  get idMes(): string {
    return `${this.controlId}-mes`;
  }

  onAnioChange(value: string): void {
    this.anioChange.emit(value);
    this.filtroChange.emit();
  }

  onMesChange(value: string): void {
    this.mesChange.emit(value);
    this.filtroChange.emit();
  }

  trackByAnio(_index: number, year: number): number {
    return year;
  }
}
