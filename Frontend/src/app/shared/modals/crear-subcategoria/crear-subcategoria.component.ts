import { Component, OnInit, TemplateRef, Output, Input, EventEmitter } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { NgSelectModule } from '@ng-select/ng-select';

import { AlertService } from '../../../services/alert.service';
import { ApiService } from '../../../services/api.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';
import { ModalManagerService } from '../../../services/modal-manager.service';
import { BaseModalComponent } from '../../../shared/base/base-modal.component';

@Component({
    selector: 'app-crear-subcategoria',
    templateUrl: './crear-subcategoria.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, NgSelectModule],
    
})
export class CrearSubCategoriaComponent extends BaseModalComponent implements OnInit {

    public subcategoria: any = {};
    public categorias: any[] = [];
    @Input() categoria_id: any;
    @Output() update = new EventEmitter();

    constructor(
        private apiService: ApiService,
        protected override alertService: AlertService,
        protected override modalManager: ModalManagerService
    ) {
        super(modalManager, alertService);
    }

    ngOnInit() {
        this.loadCategorias();
    }

    loadCategorias() {
        this.apiService.getAll('categorias/padre')
            .pipe(this.untilDestroyed())
            .subscribe(
            categorias => { this.categorias = categorias; },
            error => { this.alertService.error(error); }
        );
    }

    override openModal(template: TemplateRef<any>) {
        this.subcategoria = {};
        this.subcategoria.enable = true;
        this.subcategoria.subcategoria = true;
        this.subcategoria.id_cate_padre = this.categoria_id ?? null;
        this.subcategoria.id_empresa = this.apiService.auth_user().id_empresa;
        super.openModal(template, { class: 'modal-sm', backdrop: 'static' });
    }

    public onSubmit() {
        this.loading = true;

        this.apiService.store('categoria', this.subcategoria)
            .pipe(this.untilDestroyed())
            .subscribe(
            subcategoria => {
                this.update.emit(subcategoria);
                this.closeModal();
                this.loading = false;
                this.alertService.success('Subcategoria creada', 'La subcategoria ha sido agregada.');
            },
            error => {
                this.alertService.error(error);
                this.loading = false;
            }
        );
    }
}
