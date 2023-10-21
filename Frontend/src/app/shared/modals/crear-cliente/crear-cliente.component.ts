import { Component, OnInit, TemplateRef, Output, EventEmitter  } from '@angular/core';
import { BsModalService } from 'ngx-bootstrap/modal';
import { BsModalRef } from 'ngx-bootstrap/modal/bs-modal-ref.service';

import { AlertService } from '../../../services/alert.service';
import { ApiService } from '../../../services/api.service';

@Component({
  selector: 'app-crear-cliente',
  templateUrl: './crear-cliente.component.html'
})
export class CrearClienteComponent implements OnInit {

    public cliente: any = {};
    @Output() update = new EventEmitter();
    public loading = false;

    modalRef?: BsModalRef;

    constructor( 
        private apiService: ApiService, private alertService: AlertService,
        private modalService: BsModalService
    ) {}

    ngOnInit() {
    }

    openModal(template: TemplateRef<any>) {
        this.cliente = {};
        this.modalRef = this.modalService.show(template, { class: 'modal-lg', backdrop: 'static' });
    }

    public onSubmit() {
        this.loading = true;
        this.cliente.usuario_id = this.apiService.auth_user().id;
        this.cliente.empresa_id = this.apiService.auth_user().empresa_id;
        this.apiService.store('cliente', this.cliente).subscribe(cliente => {
            this.update.emit(cliente);
            this.modalRef?.hide();
            this.loading = false;
            this.alertService.success("Cliente guardado");
        },error => {this.alertService.error(error); this.loading = false; });
    }


}
