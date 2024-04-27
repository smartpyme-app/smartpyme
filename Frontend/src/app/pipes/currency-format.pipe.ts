import { Pipe, PipeTransform } from '@angular/core';
import { ApiService } from '@services/api.service';

@Pipe({
  name: 'currency'
})
export class CurrencyPipe implements PipeTransform {

  public currencyCode:any = 'USD';

  constructor(private apiService: ApiService) { }

  transform(value: number): string {
    if(value){
    }else{
        value = 0;
    }
        this.currencyCode = this.apiService.auth_user() ? this.apiService.auth_user().empresa.moneda : 'USD';

        const formattedValue = new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: this.currencyCode,
        }).format(value);

        return formattedValue;
  }
}
