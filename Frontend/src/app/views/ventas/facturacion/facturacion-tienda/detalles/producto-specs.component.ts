import { Component, Input, Output, EventEmitter } from '@angular/core';

@Component({
  selector: 'app-producto-specs',
  template: `
    <ng-container [ngSwitch]="field.field_type">
      <select *ngSwitchCase="'select'" 
              class="form-select form-select-sm"
              [(ngModel)]="selectedValue"
              (change)="specChanged()">
        <option value="">Seleccionar</option>
        <option *ngFor="let value of field.values" [ngValue]="value">
          {{value.value}}
        </option>
      </select>

      <input *ngSwitchCase="'text'"
             type="text" 
             class="form-control form-control-sm"
             [(ngModel)]="inputValue"
             (change)="specChanged()">

      <input *ngSwitchCase="'number'"
             type="number"
             class="form-control form-control-sm" 
             [(ngModel)]="inputValue"
             (change)="specChanged()">
    </ng-container>
  `
})
export class ProductoSpecsComponent {
  @Input() producto: any;
  @Input() field: any;
  @Output() onChange = new EventEmitter();

  selectedValue: any;
  inputValue: string | number = '';

  ngOnInit() {
    if(!this.producto.custom_fields) {
      this.producto.custom_fields = [];
    }
  }

  specChanged() {
    const customField = {
      id: this.field.id,
      value: this.field.field_type === 'select' ? this.selectedValue.value : this.inputValue,
      id_value: this.field.field_type === 'select' ? this.selectedValue.id : null
    };

    const index = this.producto.custom_fields.findIndex((cf: any) => cf.id === this.field.id);
    if (index >= 0) {
      this.producto.custom_fields[index] = customField;
    } else {
      this.producto.custom_fields.push(customField);
    }

    this.onChange.emit();
  }
}