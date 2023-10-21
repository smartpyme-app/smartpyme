import { Component, OnInit, EventEmitter, Output, Input } from '@angular/core';
import { AlertService } from '../../../services/alert.service';
import { ApiService } from '../../../services/api.service';

import { debounceTime, distinctUntilChanged, map } from 'rxjs/operators';
import { fromEvent, timer } from 'rxjs';

@Component({
  selector: 'app-buscador-proveedores',
  templateUrl: './buscador-proveedores.component.html'
})
export class BuscadorProveedoresComponent implements OnInit {

	@Output() setProveedor = new EventEmitter();

	public proveedores: any = [];
    public proveedor: any = {};
    public searching = false;

	constructor(private apiService: ApiService, private alertService: AlertService) { }

	ngOnInit() {

        const input = document.getElementById('example')!;
        const example = fromEvent(input, 'keyup').pipe(map(i => (<HTMLTextAreaElement>i.currentTarget).value));
        const debouncedInput = example.pipe(debounceTime(500));
        const subscribe = debouncedInput.subscribe(val => { this.searchProducto(); });

    }

    searchProducto(){
        if(this.proveedor.nombre && this.proveedor.nombre.length > 1) {
            this.searching = true;
            this.apiService.read('proveedores/buscar/', this.proveedor.nombre).subscribe(proveedores => {
               this.proveedores = proveedores;
               this.searching = false;
            }, error => {this.alertService.error(error);this.searching = false;});
        }else if (!this.proveedor.nombre  || this.proveedor.nombre.length < 1 ){ this.searching = false; this.proveedor = {}; this.proveedores.total = 0; }
    }

    selectProveedor(proveedor:any){
        this.proveedores = [];
        this.proveedor = proveedor;
        console.log(this.proveedor);
        this.setProveedor.emit({proveedor: this.proveedor});
    }

}
