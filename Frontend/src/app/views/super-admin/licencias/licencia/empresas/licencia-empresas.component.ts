import { Component, OnInit, TemplateRef, Input } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { NgSelectModule } from '@ng-select/ng-select';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { ModalManagerService } from '@services/modal-manager.service';
import { BaseCrudComponent } from '@shared/base/base-crud.component';

@Component({
    selector: 'app-licencia-empresas',
    templateUrl: './licencia-empresas.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, NgSelectModule],

})
export class LicenciaEmpresasComponent extends BaseCrudComponent<any> implements OnInit {

    @Input() licencia: any = {};
    public empresas: any = [];
    public empresa: any = {};
    public buscador: string = '';

    constructor(
        apiService: ApiService,
        alertService: AlertService,
        modalManager: ModalManagerService
    ){
        super(apiService, alertService, modalManager, {
            endpoint: 'licencia/empresa',
            itemsProperty: 'empresas',
            itemProperty: 'empresa',
            reloadAfterSave: false,
            reloadAfterDelete: false,
            messages: {
                created: 'El empresa fue agregado exitosamente.',
                updated: 'El empresa fue actualizado exitosamente.',
                deleted: 'El empresa fue eliminado exitosamente.',
                createTitle: 'Empresa agregado',
                updateTitle: 'Empresa actualizado',
                deleteTitle: 'Empresa eliminado',
                deleteConfirm: '¿Desea eliminar el Registro?'
            },
            beforeSave: (item) => {
                item.id_licencia = this.licencia.id;
                return item;
            },
            afterSave: (item, isNew) => {
                if (isNew && this.licencia.empresas) {
                    this.licencia.empresas.push(item);
                }
                this.empresa = {};
            },
            afterDelete: (item) => {
                if (this.licencia.empresas) {
                    const index = this.licencia.empresas.findIndex((e: any) => e.id === item.id);
                    if (index !== -1) {
                        this.licencia.empresas.splice(index, 1);
                    }
                }
            }
        });
    }

    protected aplicarFiltros(): void {
        this.loadAll();
    }

    ngOnInit() {
        this.loadAll();
    }

    public override loadAll(){
        this.loading = true;
        this.apiService.getAll('empresas/list')
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

    public override openModal(template: TemplateRef<any>, empresa?: any) {
        if (empresa) {
            this.empresa = { ...empresa };
        } else {
            this.empresa = {};
        }
        this.empresa.id_empresa = '';
        super.openModal(template, undefined, { class: 'modal-md' });
    }

    // empresa
    public setEmpresa(empresa: any){
        if(!this.empresa.id_empresa){
            this.empresas.push(empresa);
        }
        this.empresa.id_empresa = empresa.id;
    }

    public override delete(empresa: any) {
        super.delete(empresa.id || empresa);
                }
}
