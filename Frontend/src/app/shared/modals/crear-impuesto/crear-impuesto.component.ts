import { Component, OnInit, TemplateRef, Output, Input, EventEmitter, inject  } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';
import { ModalManagerService } from '@services/modal-manager.service';
import { BaseModalComponent } from '../../base/base-modal.component';

@Component({
    selector: 'app-crear-impuesto',
    templateUrl: './crear-impuesto.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule],

})
export class CrearImpuestoComponent extends BaseModalComponent implements OnInit {

    public impuesto: any = {};
    @Input() id_impuesto:any = null;
    @Output() update = new EventEmitter();
    public override loading = false;
    public override saving = false;

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
        if(this.id_impuesto){
            this.apiService.read('impuesto/', this.id_impuesto).subscribe(impuesto => {
                // Crear una copia del objeto para evitar modificar el original
                this.impuesto = {...impuesto};
                // Convertir valores null/undefined a false, pero mantener false y true como están
                if (this.impuesto.aplica_ventas === undefined || this.impuesto.aplica_ventas === null) {
                    this.impuesto.aplica_ventas = false;
                } else {
                    this.impuesto.aplica_ventas = Boolean(this.impuesto.aplica_ventas);
                }
                if (this.impuesto.aplica_gastos === undefined || this.impuesto.aplica_gastos === null) {
                    this.impuesto.aplica_gastos = false;
                } else {
                    this.impuesto.aplica_gastos = Boolean(this.impuesto.aplica_gastos);
                }
                if (this.impuesto.aplica_compras === undefined || this.impuesto.aplica_compras === null) {
                    this.impuesto.aplica_compras = false;
                } else {
                    this.impuesto.aplica_compras = Boolean(this.impuesto.aplica_compras);
                }
                this.loading = false;
            }, error => {
                this.alertService.error(error);
                this.loading = false;
            });
        } else {
            this.impuesto = {};
            this.impuesto.estado = 1;
            this.impuesto.id_usuario = this.apiService.auth_user().id;
            this.impuesto.id_empresa = this.apiService.auth_user().id_empresa;
            // Solo establecer valores por defecto para nuevos impuestos
            this.impuesto.aplica_ventas = true;
            this.impuesto.aplica_gastos = true;
            this.impuesto.aplica_compras = true;
        }
        super.openModal(template, { class: 'modal-lg', backdrop: 'static' });
    }

    public onSubmit() {
        this.saving = true;
        this.apiService.store('impuesto', this.impuesto)
            .pipe(this.untilDestroyed())
            .subscribe(impuesto => {
            this.update.emit(impuesto);
            this.closeModal();
            this.saving = false;
            this.alertService.success('Impuesto guardado', 'El impuesto fue guardado exitosamente.');
        }, error => {
            this.alertService.error(error);
            this.saving = false;
        });
    }

    public verificarSiExiste(){
        if(this.impuesto.nombre){
            this.apiService.getAll('impuestos', {
                nombre: this.impuesto.nombre,
                estado: 1,
            })
                .pipe(this.untilDestroyed())
                .subscribe(impuestos => {
                if(impuestos.data[0]){
                    this.alertService.warning('🚨 Alerta duplicado: Hemos encontrado otro registro similar con estos datos.',
                        'Por favor, verificar. Puedes ignorar esta alerta si consideras que no estas duplicando el registro.'
                    );
                }
                this.loading = false;
            }, error => {
                this.alertService.error(error);
                this.loading = false;
            });
        }
    }
}
