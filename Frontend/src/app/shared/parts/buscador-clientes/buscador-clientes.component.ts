import { Component, OnInit, EventEmitter, Output, Input } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { fromEvent } from 'rxjs';
import { debounceTime, distinctUntilChanged, filter, map } from 'rxjs/operators';

@Component({
    selector: 'app-buscador-clientes',
    templateUrl: './buscador-clientes.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule],
    
})
export class BuscadorClientesComponent implements OnInit {

    @Input() cliente:any = {};
    @Output() selectCliente = new EventEmitter();
    @Input() focus:boolean = false;

    public clientes:any = [];
    public searching = false;
    public searchExecuted: boolean = false;

    constructor(private apiService: ApiService, private alertService: AlertService) { }

    ngOnInit() {
        if(!this.cliente.nombre){
            this.cliente = {};
        }
        const input = document.getElementById('buscadorCliente') as HTMLInputElement;
        fromEvent(input, 'keyup').pipe(
            map((event: Event) => (event.target as HTMLInputElement).value.trim()), // Obtiene el valor y elimina espacios en blanco
            debounceTime(800), // Espera 500ms después de la última tecla presionada
            distinctUntilChanged(), // Evita búsquedas repetidas con el mismo valor
            filter(value => value.length > 2) // Solo permite búsquedas si hay más de 2 caracteres
        ).subscribe(() => this.searchClientes());
    }


    searchClientes() {
        this.searching = true;
        this.searchExecuted = true;
        this.apiService.read('clientes/buscar/', this.cliente.nombre).subscribe(clientes => {
            this.clientes = clientes;
            this.searching = false;
        }, error => {
            this.alertService.error(error);
            this.searching = false;
        });
    }


    clienteSelect(cliente:any){
        if(cliente.nombre == ''){
            cliente = {};
        }
        this.searchExecuted = false;
        this.clientes = [];
        this.cliente = cliente;
        this.selectCliente.emit(cliente);
    }

}
