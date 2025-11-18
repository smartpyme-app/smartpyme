import { Component, OnInit, TemplateRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { NgSelectModule } from '@ng-select/ng-select';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { ModalManagerService } from '@services/modal-manager.service';
import { BasePaginatedModalComponent, PaginatedResponse } from '@shared/base/base-paginated-modal.component';

@Component({
    selector: 'app-materias-prima',
    templateUrl: './materias-prima.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, NgSelectModule],
    
})
export class MateriasPrimaComponent extends BasePaginatedModalComponent implements OnInit {

    public productos: PaginatedResponse<any> = {} as PaginatedResponse;
    public buscador:any = '';
    
    public filtro:any = {};
    public producto:any = {};
    public sucursales:any = [];
    public filtrado:boolean = false;
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
        if(!this.categorias.length){
            this.apiService.getAll('categorias/list')
              .pipe(this.untilDestroyed())
              .subscribe(categorias => { 
                this.categorias = categorias;
            }, error => {this.alertService.error(error); });
        }
    }

    public loadAll() {
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
            }, error => {this.alertService.error(error); this.loading = false;});
            this.loading = false; this.filtrado = false;
        }, error => {this.alertService.error(error); this.loading = false;});
    }

    public search(){
        if(this.buscador && this.buscador.length > 2) {
            this.loading = true;
            this.apiService.read('materias-primas/buscar/', this.buscador)
              .pipe(this.untilDestroyed())
              .subscribe(productos => { 
                this.productos = productos;
                this.loading = false; this.filtrado = true;
            }, error => {this.alertService.error(error); this.loading = false;});
        }else{
            this.loadAll();
        }
    }

    public delete(id:number) {
        if (confirm('¿Desea eliminar el Registro?')) {
            this.apiService.delete('materia-prima/', id)
              .pipe(this.untilDestroyed())
              .subscribe(data => {
                for (let i = 0; i < this.productos['data'].length; i++) { 
                    if (this.productos['data'][i].id == data.id )
                        this.productos['data'].splice(i, 1);
                }
            }, error => {this.alertService.error(error); });
                   
        }

    }

    public descargar(){
        window.open(this.apiService.baseUrl + '/api/productos/export' + '?token=' + this.apiService.auth_token(), 'Impresión', 'width=400');
    }

    // setPagination() ahora se hereda de BasePaginatedComponent

    // Filtros
    openFilter(template: TemplateRef<any>) {
        this.filtro.id_categoria = '';
        if(!this.categorias.length){
            this.apiService.getAll('categorias')
              .pipe(this.untilDestroyed())
              .subscribe(categorias => { 
                this.categorias = categorias;
            }, error => {this.alertService.error(error); });
        }
        this.openModal(template);
    }

    onFiltrar(){
        this.loading = true;
        this.apiService.store('materias-primas/filtrar', this.filtro)
          .pipe(this.untilDestroyed())
          .subscribe(productos => { 
            this.productos = productos;
            this.loading = false; this.filtrado = true;
            this.closeModal();
        }, error => {this.alertService.error(error); this.loading = false;});

    }

    openModalPrecio(template: TemplateRef<any>, producto:any) {
        if(this.apiService.validateRole('super_admin', true) || this.apiService.validateRole('admin', true)) {
            this.producto = producto;
            this.openModal(template, {class: 'modal-sm'});
        }

        // if(this.apiService.auth_user().tipo == 'Administrador') {
        //     this.producto = producto;
        //     this.modalRef = this.modalService.show(template, {class: 'modal-sm'});
        // }

    }

    public onSubmit() {
        this.loading = true;
        // Guardamos la caja
        this.apiService.store('producto', this.producto)
          .pipe(this.untilDestroyed())
          .subscribe(producto=> {
            this.producto= {};
            this.alertService.success('Materia prima actualizada', 'La materia prima fue guardada exitosamente.');
            this.loading = false;
            this.closeModal();
        },error => {this.alertService.error(error); this.loading = false;
        });
    }

}
