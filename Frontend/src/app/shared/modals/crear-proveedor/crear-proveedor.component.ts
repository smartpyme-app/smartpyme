import { Component, OnInit, TemplateRef, Output, EventEmitter  } from '@angular/core';
import { BsModalService } from 'ngx-bootstrap/modal';
import { BsModalRef } from 'ngx-bootstrap/modal/bs-modal-ref.service';

import { AlertService } from '../../../services/alert.service';
import { ApiService } from '../../../services/api.service';

@Component({
  selector: 'app-crear-proveedor',
  templateUrl: './crear-proveedor.component.html'
})
export class CrearProveedorComponent implements OnInit {

    public proveedor: any = {};
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
        this.proveedor = {};
        this.modalRef = this.modalService.show(template, { class: 'modal-lg', backdrop: 'static' });
    }

    public onSubmit() {
        this.loading = true;
        this.proveedor.usuario_id = this.apiService.auth_user().id;
        this.proveedor.empresa_id = this.apiService.auth_user().empresa_id;
        this.apiService.store('proveedor', this.proveedor).subscribe(proveedor => {
            this.update.emit(proveedor);
            this.modalRef?.hide();
            this.loading = false;
            this.alertService.success("Proveedor guardado");
        },error => {this.alertService.error(error); this.loading = false; });
    }


}
