import { Component, OnInit, TemplateRef, Output, EventEmitter, inject  } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';

import { AlertService } from '../../../services/alert.service';
import { ApiService } from '../../../services/api.service';    
import { subscriptionHelper } from '@shared/utils/subscription.helper';
import { ModalManagerService } from '../../../services/modal-manager.service';
import { BaseModalComponent } from '../../base/base-modal.component';

@Component({
    selector: 'app-crear-categoria-gasto',
    templateUrl: './crear-categoria-gasto.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule],
    
})
export class CrearCategoriaGastoComponent extends BaseModalComponent implements OnInit {

    public categoria: any = {};
    @Output() update = new EventEmitter();
    public override loading = false;

    constructor( 
        private apiService: ApiService,
        protected override alertService: AlertService,
        protected override modalManager: ModalManagerService
    ) {
        super(modalManager, alertService);
    }

    ngOnInit() {
    }

    override openModal(template: TemplateRef<any>) {
        this.categoria = {};
        super.openModal(template, { class: 'modal-md', backdrop: 'static' });
    }

    public onSubmit() {
        this.loading = true;
        this.categoria.id_empresa = this.apiService.auth_user().id_empresa;
        this.apiService.store('gastos/categoria', this.categoria)
            .pipe(this.untilDestroyed())
            .subscribe(categoria => {
            this.update.emit(categoria);
            this.closeModal();
            this.loading = false;
            this.alertService.success('Categoria creada', 'La categoria ha sido agregada.');
        },error => {this.alertService.error(error); this.loading = false; });
    }

}
