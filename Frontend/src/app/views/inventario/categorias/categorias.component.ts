import { Component, OnInit, TemplateRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { PaginationComponent } from '@shared/parts/pagination/pagination.component';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { ModalManagerService } from '@services/modal-manager.service';
import { BaseCrudComponent } from '@shared/base/base-crud.component';

@Component({
    selector: 'app-categorias',
    templateUrl: './categorias.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, PaginationComponent],
    
})

export class CategoriasComponent extends BaseCrudComponent<any> implements OnInit {

    public categorias: any = {};
    public categoria: any = {};
    public sucursales: any = [];
    public catalogo: any = [];

    constructor(
        apiService: ApiService, 
        alertService: AlertService,
        modalManager: ModalManagerService
    ){
        super(apiService, alertService, modalManager, {
            endpoint: 'categoria',
            itemsProperty: 'categorias',
            itemProperty: 'categoria',
            messages: {
                created: 'La categoria fue añadida exitosamente.',
                updated: 'La categoria fue guardada exitosamente.',
                createTitle: 'Categoria creada',
                updateTitle: 'Categoria guardada'
            },
            initNewItem: (item) => {
                item.id_empresa = apiService.auth_user().id_empresa;
                item.enable = true;
                return item;
            }
        });
    }

    protected aplicarFiltros(): void {
        this.filtrarCategorias();
    }

    ngOnInit() {
        // Cargar datos adicionales necesarios para el componente
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

        // Cargar categorías usando el método heredado
        this.loadAll();
    }

    public override loadAll() {
        // Resetear filtros específicos de categorías
        this.filtros.id_sucursal = '';
        // Llamar al método base que resetea filtros comunes y llama a aplicarFiltros()
        super.loadAll();
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

    override openModal(template: TemplateRef<any>, categoria?: any) {
        // Usar el método heredado que maneja la inicialización del item
        // y pasar la configuración del modal
        super.openModal(template, categoria, {
            class: 'modal-lg',
            backdrop: 'static'
        });
    }

    public setEstado(categoria: any) {
        this.categoria = categoria;
        // Usar el método heredado onSubmit
        this.onSubmit();
    }

    // Los métodos onSubmit() y delete() ahora se heredan de BaseCrudComponent
    // No es necesario redefinirlos a menos que necesites comportamiento personalizado

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
