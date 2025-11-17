import { Component, OnInit, TemplateRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { NgSelectModule } from '@ng-select/ng-select';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { ModalManagerService } from '@services/modal-manager.service';
import { BaseModalComponent } from '@shared/base/base-modal.component';

import Swal from 'sweetalert2';

@Component({
    selector: 'app-gastos-categorias',
    templateUrl: './gastos-categorias.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, NgSelectModule],
    
})

export class GastosCategoriasComponent extends BaseModalComponent implements OnInit {

    public categorias:any = [];
    public categoria:any = {};
    public catalogo:any = [];
    public filtro:any = {};
    public filtrado:boolean = false;

    constructor(
        public apiService: ApiService, 
        protected override alertService: AlertService,
        protected override modalManager: ModalManagerService
    ) {
        super(modalManager, alertService);
    }

    ngOnInit() {
        this.loadAll();
    }

    public loadAll() {        
        this.loading = true;
        this.filtro.estado = '';
        this.apiService.getAll('gastos/categorias').subscribe(categorias => { 
            this.categorias = categorias;
            this.loading = false;this.filtrado = false;
        }, error => {this.alertService.error(error); });
    }

    override openModal(template: TemplateRef<any>, categoria:any) {
        this.categoria = categoria;
        if (!this.categoria.id) {
            this.categoria.id_empresa = this.apiService.auth_user().id_empresa;
            this.categoria.enable = true;
        }
        this.apiService.getAll('catalogo/list').subscribe(catalogo => {
            this.catalogo = catalogo;
        }, error => {this.alertService.error(error);});
        super.openModal(template, { class: 'modal-md', backdrop: 'static' });
    }

    public setEstado(categoria:any){
        this.categoria = categoria;
        this.onSubmit();
    }

    public onSubmit(){
        this.saving = true;
        this.apiService.store('gastos/categoria', this.categoria).subscribe(categoria => {
            if (!this.categoria.id) {
                this.categorias.push(categoria);
                this.alertService.success('Categoria creado', 'El categoria fue añadido exitosamente.');
            }else{
                this.alertService.success('Categoria guardado', 'El categoria fue guardado exitosamente.');
            }
            this.saving = false;
            this.closeModal();
            this.loadAll();
        }, error => {this.alertService.error(error); this.saving = false;});
    }


    public delete(id:number) {

        Swal.fire({
          title: '¿Estás seguro?',
          text: '¡No podrás revertir esto!',
          icon: 'warning',
          showCancelButton: true,
          confirmButtonText: 'Sí, eliminarlo',
          cancelButtonText: 'Cancelar'
        }).then((result) => {
          if (result.isConfirmed) {
                this.apiService.delete('categoria/', id) .subscribe(data => {
                    for (let i = 0; i < this.categorias.length; i++) { 
                        if (this.categorias[i].id == data.id )
                            this.categorias.splice(i, 1);
                    }
                }, error => {this.alertService.error(error); });
          } else if (result.dismiss === Swal.DismissReason.cancel) {
            // Swal.fire('Cancelado', 'Tu archivo está seguro :)', 'info');
          }
        });


    }

}
