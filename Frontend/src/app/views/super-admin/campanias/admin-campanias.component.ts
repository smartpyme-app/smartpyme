import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { PopoverModule } from 'ngx-bootstrap/popover';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { ModalManagerService } from '@services/modal-manager.service';
import { BaseCrudComponent } from '@shared/base/base-crud.component';
import { PaginationComponent } from '@shared/parts/pagination/pagination.component';
import { NotificacionesContainerComponent } from '@shared/parts/notificaciones/notificaciones-container.component';

@Component({
    selector: 'app-admin-campanias',
    templateUrl: './admin-campanias.component.html',
    standalone: true,
    imports: [
        CommonModule,
        RouterModule,
        FormsModule,
        PopoverModule,
        TooltipModule,
        PaginationComponent,
        NotificacionesContainerComponent,
    ],
})
export class AdminCampaniasComponent extends BaseCrudComponent<any> implements OnInit {

    public campanias: any = { data: [] };
    public campania: any = {};

    constructor(
        apiService: ApiService,
        alertService: AlertService,
        modalManager: ModalManagerService,
    ) {
        super(apiService, alertService, modalManager, {
            endpoint: 'campania',
            itemsProperty: 'campanias',
            itemProperty: 'campania',
            reloadAfterSave: true,
            reloadAfterDelete: true,
            messages: {
                created: 'La campaña fue añadida exitosamente.',
                updated: 'La campaña fue guardada exitosamente.',
                deleted: 'La campaña fue eliminada exitosamente.',
                createTitle: 'Campaña creada',
                updateTitle: 'Campaña actualizada',
                deleteTitle: 'Campaña eliminada',
                deleteConfirm: '¿Desea eliminar la campaña?',
            },
            initNewItem: (item) => {
                item.activo = true;
                item.descripcion = '';
                return item;
            },
        });
    }

    ngOnInit() {
        this.filtros.estado = '';
        this.filtros.buscador = '';
        this.filtros.orden = 'nombre';
        this.filtros.direccion = 'asc';
        this.filtros.paginate = 10;
        this.loadAll();
    }

    public override loadAll() {
        this.filtros.estado = '';
        this.filtros.buscador = '';
        this.filtros.page = 1;
        this.aplicarFiltros();
    }

    public buscar() {
        this.filtros.page = 1;
        this.aplicarFiltros();
    }

    public toggleActivo(item: any) {
        this.onSubmit({ ...item, activo: !item.activo });
    }

    protected aplicarFiltros(): void {
        this.loading = true;
        this.apiService.getAll('campanias', this.filtros)
            .pipe(this.untilDestroyed())
            .subscribe(campanias => {
                this.campanias = campanias;
                this.loading = false;
            }, error => {
                this.alertService.error(error);
                this.loading = false;
            });
    }
}
