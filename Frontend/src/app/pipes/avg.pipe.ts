import { Pipe, PipeTransform } from '@angular/core';

@Pipe({
  name: 'avg'
})
export class AvgPipe implements PipeTransform {

    transform(items: any[], attr: string): any {
        if(items) {
            return items.reduce((a, b) => (( a * 1) + (b[attr] * 1) / items.length), 0);
        }
    }
}