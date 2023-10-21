import { Component, OnInit, EventEmitter, Input, Output, TemplateRef } from '@angular/core';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';

import { debounceTime, distinctUntilChanged, map } from 'rxjs/operators';
import { fromEvent, timer } from 'rxjs';

import { ApiService } from '../../../../../services/api.service';
import { AlertService } from '../../../../../services/alert.service';

@Component({
  selector: 'app-flete-cliente',
  templateUrl: './flete-cliente.component.html'
})
export class FleteClienteComponent implements OnInit {

    @Input() cliente: any = {};
    @Output() clienteSelect = new EventEmitter();
    public clientes: any = [];
    public searching = false;
    public loading = false;
    modalRef!: BsModalRef;

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
        this.modalRef = this.modalService.show(template, {class:'modal-lg', backdrop:'static'});

        const input = document.getElementById('example')!;
        console.log(input);
        const example = fromEvent(input, 'keyup').pipe(map(i => (<HTMLTextAreaElement>i.currentTarget).value));
        const debouncedInput = example.pipe(debounceTime(500));
        const subscribe = debouncedInput.subscribe(val => { this.searchCliente(); });
        
    }


    searchCliente(){
        if(this.cliente.nombre) {
            this.searching = true;
            this.apiService.read('clientes/buscar/', this.cliente.nombre).subscribe(clientes => {
               this.clientes = clientes;
               this.searching = false;
            }, error => {this.alertService.error(error);this.searching = false;});
        }else if (!this.cliente.nombre  || this.cliente.nombre.length < 1 ){ 
            this.searching = false;
            this.cliente = {};
            this.clientes.total = 0; 
        }
    }

    public clear(){
        this.cliente = {};
    }

    public selectCliente(cliente:any){
        this.clientes = [];
        this.cliente = cliente;
        document.getElementById("registro")!.focus();
    }

    public nuevoCliente(){
        this.clientes = [];
    }

    public onSubmit(cliente:any){
        this.cliente = cliente;
        if (!this.cliente.id) {
            this.cliente.empresa_id = this.apiService.auth_user().empresa_id;
            this.cliente.usuario_id = this.apiService.auth_user().id;
        }

        this.loading = true;
        this.apiService.store('cliente', this.cliente).subscribe(cliente => { 
            this.loading = false;
            this.clienteSelect.emit(cliente);
            this.modalRef.hide();
            this.alertService.success('Guardado');
        }, error => {this.alertService.error(error); this.loading = false;});
    }


}
