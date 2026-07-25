import { Pipe, PipeTransform } from '@angular/core';
import { ApiService } from '@services/api.service';
import { formatEmpresaCurrency } from '../helpers/currency-format.helper';

/**
 * Formatea con la moneda de la empresa (no el CurrencyPipe de Angular).
 * En componentes standalone: importar DESPUÉS de CommonModule, o el pipe USD de Angular pisa este.
 */
@Pipe({
    name: 'currency',
    standalone: true
})
export class CurrencyPipe implements PipeTransform {
  constructor(private apiService: ApiService) { }

  transform(value: number): string {
    return formatEmpresaCurrency(value, this.apiService.auth_user()?.empresa);
  }
}
