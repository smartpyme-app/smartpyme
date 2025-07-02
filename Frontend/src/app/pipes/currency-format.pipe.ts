import { Pipe, PipeTransform } from '@angular/core';
import { ApiService } from '@services/api.service';

@Pipe({
  name: 'currency'
})
export class CurrencyPipe implements PipeTransform {

    public currencyCode:any = 'USD';

    constructor(private apiService: ApiService) { }
    transform(value: number): string {
     
      if (value === null || value === undefined) {
        value = 0;
      }
    
      
      const empresa = this.apiService.auth_user()?.empresa;
      this.currencyCode = empresa?.moneda || 'USD';
      
      
      const currencySymbol = empresa?.currency?.currency_symbol;
    
   
      const options: Intl.NumberFormatOptions = {
        style: 'currency',
        currency: this.currencyCode,
      };
    
      
      if (currencySymbol) {
        options.style = 'decimal';
        options.minimumFractionDigits = 2;
        options.maximumFractionDigits = 2;
      }
    
    
      let formattedValue = new Intl.NumberFormat('en-US', options).format(Math.abs(value));
    
     
      if (currencySymbol) {
        formattedValue = `${currencySymbol}${formattedValue}`;
      }
    
      
      if (value < 0) {
        return `(${formattedValue})`;
      }
    
      return formattedValue;
    }

}
