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

  @Output() selectionChange = new EventEmitter<DropdownMultiFiltroSelection>();

  panelAbierto = false;

  @ViewChild('wrap', { read: ElementRef })
  private wrap?: ElementRef<HTMLElement>;

  constructor(private cdr: ChangeDetectorRef) {}

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

  get seleccionarTodoChecked(): boolean {
    return this.todasImplicitas && this.items.length > 0;
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
    this.panelAbierto = false;
    this.cdr.markForCheck();
  }

  togglePanel(ev: MouseEvent): void {
    ev.stopPropagation();
    if (this.disabled) {
      return;
    }
    this.panelAbierto = !this.panelAbierto;
    this.cdr.markForCheck();
  }

  onSeleccionarTodoChange(ev: Event): void {
    const cb = ev.target as HTMLInputElement;
    if (cb.checked) {
      this.emit({ todasImplicitas: true, seleccionados: [] });
    } else {
      this.emit({ todasImplicitas: false, seleccionados: [] });
    }
  }

  onItemChange(id: string, checked: boolean): void {
    const allIds = this.items.map(s => s.id);

    if (this.todasImplicitas) {
      if (!checked) {
        const seleccionados = allIds.filter(x => x !== id);
        this.emit({ todasImplicitas: false, seleccionados });
      }
      return;
    }

    let sel = [...this.seleccionados];

    if (sel.length === 0) {
      if (checked) {
        sel = [id];
      } else {
        return;
      }
    } else {
      if (checked) {
        if (!sel.includes(id)) {
          sel.push(id);
        }
        if (sel.length === allIds.length) {
          this.emit({ todasImplicitas: true, seleccionados: [] });
          return;
        }
      } else {
        sel = sel.filter(x => x !== id);
      }
    }

    this.emit({ todasImplicitas: false, seleccionados: sel });
  }

  private emit(next: DropdownMultiFiltroSelection): void {
    this.selectionChange.emit(next);
    this.cdr.markForCheck();
  }

  trackById(_i: number, item: DropdownMultiFiltroItem): string {
    return item.id;
  }
}
