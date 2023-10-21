import { Component, OnInit, EventEmitter, Output, Input } from '@angular/core';
import { AlertService } from '../../../services/alert.service';
import { ApiService } from '../../../services/api.service';

import { debounceTime, distinctUntilChanged, map } from 'rxjs/operators';
import { fromEvent, timer } from 'rxjs';

@Component({
  selector: 'app-buscador-clientes',
  templateUrl: './buscador-clientes.component.html'
})
export class BuscadorClientesComponent implements OnInit {

	@Output() setCliente = new EventEmitter();
    @Input() cliente: any = {};

	public clientes: any = [];
    public searching = false;

	constructor(private apiService: ApiService, private alertService: AlertService) { }

	ngOnInit() {

        const input = document.getElementById('example')!;
        const example = fromEvent(input, 'keyup').pipe(map(i => (<HTMLTextAreaElement>i.currentTarget).value));
        const debouncedInput = example.pipe(debounceTime(500));
        const subscribe = debouncedInput.subscribe(val => { this.searchProducto(); });

    }

    searchProducto(){
        if(this.cliente.nombre && this.cliente.nombre.length > 1) {
            this.searching = true;
            this.apiService.read('clientes/buscar/', this.cliente.nombre).subscribe(clientes => {
               this.clientes = clientes;
               this.searching = false;
            }, error => {this.alertService.error(error);this.searching = false;});
        }else if (!this.cliente.nombre  || this.cliente.nombre.length < 1 ){ this.searching = false; this.cliente = {}; this.clientes.total = 0; }
    }

    selectCliente(cliente:any){
        this.clientes = [];
        this.cliente = cliente;
        this.setCliente.emit(this.cliente);
    }

}
