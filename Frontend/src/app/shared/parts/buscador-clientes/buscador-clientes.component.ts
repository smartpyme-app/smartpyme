import { Component, AfterViewInit, OnDestroy, EventEmitter, Output, Input, ViewChild, ElementRef } from '@angular/core';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { Subscription, fromEvent } from 'rxjs';
import { debounceTime, distinctUntilChanged, filter, map } from 'rxjs/operators';

@Component({
  selector: 'app-buscador-clientes',
  templateUrl: './buscador-clientes.component.html'
})
export class BuscadorClientesComponent implements AfterViewInit, OnDestroy {

    @Input() cliente:any = {};
    @Output() selectCliente = new EventEmitter();
    @Input() focus:boolean = false;
    @Input() placeholder = 'Consumidor Final';
    @Input() inputId = 'buscadorCliente';

    @ViewChild('searchInput') searchInput?: ElementRef<HTMLInputElement>;

    public clientes:any = [];
    public searching = false;
    public searchExecuted: boolean = false;

    private searchSub?: Subscription;

    constructor(private apiService: ApiService, private alertService: AlertService) { }

    ngAfterViewInit(): void {
        if (!this.cliente) {
            this.cliente = { nombre: '' };
        } else if (this.cliente.nombre === undefined || this.cliente.nombre === null) {
            this.cliente.nombre = '';
        }
        this.bindSearchInput();
    }

    ngOnDestroy(): void {
        this.searchSub?.unsubscribe();
    }

    private bindSearchInput(): void {
        const input = this.searchInput?.nativeElement;
        if (!input) {
            return;
        }
        this.searchSub?.unsubscribe();
        this.searchSub = fromEvent(input, 'keyup').pipe(
            map((event: Event) => (event.target as HTMLInputElement).value.trim()),
            debounceTime(800),
            distinctUntilChanged(),
            filter(value => value.length > 2)
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
