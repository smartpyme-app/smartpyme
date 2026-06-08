import {
  ChangeDetectionStrategy,
  ChangeDetectorRef,
  Component,
  ElementRef,
  EventEmitter,
  HostListener,
  Input,
  Output,
  ViewChild
} from '@angular/core';
import { DomSanitizer, SafeHtml } from '@angular/platform-browser';

export interface DropdownMultiFiltroItem {
  id: string;
  nombre: string;
}

export interface DropdownMultiFiltroSelection {
  todasImplicitas: boolean;
  seleccionados: string[];
}

@Component({
  selector: 'app-dropdown-multi-filtro',
  templateUrl: './dropdown-multi-filtro.component.html',
  styleUrls: ['./dropdown-multi-filtro.component.css'],
  changeDetection: ChangeDetectionStrategy.OnPush
})
export class DropdownMultiFiltroComponent {
  /** Texto encima del disparador (ej. Sucursales, Estados). */
  @Input() label = '';

  /** Opciones con id y nombre visible. */
  @Input() items: DropdownMultiFiltroItem[] = [];

  /** Deshabilita el control (ej. usuario con una sola sucursal). */
  @Input() disabled = false;

  /**
   * true: “todas” implícitas; los checkboxes se muestran todos marcados.
   * false + []: usuario desmarcó “Seleccionar todo”.
   */
  @Input() todasImplicitas = true;

  /** IDs seleccionados cuando no está en modo todas implícitas. */
  @Input() seleccionados: string[] = [];

  /** Para el resumen “N sucursales” / “N estados”. Por defecto: label en minúsculas. */
  @Input() pluralLabel = '';

  /** Texto cuando hay ítems pero ninguno elegido (modo explícito). Por defecto: “Elige …”. */
  @Input() elegirLabel = '';

  /** Placeholder del input de búsqueda. */
  @Input() buscadorPlaceholder = 'Buscar';

  /**
   * Con `mostrarBuscador`, sin texto de búsqueda solo se listan los primeros N ítems.
   * Al escribir se busca en **toda** la lista. 0 = sin límite (comportamiento anterior).
   */
  @Input() limiteVistaInicial = 0;

  /** Si se informa, sustituye el texto por defecto bajo el buscador cuando aplica límite. */
  @Input() textoAyudaLimite = '';

  /**
   * Color de acento (hex o CSS). Vacío = estilo por defecto (primary del tema).
   * Útil para alinear filtros con la paleta de una sección (ej. gastos #F19447).
   */
  @Input() accentColor = '';

  @Output() selectionChange = new EventEmitter<DropdownMultiFiltroSelection>();

  panelAbierto = false;

  /** Texto del buscador; se limpia al cerrar el panel. */
  buscadorTexto = '';

  @ViewChild('wrap', { read: ElementRef })
  private wrap?: ElementRef<HTMLElement>;

  /** Referencia al input de autocomplete para enfocar al abrir. */
  @ViewChild('autocompleteInput')
  private autocompleteInput?: ElementRef<HTMLInputElement>;

  constructor(private cdr: ChangeDetectorRef, private sanitizer: DomSanitizer) {}

  private get palabraPlural(): string {
    const p = this.pluralLabel?.trim();
    if (p) {
      return p;
    }
    return (this.label || 'opciones').toLowerCase();
  }

  private get textoElegir(): string {
    const e = this.elegirLabel?.trim();
    if (e) {
      return e;
    }
    return `Elige ${this.palabraPlural}`;
  }

  get etiquetaResumen(): string {
    if (this.disabled && this.items.length === 1) {
      return this.items[0].nombre;
    }
    const n = this.items.length;
    const sel = this.seleccionados;
    if (n === 0) {
      return this.label || this.palabraPlural;
    }
    if (this.todasImplicitas) {
      return 'Todas';
    }
    if (sel.length === 0) {
      return this.textoElegir;
    }
    if (sel.length === 1) {
      const item = this.items.find(s => s.id === sel[0]);
      return item?.nombre ?? sel[0];
    }
    return `${sel.length} ${this.palabraPlural}`;
  }

  get hayBusquedaActiva(): boolean {
    return this.buscadorTexto.trim().length > 0;
  }

  /** La búsqueda siempre está activa (el input es el disparador). */
  get mostrarBuscador(): boolean {
    return true;
  }

  /** Vista acotada: búsqueda activa o lista inicial limitada. */
  get usaAlcanceParcialEnPanel(): boolean {
    return (
      this.hayBusquedaActiva ||
      (this.mostrarBuscador && this.limiteVistaInicial > 0)
    );
  }

  get mostrarAvisoLimiteVista(): boolean {
    return (
      this.mostrarBuscador &&
      this.limiteVistaInicial > 0 &&
      !this.hayBusquedaActiva &&
      this.items.length > this.limiteVistaInicial
    );
  }

  get textoAyudaLimiteVista(): string {
    const t = this.textoAyudaLimite?.trim();
    if (t) {
      return t;
    }
    return `Mostrando los primeros ${this.limiteVistaInicial}. Busca para encontrar más.`;
  }

  /** Ítems visibles: sin buscador = todos; con buscador sin texto = slice; con texto = filtro en toda la lista. */
  get itemsFiltrados(): DropdownMultiFiltroItem[] {
    if (!this.mostrarBuscador) {
      return this.items;
    }
    const q = this.buscadorTexto.trim().toLowerCase();
    if (q) {
      return this.items.filter((i) => i.nombre.toLowerCase().includes(q));
    }
    if (this.limiteVistaInicial > 0) {
      return this.items.slice(0, this.limiteVistaInicial);
    }
    return this.items;
  }

