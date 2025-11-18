import { Component, OnInit, TemplateRef, Output, Input, EventEmitter, DestroyRef, inject  } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';

import { AlertService } from '../../../services/alert.service';
import { ApiService } from '../../../services/api.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';
import { ModalManagerService } from '../../../services/modal-manager.service';
import { BaseModalComponent } from '../../../shared/base/base-modal.component';

@Component({
    selector: 'app-crear-subcategoria',
    templateUrl: './crear-subcategoria.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule],
    
})
export class CrearSubCategoriaComponent extends BaseModalComponent implements OnInit {

    public subcategoria: any = {};
    @Input() categoria_id: any;
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
        this.subcategoria = {};
        super.openModal(template, { class: 'modal-sm', backdrop: 'static' });
    }

    public onSubmit() {
        this.loading = true;
        this.subcategoria.categoria_id = this.categoria_id;
        this.apiService.store('subcategoria', this.subcategoria)
            .pipe(this.untilDestroyed())
            .subscribe(subcategoria => {
            this.update.emit(subcategoria);
            this.closeModal();
            this.loading = false;
            this.alertService.success('Subcategoria creada', 'Tu subcategoria fue añadida exitosamente.');
        },error => {this.alertService.error(error); this.loading = false; });
    }


}
