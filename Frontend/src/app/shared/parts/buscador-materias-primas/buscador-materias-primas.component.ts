import { Component, OnInit, EventEmitter, Output, Input, DestroyRef, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { AlertService } from '../../../services/alert.service';
import { ApiService } from '../../../services/api.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';

import { debounceTime, distinctUntilChanged, map } from 'rxjs/operators';
import { fromEvent, timer } from 'rxjs';

@Component({
    selector: 'app-buscador-materias-primas',
    templateUrl: './buscador-materias-primas.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule],
    
})
export class BuscadorMateriasPrimasComponent implements OnInit {

	@Output() selectProducto = new EventEmitter();

	public productos: any = [];
    public producto: any = {};
    public searching = false;

	private destroyRef = inject(DestroyRef);
	private untilDestroyed = subscriptionHelper(this.destroyRef);

	constructor(private apiService: ApiService, private alertService: AlertService) { }

	ngOnInit() {

        const input = document.getElementById('example')!;
        const example = fromEvent(input, 'keyup').pipe(map(i => (<HTMLTextAreaElement>i.currentTarget).value));
        const debouncedInput = example.pipe(debounceTime(500));
        const subscribe = debouncedInput.pipe(this.untilDestroyed()).subscribe(val => { this.searchProducto(); });

    }

    searchProducto(){
        if(this.producto.nombre && this.producto.nombre.length > 1) {
            this.searching = true;
            this.apiService.read('materias-primas/buscar/', this.producto.nombre)
                .pipe(this.untilDestroyed())
                .subscribe(productos => {
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
