import { Component, OnInit, TemplateRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { ModalManagerService } from '@services/modal-manager.service';
import { BaseCrudComponent } from '@shared/base/base-crud.component';
import { LazyImageDirective } from '../../../../directives/lazy-image.directive';

@Component({
    selector: 'app-vendedor-productos',
    templateUrl: './vendedor-productos.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, LazyImageDirective],
    
})
export class VendedorProductosComponent extends BaseCrudComponent<any> implements OnInit {

    public productos: any = {};
    public usuario: any = {};
    public producto: any = {};
    public sucursales: any = [];
    public categorias: any = [];

    constructor(
        apiService: ApiService,
        alertService: AlertService,
        modalManager: ModalManagerService
    ){
        super(apiService, alertService, modalManager, {
            endpoint: 'producto',
            itemsProperty: 'productos',
            itemProperty: 'producto',
            reloadAfterSave: false,
            reloadAfterDelete: false,
            messages: {
                created: 'El producto fue guardado exitosamente.',
                updated: 'El producto fue guardado exitosamente.',
                deleted: 'Producto eliminado exitosamente.',
                createTitle: 'Producto guardado',
                updateTitle: 'Producto guardado',
                deleteTitle: 'Producto eliminado',
                deleteConfirm: '¿Desea eliminar el Registro?'
            },
            afterSave: () => {
                this.producto = {};
            }
        });
    }

    protected aplicarFiltros(): void {
        this.filtrarProductos();
    }

    ngOnInit() {
        this.loadAll();

        this.usuario = this.apiService.auth_user();

        this.apiService.getAll('categorias')
          .pipe(this.untilDestroyed())
          .subscribe({
            next: (categorias) => {
            this.categorias = categorias;
            },
            error: (error) => {
                this.alertService.error(error);
            }
          });

        this.apiService.getAll('sucursales/list')
          .pipe(this.untilDestroyed())
          .subscribe({
            next: (sucursales) => {
            this.sucursales = sucursales;
            },
            error: (error) => {
                this.alertService.error(error);
            }
          });
    }

    public override loadAll() {
        this.filtros.id_sucursal = '';
        this.filtros.id_categoria = '';
        this.filtros.buscador = '';
        this.filtros.orden = 'nombre';
        this.filtros.direccion = 'asc';
        this.filtros.paginate = 10;

        this.loading = true;
        this.filtrarProductos();
    }

    public filtrarProductos(){
        this.loading = true;
        this.apiService.getAll('productos', this.filtros)
          .pipe(this.untilDestroyed())
          .subscribe({
            next: (productos) => {
            this.productos = productos;
            this.loading = false;
            },
            error: (error) => {
                this.alertService.error(error);
                this.loading = false;
            }
          });
    }

    public setEstado(producto: any){
        this.onSubmit(producto, true);
    }

    public override delete(id: number) {
        super.delete(id);
    }

    public setOrden(columna: string) {
        if (this.filtros.orden === columna) {
          this.filtros.direccion = this.filtros.direccion === 'asc' ? 'desc' : 'asc';
        } else {
          this.filtros.orden = columna;
          this.filtros.direccion = 'asc';
        }

        this.filtrarProductos();
    }

    public descargar(){
        this.apiService.export('productos/exportar', this.filtros)
          .pipe(this.untilDestroyed())
          .subscribe({
            next: (data: Blob) => {
            const blob = new Blob([data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'productos.xlsx';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
            },
            error: (error) => {
                console.error('Error al exportar productos:', error);
            }
          });
    }
}
