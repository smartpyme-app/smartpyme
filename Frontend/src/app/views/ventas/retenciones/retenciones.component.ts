import { Component, OnInit, TemplateRef, ChangeDetectionStrategy, ChangeDetectorRef, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { NgSelectModule } from '@ng-select/ng-select';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { ModalManagerService } from '@services/modal-manager.service';
import { BaseCrudComponent } from '@shared/base/base-crud.component';
import { FilterPipe } from '@pipes/filter.pipe';
import { PaginationComponent } from '@shared/parts/pagination/pagination.component';

import Swal from 'sweetalert2';

@Component({
    selector: 'app-retenciones',
    templateUrl: './retenciones.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, FilterPipe, NgSelectModule, PaginationComponent],
    changeDetection: ChangeDetectionStrategy.OnPush
})

export class RetencionesComponent extends BaseCrudComponent<any> implements OnInit {

    public retenciones:any = [];
    public retencion:any = {};
    public catalogo:any = [];
    public filtro:any = {};
    public filtrado:boolean = false;

    constructor(
        apiService: ApiService,
        alertService: AlertService,
        modalManager: ModalManagerService,
        private cdr: ChangeDetectorRef
    ){
        super(apiService, alertService, modalManager, {
            endpoint: 'retencion',
            itemsProperty: 'retenciones',
            itemProperty: 'retencion',
            reloadAfterSave: false,
            reloadAfterDelete: false,
            messages: {
                created: 'El retencion fue añadido exitosamente.',
                updated: 'El retencion fue guardado exitosamente.',
                createTitle: 'Impuesto creado',
                updateTitle: 'Impuesto guardado'
            },
            initNewItem: (item) => {
                item.id_empresa = apiService.auth_user().id_empresa;
                item.enable = true;
                return item;
            }
        });
    }

    ngOnInit() {
        this.loadAll();
    }

    public override loadAll() {
        this.loading = true;
        this.filtro.estado = '';
        this.apiService.getAll('retenciones')
            .pipe(this.untilDestroyed())
            .subscribe(retenciones => { 
                this.retenciones = retenciones;
                this.loading = false;
                this.filtrado = false;
                this.cdr.markForCheck();
            }, error => {this.alertService.error(error); this.loading = false; this.cdr.markForCheck(); });
    }

    protected aplicarFiltros(): void {
        this.loadAll();
    }

    public override openModal(template: TemplateRef<any>, retencion?: any) {
        // Cargar catálogo antes de abrir el modal
        this.apiService.getAll('catalogo/list')
            .pipe(this.untilDestroyed())
            .subscribe(catalogo => {
                this.catalogo = catalogo;
                this.cdr.markForCheck();
            }, error => {this.alertService.error(error); this.cdr.markForCheck();});
        
        super.openModal(template, retencion, {class: 'modal-md', backdrop: 'static'});
    }

    public setEstado(retencion:any){
        this.retencion = retencion;
        this.onSubmit();
    }

    public override delete(item: any | number): void {
        const itemToDelete = typeof item === 'number' ? item : (item as any).id;
        
        Swal.fire({
          title: '¿Estás seguro?',
          text: '¡No podrás revertir esto!',
          icon: 'warning',
          showCancelButton: true,
          confirmButtonText: 'Sí, eliminarlo',
          cancelButtonText: 'Cancelar'
        }).then((result) => {
          if (result.isConfirmed) {
                this.loading = true;
                this.apiService.delete('retencion/', itemToDelete)
                    .pipe(this.untilDestroyed())
                    .subscribe(data => {
                        const index = this.retenciones.findIndex((r: any) => r.id === data.id);
                        if (index !== -1) {
                            this.retenciones.splice(index, 1);
                        }
                        this.alertService.success('Registro eliminado', 'El registro fue eliminado exitosamente.');
                        this.loading = false;
                        this.cdr.markForCheck();
                    }, error => {
                        this.alertService.error(error);
                        this.loading = false;
                        this.cdr.markForCheck();
                    });
          }
        });
    }

}
