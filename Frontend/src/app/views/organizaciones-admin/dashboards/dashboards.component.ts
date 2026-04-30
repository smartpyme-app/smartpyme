import { Component, OnInit, TemplateRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { NgSelectModule } from '@ng-select/ng-select';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { ModalManagerService } from '@services/modal-manager.service';
import { BaseCrudComponent } from '@shared/base/base-crud.component';
import { PaginationComponent } from '@shared/parts/pagination/pagination.component';


@Component({
    selector: 'app-dashboards',
    templateUrl: './dashboards.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, NgSelectModule, TooltipModule, PaginationComponent],
    
})

export class DashboardsComponent extends BaseCrudComponent<any> implements OnInit {

    public dashboards:any = {};
    public dashboard:any = {};
    public empresas:any = [];

    constructor(
        apiService: ApiService, 
        alertService: AlertService,
        modalManager: ModalManagerService
    ){
        super(apiService, alertService, modalManager, {
            endpoint: 'dashboard',
            itemsProperty: 'dashboards',
            itemProperty: 'dashboard',
            reloadAfterSave: true,
            reloadAfterDelete: false,
            messages: {
                created: 'La dashboard fue añadido exitosamente.',
                updated: 'La dashboard fue guardado exitosamente.',
                deleted: 'Dashboard eliminado exitosamente.',
                createTitle: 'Dashboard creado',
                updateTitle: 'Dashboard guardado',
                deleteTitle: 'Dashboard eliminado',
                deleteConfirm: '¿Desea eliminar el Registro?'
            }
        });
    }

    protected aplicarFiltros(): void {
        this.filtrarDashboards();
    }

    ngOnInit() {
        this.loadAll();
    }

    public override loadAll() {
        this.filtros.id_empresa = '';
        this.filtros.tipo = '';
        this.filtros.buscador = '';
        this.filtros.orden = 'id';
        this.filtros.direccion = 'asc';
        this.filtros.paginate = 10;

        this.loading = true;
        this.filtrarDashboards();
        this.apiService.getAll('empresas/list').pipe(this.untilDestroyed()).subscribe({
            next: (empresas) => {
                this.empresas = empresas;
            },
            error: (error) => {
                this.alertService.error(error);
            }
        });
    }

    public filtrarDashboards(){
        this.apiService.getAll('dashboards', this.filtros).pipe(this.untilDestroyed()).subscribe({
            next: (dashboards) => {
                this.dashboards = dashboards;
                this.loading = false;
            },
            error: (error) => {
                this.alertService.error(error);
                this.loading = false;
            }
        });
    }

    public setOrden(columna: string) {
        if (this.filtros.orden === columna) {
          this.filtros.direccion = this.filtros.direccion === 'asc' ? 'desc' : 'asc';
        } else {
          this.filtros.orden = columna;
          this.filtros.direccion = 'asc';
        }

        this.loadAll();
    }

    public override delete(id:number) {
        if (confirm('¿Desea eliminar el Registro?')) {
            this.apiService.delete('dashboard/', id).pipe(this.untilDestroyed()).subscribe({
                next: (data) => {
                    for (let i = 0; i < this.dashboards['data'].length; i++) { 
                        if (this.dashboards['data'][i].id == data.id )
                            this.dashboards['data'].splice(i, 1);
                    }
                },
                error: (error) => {
                    this.alertService.error(error);
                }
            });
        }
    }

    public override openModal(template: TemplateRef<any>, dashboard?: any) {
        this.dashboard = dashboard || {};
        if (!this.dashboard.id) {
            // this.dashboard.tipo = 'Administrador';
        }
        super.openModal(template, {
            size: 'lg',
            backdrop: 'static'
        });
    }
}
