import { Component, OnInit, TemplateRef } from '@angular/core';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

import Swal from 'sweetalert2';

@Component({
  selector: 'app-gastos-categorias',
  templateUrl: './gastos-categorias.component.html'
})

export class GastosCategoriasComponent implements OnInit {

    public categorias:any = [];
    public categoria:any = {};
    public catalogo:any = [];
    public loading:boolean = false;
    public saving:boolean = false;
    public filtro:any = {};
    public filtrado:boolean = false;

    modalRef!: BsModalRef;

    constructor(public apiService: ApiService, private alertService: AlertService,
                private modalService: BsModalService
    ){}

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

    public openModal(template: TemplateRef<any>, categoria:any) {
        this.categoria = categoria;
        if (!this.categoria.id) {
            this.categoria.id_empresa = this.apiService.auth_user().id_empresa;
            this.categoria.enable = true;
        }
        this.apiService.getAll('catalogo/list').subscribe(catalogo => {
            this.catalogo = catalogo;
        }, error => {this.alertService.error(error);});
        this.modalRef = this.modalService.show(template, {class: 'modal-md', backdrop: 'static'});
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
            this.modalRef.hide();
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
