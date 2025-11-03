import { Pipe, PipeTransform } from '@angular/core';

@Pipe({
    name: 'truncate',
    standalone: true
})
export class TruncatePipe implements PipeTransform {
  transform(value: string, limit = 25, completeWords = false, ellipsis = '...') {
    if (value) {
      if (completeWords) {
        limit = value.substr(0, 13).lastIndexOf(' ');
      }
      if (value.length <= limit) { ellipsis = '';}
      return `${value.substr(0, limit)}${ellipsis}`;
    }else{
      return value;
    }
  }
}