import { Pipe, PipeTransform } from '@angular/core';
import { ApiService } from '@services/api.service';

@Pipe({
  name: 'currency'
})
export class CurrencyPipe implements PipeTransform {

  public currencyCode:any = 'USD';

  constructor(private apiService: ApiService) { }

  transform(value: number): string {
    
    this.currencyCode = this.apiService.auth_user().empresa.moneda;

    const formattedValue = new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: this.currencyCode,
    }).format(value);

    return formattedValue;
  }
}
