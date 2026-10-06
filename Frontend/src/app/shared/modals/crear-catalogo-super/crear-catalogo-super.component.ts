import { Component, EventEmitter, Input, Output, TemplateRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { ModalManagerService } from '@services/modal-manager.service';
import { BaseModalComponent } from '@shared/base/base-modal.component';
import { NotificacionesContainerComponent } from '@shared/parts/notificaciones/notificaciones-container.component';

@Component({
    selector: 'app-crear-catalogo-super',
    templateUrl: './crear-catalogo-super.component.html',
    standalone: true,
    imports: [CommonModule, FormsModule, TooltipModule, NotificacionesContainerComponent],
})
export class CrearCatalogoSuperComponent extends BaseModalComponent {

    @Input() endpoint: 'campania' | 'aliado' = 'campania';
    @Output() update = new EventEmitter<any>();

    public item: any = {};
    public override saving = false;

    constructor(
        private apiService: ApiService,
        protected override alertService: AlertService,
        protected override modalManager: ModalManagerService,
    ) {
        super(modalManager, alertService);
    }

    override openModal(template: TemplateRef<any>) {
        this.item = { nombre: '', descripcion: '', activo: true };
        super.openModal(template, { class: 'modal-md', backdrop: 'static' });
    }

    public onSubmit() {
        this.saving = true;
        this.apiService.store(this.endpoint, this.item)
            .pipe(this.untilDestroyed())
            .subscribe(item => {
                this.update.emit(item);
                this.closeModal();
                this.saving = false;
                const esAliado = this.endpoint === 'aliado';
                this.alertService.success(
                    esAliado ? 'Aliado creado' : 'Campaña creada',
                    esAliado ? 'El aliado fue añadido exitosamente.' : 'La campaña fue añadida exitosamente.',
                );
            }, error => {
                this.alertService.error(error);
                this.saving = false;
            });
    }
}
