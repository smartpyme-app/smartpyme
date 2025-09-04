import { Component, OnInit, EventEmitter, Input, Output, TemplateRef } from '@angular/core';
import { of } from 'rxjs';
import { FormControl } from '@angular/forms';
import { debounceTime, switchMap, filter,catchError  } from 'rxjs/operators';

import { ApiService } from '@services/api.service';
import { AlertService } from '@services/alert.service';

@Component({
  selector: 'app-buscador-productos',
  templateUrl: './buscador-productos.component.html'
})
export class BuscadorProductosComponent implements OnInit {

    @Output() selectProducto = new EventEmitter();
    @Input() id_bodega?: number; // Parámetro opcional para filtrar por bodega
    searchControl = new FormControl();

    public productos:any = [];
    public loading:boolean = false;

    constructor( 
        private apiService: ApiService, private alertService: AlertService
    ) { }

    ngOnInit() {


        this.searchControl.valueChanges
          .pipe(
            debounceTime(500),
            filter((query: string) => query?.trim().length > 0), // Validación para evitar errores con `null` o `undefined`.
            switchMap((query: any) => {
              // Decidir qué endpoint usar basado en si se proporciona id_bodega
              let endpoint = 'productos/buscar-by-query';
              let params = `query=${encodeURIComponent(query)}`;
              
              if (this.id_bodega) {
                endpoint = 'productos/buscar-by-query-bodega';
                params += `&id_bodega=${this.id_bodega}`;
              }
              
              return this.apiService.getAll(`${endpoint}?${params}`).pipe(
                catchError(error => {
                  console.error('Error en la búsqueda:', error);
                  this.productos = []; // Limpiar resultados en caso de error.
                  this.loading = false; // Asegurar que el estado de carga se actualice.
                  return of([]); // Retornar un observable vacío para que el flujo continúe.
                })
              );
            })
          )
          .subscribe({
            next: (results: any[]) => {
              this.productos = Array.isArray(results) ? results : [];
              this.loading = false;

              if (
                results &&
                results.length == 1 &&
                (this.searchControl.value == results[0].codigo || this.searchControl.value == results[0].barcode)
              ) {
                this.productoSelect(results[0]);
              }
            },
            error: (err) => {
              console.error('Error no controlado:', err); // Log en caso de un error en la suscripción.
            }
          });

    }

    productoSelect(producto:any){
        this.productos = [];
        this.searchControl.setValue('');
        this.selectProducto.emit(producto);
    }

    onProductoClick(producto:any){
        this.productoSelect(producto);
    }

}

