import { Component, OnInit, TemplateRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { NgSelectModule } from '@ng-select/ng-select';
import { FilterPipe } from '@pipes/filter.pipe';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { ModalManagerService } from '@services/modal-manager.service';
import { PaginationComponent } from '@shared/parts/pagination/pagination.component';
import { BasePaginatedModalComponent, PaginatedResponse } from '@shared/base/base-paginated-modal.component';

@Component({
    selector: 'app-productos-consignas',
    templateUrl: './productos-consignas.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, NgSelectModule, FilterPipe, PaginationComponent],
    
})
export class ProductosConsignasComponent extends BasePaginatedModalComponent implements OnInit {

    public productos: PaginatedResponse<any> = {} as PaginatedResponse;
    public buscador:any = '';
    public downloading:boolean = false;
    
    public override filtros:any = {};
    public producto:any = {};
    public sucursales:any = [];
    public categorias:any = [];

    constructor(
        apiService: ApiService, 
        alertService: AlertService,
        modalManager: ModalManagerService
    ){
        super(apiService, alertService, modalManager);
    }

    protected getPaginatedData(): PaginatedResponse | null {
        return this.productos;
    }

    protected setPaginatedData(data: PaginatedResponse): void {
        this.productos = data;
    }

    ngOnInit() {
        this.loadAll();

        this.apiService.getAll('categorias/list').subscribe(categorias => {
            this.categorias = categorias;
        }, error => {this.alertService.error(error);});

        this.apiService.getAll('sucursales/list').subscribe(sucursales => { 
            this.sucursales = sucursales;
        }, error => {this.alertService.error(error); });
    }

    public loadAll() {
        this.filtros.categoria = '';
        this.loading = true;
        this.apiService.getAll('productos/consignas').subscribe(productos => { 
            this.productos = productos;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});

    }

    public search(){
        if(this.buscador && this.buscador.length > 2) {
            this.loading = true;
            this.apiService.read('productos/buscar/', this.buscador).subscribe(productos => { 
                this.productos = productos;
                this.loading = false;
            }, error => {this.alertService.error(error); this.loading = false;});
        }else{
            this.loadAll();
        }
    }

    public delete(id:number) {
        if (confirm('¿Desea eliminar el Registro?')) {
            this.apiService.delete('producto/', id) .subscribe(data => {
                for (let i = 0; i < this.productos['data'].length; i++) { 
                    if (this.productos['data'][i].id == data.id )
                        this.productos['data'].splice(i, 1);
                }
            }, error => {this.alertService.error(error); });
                   
        }

    }

    // setPagination() ahora se hereda de BasePaginatedComponent

    public onFiltrar(){
        this.loading = true;
        this.apiService.store('productos/filtrar', this.filtros).subscribe(productos => { 
            this.productos = productos;
            this.loading = false;
            this.closeModal();
        }, error => {this.alertService.error(error); this.loading = false;});

    }

    public override openModal(template: TemplateRef<any>, producto?: any) {
        this.producto = producto || {};
        super.openModal(template, {
            class: 'modal-lg',
            backdrop: 'static'
        });
    }

    public onSubmit() {
        this.loading = true;
        this.apiService.store('producto', this.producto).subscribe(producto=> {
            this.producto = {};
            this.alertService.success('Consigna guardada', 'La consigna fue guardado exitosamente.');
            this.loading = false;
            this.closeModal();
        },error => {this.alertService.error(error); this.loading = false; });
    }


    public descargar(){
        this.downloading = true;
        this.apiService.export('productos/consignas/exportar', this.filtros).subscribe((data:Blob) => {
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
          }, (error) => { this.alertService.error(error); this.downloading = false; }
        );
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
