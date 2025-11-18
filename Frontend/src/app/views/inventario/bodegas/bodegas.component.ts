import { Component, OnInit, TemplateRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { Router, ActivatedRoute } from '@angular/router';

import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { ModalManagerService } from '@services/modal-manager.service';
import { PaginationComponent } from '@shared/parts/pagination/pagination.component';
import { BaseFilteredPaginatedModalComponent } from '@shared/base/base-filtered-paginated-modal.component';

@Component({
    selector: 'app-bodegas',
    templateUrl: './bodegas.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, PaginationComponent],

})
export class BodegasComponent extends BaseFilteredPaginatedModalComponent implements OnInit {

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
        super(apiService, alertService, modalManager);
    }

    protected aplicarFiltros(): void {
        this.loadAll();
    }

    ngOnInit() {
        this.filtros.estado = '';
        this.filtros.buscador = '';
        this.filtros.orden = 'nombre';
        this.filtros.direccion = 'desc';
        this.filtros.paginate = 10;
        
        this.loadAll();

    }

    public loadAll(){
        this.loading = true;
        this.apiService.getAll('bodegas', this.filtros)
            .pipe(this.untilDestroyed())
            .subscribe(bodegas => {
                this.bodegas = bodegas;
                this.loading = false;
            }, error => {this.alertService.error(error); this.loading = false; });
    }

    // setPagination() ahora se hereda de BaseFilteredPaginatedComponent

    override openModal(template: TemplateRef<any>, bodega:any) {
        this.bodega = bodega;
        if(!this.bodega.id){
            this.bodega.id_sucursal = this.apiService.auth_user().id_sucursal;
            this.bodega.id_empresa = this.apiService.auth_user().id_empresa;
            this.bodega.activo = 1;
        }

        this.apiService.getAll('sucursales/list')
            .pipe(this.untilDestroyed())
            .subscribe(sucursales => {
                this.sucursales = sucursales;
            }, error => {this.alertService.error(error); this.loading = false; });

        super.openModal(template);
    }

    public delete(id:number) {
        if (confirm('¿Desea eliminar el Registro?')) {
            this.apiService.delete('bodega/', id)
                .pipe(this.untilDestroyed())
                .subscribe(data => {
                for (let i = 0; i < this.bodegas.data.length; i++) { 
                    if (this.bodegas.data[i].id == data.id )
                        this.bodegas.data.splice(i, 1);
                }
            }, error => {this.alertService.error(error); });
               
        }
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

    
    public onSubmit() {
          this.saving = true;
          this.apiService.store('bodega', this.bodega)
              .pipe(this.untilDestroyed())
              .subscribe(bodega => {
              if (!this.bodega.id) {
                    this.bodegas.data.push(bodega);
                    this.alertService.success('Bodega guardada', 'La bodega fue añadida exitosamente.');
              }
              this.bodega = {};
              this.saving = false;
              this.closeModal();
          },error => {this.alertService.error(error); this.saving = false; });
      }

}
