import { Component, OnInit, TemplateRef, Output, EventEmitter  } from '@angular/core';
import { BsModalService } from 'ngx-bootstrap/modal';
import { BsModalRef } from 'ngx-bootstrap/modal/bs-modal-ref.service';

import { AlertService } from '../../../services/alert.service';
import { ApiService } from '../../../services/api.service';

@Component({
  selector: 'app-crear-categoria-gasto',
  templateUrl: './crear-categoria-gasto.component.html'
})
export class CrearCategoriaGastoComponent implements OnInit {

    public categoria: any = {};
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
        this.categoria = {};
        this.modalRef = this.modalService.show(template, { class: 'modal-md', backdrop: 'static' });
    }

    public onSubmit() {
        this.loading = true;
        this.categoria.id_empresa = this.apiService.auth_user().id_empresa;
        this.apiService.store('gastos/categoria', this.categoria).subscribe(categoria => {
            this.update.emit(categoria);
            this.modalRef?.hide();
            this.loading = false;
            this.alertService.success('Categoria creada', 'La categoria ha sido agregada.');
        },error => {this.alertService.error(error); this.loading = false; });
    }


}
