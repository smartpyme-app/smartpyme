import { Component, Input, Output, EventEmitter, forwardRef, OnInit, OnDestroy, OnChanges, ChangeDetectorRef } from '@angular/core';
import { ControlValueAccessor, NG_VALUE_ACCESSOR } from '@angular/forms';
import { Subject, Observable, of } from 'rxjs';
import { debounceTime, distinctUntilChanged, switchMap, catchError } from 'rxjs/operators';

@Component({
  selector: 'app-select-search',
  templateUrl: './select-search.component.html',
  styleUrls: ['./select-search.component.css'],
  providers: [
    {
      provide: NG_VALUE_ACCESSOR,
      useExisting: forwardRef(() => SelectSearchComponent),
      multi: true
    }
  ]
})
export class SelectSearchComponent implements ControlValueAccessor, OnInit, OnDestroy, OnChanges {
  @Input() name: string = '';
  @Input() placeholder: string = 'Buscar registros...';
  @Input() items: any[] = []; // Para datos estáticos
  @Input() displayProperty: string = 'name'; // Propiedad a mostrar
  @Input() valueProperty: string = 'id'; // Propiedad del valor
  @Input() searchFunction?: (term: string) => Observable<any[]>; // Función de búsqueda personalizada
  @Input() customDisplayFunction?: (item: any) => string; // Función personalizada para mostrar
  @Input() loading: boolean = false;
  @Input() clearable: boolean = true;
  @Input() disabled: boolean = false;
  @Input() required: boolean = false;
  @Input() cssClass: string = 'form-select p-0';

  @Output() selectionChange = new EventEmitter<any>();
  @Output() search = new EventEmitter<string>();

  value: any = null;
  filteredItems: any[] = [];
  searchTerm$ = new Subject<string>();
  searchTerm: string = '';
  isLoading = false;

  private onChange = (value: any) => {};
  private onTouched = () => {};

  constructor(private cdr: ChangeDetectorRef) {}

  ngOnInit() {
    this.searchTerm$.pipe(
      debounceTime(300),
      distinctUntilChanged(),
      switchMap(term => {
        if (this.searchFunction && term && term.length > 0) {
          this.isLoading = true;
          this.cdr.markForCheck();
          return this.searchFunction(term).pipe(
            catchError(() => of([]))
          );
        } else if (term && term.length > 0) {
          // Búsqueda local
          return of(this.filterLocal(term));
        } else {
          // Si no hay término, mostrar todos los items (solo para datos estáticos)
          return of(this.searchFunction ? [] : this.items || []);
        }
      })
    ).subscribe(results => {
      this.filteredItems = results;
      this.ensureSelectedOptionVisible();
      this.isLoading = false;
      this.cdr.markForCheck();
    });

    // Inicializar: si hay searchFunction, empezar vacío; si no, mostrar todos
    this.filteredItems = this.searchFunction ? [] : (this.items || []);
    this.ensureSelectedOptionVisible();
  }

  ngOnChanges() {
    if (this.items && !this.searchFunction) {
      this.filteredItems = this.items;
    }
    this.ensureSelectedOptionVisible();
  }

  ngOnDestroy() {
    this.searchTerm$.complete();
  }

  onSearch(term: string) {
    this.searchTerm = term || '';
    this.search.emit(term);
    this.searchTerm$.next(term || '');
  }

  onSelectionChange(selectedValue: any) {
    this.value = selectedValue;
    this.onChange(selectedValue);
    this.onTouched();
    
    // Encontrar el item completo para emitir
    const selectedItem = this.filteredItems.find(item =>
      this.valuesMatch(this.getItemValue(item), selectedValue)
    );
    this.selectionChange.emit(selectedItem);
    this.cdr.markForCheck();
  }

  private filterLocal(term: string): any[] {
    if (!term) return this.items;
    
    return this.items.filter(item => {
      const displayText = this.getDisplayText(item).toLowerCase();
      return displayText.includes(term.toLowerCase());
    });
  }

  getDisplayText(item: any): string {
    if (!item) return '';
    if (this.customDisplayFunction) {
      return this.customDisplayFunction(item);
    }
    return item[this.displayProperty] || '';
  }

  getItemValue(item: any): any {
    if (!item) return null;
    return item[this.valueProperty];
  }

  /** Con búsqueda remota, ng-select necesita la opción en filteredItems para mostrarla seleccionada. */
  private ensureSelectedOptionVisible(): void {
    if (this.value == null || this.value === '') {
      return;
    }
    if (this.filteredItems.some(item => this.valuesMatch(this.getItemValue(item), this.value))) {
      return;
    }
    const pools = [...(this.items || []), ...(this.filteredItems || [])];
    const selectedItem = pools.find(item => this.valuesMatch(this.getItemValue(item), this.value));
    if (selectedItem) {
      this.filteredItems = [...this.filteredItems, selectedItem];
    }
    this.cdr.markForCheck();
  }

  private valuesMatch(a: any, b: any): boolean {
    return a == b;
  }

  // ControlValueAccessor implementation
  writeValue(value: any): void {
    this.value = value;
    this.ensureSelectedOptionVisible();
    this.cdr.markForCheck();
  }

  registerOnChange(fn: any): void {
    this.onChange = fn;
  }

  registerOnTouched(fn: any): void {
    this.onTouched = fn;
  }

  setDisabledState(isDisabled: boolean): void {
    this.disabled = isDisabled;
    this.cdr.markForCheck();
  }
}