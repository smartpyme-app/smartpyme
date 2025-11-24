import { Component, OnInit, TemplateRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { NgSelectModule } from '@ng-select/ng-select';
import { PopoverModule } from 'ngx-bootstrap/popover';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { ModalManagerService } from '@services/modal-manager.service';
import { BaseCrudComponent } from '@shared/base/base-crud.component';
import { FilterPipe } from '@pipes/filter.pipe';
import { PaginationComponent } from '@shared/parts/pagination/pagination.component';

import Swal from 'sweetalert2';

@Component({
    selector: 'app-impuestos',
    templateUrl: './impuestos.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, NgSelectModule, FilterPipe, PaginationComponent, PopoverModule, TooltipModule],
    
})

export class ImpuestosComponent extends BaseCrudComponent<any> implements OnInit {

    public impuestos:any = [];
    public impuesto:any = {};
    public catalogo:any = [];
    public filtro:any = {};
    public filtrado:boolean = false;

    constructor(
        apiService: ApiService,
        alertService: AlertService,
        modalManager: ModalManagerService
    ){
        super(apiService, alertService, modalManager, {
            endpoint: 'impuesto',
            itemsProperty: 'impuestos',
            itemProperty: 'impuesto',
            reloadAfterSave: false,
            reloadAfterDelete: false,
            messages: {
                created: 'El impuesto fue añadido exitosamente.',
                updated: 'El impuesto fue guardado exitosamente.',
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
        this.apiService.getAll('impuestos')
            .pipe(this.untilDestroyed())
            .subscribe(impuestos => { 
                this.impuestos = impuestos;
                this.loading = false;
                this.filtrado = false;
            }, error => {this.alertService.error(error); this.loading = false; });
    }

    protected aplicarFiltros(): void {
        this.loadAll();
    }

    public override openModal(template: TemplateRef<any>, impuesto?: any) {
        // Cargar catálogo antes de abrir el modal
        this.apiService.getAll('catalogo/list')
            .pipe(this.untilDestroyed())
            .subscribe(catalogo => {
                this.catalogo = catalogo;
            }, error => {this.alertService.error(error);});
        
        super.openModal(template, impuesto, {class: 'modal-md', backdrop: 'static'});
    }

    public setEstado(impuesto:any){
        this.impuesto = impuesto;
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
                this.apiService.delete('impuesto/', itemToDelete)
                    .pipe(this.untilDestroyed())
                    .subscribe(data => {
                        const index = this.impuestos.findIndex((i: any) => i.id === data.id);
                        if (index !== -1) {
                            this.impuestos.splice(index, 1);
                        }
                        this.alertService.success('Registro eliminado', 'El registro fue eliminado exitosamente.');
                        this.loading = false;
                    }, error => {
                        this.alertService.error(error);
                        this.loading = false;
                    });
          }
        });
    }

}
