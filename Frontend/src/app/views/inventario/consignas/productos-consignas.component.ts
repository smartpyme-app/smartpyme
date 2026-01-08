import { Component, OnInit, TemplateRef, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { NgSelectModule } from '@ng-select/ng-select';
import { FilterPipe } from '@pipes/filter.pipe';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { ModalManagerService } from '@services/modal-manager.service';
import { PaginationComponent } from '@shared/parts/pagination/pagination.component';
import { BaseCrudComponent } from '@shared/base/base-crud.component';
import { LazyImageDirective } from '../../../directives/lazy-image.directive';

@Component({
    selector: 'app-productos-consignas',
    templateUrl: './productos-consignas.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, NgSelectModule, FilterPipe, PaginationComponent, LazyImageDirective],
    changeDetection: ChangeDetectionStrategy.OnPush
})
export class ProductosConsignasComponent extends BaseCrudComponent<any> implements OnInit {

    public productos:any = {};
    public buscador:any = '';
    public downloading:boolean = false;
    public producto:any = {};
    public sucursales:any = [];
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
            reloadAfterSave: true,
            reloadAfterDelete: false,
            messages: {
                created: 'Consigna guardada',
                updated: 'Consigna guardada',
                deleted: 'Consigna eliminada exitosamente.',
                createTitle: 'Consigna guardada',
                updateTitle: 'Consigna guardada',
                deleteTitle: 'Consigna eliminada',
                deleteConfirm: '¿Desea eliminar el Registro?'
            }
        });
    }

    protected aplicarFiltros(): void {
        this.onFiltrar();
    }

    ngOnInit() {
        this.loadAll();

        this.apiService.getAll('categorias/list')
          .pipe(this.untilDestroyed())
          .subscribe({
            next: (categorias) => {
              this.categorias = categorias;
              this.cdr.markForCheck();
            },
            error: (error) => {
              this.alertService.error(error);
              this.cdr.markForCheck();
            }
          });

        this.apiService.getAll('sucursales/list')
          .pipe(this.untilDestroyed())
          .subscribe({
            next: (sucursales) => {
              this.sucursales = sucursales;
              this.cdr.markForCheck();
            },
            error: (error) => {
              this.alertService.error(error);
              this.cdr.markForCheck();
            }
          });
    }

    public override loadAll() {
        this.filtros.categoria = '';
        this.loading = true;
        this.apiService.getAll('productos/consignas')
          .pipe(this.untilDestroyed())
          .subscribe({
            next: (productos) => {
              this.productos = productos;
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
            this.apiService.read('productos/buscar/', this.buscador)
              .pipe(this.untilDestroyed())
              .subscribe({
                next: (productos) => {
                  this.productos = productos;
                  this.loading = false;
                  this.cdr.markForCheck();
                },
                error: (error) => {
                  this.alertService.error(error);
                  this.loading = false;
                  this.cdr.markForCheck();
                }
              });
        }else{
            this.loadAll();
        }
    }

    public override delete(id:number) {
        if (confirm('¿Desea eliminar el Registro?')) {
            this.apiService.delete('producto/', id)
              .pipe(this.untilDestroyed())
              .subscribe({
                next: (data) => {
                  for (let i = 0; i < this.productos['data'].length; i++) { 
                      if (this.productos['data'][i].id == data.id )
                          this.productos['data'].splice(i, 1);
                  }
                  this.cdr.markForCheck();
                },
                error: (error) => {
                  this.alertService.error(error);
                  this.cdr.markForCheck();
                }
              });
        }
    }

    public onFiltrar(){
        this.loading = true;
        this.apiService.store('productos/filtrar', this.filtros)
          .pipe(this.untilDestroyed())
          .subscribe({
            next: (productos) => {
              this.productos = productos;
              this.loading = false;
              this.closeModal();
              this.cdr.markForCheck();
            },
            error: (error) => {
              this.alertService.error(error);
              this.loading = false;
              this.cdr.markForCheck();
            }
          });
    }

    public override openModal(template: TemplateRef<any>, producto?: any) {
        this.producto = producto || {};
        super.openModal(template, {
            class: 'modal-lg',
            backdrop: 'static'
        });
        this.cdr.markForCheck();
    }

    public override async onSubmit() {
        this.loading = true;
        this.saving = true;
        try {
            await this.apiService.store('producto', this.producto)
          .pipe(this.untilDestroyed())
                .toPromise();
            
              this.producto = {};
              this.alertService.success('Consigna guardada', 'La consigna fue guardado exitosamente.');
              this.closeModal();
              this.loadAll();
              this.cdr.markForCheck();
        } catch (error: any) {
              this.alertService.error(error);
        } finally {
              this.loading = false;
            this.saving = false;
            this.cdr.markForCheck();
            }
    }

    public descargar(){
        this.downloading = true;
        this.apiService.export('productos/consignas/exportar', this.filtros)
          .pipe(this.untilDestroyed())
          .subscribe({
            next: (data:Blob) => {
              const blob = new Blob([data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
              const url = window.URL.createObjectURL(blob);
              const a = document.createElement('a');
              a.href = url;
              a.download = 'consignas.xlsx';
              document.body.appendChild(a);
              a.click();
              document.body.removeChild(a);
              window.URL.revokeObjectURL(url);
              this.downloading = false;
              this.cdr.markForCheck();
            },
            error: (error) => {
              this.alertService.error(error);
              this.downloading = false;
              this.cdr.markForCheck();
            }
          });
    }

    /**
     * Verifica si Shopify está activo en la empresa
     */
    public isShopifyActive(): boolean {
        const empresa = this.apiService.auth_user()?.empresa;
        if (!empresa) return false;
        
        // Verificar si Shopify está configurado y conectado
        return !!(empresa.shopify_store_url && 
                 empresa.shopify_consumer_secret && 
                 empresa.shopify_status === 'connected');
    }

    /**
     * Verifica si el usuario puede ver las opciones de inventario
     * Oculta ciertas opciones para Supervisores de la empresa 324
     */
    public puedeVerOpcionesInventario(): boolean {
        const user = this.apiService.auth_user();
        return !(user?.tipo === 'Supervisor' && user?.id_empresa === 324);
    }

}
