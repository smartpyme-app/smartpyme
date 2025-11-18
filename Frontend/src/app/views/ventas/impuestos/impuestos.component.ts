import { Component, OnInit, TemplateRef, DestroyRef, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { NgSelectModule } from '@ng-select/ng-select';
import { PopoverModule } from 'ngx-bootstrap/popover';
import { TooltipModule } from 'ngx-bootstrap/tooltip';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { ModalManagerService } from '@services/modal-manager.service';
import { BaseModalComponent } from '@shared/base/base-modal.component';
import { FilterPipe } from '@pipes/filter.pipe';
import { PaginationComponent } from '@shared/parts/pagination/pagination.component';
import { subscriptionHelper } from '@shared/utils/subscription.helper';

import Swal from 'sweetalert2';

@Component({
    selector: 'app-impuestos',
    templateUrl: './impuestos.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule, NgSelectModule, FilterPipe, PaginationComponent, PopoverModule, TooltipModule],
    
})

export class ImpuestosComponent extends BaseModalComponent implements OnInit {

    public impuestos:any = [];
    public impuesto:any = {};
    public catalogo:any = [];
    public override loading:boolean = false;
    public override saving:boolean = false;
    public filtro:any = {};
    public filtrado:boolean = false;

    private destroyRef = inject(DestroyRef);
    private untilDestroyed = subscriptionHelper(this.destroyRef);

    constructor(
        public apiService: ApiService,
        protected override alertService: AlertService,
        protected override modalManager: ModalManagerService
    ){
        super(modalManager, alertService);
    }

    ngOnInit() {
        this.loadAll();
    }

    public loadAll() {        
        this.loading = true;
        this.filtro.estado = '';
        this.apiService.getAll('impuestos')
            .pipe(this.untilDestroyed())
            .subscribe(impuestos => { 
                this.impuestos = impuestos;
                this.loading = false;this.filtrado = false;
            }, error => {this.alertService.error(error); });
    }

    public override openModal(template: TemplateRef<any>, impuesto:any) {
        this.impuesto = impuesto;
        if (!this.impuesto.id) {
            this.impuesto.id_empresa = this.apiService.auth_user().id_empresa;
            this.impuesto.enable = true;
        }
        this.apiService.getAll('catalogo/list')
            .pipe(this.untilDestroyed())
            .subscribe(catalogo => {
                this.catalogo = catalogo;
            }, error => {this.alertService.error(error);});
        super.openModal(template, {class: 'modal-md', backdrop: 'static'});
    }

    public setEstado(impuesto:any){
        this.impuesto = impuesto;
        this.onSubmit();
    }

    public onSubmit(){
        this.saving = true;
        this.apiService.store('impuesto', this.impuesto)
            .pipe(this.untilDestroyed())
            .subscribe(impuesto => {
            if (!this.impuesto.id) {
                this.impuestos.push(impuesto);
                this.alertService.success('Impuesto creado', 'El impuesto fue añadido exitosamente.');
            }else{
                this.alertService.success('Impuesto guardado', 'El impuesto fue guardado exitosamente.');
            }
            this.saving = false;
            if (this.modalRef) {
                this.closeModal();
            }
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
                this.apiService.delete('impuesto/', id)
                    .pipe(this.untilDestroyed())
                    .subscribe(data => {
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
