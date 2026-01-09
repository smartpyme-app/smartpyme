import { Component, OnInit, TemplateRef, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { PaginationComponent } from '@shared/parts/pagination/pagination.component';
import { AlertService } from '../../../services/alert.service';
import { ApiService } from '../../../services/api.service';
import { ModalManagerService } from '../../../services/modal-manager.service';
import { FilterPipe } from '../../../pipes/filter.pipe';
import { BaseCrudComponent } from '@shared/base/base-crud.component';
import * as moment from 'moment';

@Component({
    selector: 'app-promociones',
    templateUrl: './promociones.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, PaginationComponent],
    providers: [FilterPipe],
    changeDetection: ChangeDetectionStrategy.OnPush
})
export class PromocionesComponent extends BaseCrudComponent<any> implements OnInit {

    public productos:any = [];
    public promociones:any = [];
    public promocionesFiltradas:any = [];
    public categorias:any = [];
    public categoria:any = {};
    public subcategorias:any = [];
    public subcategoria:any = {};
    public promocion:any = {};
    public filtro:any = {};
    public producto:any = {};
    public sucursales:any = [];
    public filtrado:boolean = false;

    constructor(
        apiService: ApiService, 
        alertService: AlertService,
        modalManager: ModalManagerService,
        private filterPipe:FilterPipe,
        private cdr: ChangeDetectorRef
    ){
        super(apiService, alertService, modalManager, {
            endpoint: 'producto/promocion',
            itemsProperty: 'promociones',
            itemProperty: 'promocion',
            reloadAfterSave: false,
            reloadAfterDelete: false,
            messages: {
                created: 'La promoción fue guardad exitosamente.',
                updated: 'La promoción fue guardad exitosamente.',
                createTitle: 'Promoción guardada',
                updateTitle: 'Promoción guardada'
            },
            afterSave: (item) => {
                this.promocion = item;
            }
        });
    }

    protected aplicarFiltros(): void {
        this.loadAll();
    }

    ngOnInit() {
        this.loadAll();

        this.filtro.nombre_producto = '';
        this.filtro.enable = '';
        this.filtro.subcategoria = '';
        this.apiService.getAll('categorias')
            .pipe(this.untilDestroyed())
            .subscribe(categorias => {
                this.categorias = categorias;
                this.cdr.markForCheck();
            }, error => {this.alertService.error(error); this.cdr.markForCheck();});
    }

    public override loadAll() {
        this.loading = true;
        this.apiService.getAll('promociones')
            .pipe(this.untilDestroyed())
            .subscribe({
                next: (promociones) => {
                    this.promociones = promociones;
                    this.loading = false;
                    this.filtrado = false;
                    this.cdr.markForCheck();
                },
                error: (error) => {
                    this.alertService.error(error);
                    this.loading = false;
                    this.cdr.markForCheck();
                }
            });
    }

    public onSelectCategoria(categoria:any){
        this.categoria = this.categorias.find((item:any) => item.nombre == categoria);
        this.subcategorias = this.categoria.subcategorias;
    }

    public override delete(item: any | number): void {
        const itemToDelete = typeof item === 'number' ? item : (item as any).id;
        
        if (!confirm('¿Desea eliminar el Registro?')) {
            return;
        }

        this.loading = true;
        this.apiService.delete('producto/promocion/', itemToDelete)
            .pipe(this.untilDestroyed())
            .subscribe({
                next: (deletedItem: any) => {
                    const index = this.promociones.findIndex((p: any) => p.id === deletedItem.id);
                    if (index !== -1 && index >= 0) {
                        this.promociones.splice(index, 1);
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

    public deleteAll() {
        if (confirm('¿Desea eliminar todos los registros?')) {
            this.loading = true;
            this.apiService.getAll('producto/promociones/eliminar')
                .pipe(this.untilDestroyed())
                .subscribe({
                    next: () => {
                        this.loadAll();
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
    }

    public openModalProductos(template: TemplateRef<any>) {
        this.promocionesFiltradas = this.promociones;
     
        this.promocionesFiltradas = this.filterPipe.transform(this.promociones, ['nombre_producto'], this.filtro.nombre_producto);
        this.promocionesFiltradas = this.filterPipe.transform(this.promocionesFiltradas, ['nombre_categoria'], this.filtro.categoria);
        this.promocionesFiltradas = this.filterPipe.transform(this.promocionesFiltradas, ['nombre_subcategoria'], this.filtro.subcategoria);

        this.promocion = {};
        this.promocion.inicio = moment().startOf('month').format('YYYY-MM-DDTHH:mm');
        this.promocion.fin = moment().endOf('month').format('YYYY-MM-DDTHH:mm');
        this.promocion.tipo_descuento = 'Porcentaje';
        this.promocion.descuento = 0;

        this.openModal(template, undefined, {class: 'modal-md'});
    }

    public openModalPromocion(template: TemplateRef<any>, promocion:any) {
        this.promocion = promocion;
        if (!this.promocion.id) {
            this.promocion.inicio = moment().startOf('month').format('YYYY-MM-DDTHH:mm');
            this.promocion.fin = moment().endOf('month').format('YYYY-MM-DDTHH:mm');
        }else{
            this.promocion.inicio = moment(this.promocion.inicio).format('YYYY-MM-DDTHH:mm');
            this.promocion.fin = moment(this.promocion.fin).format('YYYY-MM-DDTHH:mm');
        }

        this.openModal(template, promocion, {class: 'modal-sm'});
    }

    public loadProductos() {
        this.loading = true;
        this.apiService.getAll('productos/list')
            .pipe(this.untilDestroyed())
            .subscribe({
                next: (productos) => {
                    this.promociones = [];
                    for (let i = 0; i < productos.length; i++) { 
                        let promocion:any = {};
                        promocion.producto = {};
                        promocion.producto_id = productos[i].id;
                        promocion.nombre_producto = productos[i].nombre;
                        promocion.producto.precio = productos[i].precio;
                        promocion.nombre_categoria = productos[i].nombre_categoria;
                        promocion.nombre_subcategoria = productos[i].nombre_subcategoria;

                        if (productos[i].promocion) {
                            promocion.id = productos[i].promocion.id;
                            promocion.precio = productos[i].promocion.precio;
                            promocion.inicio = productos[i].promocion.inicio;
                            promocion.fin = productos[i].promocion.fin;
                        }
                        this.promociones.push(promocion);
                    }
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

    public generarPromociones() {
        this.loading = true;

        for (let i = 0; i < this.promocionesFiltradas.length; i++) { 

            if (this.promocion.tipo_descuento == 'Porcentaje') {
                this.promocionesFiltradas[i].precio = this.promocionesFiltradas[i].producto.precio - (this.promocionesFiltradas[i].producto.precio * (this.promocion.descuento / 100));
            }else{
                this.promocionesFiltradas[i].precio = this.promocionesFiltradas[i].producto.precio - this.promocion.descuento;
            }

            this.promocionesFiltradas[i].inicio = moment(this.promocion.inicio).format('YYYY-MM-DDTHH:mm');
            this.promocionesFiltradas[i].fin = moment(this.promocion.fin).format('YYYY-MM-DDTHH:mm');

            this.apiService.store('producto/promocion', this.promocionesFiltradas[i])
                .pipe(this.untilDestroyed())
                .subscribe({
                    next: () => {
                        if (this.promocionesFiltradas.length == i + 1) {
                            this.alertService.success('Promociones agregadas', (i + 1) + " promociones configuradas");
                            this.loading = false;
                            this.closeModal();
                            this.loadAll();
                            this.cdr.markForCheck();
                        }
                    },
                    error: (error) => {
                        this.alertService.error(error);
                        this.loading = false;
                        this.cdr.markForCheck();
                    }
                });
        }
    }

}
