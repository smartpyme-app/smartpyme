import { Component, OnInit, EventEmitter, Input, Output, TemplateRef } from '@angular/core';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';

import { debounceTime, distinctUntilChanged, map } from 'rxjs/operators';
import { fromEvent, timer } from 'rxjs';

import { ApiService } from '../../../../services/api.service';
import { AlertService } from '../../../../services/alert.service';

@Component({
  selector: 'app-orden-cliente',
  templateUrl: './orden-cliente.component.html'
})
export class OrdenClienteComponent implements OnInit {

    @Input() cliente: any = {};
    @Output() clienteSelect = new EventEmitter();
    public clientes: any = [];
    public searching = false;
    modalRef!: BsModalRef;

    public pagoAnticipado:boolean = false;

    constructor( 
        private apiService: ApiService, private alertService: AlertService,
        private modalService: BsModalService
    ) { }

    ngOnInit() {
    }

    openModal(template: TemplateRef<any>) {
        if(this.cliente.id) {
            this.searching = false;
        }
        this.modalRef = this.modalService.show(template, { backdrop: 'static' });

        setTimeout(()=>{
            const input = document.getElementById('example')!;
            const example = fromEvent(input, 'keyup').pipe(map(i => (<HTMLTextAreaElement>i.currentTarget).value));
            const debouncedInput = example.pipe(debounceTime(500));
            const subscribe = debouncedInput.subscribe(val => { this.searchCliente(); });
        }, 500);
        
    }


    searchCliente(){
        if(this.cliente.nombre && this.cliente.nombre.length > 1) {
            this.searching = true;
            this.apiService.read('clientes/buscar/', this.cliente.nombre).subscribe(clientes => {
               this.clientes = clientes;
               this.searching = false;
               if (this.clientes.total == 0) {
                   let nombre = this.cliente.nombre;
                   this.cliente = {};
                   this.cliente.nombre = nombre;
               }
            }, error => {this.alertService.error(error);this.searching = false;});
        }else if (!this.cliente.nombre  || this.cliente.nombre.length < 1 ){ this.searching = false; this.cliente = {}; this.clientes.total = 0; }
    }

    public selectCliente(cliente:any){
        this.clientes = [];
        this.cliente = cliente;
    }

    public onSubmit(cliente:any){
        this.clienteSelect.emit(this.cliente);
        this.modalRef.hide()
    }

    clear(){
        if(this.clientes.data && this.clientes.data.length == 0) { this.clientes = []; }
    }

}
