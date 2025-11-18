import { Component, OnInit, TemplateRef, Output, EventEmitter, DestroyRef, inject  } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';

import { AlertService } from '../../../services/alert.service';
import { ApiService } from '../../../services/api.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';
import { ModalManagerService } from '../../../services/modal-manager.service';
import { BaseModalComponent } from '../../base/base-modal.component';

@Component({
    selector: 'app-crear-categoria-activo',
    templateUrl: './crear-categoria-activo.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule],
    
})
export class CrearCategoriaActivoComponent extends BaseModalComponent implements OnInit {

    public categoria: any = {};
    @Output() update = new EventEmitter();
    public override loading = false;

    private destroyRef = inject(DestroyRef);
    private untilDestroyed = subscriptionHelper(this.destroyRef);

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
        super.openModal(template, { class: 'modal-sm', backdrop: 'static' });
    }

    public onSubmit() {
        this.loading = true;
        this.categoria.empresa_id = this.apiService.auth_user().empresa_id;
        this.apiService.store('activos/categoria', this.categoria)
            .pipe(this.untilDestroyed())
            .subscribe(categoria => {
            this.update.emit(categoria);
            this.closeModal();
            this.loading = false;
            this.alertService.success('Categoria creada', 'La categoria ha sido agregada.');
        },error => {this.alertService.error(error); this.loading = false; });
    }


}
