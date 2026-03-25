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

  /** Si es true, al cargar se muestran año/mes; si false, los atajos. */
  @Input() iniciarEnModoAvanzado = false;

  modoAvanzado = false;

  aniosDisponibles: number[] = [];

  ngOnInit(): void {
    this.modoAvanzado = this.iniciarEnModoAvanzado;
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

  /** Atajo que coincide con anio/mes actuales, o null si es una combinación manual. */
  get presetActivo(): 'este-mes' | 'mes-anterior' | 'este-anio' | 'anio-pasado' | null {
    const d = new Date();
    const yActual = d.getFullYear();
    const mActual = d.getMonth() + 1;
    const anioN = Number(this.anio);
    const mesStr = String(this.mes || '').trim();

    if (anioN === yActual && mesStr === String(mActual)) {
      return 'este-mes';
    }

    const ant = this.referenciaMesAnterior();
    if (anioN === ant.anio && mesStr === String(ant.mes)) {
      return 'mes-anterior';
    }

    if (anioN === yActual && !mesStr) {
      return 'este-anio';
    }

    if (anioN === yActual - 1 && !mesStr) {
      return 'anio-pasado';
    }

    return null;
  }

  onAnioChange(value: string): void {
    this.anioChange.emit(value);
    this.filtroChange.emit();
  }

  onMesChange(value: string): void {
    this.mesChange.emit(value);
    this.filtroChange.emit();
  }

  presetEsteMes(): void {
    const d = new Date();
    this.emitAnioMes(String(d.getFullYear()), String(d.getMonth() + 1));
  }

  presetMesAnterior(): void {
    const { anio, mes } = this.referenciaMesAnterior();
    this.emitAnioMes(String(anio), String(mes));
  }

  presetEsteAnio(): void {
    this.emitAnioMes(String(new Date().getFullYear()), '');
  }

  presetAnioPasado(): void {
    this.emitAnioMes(String(new Date().getFullYear() - 1), '');
  }

  alternarModoAvanzado(): void {
    this.modoAvanzado = !this.modoAvanzado;
  }

  trackByAnio(_index: number, year: number): number {
    return year;
  }

  private referenciaMesAnterior(): { anio: number; mes: number } {
    const d = new Date();
    const ref = new Date(d.getFullYear(), d.getMonth(), 1);
    ref.setMonth(ref.getMonth() - 1);
    return { anio: ref.getFullYear(), mes: ref.getMonth() + 1 };
  }

  private emitAnioMes(anioVal: string, mesVal: string): void {
    this.anioChange.emit(anioVal);
    this.mesChange.emit(mesVal);
    this.filtroChange.emit();
  }
}
