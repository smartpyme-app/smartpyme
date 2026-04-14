import { Pipe, PipeTransform } from '@angular/core';
@Pipe({
    name: 'filter',
    standalone: true
})
export class FilterPipe implements PipeTransform {
  /** Columnas numéricas de relación: deben coincidir en igualdad, no con .includes() (evita que bodega 5 sume stock de15, 50, etc.). */
  private static readonly EXACT_ID_COLUMNS = new Set([
    'id_bodega',
    'id_sucursal',
    'id_empresa',
    'id_producto',
    'sucursal_id',
  ]);

  /** Códigos MH (p. ej. departamento): comparar como string, no con Number() (evita 06 → 6). */
  private static readonly EXACT_STRING_CODE_COLUMNS = new Set([
    'cod_departamento',
  ]);

  transform(items: any[], column:any, searchText: any): any[] {

    if(!items)
      return [];

    if (!Array.isArray(items)) {
      return [];
    }

    if(searchText === '' || searchText === undefined || searchText === null)
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

    const col = String(column);
    if (FilterPipe.EXACT_STRING_CODE_COLUMNS.has(col)) {
      if (searchText === '' || searchText === undefined || searchText === null) {
        return items;
      }
      const needle = String(searchText);
      return items.filter((item) => {
        const v = item[column];
        if (v === undefined || v === null) {
          return false;
        }
        return String(v) === needle;
      });
    }

    if (FilterPipe.EXACT_ID_COLUMNS.has(col)) {
      const needle = Number(searchText);
      if (Number.isNaN(needle)) {
        return [];
      }
      return items.filter((item) => {
        const v = item[column];
        if (v === undefined || v === null) {
          return false;
        }
        return Number(v) === needle;
      });
    }

    const searchLower = searchText.toString().toLowerCase();

    return items.filter( item => {
        return item[column] ? item[column].toString().toLowerCase().includes(searchLower) : false;
    });


   }
}
