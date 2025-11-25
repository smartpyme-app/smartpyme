import { Component, OnInit, TemplateRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { Router, ActivatedRoute } from '@angular/router';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { ModalManagerService } from '@services/modal-manager.service';
import { BaseCrudComponent } from '@shared/base/base-crud.component';

@Component({
    selector: 'app-sucursales',
    templateUrl: './sucursales.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule],
    
})
export class SucursalesComponent extends BaseCrudComponent<any> implements OnInit {

    public sucursales:any = [];
    public sucursal:any = {};
    public sucursales_activas:any = 0;

  	constructor( 
  	    apiService: ApiService,
        alertService: AlertService,
        modalManager: ModalManagerService,
  	    private route: ActivatedRoute, 
        private router: Router
  	) {
        super(apiService, alertService, modalManager, {
            endpoint: 'sucursal',
            itemsProperty: 'sucursales',
            itemProperty: 'sucursal',
            reloadAfterSave: false,
            reloadAfterDelete: false,
            initNewItem: (item) => {
                item.id_empresa = apiService.auth_user().id_empresa;
                item.activo = 1;
                return item;
            },
            afterSave: (item, isNew) => {
                if (isNew) {
                    this.sucursales.data.push(item);
                }
                this.contarActivos();
                this.sucursal = {};
            },
            afterDelete: () => {
                this.contarActivos();
            }
        });
    }

  	ngOnInit() {
        this.filtros.estado = '';
        this.filtros.buscador = '';
        this.filtros.orden = 'nombre';
        this.filtros.direccion = 'desc';
        this.filtros.paginate = 10;
  	    
        this.loadAll();
  	}

    public override loadAll() {
        this.loading = true;
        this.apiService.getAll('sucursales', this.filtros)
            .pipe(this.untilDestroyed())
            .subscribe(sucursales => {
            this.sucursales = sucursales;
            this.loading = false;
            this.contarActivos();
        }, error => {this.alertService.error(error); this.loading = false; });
    }

    protected aplicarFiltros(): void {
        this.loadAll();
    }

    override openModal(template: TemplateRef<any>, sucursal?: any) {
        super.openModal(template, sucursal, {class: 'modal-lg'});
    }

    public contarActivos(){
        this.sucursales_activas = this.sucursales.data?.filter((item:any) => item.activo == '1').length || 0;
    }

    public setEstado(sucursal:any){
        this.apiService.store('sucursal', sucursal)
            .pipe(this.untilDestroyed())
            .subscribe(sucursal => { 
            if(sucursal.activo == '1'){
                this.alertService.success('Sucursal activada', 'La sucursal fue activada exitosamente.');
            }else{
                this.alertService.success('Sucursal desactivada', 'La sucursal fue desactivada exitosamente.');
            }
            this.contarActivos();
        }, error => {this.alertService.error(error); this.loading = false;});
    }

}
