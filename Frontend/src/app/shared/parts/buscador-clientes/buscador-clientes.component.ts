import { Component, AfterViewInit, OnDestroy, EventEmitter, Output, Input, ViewChild, ElementRef, DestroyRef, inject, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';
import { fromEvent } from 'rxjs';
import { debounceTime, distinctUntilChanged, filter, map } from 'rxjs/operators';

@Component({
    selector: 'app-buscador-clientes',
    templateUrl: './buscador-clientes.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule],
    changeDetection: ChangeDetectionStrategy.OnPush,
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

    private destroyRef = inject(DestroyRef);
    private untilDestroyed = subscriptionHelper(this.destroyRef);
    private cdr = inject(ChangeDetectorRef);

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
        // destroyRef maneja la suscripción
    }

    private bindSearchInput(): void {
        const input = this.searchInput?.nativeElement;
        if (!input) {
            return;
        }
        fromEvent(input, 'keyup').pipe(
            map((event: Event) => (event.target as HTMLInputElement).value.trim()),
            debounceTime(800),
            distinctUntilChanged(),
            filter(value => value.length > 2),
            this.untilDestroyed()
        ).subscribe(() => this.searchClientes());
    }

    searchClientes() {
        this.searching = true;
        this.searchExecuted = true;
        this.apiService.read('clientes/buscar/', this.cliente.nombre)
            .pipe(this.untilDestroyed())
            .subscribe(clientes => {
            this.clientes = clientes;
            this.searching = false;
            this.cdr.markForCheck();
        }, error => {
            this.alertService.error(error);
            this.searching = false;
            this.cdr.markForCheck();
        });
    }


    clienteSelect(cliente:any){
        if(cliente.nombre == ''){
            cliente = {};
        }
        this.searchExecuted = false;
        this.clientes = [];
        this.cliente = cliente;
        this.cdr.markForCheck();
        this.selectCliente.emit(cliente);
    }

}
