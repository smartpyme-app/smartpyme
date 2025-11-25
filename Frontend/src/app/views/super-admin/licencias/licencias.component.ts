import { Component, OnInit, TemplateRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { NgSelectModule } from '@ng-select/ng-select';
import { PaginationComponent } from '@shared/parts/pagination/pagination.component';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { ModalManagerService } from '@services/modal-manager.service';
import { BaseCrudComponent } from '@shared/base/base-crud.component';

@Component({
    selector: 'app-licencias',
    templateUrl: './licencias.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, NgSelectModule, PaginationComponent],
    
})

export class LicenciasComponent extends BaseCrudComponent<any> implements OnInit {

    public licencias:any = {};
    public empresas:any = [];
    public licencia:any = {};

    constructor(
        apiService: ApiService, 
        alertService: AlertService,
        modalManager: ModalManagerService
    ){
        super(apiService, alertService, modalManager, {
            endpoint: 'licencia',
            itemsProperty: 'licencias',
            itemProperty: 'licencia',
            reloadAfterSave: true,
            reloadAfterDelete: false,
            messages: {
                created: 'La licencia fue añadida exitosamente.',
                updated: 'La licencia fue guardada exitosamente.',
                deleted: 'Licencia eliminada exitosamente.',
                createTitle: 'Licencia creada',
                updateTitle: 'Licencia guardada',
                deleteTitle: 'Licencia eliminada',
                deleteConfirm: '¿Desea eliminar el Registro?'
            }
        });
    }

    protected aplicarFiltros(): void {
        this.filtrarLicencias();
    }

    ngOnInit() {
        this.apiService.getAll('empresas/list')
            .pipe(this.untilDestroyed())
            .subscribe({
                next: (empresas) => {
                    this.empresas = empresas;
                },
                error: (error) => {
                    this.alertService.error(error);
                }
            });

        this.loadAll();
    }

    public override loadAll() {
        this.filtros.activo = '';
        this.filtros.buscador = '';
        this.filtros.orden = 'id';
        this.filtros.direccion = 'asc';
        this.filtros.paginate = 10;

        this.loading = true;
        this.filtrarLicencias();
    }

    public filtrarLicencias(){
        this.apiService.getAll('licencias', this.filtros)
            .pipe(this.untilDestroyed())
            .subscribe({
                next: (licencias) => {
                    this.licencias = licencias;
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

        this.filtrarLicencias();
    }

    public setEstado(licencia:any){
        this.apiService.store('licencia', licencia)
            .pipe(this.untilDestroyed())
            .subscribe({
                next: () => {
                    this.alertService.success('Licencia guardada', 'La licencia fue guardada exitosamente.');
                },
                error: (error) => {
                    this.alertService.error(error);
                }
            });
    }

    public override delete(id:number) {
        if (confirm('¿Desea eliminar el Registro?')) {
            this.apiService.delete('licencia/', id)
                .pipe(this.untilDestroyed())
                .subscribe({
                    next: (data) => {
                        for (let i = 0; i < this.licencias['data'].length; i++) { 
                            if (this.licencias['data'][i].id == data.id )
                                this.licencias['data'].splice(i, 1);
                        }
                    },
                    error: (error) => {
                        this.alertService.error(error);
                    }
                });
        }
    }

    public override openModal(template: TemplateRef<any>, licencia?: any) {
        this.licencia = licencia || {};
        if (!this.licencia.id) {
            // this.licencia.industria = '';
        }
        super.openModal(template, { class: 'modal-md', backdrop: 'static' });
    }
}
