import { Component, OnInit, TemplateRef, Output, Input, EventEmitter  } from '@angular/core';
import { BsModalService } from 'ngx-bootstrap/modal';
import { BsModalRef } from 'ngx-bootstrap/modal/bs-modal-ref.service';

import { AlertService } from '../../../services/alert.service';
import { ApiService } from '../../../services/api.service';

@Component({
  selector: 'app-crear-subcategoria',
  templateUrl: './crear-subcategoria.component.html'
})
export class CrearSubCategoriaComponent implements OnInit {

    public subcategoria: any = {};
    @Input() categoria_id: any;
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
        this.subcategoria = {};
        this.modalRef = this.modalService.show(template, { class: 'modal-sm', backdrop: 'static' });
    }

    public onSubmit() {
        this.loading = true;
        this.subcategoria.categoria_id = this.categoria_id;
        this.apiService.store('subcategoria', this.subcategoria).subscribe(subcategoria => {
            this.update.emit(subcategoria);
            this.modalRef?.hide();
            this.loading = false;
            this.alertService.success('Subcategoria creada', 'Tu subcategoria fue añadida exitosamente.');
        },error => {this.alertService.error(error); this.loading = false; });
    }


}
