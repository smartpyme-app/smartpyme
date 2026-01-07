import { Component, OnInit, TemplateRef, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { NgSelectModule } from '@ng-select/ng-select';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { ModalManagerService } from '@services/modal-manager.service';
import { BaseCrudComponent } from '@shared/base/base-crud.component';
import { LazyImageDirective } from '../../../directives/lazy-image.directive';

@Component({
    selector: 'app-materias-prima',
    templateUrl: './materias-prima.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, NgSelectModule, LazyImageDirective],
    changeDetection: ChangeDetectionStrategy.OnPush
})
export class MateriasPrimaComponent extends BaseCrudComponent<any> implements OnInit {

    public productos:any = {};
    public buscador:any = '';
    public filtro:any = {};
    public producto:any = {};
    public sucursales:any = [];
    public filtrado:boolean = false;
    public categorias:any = [];

    constructor(
        apiService: ApiService, 
        alertService: AlertService,
        modalManager: ModalManagerService,
        private cdr: ChangeDetectorRef
    ){
        super(apiService, alertService, modalManager, {
            endpoint: 'producto',
            itemsProperty: 'productos',
            itemProperty: 'producto',
            reloadAfterSave: false,
            reloadAfterDelete: false,
            messages: {
                created: 'La materia prima fue guardada exitosamente.',
                updated: 'La materia prima fue guardada exitosamente.',
                createTitle: 'Materia prima actualizada',
                updateTitle: 'Materia prima actualizada'
            },
            afterSave: () => {
                this.producto = {};
            }
        });
    }

    protected aplicarFiltros(): void {
        this.loadAll();
    }

    ngOnInit() {
        this.loadAll();
        if(!this.categorias.length){
            this.apiService.getAll('categorias/list')
              .pipe(this.untilDestroyed())
              .subscribe(categorias => { 
                this.categorias = categorias;
                this.cdr.markForCheck();
            }, error => {this.alertService.error(error); this.cdr.markForCheck(); });
        }
    }

    public override loadAll() {
        this.filtro.id_categoria = '';
        this.loading = true;
        this.apiService.getAll('materias-primas')
          .pipe(this.untilDestroyed())
          .subscribe(productos => { 
            this.productos = productos;
            this.apiService.getAll('sucursales/list')
              .pipe(this.untilDestroyed())
              .subscribe(sucursales => { 
                this.sucursales = sucursales;
                this.cdr.markForCheck();
            }, error => {this.alertService.error(error); this.loading = false; this.cdr.markForCheck();});
            this.loading = false; 
            this.filtrado = false;
            this.cdr.markForCheck();
        }, error => {this.alertService.error(error); this.loading = false; this.cdr.markForCheck();});
    }

    public search(){
        if(this.buscador && this.buscador.length > 2) {
            this.loading = true;
            this.apiService.read('materias-primas/buscar/', this.buscador)
              .pipe(this.untilDestroyed())
              .subscribe(productos => { 
                this.productos = productos;
                this.loading = false; 
                this.filtrado = true;
                this.cdr.markForCheck();
            }, error => {this.alertService.error(error); this.loading = false; this.cdr.markForCheck();});
        }else{
            this.loadAll();
        }
    }

    public override delete(item: any | number): void {
        const itemToDelete = typeof item === 'number' ? item : (item as any).id;
        
        if (!confirm('¿Desea eliminar el Registro?')) {
            return;
        }

        this.loading = true;
        this.apiService.delete('materia-prima/', itemToDelete)
            .pipe(this.untilDestroyed())
            .subscribe({
                next: (deletedItem: any) => {
                    const index = this.productos.data?.findIndex((p: any) => p.id === deletedItem.id);
                    if (index !== -1 && index >= 0) {
                        this.productos.data.splice(index, 1);
                    }
                    this.alertService.success('Registro eliminado', 'El registro fue eliminado exitosamente.');
                    this.loading = false;
                    this.cdr.markForCheck();
                },
                error: (error: any) => {
                    this.alertService.error(error);
                    this.loading = false;
                    this.cdr.markForCheck();
                }
            });
    }

    public descargar(){
        window.open(this.apiService.baseUrl + '/api/productos/export' + '?token=' + this.apiService.auth_token(), 'Impresión', 'width=400');
    }

    openFilter(template: TemplateRef<any>) {
        this.filtro.id_categoria = '';
        if(!this.categorias.length){
            this.apiService.getAll('categorias')
              .pipe(this.untilDestroyed())
              .subscribe(categorias => { 
                this.categorias = categorias;
                this.cdr.markForCheck();
            }, error => {this.alertService.error(error); this.cdr.markForCheck(); });
        }
        this.openModal(template);
    }

    onFiltrar(){
        this.loading = true;
        this.apiService.store('materias-primas/filtrar', this.filtro)
          .pipe(this.untilDestroyed())
          .subscribe(productos => { 
            this.productos = productos;
            this.loading = false; 
            this.filtrado = true;
            this.closeModal();
            this.cdr.markForCheck();
        }, error => {this.alertService.error(error); this.loading = false; this.cdr.markForCheck();});
    }

    openModalPrecio(template: TemplateRef<any>, producto:any) {
        if(this.apiService.validateRole('super_admin', true) || this.apiService.validateRole('admin', true)) {
            this.producto = producto;
            this.openModal(template, producto, {class: 'modal-sm'});
        }
    }

}
