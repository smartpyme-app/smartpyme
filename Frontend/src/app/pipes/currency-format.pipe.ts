import { Pipe, PipeTransform } from '@angular/core';
import { ApiService } from '@services/api.service';

@Pipe({
  name: 'currency'
})
export class CurrencyPipe implements PipeTransform {

    public currencyCode:any = 'USD';

    constructor(private apiService: ApiService) { }

    // transform(value: number): string {
    //     // Si el valor es nulo o indefinido, se asigna 0.
    //     if (value === null || value === undefined) {
    //       value = 0;
    //     }

    //     // Obtener el código de moneda según el usuario autenticado
    //     this.currencyCode = this.apiService.auth_user()
    //       ? this.apiService.auth_user().empresa.moneda
    //       : 'USD'; // Default a USD si no hay usuario autenticado.

    //     // Si el valor es negativo, formatear y ponerlo entre paréntesis
    //     if (value < 0) {
    //       return `(${new Intl.NumberFormat('en-US', {
    //         style: 'currency',
    //         currency: this.currencyCode,
    //       }).format(Math.abs(value))})`;
    //     }

    //     // Formatear el valor como moneda usando Intl.NumberFormat
    //     const formattedValue = new Intl.NumberFormat('en-US', {
    //       style: 'currency',
    //       currency: this.currencyCode,
    //     }).format(value);

    //     // Devolver el valor formateado
    //     return formattedValue;
    // }
    transform(value: number): string {
      // Si el valor es nulo o indefinido, se asigna 0.
      if (value === null || value === undefined) {
        value = 0;
      }
    
      // Obtener datos de moneda desde el usuario autenticado
      const empresa = this.apiService.auth_user()?.empresa;
      this.currencyCode = empresa?.moneda || 'USD';
      
      // Si tenemos acceso al objeto currency con el símbolo, lo usamos
      const currencySymbol = empresa?.currency?.currency_symbol;
    
      // Opciones para el formateador
      const options: Intl.NumberFormatOptions = {
        style: 'currency',
        currency: this.currencyCode,
      };
    
      // Si tenemos un símbolo personalizado, usamos 'decimal' y añadimos el símbolo manualmente
      if (currencySymbol) {
        options.style = 'decimal';
        options.minimumFractionDigits = 2;
        options.maximumFractionDigits = 2;
      }
    
      // Formatear el valor
      let formattedValue = new Intl.NumberFormat('en-US', options).format(Math.abs(value));
    
      // Añadir el símbolo personalizado si está disponible
      if (currencySymbol) {
        formattedValue = `${currencySymbol} ${formattedValue}`;
      }
    
      // Para valores negativos
      if (value < 0) {
        return `(${formattedValue})`;
      }
    
      return formattedValue;
    }

}
