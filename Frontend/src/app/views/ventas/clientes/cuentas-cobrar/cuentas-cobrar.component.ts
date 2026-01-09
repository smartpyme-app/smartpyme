import { Component, OnInit, TemplateRef, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { PaginationComponent } from '@shared/parts/pagination/pagination.component';
import { BaseCrudComponent } from '@shared/base/base-crud.component';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { ModalManagerService } from '@services/modal-manager.service';

@Component({
    selector: 'app-cuentas-cobrar',
    templateUrl: './cuentas-cobrar.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, PaginationComponent],
    changeDetection: ChangeDetectionStrategy.OnPush,
    
})

export class CuentasCobrarComponent extends BaseCrudComponent<any> implements OnInit {

    public cobros: any = {};
    public buscador: any = '';

    constructor(
        apiService: ApiService,
        alertService: AlertService,
        modalManager: ModalManagerService,
        private cdr: ChangeDetectorRef
    ){
        super(apiService, alertService, modalManager, {
            endpoint: 'venta',
            itemsProperty: 'cobros',
            itemProperty: 'venta',
            reloadAfterSave: false,
            reloadAfterDelete: false,
            messages: {
                created: 'La venta fue actualizada exitosamente.',
                updated: 'La venta fue actualizada exitosamente.',
                deleted: 'Venta eliminada exitosamente.',
                createTitle: 'Venta actualizada',
                updateTitle: 'Venta actualizada',
                deleteTitle: 'Venta eliminada',
                deleteConfirm: '¿Desea eliminar el Registro?'
            }
        });
    }

    protected aplicarFiltros(): void {
        this.loadAll();
    }

    ngOnInit() {
        this.loadAll();
    }

    public override loadAll() {
        this.loading = true;
        this.cdr.markForCheck();
        this.apiService.getAll('cuentas-cobrar')
            .pipe(this.untilDestroyed())
            .subscribe({
                next: (cobros) => {
                    this.cobros = cobros;
                    this.loading = false;
                    this.cdr.markForCheck();
                },
                error: (error) => {
                    this.alertService.error(error);
                    this.loading = false;
                    this.cdr.markForCheck();
                }
            });
    }

    public search(){
        if(this.buscador && this.buscador.length > 2) {
            this.loading = true;
            this.cdr.markForCheck();
            this.apiService.read('cuentas-cobrar/buscar/', this.buscador)
                .pipe(this.untilDestroyed())
                .subscribe({
                    next: (cobros) => {
                        this.cobros = cobros;
                        this.loading = false;
                        this.cdr.markForCheck();
                    },
                    error: (error) => {
                        this.alertService.error(error);
                        this.loading = false;
                        this.cdr.markForCheck();
                    }
                });
        } else {
            this.loadAll();
        }
    }

    public setEstado(venta: any, estado: string){
        venta.estado = estado;
        this.onSubmit(venta, true);
    }
}
