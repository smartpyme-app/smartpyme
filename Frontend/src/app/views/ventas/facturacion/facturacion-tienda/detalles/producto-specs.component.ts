import { Component, Input, Output, EventEmitter, OnInit } from '@angular/core';

@Component({
  selector: 'app-producto-specs',
  template: `
    <ng-container [ngSwitch]="field.field_type">
      <select *ngSwitchCase="'select'"
              class="form-select form-select-sm"
              [(ngModel)]="selectedValue"
              (change)="specChanged()">
        <option value="">Seleccionar</option>
        <option *ngFor="let value of field.values" 
                [ngValue]="value">
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
export class ProductoSpecsComponent implements OnInit {
  @Input() producto: any;
  @Input() field: any;
  @Output() onChange = new EventEmitter();

  selectedValue: any = null;
  inputValue: string | number = '';

  ngOnInit() {
    //console.log('producto', this.producto);
    if (!this.producto.custom_fields) {
      this.producto.custom_fields = [];
    }


    const existingField = this.producto.custom_fields.find(
      (cf: any) => cf.custom_field?.id === this.field.id
    );

    console.log('existingField', existingField);

    if (existingField) {
      if (this.field.field_type === 'select') {

        this.selectedValue = this.field.values?.find(
          (v: any) => v.id === existingField.custom_field_value_id
        );
      } else {
        this.inputValue = existingField.value || '';
      }
    }
  }

  specChanged() {
    const customField = {

      id: this.findExistingFieldId(),

      custom_field_id: this.field.id,
      cotizacion_venta_detalle_id: this.producto.id,
      value: this.field.field_type === 'select' ? this.selectedValue?.value : this.inputValue,
      custom_field_value_id: this.field.field_type === 'select' ? this.selectedValue?.id : null,

      custom_field: this.field,
      custom_field_value: this.selectedValue
    };

    const index = this.producto.custom_fields.findIndex(
      (cf: any) => cf.custom_field?.id === this.field.id
    );

    if (index >= 0) {
      this.producto.custom_fields[index] = customField;
    } else {
      this.producto.custom_fields.push(customField);
    }

    this.onChange.emit();
  }

  private findExistingFieldId(): number | null {
    const existingField = this.producto.custom_fields.find(
      (cf: any) => cf.custom_field?.id === this.field.id
    );
    return existingField ? existingField.id : null;
  }
}