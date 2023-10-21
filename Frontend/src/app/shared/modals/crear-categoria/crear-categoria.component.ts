import { Component, OnInit, TemplateRef, Output, EventEmitter  } from '@angular/core';
import { BsModalService } from 'ngx-bootstrap/modal';
import { BsModalRef } from 'ngx-bootstrap/modal/bs-modal-ref.service';

import { AlertService } from '../../../services/alert.service';
import { ApiService } from '../../../services/api.service';

@Component({
  selector: 'app-crear-categoria',
  templateUrl: './crear-categoria.component.html'
})
export class CrearCategoriaComponent implements OnInit {

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
        this.modalRef = this.modalService.show(template, { class: 'modal-sm', backdrop: 'static' });
    }

    public onSubmit() {
        this.loading = true;
        this.categoria.empresa_id = this.apiService.auth_user().empresa_id;
        this.apiService.store('categoria', this.categoria).subscribe(categoria => {
            this.update.emit(categoria);
            this.modalRef?.hide();
            this.loading = false;
            this.alertService.success("Categoria guardada");
        },error => {this.alertService.error(error); this.loading = false; });
    }


}
