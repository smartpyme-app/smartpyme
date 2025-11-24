import { Component, OnInit, TemplateRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { PaginationComponent } from '@shared/parts/pagination/pagination.component';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { ModalManagerService } from '@services/modal-manager.service';
import { BaseCrudComponent } from '@shared/base/base-crud.component';


@Component({
    selector: 'app-organizacion-empresas',
    templateUrl: './organizacion-empresas.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, PaginationComponent],
    
})

export class OrganizacionEmpresasComponent extends BaseCrudComponent<any> implements OnInit {

    public empresas:any = {};
    public empresasList:any = [];
    public empresa:any = {};

    constructor(
        apiService: ApiService, 
        alertService: AlertService,
        modalManager: ModalManagerService
    ){
        super(apiService, alertService, modalManager, {
            endpoint: 'licencia/empresa',
            itemsProperty: 'empresas',
            itemProperty: 'empresa',
            reloadAfterSave: true,
            reloadAfterDelete: false,
            messages: {
                created: 'La empresa fue añadida exitosamente.',
                updated: 'La empresa fue guardada exitosamente.',
                deleted: 'Empresa eliminada exitosamente.',
                createTitle: 'Empresa agregada',
                updateTitle: 'Empresa guardada',
                deleteTitle: 'Empresa eliminada',
                deleteConfirm: '¿Desea eliminar el Registro?'
            }
        });
    }

    protected aplicarFiltros(): void {
        this.filtrarEmpresas();
    }

    ngOnInit() {
        this.loadAll();
    }

    public override loadAll() {
        this.filtros.activo = '';
        this.filtros.buscador = '';
        this.filtros.orden = 'id';
        this.filtros.direccion = 'asc';
        this.filtros.id_licencia = this.apiService.auth_user().empresa.licencia.id;
        this.filtros.paginate = 10;

        this.loading = true;
        this.filtrarEmpresas();
    }

    public filtrarEmpresas(){
        this.apiService.getAll('licencias/empresas', this.filtros)
            .pipe(this.untilDestroyed())
            .subscribe({
                next: (empresas) => {
                    this.empresas = empresas;
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

        this.filtrarEmpresas();
    }

    public setEstado(empresa:any){
        this.apiService.store('empresa', empresa)
            .pipe(this.untilDestroyed())
            .subscribe({
                next: () => {
                    this.alertService.success('Empresa guardada', 'La empresa fue guardada exitosamente.');
                },
                error: (error) => {
                    this.alertService.error(error);
                }
            });
    }

    public override delete(id:number) {
        if (confirm('¿Desea eliminar el Registro?')) {
            this.apiService.delete('empresa/', id)
                .pipe(this.untilDestroyed())
                .subscribe({
                    next: (data) => {
                        for (let i = 0; i < this.empresas['data'].length; i++) { 
                            if (this.empresas['data'][i].id == data.id )
                                this.empresas['data'].splice(i, 1);
                        }
                    },
                    error: (error) => {
                        this.alertService.error(error);
                    }
                });
        }
    }

    override openModal(template: TemplateRef<any>, empresa?: any) {
        this.empresa = empresa || {};

        this.apiService.getAll('empresas/list')
            .pipe(this.untilDestroyed())
            .subscribe({
                next: (empresasList) => {
                    this.empresasList = empresasList;
                },
                error: (error) => {
                    this.alertService.error(error);
                }
            });
        
        if (!this.empresa.id) {
            this.empresa.id_licencia = this.apiService.auth_user().empresa.licencia.id;
        }
        super.openModal(template, { class: 'modal-md', backdrop: 'static' });
    }
}
