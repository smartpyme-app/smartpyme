import { Pipe, PipeTransform } from '@angular/core';
@Pipe({
  name: 'filter'
})
export class FilterPipe implements PipeTransform {
  transform(items: any[], column:any, searchText: any): any[] {

    if(!items)
      return [];

    if(!searchText || searchText == "")
        return items;

    if (Array.isArray(column)) {
        let filterItems = column;
        return items.filter(item => {
              var itemFound: Boolean = false;

              for (let i = 0; i < filterItems.length; i++) {
                if (item[filterItems[i]]) {
                  if (item[filterItems[i]].toString().toLowerCase().indexOf(searchText.toLowerCase()) !== -1) {
                    itemFound = true;
                    break;
                  }
                }
              }
              return itemFound;
        });  
    }

    searchText = searchText.toString().toLowerCase();

    return items.filter( item => {
        return item[column] ? item[column].toString().toLowerCase().includes(searchText) : null;
    });


   }
}
