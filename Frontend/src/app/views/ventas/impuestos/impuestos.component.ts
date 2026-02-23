import { Component, OnInit, TemplateRef } from '@angular/core';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

import Swal from 'sweetalert2';

@Component({
  selector: 'app-impuestos',
  templateUrl: './impuestos.component.html'
})

export class ImpuestosComponent implements OnInit {

    public impuestos:any = [];
    public impuesto:any = {};
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
        // Crear una copia del objeto para evitar modificar el original
        this.impuesto = {...impuesto};
        if (!this.impuesto.id) {
            this.impuesto.id_empresa = this.apiService.auth_user().id_empresa;
            this.impuesto.enable = true;
            // Solo establecer valores por defecto para nuevos impuestos
            this.impuesto.aplica_ventas = true;
            this.impuesto.aplica_gastos = true;
            this.impuesto.aplica_compras = true;
        } else {
            // Para impuestos existentes, respetar los valores del backend
            // Convertir valores null/undefined a false, pero mantener false y true como están
            if (this.impuesto.aplica_ventas === undefined || this.impuesto.aplica_ventas === null) {
                this.impuesto.aplica_ventas = false;
            } else {
                this.impuesto.aplica_ventas = Boolean(this.impuesto.aplica_ventas);
            }
            if (this.impuesto.aplica_gastos === undefined || this.impuesto.aplica_gastos === null) {
                this.impuesto.aplica_gastos = false;
            } else {
                this.impuesto.aplica_gastos = Boolean(this.impuesto.aplica_gastos);
            }
            if (this.impuesto.aplica_compras === undefined || this.impuesto.aplica_compras === null) {
                this.impuesto.aplica_compras = false;
            } else {
                this.impuesto.aplica_compras = Boolean(this.impuesto.aplica_compras);
            }
        }
        this.modalRef = this.modalService.show(template, {class: 'modal-sm', backdrop: 'static'});
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
