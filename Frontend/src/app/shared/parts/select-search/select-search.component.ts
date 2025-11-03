import { Component, Input, Output, EventEmitter, forwardRef, OnInit, OnDestroy, OnChanges } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { ControlValueAccessor, NG_VALUE_ACCESSOR } from '@angular/forms';
import { NgSelectModule } from '@ng-select/ng-select';
import { Subject, Observable, of } from 'rxjs';
import { debounceTime, distinctUntilChanged, switchMap, catchError } from 'rxjs/operators';

@Component({
    selector: 'app-select-search',
    templateUrl: './select-search.component.html',
    styleUrls: ['./select-search.component.css'],
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, NgSelectModule],
    providers: [
        {
            provide: NG_VALUE_ACCESSOR,
            useExisting: forwardRef(() => SelectSearchComponent),
            multi: true
        }
    ],
    
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
  
  // Getter para los items procesados que usa ng-select
  get processedItems() {
    return this.filteredItems.map(item => ({
      value: this.getItemValue(item),
      display: this.getDisplayText(item),
      original: item
    }));
  }
  
  private onChange = (value: any) => {};
  private onTouched = () => {};

  ngOnInit() {
    this.searchTerm$.pipe(
      debounceTime(300),
      distinctUntilChanged(),
      switchMap(term => {
        if (this.searchFunction && term && term.length > 0) {
          this.isLoading = true;
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
      this.isLoading = false;
    });

    // Inicializar: si hay searchFunction, empezar vacío; si no, mostrar todos
    this.filteredItems = this.searchFunction ? [] : (this.items || []);
    
    // Si tenemos un valor inicial pero no está en los items filtrados, 
    // buscar el item correspondiente en los items originales
    if (this.value && this.items && this.items.length > 0) {
      const selectedItem = this.items.find(item => this.getItemValue(item) === this.value);
      if (selectedItem && !this.filteredItems.find(item => this.getItemValue(item) === this.value)) {
        this.filteredItems.push(selectedItem);
      }
    }
  }

  ngOnChanges() {
    // Actualizar items filtrados cuando cambien los items de entrada (solo si no hay searchFunction)
    if (this.items && !this.searchFunction) {
      this.filteredItems = this.items;
    }
    
    // Si tenemos un valor pero no está en los items filtrados, agregarlo
    if (this.value && this.items && this.items.length > 0) {
      const selectedItem = this.items.find(item => this.getItemValue(item) === this.value);
      if (selectedItem && !this.filteredItems.find(item => this.getItemValue(item) === this.value)) {
        this.filteredItems.push(selectedItem);
      }
    }
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
      this.getItemValue(item) === selectedValue
    );
    this.selectionChange.emit(selectedItem);
  }

  private filterLocal(term: string): any[] {
    if (!term) return this.items;
    
    return this.items.filter(item => {
      const displayText = this.getDisplayText(item).toLowerCase();
      return displayText.includes(term.toLowerCase());
    });
  }

  getDisplayText(item: any): string {
    if (this.customDisplayFunction) {
      return this.customDisplayFunction(item);
    }
    return item[this.displayProperty] || '';
  }

  getItemValue(item: any): any {
    return item[this.valueProperty];
  }

  // ControlValueAccessor implementation
  writeValue(value: any): void {
    this.value = value;
    
    // Si tenemos un valor pero no está en los items filtrados, buscar el item correspondiente
    if (value && this.items && this.items.length > 0) {
      const selectedItem = this.items.find(item => this.getItemValue(item) === value);
      if (selectedItem && !this.filteredItems.find(item => this.getItemValue(item) === value)) {
        this.filteredItems.push(selectedItem);
      }
    }
  }

  registerOnChange(fn: any): void {
    this.onChange = fn;
  }

  registerOnTouched(fn: any): void {
    this.onTouched = fn;
  }

  setDisabledState(isDisabled: boolean): void {
    this.disabled = isDisabled;
  }
}