import { Component, OnInit, TemplateRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { PaginationComponent } from '@shared/parts/pagination/pagination.component';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { BaseFilteredPaginatedComponent } from '@shared/base/base-filtered-paginated.component';

@Component({
    selector: 'app-categorias',
    templateUrl: './categorias.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, PaginationComponent],
    
})

export class CategoriasComponent extends BaseFilteredPaginatedComponent implements OnInit {

    public categorias: any = {};
    public categoria: any = {};
    public sucursales: any = [];
    public catalogo: any = [];

    modalRef?: BsModalRef;

    constructor(apiService: ApiService, alertService: AlertService,
                private modalService: BsModalService
    ){
        super(apiService, alertService);
    }

    protected aplicarFiltros(): void {
        this.filtrarCategorias();
    }

    ngOnInit() {
        this.loadAll();

        this.apiService.getAll('sucursales/list')
            .pipe(this.untilDestroyed())
            .subscribe(sucursales => { 
                this.sucursales = sucursales;
            }, error => {this.alertService.error(error); });

        this.apiService.getAll('catalogo/list')
            .pipe(this.untilDestroyed())
            .subscribe(catalogo => {
                this.catalogo = catalogo;
            }, error => { this.alertService.error(error); });
    }

    public loadAll() {
        this.loading = true;
        this.filtros.estado = '';
        this.filtros.id_sucursal = '';
        this.filtros.buscador = '';
        this.filtros.page = 1;
        this.filtros.paginate = 10;
        this.filtrarCategorias();
    }

    public filtrarCategorias() {
        this.loading = true;

        this.apiService.getAll('categorias', this.filtros)
            .pipe(this.untilDestroyed())
            .subscribe((response: any) => {
                // El backend devuelve un objeto de paginación de Laravel
                this.categorias = response;
                this.loading = false;
            }, error => { this.alertService.error(error); this.loading = false; });
    }

    // setPagination() ahora se hereda de BaseFilteredPaginatedComponent

    public openModal(template: TemplateRef<any>, categoria: any) {
        this.categoria = categoria;
        if (!this.categoria.id) {
            this.categoria.id_empresa = this.apiService.auth_user().id_empresa;
            this.categoria.enable = true;
        }
        this.alertService.modal = true;
        this.modalRef = this.modalService.show(template, { class: 'modal-lg', backdrop: 'static' });
    }

    public setEstado(categoria: any) {
        this.categoria = categoria;
        this.onSubmit();
    }

    public onSubmit(): void {
        this.loading = true;
        this.apiService.store('categoria', this.categoria)
            .pipe(this.untilDestroyed())
            .subscribe(categoria => {
            if (!this.categoria.id) {
                this.alertService.success('Categoria creada', 'La categoria fue añadida exitosamente.');
            }else{
                this.alertService.success('Categoria guardada', 'La categoria fue guardada exitosamente.');
            }
            this.alertService.modal = false;
            this.filtrarCategorias();
            this.loading = false;
            this.modalRef?.hide();
        }, error => { this.alertService.error(error); this.loading = false; });
    }

    public delete(categoria: any) {
        if (confirm('¿Desea eliminar el Registro?')) {
            this.apiService.delete('categoria/', categoria.id)
                .pipe(this.untilDestroyed())
                .subscribe(data => {
                this.filtrarCategorias();
            }, error => { this.alertService.error(error); });
        }
    }

    public verificarSiExiste() {
        if (this.categoria.nombre) {
            if (this.categorias.data?.filter((item: any) => item.nombre.toLowerCase() == this.categoria.nombre.toLowerCase())[0]) {
                this.alertService.warning('🚨 Alerta duplicado: Hemos encontrado otro registro similar con estos datos.',
                    'Puedes ignorar esta alerta si consideras que no estas duplicando el registros.'
                );
            }
        }
    }

}
