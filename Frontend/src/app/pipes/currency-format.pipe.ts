import { Pipe, PipeTransform } from '@angular/core';
import { ApiService } from '@services/api.service';
import { formatEmpresaCurrency } from '../helpers/currency-format.helper';

@Pipe({
  name: 'currency'
})
export class CurrencyPipe implements PipeTransform {
  constructor(private apiService: ApiService) { }

  transform(value: number): string {
    return formatEmpresaCurrency(value, this.apiService.auth_user()?.empresa);
  }
}