  get seleccionarTodoChecked(): boolean {
    const scope = this.itemsFiltrados;
    if (scope.length === 0) {
      return false;
    }
    if (this.usaAlcanceParcialEnPanel) {
      return scope.every((i) => this.isItemChecked(i.id));
    }
    return this.todasImplicitas && this.items.length > 0;
  }

  onBusquedaInput(ev: Event): void {
    this.buscadorTexto = (ev.target as HTMLInputElement).value;
    if (!this.panelAbierto) {
      this.panelAbierto = true;
    }
    this.cdr.markForCheck();
  }

  /** Abre el panel al hacer foco en el input. */
  onInputFocus(): void {
    if (this.disabled || this.items.length === 0) return;
    if (!this.panelAbierto) {
      this.panelAbierto = true;
      // Limpia el input para que el placeholder muestre la selección y se pueda escribir libremente
      this.buscadorTexto = '';
      this.cdr.markForCheck();
    }
  }

  /** Cierra el panel con Escape, confirma con Enter. */
  onInputKeydown(ev: KeyboardEvent): void {
    if (ev.key === 'Escape') {
      this.cerrarPanel();
    }
  }

  /** Limpia el texto de búsqueda desde el botón ✕. */
  limpiarBuscador(ev: MouseEvent): void {
    ev.preventDefault(); // evita que el blur cierre el panel antes de limpiar
    this.buscadorTexto = '';
    this.autocompleteInput?.nativeElement.focus();
    this.cdr.markForCheck();
  }

  /**
   * Resalta en negrita la parte del nombre que coincide con la búsqueda.
   * Devuelve HTML seguro.
   */
  resaltarCoincidencia(nombre: string, query: string): SafeHtml {
    if (!query) return this.sanitizer.bypassSecurityTrustHtml(nombre);
    const idx = nombre.toLowerCase().indexOf(query.toLowerCase());
    if (idx < 0) return this.sanitizer.bypassSecurityTrustHtml(nombre);
    const antes  = nombre.slice(0, idx);
    const match  = nombre.slice(idx, idx + query.length);
    const despues = nombre.slice(idx + query.length);
    return this.sanitizer.bypassSecurityTrustHtml(
      `${antes}<strong class="dmf-highlight">${match}</strong>${despues}`
    );
  }

  isItemChecked(id: string): boolean {
    if (this.todasImplicitas) {
      return true;
    }
    return this.seleccionados.includes(id);
  }

  @HostListener('document:click', ['$event'])
  onDocumentClick(ev: MouseEvent): void {
    if (!this.panelAbierto) {
      return;
    }
    const root = this.wrap?.nativeElement;
    if (root?.contains(ev.target as Node)) {
      return;
    }
    this.cerrarPanel();
  }

  togglePanel(ev: MouseEvent): void {
    ev.stopPropagation();
    if (this.disabled) return;
    if (this.panelAbierto) {
      this.cerrarPanel();
    } else {
      this.panelAbierto = true;
      this.buscadorTexto = '';
      this.cdr.markForCheck();
      setTimeout(() => this.autocompleteInput?.nativeElement.focus(), 0);
    }
  }

  private cerrarPanel(): void {
    this.panelAbierto = false;
    this.buscadorTexto = '';
    this.cdr.markForCheck();
  }

  private idsSeleccionEfectivos(): Set<string> {
    if (this.todasImplicitas) {
      return new Set(this.items.map((s) => s.id));
    }
    return new Set(this.seleccionados);
  }

  private emitDesdeSet(set: Set<string>): void {
    const allIds = this.items.map((s) => s.id);
    const todos =
      allIds.length > 0 && allIds.every((id) => set.has(id));
    if (todos) {
      this.emit({ todasImplicitas: true, seleccionados: [] });
      return;
    }
    this.emit({
      todasImplicitas: false,
      seleccionados: allIds.filter((id) => set.has(id)),
    });
  }

  onSeleccionarTodoChange(ev: Event): void {
    const cb = ev.target as HTMLInputElement;
    if (this.usaAlcanceParcialEnPanel) {
      const idsScope = this.itemsFiltrados.map((i) => i.id);
      if (idsScope.length === 0) {
        return;
      }
      /**
       * Con “todas” implícitas, el conjunto efectivo es la lista completa. Si el usuario
       * desmarca “Seleccionar todo” solo sobre la vista (primeros N o resultado de búsqueda),
       * antes se hacía `all − visible` y quedaban seleccionados casi todos los ítems.
       * Lo correcto es pasar a selección explícita vacía y volver a sumar desde cero.
       */
      if (!cb.checked && this.todasImplicitas) {
        this.emit({ todasImplicitas: false, seleccionados: [] });
        return;
      }
      const set = this.idsSeleccionEfectivos();
      if (cb.checked) {
        idsScope.forEach((id) => set.add(id));
      } else {
        idsScope.forEach((id) => set.delete(id));
      }
      this.emitDesdeSet(set);
      return;
    }
    if (cb.checked) {
      this.emit({ todasImplicitas: true, seleccionados: [] });
    } else {
      this.emit({ todasImplicitas: false, seleccionados: [] });
    }
  }

  /**
   * Un solo camino vía conjunto efectivo + `emitDesdeSet` evita estados raros con buscador
   * (p. ej. `seleccionados` vacío pero distinto del conjunto implícito “todas”).
   */
  onItemChange(id: string, checked: boolean): void {
    const set = this.idsSeleccionEfectivos();
    if (checked) {
      set.add(id);
    } else {
      set.delete(id);
    }
    this.emitDesdeSet(set);
  }

  private emit(next: DropdownMultiFiltroSelection): void {
    this.selectionChange.emit(next);
    this.cdr.markForCheck();
  }

  trackById(_i: number, item: DropdownMultiFiltroItem): string {
    return item.id;
  }
}
