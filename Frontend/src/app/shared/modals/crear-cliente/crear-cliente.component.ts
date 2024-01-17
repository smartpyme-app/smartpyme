import { Component, OnInit, TemplateRef, Output, Input, EventEmitter  } from '@angular/core';
import { BsModalService } from 'ngx-bootstrap/modal';
import { BsModalRef } from 'ngx-bootstrap/modal/bs-modal-ref.service';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

@Component({
  selector: 'app-crear-cliente',
  templateUrl: './crear-cliente.component.html'
})
export class CrearClienteComponent implements OnInit {

    public cliente: any = {};
    @Input() id_cliente:any = null;
    @Output() update = new EventEmitter();
    public loading = false;
    public saving = false;

    modalRef?: BsModalRef;

    constructor( 
        private apiService: ApiService, private alertService: AlertService,
        private modalService: BsModalService
    ) {}

    ngOnInit() {
    }

    openModal(template: TemplateRef<any>) {
        if(this.id_cliente){
            this.apiService.read('cliente/', this.id_cliente).subscribe(cliente => {
            this.cliente = cliente;
            this.loading = false;
            }, error => {this.alertService.error(error); this.loading = false;});
        }else{
            this.cliente = {};
            this.cliente.tipo = 'Persona';
            this.cliente.id_usuario = this.apiService.auth_user().id;
            this.cliente.id_empresa = this.apiService.auth_user().id_empresa;
        }
        this.alertService.modal = true;
        this.modalRef = this.modalService.show(template, { class: 'modal-lg', backdrop: 'static' });
    }

    public setTipo(tipo:any){
        this.cliente.tipo = tipo;
    }

    public onSubmit() {
        this.saving = true;
        this.apiService.store('cliente', this.cliente).subscribe(cliente => {
            this.update.emit(cliente);
            this.modalRef?.hide();
            this.saving = false;
            this.alertService.modal = false;
            this.alertService.success('Cliente creado', 'El cliente ha sido agregado.');
        },error => {this.alertService.error(error); this.saving = false; });
    }


}
