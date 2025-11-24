import { Component, OnInit, TemplateRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { Router, ActivatedRoute } from '@angular/router';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { ModalManagerService } from '@services/modal-manager.service';
import { PaginationComponent } from '@shared/parts/pagination/pagination.component';
import { BaseCrudComponent } from '@shared/base/base-crud.component';

@Component({
    selector: 'app-bodegas',
    templateUrl: './bodegas.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, PaginationComponent],

})
export class BodegasComponent extends BaseCrudComponent<any> implements OnInit {

    public bodegas:any = [];
    public sucursales:any = [];
    public bodega:any = {};

    constructor( 
        apiService: ApiService, 
        alertService: AlertService,
        modalManager: ModalManagerService,
        private route: ActivatedRoute, 
        private router: Router
    ) {
        super(apiService, alertService, modalManager, {
            endpoint: 'bodega',
            itemsProperty: 'bodegas',
            itemProperty: 'bodega',
            reloadAfterSave: false,
            reloadAfterDelete: false,
            initNewItem: (item) => {
                item.id_sucursal = apiService.auth_user().id_sucursal;
                item.id_empresa = apiService.auth_user().id_empresa;
                item.activo = 1;
                return item;
            },
            afterSave: (item, isNew) => {
                if (isNew) {
                    this.bodegas.data.push(item);
                }
                this.bodega = {};
            }
        });
    }

    protected aplicarFiltros(): void {
        this.loading = true;
        this.apiService.getAll('bodegas', this.filtros)
            .pipe(this.untilDestroyed())
            .subscribe(bodegas => {
                this.bodegas = bodegas;
                this.loading = false;
            }, error => {this.alertService.error(error); this.loading = false; });
    }

    ngOnInit() {
        this.filtros.estado = '';
        this.filtros.buscador = '';
        this.filtros.orden = 'nombre';
        this.filtros.direccion = 'desc';
        this.filtros.paginate = 10;
        
        this.loadAll();
    }

    override openModal(template: TemplateRef<any>, bodega?: any) {
        // Cargar sucursales antes de abrir el modal
        this.apiService.getAll('sucursales/list')
            .pipe(this.untilDestroyed())
            .subscribe(sucursales => {
                this.sucursales = sucursales;
            }, error => {this.alertService.error(error); this.loading = false; });

        super.openModal(template, bodega);
    }

    public setEstado(bodega:any){
        this.apiService.store('bodega', bodega)
            .pipe(this.untilDestroyed())
            .subscribe(bodega => { 
            if(bodega.activo == '1'){
                this.alertService.success('Bodega activada', 'La bodega fue activada exitosamente.');
            }else{
                this.alertService.success('Bodega desactivada', 'La bodega fue desactivada exitosamente.');
            }
        }, error => {this.alertService.error(error); this.loading = false;});
    }

}
