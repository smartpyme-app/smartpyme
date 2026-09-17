import { Component, OnInit, TemplateRef, Output, EventEmitter, inject  } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { TooltipModule } from 'ngx-bootstrap/tooltip';

import { AlertService } from '../../../services/alert.service';
import { ApiService } from '../../../services/api.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';
import { ModalManagerService } from '../../../services/modal-manager.service';
import { BaseModalComponent } from '../../base/base-modal.component';

@Component({
    selector: 'app-crear-categoria-activo',
    templateUrl: './crear-categoria-activo.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, TooltipModule],
    
})
export class CrearCategoriaActivoComponent extends BaseModalComponent implements OnInit {

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
        this.categoria = {
            metodo_depreciacion: 'linea_recta',
            valor_residual_default: 0,
            permite_bien_usado: false,
        };
        super.openModal(template, { class: 'modal-md', backdrop: 'static' });
    }

    sincronizarDesdePorcentaje() {
        const pct = Number(this.categoria.porcentaje_anual);
        if (pct > 0) {
            this.categoria.vida_util_anios = Math.round((100 / pct) * 100) / 100;
        }
    }

    sincronizarDesdeVidaUtil() {
        const años = Number(this.categoria.vida_util_anios);
        if (años > 0) {
            this.categoria.porcentaje_anual = Math.round((100 / años) * 100) / 100;
        }
    }

    public onSubmit() {
        this.loading = true;
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
