import { Component, OnInit, TemplateRef, Output, EventEmitter  } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { BsModalService } from 'ngx-bootstrap/modal';
import { BsModalRef } from 'ngx-bootstrap/modal';

import { AlertService } from '../../../services/alert.service';
import { ApiService } from '../../../services/api.service';

@Component({
    selector: 'app-crear-categoria-activo',
    templateUrl: './crear-categoria-activo.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule],
    
})
export class CrearCategoriaActivoComponent implements OnInit {

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
        this.apiService.store('activos/categoria', this.categoria).subscribe(categoria => {
            this.update.emit(categoria);
            this.modalRef?.hide();
            this.loading = false;
            this.alertService.success('Categoria creada', 'La categoria ha sido agregada.');
        },error => {this.alertService.error(error); this.loading = false; });
    }


}
