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
    selector: 'app-empresas',
    templateUrl: './empresas.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, PaginationComponent],
    
})

export class EmpresasComponent extends BaseCrudComponent<any> implements OnInit {

    public empresas: any = {};
    public empresa: any = {};

    constructor(
        apiService: ApiService,
        alertService: AlertService,
        modalManager: ModalManagerService
    ){
        super(apiService, alertService, modalManager, {
            endpoint: 'empresa',
            itemsProperty: 'empresas',
            itemProperty: 'empresa',
            reloadAfterSave: true,
            reloadAfterDelete: false,
            messages: {
                created: 'La empresa fue añadida exitosamente.',
                updated: 'La empresa fue guardada exitosamente.',
                deleted: 'Empresa eliminada exitosamente.',
                createTitle: 'Empresa creada',
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
        this.filtros.forma_pago = '';
        this.filtros.plan = '';
        this.filtros.buscador = '';
        this.filtros.orden = 'id';
        this.filtros.direccion = 'asc';
        this.filtros.paginate = 10;

        this.loading = true;
        this.filtrarEmpresas();
    }

    public filtrarEmpresas(){
        this.loading = true;
        this.apiService.getAll('empresas', this.filtros)
            .pipe(this.untilDestroyed())
            .subscribe(empresas => { 
                this.empresas = empresas;
                this.loading = false;
            }, error => {this.alertService.error(error); this.loading = false;});
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

    public setEstado(empresa: any){
        this.onSubmit(empresa, true);
    }

    public override delete(id: number) {
        super.delete(id);
    }

    public openFilter(template: TemplateRef<any>) {
        this.openModal(template, undefined, { class: 'modal-md', backdrop: 'static' });
    }
}
