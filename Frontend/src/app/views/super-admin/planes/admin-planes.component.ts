import { Component, OnInit, TemplateRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { Router, ActivatedRoute } from '@angular/router';
import { NgSelectModule } from '@ng-select/ng-select';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { ModalManagerService } from '@services/modal-manager.service';
import { BaseCrudComponent } from '@shared/base/base-crud.component';

@Component({
    selector: 'app-admin-planes',
    templateUrl: './admin-planes.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, NgSelectModule],
    
})
export class AdminPlanesComponent extends BaseCrudComponent<any> implements OnInit {

    public planes:any = [];
    public productos:any = [];
    public plan:any = {};

  	constructor( 
  	    apiService: ApiService,
        alertService: AlertService,
        modalManager: ModalManagerService,
  	    private route: ActivatedRoute, 
        private router: Router
  	) {
        super(apiService, alertService, modalManager, {
            endpoint: 'plan',
            itemsProperty: 'planes',
            itemProperty: 'plan',
            reloadAfterSave: false,
            reloadAfterDelete: false,
            messages: {
                created: 'El plan fue añadida exitosamente.',
                updated: 'El plan fue guardado exitosamente.',
                createTitle: 'Plan guardado',
                updateTitle: 'Plan actualizado'
            },
            initNewItem: (item) => {
                item.activo = 1;
                return item;
            },
            afterSave: () => {
                this.plan = {};
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
        this.apiService.getAll('planes', this.filtros)
            .pipe(this.untilDestroyed())
            .subscribe(planes => {
                this.planes = planes;
                this.loading = false;
            }, error => {this.alertService.error(error); this.loading = false; });
    }

    protected aplicarFiltros(): void {
        this.loadAll();
    }

    public override openModal(template: TemplateRef<any>, plan?: any) {
        if(!this.productos.length){
            this.apiService.getAll('productos/list')
                .pipe(this.untilDestroyed())
                .subscribe(productos => {
                    this.productos = productos;
                }, error => {this.alertService.error(error);});
        }
        super.openModal(template, plan);
    }

    public override delete(item: any | number): void {
        const itemToDelete = typeof item === 'number' ? item : (item as any).id;
        
        if (!confirm('¿Desea eliminar el Registro?')) {
            return;
        }

        this.loading = true;
        this.apiService.delete('plan/', itemToDelete)
            .pipe(this.untilDestroyed())
            .subscribe({
                next: (deletedItem: any) => {
                    const index = this.planes.data?.findIndex((p: any) => p.id === deletedItem.id);
                    if (index !== -1 && index >= 0) {
                        this.planes.data.splice(index, 1);
                    }
                    this.alertService.success('Registro eliminado', 'El registro fue eliminado exitosamente.');
                    this.loading = false;
                },
                error: (error: any) => {
                    this.alertService.error(error);
                    this.loading = false;
                }
            });
    }

}
