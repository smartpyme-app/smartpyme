import { Component, OnInit, EventEmitter, Output, Input } from '@angular/core';
import { AlertService } from '../../../services/alert.service';
import { ApiService } from '../../../services/api.service';

import { debounceTime, distinctUntilChanged, map } from 'rxjs/operators';
import { fromEvent, timer } from 'rxjs';

@Component({
  selector: 'app-buscador-materias-primas',
  templateUrl: './buscador-materias-primas.component.html'
})
export class BuscadorMateriasPrimasComponent implements OnInit {

	@Output() selectProducto = new EventEmitter();

	public productos: any = [];
    public producto: any = {};
    public searching = false;

	constructor(private apiService: ApiService, private alertService: AlertService) { }

	ngOnInit() {

        const input = document.getElementById('example')!;
        const example = fromEvent(input, 'keyup').pipe(map(i => (<HTMLTextAreaElement>i.currentTarget).value));
        const debouncedInput = example.pipe(debounceTime(500));
        const subscribe = debouncedInput.subscribe(val => { this.searchProducto(); });

    }

    searchProducto(){
        if(this.producto.nombre && this.producto.nombre.length > 1) {
            this.searching = true;
            this.apiService.read('materias-primas/buscar/', this.producto.nombre).subscribe(productos => {
               this.productos = productos;
               this.searching = false;
            }, error => {this.alertService.error(error);this.searching = false;});
        }else if (!this.producto.nombre  || this.producto.nombre.length < 1 ){ this.searching = false; this.producto = {}; this.productos.total = 0; }
    }

    productoSelect(producto:any){
        this.productos = [];
        this.producto = producto;
        this.selectProducto.emit(this.producto);
    }

}
