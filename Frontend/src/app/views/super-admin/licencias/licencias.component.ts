import { Component, OnInit, TemplateRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { NgSelectModule } from '@ng-select/ng-select';
import { PaginationComponent } from '@shared/parts/pagination/pagination.component';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { ModalManagerService } from '@services/modal-manager.service';
import { BasePaginatedModalComponent, PaginatedResponse } from '@shared/base/base-paginated-modal.component';

@Component({
    selector: 'app-licencias',
    templateUrl: './licencias.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, NgSelectModule, PaginationComponent],
    
})

export class LicenciasComponent extends BasePaginatedModalComponent implements OnInit {

    public licencias: PaginatedResponse<any> = {} as PaginatedResponse;
    public empresas:any = [];
    public licencia:any = {};
    public override filtros:any = {};

    constructor(
        apiService: ApiService, 
        alertService: AlertService,
        modalManager: ModalManagerService
    ){
        super(apiService, alertService, modalManager);
    }

    protected getPaginatedData(): PaginatedResponse | null {
        return this.licencias;
    }

    protected setPaginatedData(data: PaginatedResponse): void {
        this.licencias = data;
    }

    ngOnInit() {
        this.apiService.getAll('empresas/list').subscribe(empresas => { 
            this.empresas = empresas;
        }, error => {this.alertService.error(error); });

        this.loadAll();
    }

    public loadAll() {
        this.filtros.activo = '';
        this.filtros.buscador = '';
        this.filtros.orden = 'id';
        this.filtros.direccion = 'asc';
        this.filtros.paginate = 10;

        this.loading = true;
        this.filtrarLicencias();
    }

    public filtrarLicencias(){
        this.apiService.getAll('licencias', this.filtros).subscribe(licencias => { 
            this.licencias = licencias;
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

        this.filtrarLicencias();
    }


    public setEstado(licencia:any){
        this.apiService.store('licencia', licencia).subscribe(licencia => { 
            this.alertService.success('Licencia guardada', 'La licencia fue guardada exitosamente.');
        }, error => {this.alertService.error(error); });
    }


    public delete(id:number) {
        if (confirm('¿Desea eliminar el Registro?')) {
            this.apiService.delete('licencia/', id) .subscribe(data => {
                for (let i = 0; i < this.licencias['data'].length; i++) { 
                    if (this.licencias['data'][i].id == data.id )
                        this.licencias['data'].splice(i, 1);
                }
            }, error => {this.alertService.error(error); });
                   
        }

    }

    public onSubmit() {
        this.saving = true;
        this.apiService.store('licencia', this.licencia).subscribe(licencia => {
            this.loadAll();
            this.saving = false;
            if(!this.licencia.id){
                this.alertService.success('Licencia creada', 'La licencia fue añadida exitosamente.');
            }else{
                this.alertService.success('Licencia guardada', 'La licencia fue guardada exitosamente.');
            }
            this.closeModal();
        },error => {this.alertService.error(error); this.saving = false; });

    }

    // setPagination() ahora se hereda de BasePaginatedComponent
    // openModal() ahora se hereda de BasePaginatedModalComponent

    override openModal(template: TemplateRef<any>, licencia:any) {
        this.licencia = licencia;
        if (!this.licencia.id) {
            // this.licencia.industria = '';
        }
        super.openModal(template, { class: 'modal-md', backdrop: 'static' });
    }


}
