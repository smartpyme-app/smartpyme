import { Pipe, PipeTransform } from '@angular/core';
import { ApiService } from '@services/api.service';

@Pipe({
  name: 'currency'
})
export class CurrencyPipe implements PipeTransform {

    public currencyCode:any = 'USD';

    constructor(private apiService: ApiService) { }

    transform(value: number): string {
        // Si el valor es nulo o indefinido, se asigna 0.
        if (value === null || value === undefined) {
          value = 0;
        }

        // Obtener el código de moneda según el usuario autenticado
        this.currencyCode = this.apiService.auth_user()
          ? this.apiService.auth_user().empresa.moneda
          : 'USD'; // Default a USD si no hay usuario autenticado.

        // Si el valor es negativo, formatear y ponerlo entre paréntesis
        if (value < 0) {
          return `(${new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: this.currencyCode,
          }).format(Math.abs(value))})`;
        }

        // Formatear el valor como moneda usando Intl.NumberFormat
        const formattedValue = new Intl.NumberFormat('en-US', {
          style: 'currency',
          currency: this.currencyCode,
        }).format(value);

        // Devolver el valor formateado
        return formattedValue;
    }

}
