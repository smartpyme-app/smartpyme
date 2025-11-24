import { Component, OnInit, Input, TemplateRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { BaseCrudComponent } from '@shared/base/base-crud.component';
import { PaginatedResponse } from '@shared/base/base-paginated-modal.component';

import { AlertService } from '../../../services/alert.service';
import { ApiService } from '../../../services/api.service';
import { ModalManagerService } from '../../../services/modal-manager.service';
import { TruncatePipe } from '../../../pipes/truncate.pipe';

@Component({
    selector: 'app-notificaciones',
    templateUrl: './notificaciones.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, TruncatePipe],
    
})

export class NotificacionesComponent extends BaseCrudComponent<any> implements OnInit {

    public notificacion:any = {};
    public cajas:any = [];
    public departamentos:any = [];
    public sucursales:any = [];
    public notificaciones: PaginatedResponse<any> = {} as PaginatedResponse;
    public paginacion = [];
    public override filtros:any = {};

    constructor(
        protected override apiService:ApiService,
        protected override alertService:AlertService,
        protected override modalManager: ModalManagerService
    ){
        super(apiService, alertService, modalManager, {
            endpoint: 'notificacion',
            itemsProperty: 'notificaciones',
            itemProperty: 'notificacion',
            reloadAfterSave: false,
            reloadAfterDelete: false,
            messages: {
                created: 'Notificación actualizada.',
                updated: 'Notificación actualizada.',
                deleted: 'Notificación eliminada.',
                createTitle: 'Notificación',
                updateTitle: 'Notificación',
                deleteTitle: 'Notificación',
                deleteConfirm: '¿Desea eliminar el registro?'
            },
            afterSave: () => {
                this.filtrarNotificaciones();
            }
        });
    }

	ngOnInit() {
        this.loadAll();
    }

    public override loadAll() {
        this.filtros.categoria = '';
        this.filtros.tipo = '';
        this.filtros.leido = '';
        this.filtros.buscador = '';
        this.filtros.orden = 'id';
        this.filtros.direccion = 'desc';
        this.filtros.paginate = 10;

        this.filtrarNotificaciones();
    }

    protected aplicarFiltros(): void {
        this.filtrarNotificaciones();
    }

    public filtrarNotificaciones(){
        this.loading = true;
        this.apiService.getAll('notificaciones', this.filtros)
            .pipe(this.untilDestroyed())
            .subscribe(notificaciones => { 
            this.notificaciones = notificaciones;
            this.loading = false;
            if(this.modalRef){
                this.closeModal();
            }
        }, error => {this.alertService.error(error); });
    }

    override openModal(template: TemplateRef<any>, notificacion?: any, modalConfig?: any) {
        if (notificacion) {
            const updated = { ...notificacion, leido: true };
            this.setEstado(updated);
            super.openModal(template, updated, modalConfig);
        } else {
            super.openModal(template, notificacion, modalConfig);
        }
    }

    public setEstado(notificacion:any){
        this.onSubmit({ ...notificacion, leido: true }, true);
    }

    // setPagination() ahora se hereda de BasePaginatedComponent



}

