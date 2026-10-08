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
    selector: 'app-admin-aliados',
    templateUrl: './admin-aliados.component.html',
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
export class AdminAliadosComponent extends BaseCrudComponent<any> implements OnInit {

    public aliados: any = { data: [] };
    public aliado: any = {};

    constructor(
        apiService: ApiService,
        alertService: AlertService,
        modalManager: ModalManagerService,
    ) {
        super(apiService, alertService, modalManager, {
            endpoint: 'aliado',
            itemsProperty: 'aliados',
            itemProperty: 'aliado',
            reloadAfterSave: true,
            reloadAfterDelete: true,
            messages: {
                created: 'El aliado fue añadido exitosamente.',
                updated: 'El aliado fue guardado exitosamente.',
                deleted: 'El aliado fue eliminado exitosamente.',
                createTitle: 'Aliado creado',
                updateTitle: 'Aliado actualizado',
                deleteTitle: 'Aliado eliminado',
                deleteConfirm: '¿Desea eliminar el aliado?',
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
        this.apiService.getAll('aliados', this.filtros)
            .pipe(this.untilDestroyed())
            .subscribe(aliados => {
                this.aliados = aliados;
                this.loading = false;
            }, error => {
                this.alertService.error(error);
                this.loading = false;
            });
    }
}
