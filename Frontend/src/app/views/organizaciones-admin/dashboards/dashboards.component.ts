import { Component, OnInit, TemplateRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { NgSelectModule } from '@ng-select/ng-select';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { ModalManagerService } from '@services/modal-manager.service';
import { BasePaginatedModalComponent, PaginatedResponse } from '@shared/base/base-paginated-modal.component';


@Component({
    selector: 'app-dashboards',
    templateUrl: './dashboards.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, NgSelectModule],
    
})

export class DashboardsComponent extends BasePaginatedModalComponent implements OnInit {

    public dashboards: PaginatedResponse<any> = {} as PaginatedResponse;
    public dashboard:any = {};
    public empresas:any = [];
    public override filtros:any = {};

    constructor(
        apiService: ApiService, 
        alertService: AlertService,
        modalManager: ModalManagerService
    ){
        super(apiService, alertService, modalManager);
    }

    protected getPaginatedData(): PaginatedResponse | null {
        return this.dashboards;
    }

    protected setPaginatedData(data: PaginatedResponse): void {
        this.dashboards = data;
    }

    ngOnInit() {
        this.loadAll();
    }

    public loadAll() {
        this.filtros.id_empresa = '';
        this.filtros.tipo = '';
        this.filtros.buscador = '';
        this.filtros.orden = 'id';
        this.filtros.direccion = 'asc';
        this.filtros.paginate = 10;

        this.loading = true;
        this.filtrarDashboards();
        this.apiService.getAll('empresas/list').pipe(this.untilDestroyed()).subscribe(empresas => { 
            this.empresas = empresas;
        }, error => {this.alertService.error(error); });
    }

    public filtrarDashboards(){
        this.apiService.getAll('dashboards', this.filtros).pipe(this.untilDestroyed()).subscribe(dashboards => { 
            this.dashboards = dashboards;
            this.loading = false;
        }, error => {this.alertService.error(error); });
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


    public delete(id:number) {
        if (confirm('¿Desea eliminar el Registro?')) {
            this.apiService.delete('dashboard/', id).pipe(this.untilDestroyed()).subscribe(data => {
                for (let i = 0; i < this.dashboards['data'].length; i++) { 
                    if (this.dashboards['data'][i].id == data.id )
                        this.dashboards['data'].splice(i, 1);
                }
            }, error => {this.alertService.error(error); });
                   
        }

    }

    // setPagination() ahora se hereda de BasePaginatedComponent
    // openModal() ahora se hereda de BasePaginatedModalComponent

    override openModal(template: TemplateRef<any>, dashboard?: any) {
        this.dashboard = dashboard || {};
        if (!this.dashboard.id) {
            // this.dashboard.tipo = 'Administrador';
        }
        super.openModal(template, {
            size: 'lg',
            backdrop: 'static'
        });
    }

    public onSubmit() {
        this.saving = true;
        this.apiService.store('dashboard', this.dashboard).pipe(this.untilDestroyed()).subscribe(dashboard => {
            this.loadAll();
            this.saving = false;
            if(!this.dashboards.id){
                this.alertService.success('Dashboard creado', 'La dashboard fue añadido exitosamente.');
            }else{
                this.alertService.success('Dashboard guardado', 'La dashboard fue guardado exitosamente.');
            }
            this.closeModal();
        },error => {this.alertService.error(error); this.saving = false; });

    }


}
