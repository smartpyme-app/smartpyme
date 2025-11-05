import { Component, OnInit, TemplateRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { NgSelectModule } from '@ng-select/ng-select';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { PopoverModule } from 'ngx-bootstrap/popover';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { FilterPipe } from '@pipes/filter.pipe';
import { PaginationComponent } from '@shared/parts/pagination/pagination.component';

import Swal from 'sweetalert2';

@Component({
    selector: 'app-impuestos',
    templateUrl: './impuestos.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, NgSelectModule, FilterPipe, PaginationComponent, PopoverModule, TooltipModule],
    
})

export class ImpuestosComponent implements OnInit {

    public impuestos:any = [];
    public impuesto:any = {};
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
        this.apiService.getAll('impuestos').subscribe(impuestos => { 
            this.impuestos = impuestos;
            this.loading = false;this.filtrado = false;
        }, error => {this.alertService.error(error); });
    }

    public openModal(template: TemplateRef<any>, impuesto:any) {
        this.impuesto = impuesto;
        if (!this.impuesto.id) {
            this.impuesto.id_empresa = this.apiService.auth_user().id_empresa;
            this.impuesto.enable = true;
        }
        this.apiService.getAll('catalogo/list').subscribe(catalogo => {
            this.catalogo = catalogo;
        }, error => {this.alertService.error(error);});
        this.modalRef = this.modalService.show(template, {class: 'modal-md', backdrop: 'static'});
    }

    public setEstado(impuesto:any){
        this.impuesto = impuesto;
        this.onSubmit();
    }

    public onSubmit(){
        this.saving = true;
        this.apiService.store('impuesto', this.impuesto).subscribe(impuesto => {
            if (!this.impuesto.id) {
                this.impuestos.push(impuesto);
                this.alertService.success('Impuesto creado', 'El impuesto fue añadido exitosamente.');
            }else{
                this.alertService.success('Impuesto guardado', 'El impuesto fue guardado exitosamente.');
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
                this.apiService.delete('impuesto/', id) .subscribe(data => {
                    for (let i = 0; i < this.impuestos.length; i++) { 
                        if (this.impuestos[i].id == data.id )
                            this.impuestos.splice(i, 1);
                    }
                }, error => {this.alertService.error(error); });
          } else if (result.dismiss === Swal.DismissReason.cancel) {
            // Swal.fire('Cancelado', 'Tu archivo está seguro :)', 'info');
          }
        });


    }

}
