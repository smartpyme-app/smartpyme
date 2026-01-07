import { Component, OnInit, TemplateRef, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { Router, ActivatedRoute } from '@angular/router';
import { NgSelectModule } from '@ng-select/ng-select';
import { ImportarExcelComponent } from '@shared/parts/importar-excel/importar-excel.component';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { ModalManagerService } from '@services/modal-manager.service';
import { PaginationComponent } from '@shared/parts/pagination/pagination.component';
import { BaseCrudComponent } from '@shared/base/base-crud.component';
import { LazyImageDirective } from '../../../directives/lazy-image.directive';

@Component({
    selector: 'app-servicios',
    templateUrl: './servicios.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, NgSelectModule, ImportarExcelComponent, PaginationComponent, LazyImageDirective],
    changeDetection: ChangeDetectionStrategy.OnPush
})
export class ServiciosComponent extends BaseCrudComponent<any> implements OnInit {

    public servicios:any = [];
    public buscador:any = '';
    public downloading:boolean = false;
    public servicio:any = {};
    public sucursales:any = [];
    public filtrado:boolean = false;
    public categorias:any = [];

    constructor(
        apiService: ApiService, 
        alertService: AlertService,
        modalManager: ModalManagerService,
        private router: Router, 
        private route: ActivatedRoute,
        private cdr: ChangeDetectorRef
    ){
        super(apiService, alertService, modalManager, {
            endpoint: 'servicio',
            itemsProperty: 'servicios',
            itemProperty: 'servicio',
            reloadAfterSave: false,
            reloadAfterDelete: false,
            afterSave: () => {
                this.servicio = {};
            }
        });
    }

    protected aplicarFiltros(): void {
        this.filtrarServicios();
    }

    ngOnInit() {
        this.route.queryParams.pipe(this.untilDestroyed()).subscribe(params => {
            this.filtros = {
                buscador: params['buscador'] || '',
                id_categoria: +params['id_categoria'] || '',
                id_sucursal: +params['id_sucursal'] || '',
                estado: params['estado'] || '',
                orden: params['orden'] || 'id',
                direccion: params['direccion'] || 'desc',
                paginate: params['paginate'] || 10,
                page: params['page'] || 1,
            };

            this.filtrarServicios();
            this.cdr.markForCheck();
        });

        this.apiService.getAll('categorias/list').pipe(this.untilDestroyed()).subscribe(categorias => {
            this.categorias = categorias;
            this.cdr.markForCheck();
        }, error => {this.alertService.error(error); this.cdr.markForCheck();});
    }

    public override loadAll() {
        this.filtros.id_sucursal = '';
        this.filtros.id_categoria = '';
        this.filtros.estado = '';
        this.filtros.buscador = '';
        this.filtros.orden = 'nombre';
        this.filtros.direccion = 'asc';
        this.filtros.paginate = 10;
        this.filtros.page = 1;
        this.loading = true;
        this.filtrarServicios();
    }

    public filtrarServicios(){
        this.router.navigate([], {
            relativeTo: this.route,
            queryParams: this.filtros,
            queryParamsHandling: 'merge',
        });

        this.loading = true;
        if(!this.filtros.id_categoria){
            this.filtros.id_categoria = '';
        }
        this.apiService.getAll('servicios', this.filtros).pipe(this.untilDestroyed()).subscribe(servicios => { 
            this.servicios = servicios;
            this.loading = false;
            this.cdr.markForCheck();
        }, error => {this.alertService.error(error); this.loading = false; this.cdr.markForCheck();});
    }

    public override async delete(item: any | number): Promise<void> {
        const itemToDelete = typeof item === 'number' ? item : (item as any).id;
        
        if (!confirm('¿Desea eliminar el Registro?')) {
            return;
        }

        this.loading = true;
        try {
            const deletedItem = await this.apiService.delete('servicio/', itemToDelete)
                .pipe(this.untilDestroyed())
                .toPromise();
            
            const index = this.servicios.data?.findIndex((s: any) => s.id === deletedItem.id);
            if (index !== -1 && index >= 0) {
                this.servicios.data.splice(index, 1);
            }
            this.alertService.success('Registro eliminado', 'El registro fue eliminado exitosamente.');
            this.cdr.markForCheck();
        } catch (error: any) {
            this.alertService.error(error);
            this.cdr.markForCheck();
        } finally {
            this.loading = false;
            this.cdr.markForCheck();
        }
    }

    openModalPrecio(template: TemplateRef<any>, servicio:any) {
        if(this.apiService.validateRole('super_admin', true) || this.apiService.validateRole('admin', true)) {
            this.servicio = servicio;
            this.openModal(template, servicio, {class: 'modal-sm'});
        }
    }

    public setEstado(servicio: any){
        this.onSubmit(servicio, true);
    }

    public override async onSubmit(item?: any, isStatusChange: boolean = false): Promise<void> {
        await super.onSubmit(item, isStatusChange);
        this.servicio = {};
    }

    public descargar(){
        this.downloading = true;
        this.apiService.export('servicios/exportar', this.filtros).pipe(this.untilDestroyed()).subscribe((data:Blob) => {
            const blob = new Blob([data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'servicios.xlsx';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
            this.downloading = false;
            this.cdr.markForCheck();
          }, (error) => { this.alertService.error(error); this.downloading = false; this.cdr.markForCheck(); }
        );
    }

}
