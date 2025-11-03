import { Component, OnInit, TemplateRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { NgSelectModule } from '@ng-select/ng-select';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { FilterPipe } from '@pipes/filter.pipe';
import { PaginationComponent } from '@shared/parts/pagination/pagination.component';

import Swal from 'sweetalert2';

@Component({
    selector: 'app-retenciones',
    templateUrl: './retenciones.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, FilterPipe, NgSelectModule, PaginationComponent],
    
})

export class RetencionesComponent implements OnInit {

    public retenciones:any = [];
    public retencion:any = {};
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
        this.apiService.getAll('retenciones').subscribe(retenciones => { 
            this.retenciones = retenciones;
            this.loading = false;this.filtrado = false;
        }, error => {this.alertService.error(error); });
    }

    public openModal(template: TemplateRef<any>, retencion:any) {
        this.retencion = retencion;
        if (!this.retencion.id) {
            this.retencion.id_empresa = this.apiService.auth_user().id_empresa;
            this.retencion.enable = true;
        }
        this.apiService.getAll('catalogo/list').subscribe(catalogo => {
            this.catalogo = catalogo;
        }, error => {this.alertService.error(error);});
        this.modalRef = this.modalService.show(template, {class: 'modal-md', backdrop: 'static'});
    }

    public setEstado(retencion:any){
        this.retencion = retencion;
        this.onSubmit();
    }

    public onSubmit(){
        this.saving = true;
        this.apiService.store('retencion', this.retencion).subscribe(retencion => {
            if (!this.retencion.id) {
                this.retenciones.push(retencion);
                this.alertService.success('Impuesto creado', 'El retencion fue añadido exitosamente.');
            }else{
                this.alertService.success('Impuesto guardado', 'El retencion fue guardado exitosamente.');
            }
            this.saving = false;
            this.modalRef.hide();
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
                this.apiService.delete('retencion/', id) .subscribe(data => {
                    for (let i = 0; i < this.retenciones.length; i++) { 
                        if (this.retenciones[i].id == data.id )
                            this.retenciones.splice(i, 1);
                    }
                }, error => {this.alertService.error(error); });
          } else if (result.dismiss === Swal.DismissReason.cancel) {
            // Swal.fire('Cancelado', 'Tu archivo está seguro :)', 'info');
          }
        });


    }

}
