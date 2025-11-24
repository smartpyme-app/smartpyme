import { Component, OnInit, TemplateRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { NgSelectModule } from '@ng-select/ng-select';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { ModalManagerService } from '@services/modal-manager.service';
import { BaseCrudComponent } from '@shared/base/base-crud.component';

import Swal from 'sweetalert2';

@Component({
    selector: 'app-gastos-categorias',
    templateUrl: './gastos-categorias.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, NgSelectModule],
    
})

export class GastosCategoriasComponent extends BaseCrudComponent<any> implements OnInit {

    public categorias:any = [];
    public categoria:any = {};
    public catalogo:any = [];
    public filtro:any = {};
    public filtrado:boolean = false;

    constructor(
        apiService: ApiService, 
        alertService: AlertService,
        modalManager: ModalManagerService
    ) {
        super(apiService, alertService, modalManager, {
            endpoint: 'gastos/categoria',
            itemsProperty: 'categorias',
            itemProperty: 'categoria',
            messages: {
                created: 'El categoria fue añadido exitosamente.',
                updated: 'El categoria fue guardado exitosamente.',
                createTitle: 'Categoria creado',
                updateTitle: 'Categoria guardado'
            },
            initNewItem: (item) => {
                item.id_empresa = apiService.auth_user().id_empresa;
                item.enable = true;
                return item;
            }
        });
    }

    ngOnInit() {
        this.loadAll();
    }

    public override loadAll() {
        this.loading = true;
        this.filtro.estado = '';
        this.apiService.getAll('gastos/categorias')
          .pipe(this.untilDestroyed())
          .subscribe(categorias => { 
            this.categorias = categorias;
            this.loading = false;
            this.filtrado = false;
        }, error => {this.alertService.error(error); this.loading = false; });
    }

    protected aplicarFiltros(): void {
        this.loadAll();
    }

    override openModal(template: TemplateRef<any>, categoria?: any) {
        // Cargar catálogo antes de abrir el modal
        this.apiService.getAll('catalogo/list')
          .pipe(this.untilDestroyed())
          .subscribe(catalogo => {
            this.catalogo = catalogo;
        }, error => {this.alertService.error(error);});
        
        super.openModal(template, categoria, { class: 'modal-md', backdrop: 'static' });
    }

    public setEstado(categoria:any){
        this.categoria = categoria;
        this.onSubmit();
    }

    public override delete(item: any | number): void {
        const itemToDelete = typeof item === 'number' ? item : (item as any).id;
        
        Swal.fire({
          title: '¿Estás seguro?',
          text: '¡No podrás revertir esto!',
          icon: 'warning',
          showCancelButton: true,
          confirmButtonText: 'Sí, eliminarlo',
          cancelButtonText: 'Cancelar'
        }).then((result) => {
          if (result.isConfirmed) {
                this.loading = true;
                this.apiService.delete('categoria/', itemToDelete)
                  .pipe(this.untilDestroyed())
                  .subscribe(data => {
                    const index = this.categorias.findIndex((c: any) => c.id === data.id);
                    if (index !== -1) {
                        this.categorias.splice(index, 1);
                    }
                    this.alertService.success('Registro eliminado', 'El registro fue eliminado exitosamente.');
                    this.loading = false;
                }, error => {
                    this.alertService.error(error);
                    this.loading = false;
                });
          }
        });
    }

}
