import { Component, OnInit, TemplateRef, ViewChild } from '@angular/core';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

import * as moment from 'moment';
import Swal from 'sweetalert2';

@Component({
  selector: 'app-catalogo-cuentas',
  templateUrl: './catalogo-cuentas.component.html'
})

export class CatalogoCuentasComponent implements OnInit {

    public cuentas:any = [];
    public sucursales:any = [];
    public clientes:any = [];
    public usuarios:any = [];
    public cuenta:any = {};
    public loading:boolean = false;
    public saving:boolean = false;
    public filtros:any = {};

    modalRef!: BsModalRef;

    constructor(public apiService: ApiService, private alertService: AlertService,
                private modalService: BsModalService
    ){}

    ngOnInit() {

        this.loadAll();
    }

    public setOrden(columna: string) {
        if (this.filtros.orden === columna) {
          this.filtros.direccion = this.filtros.direccion === 'asc' ? 'desc' : 'asc';
        } else {
          this.filtros.orden = columna;
          this.filtros.direccion = 'asc';
        }

        this.filtrarCuentas();
    }

    public loadAll() {
        this.filtros.tipo = '';
        this.filtros.buscador = '';
        this.filtros.orden = 'id';
        this.filtros.direccion = 'asc';
        this.filtros.paginate = 10;
        this.filtrarCuentas();
    }

    public filtrarCuentas(){
        this.loading = true;
        this.apiService.getAll('catalogo/cuentas', this.filtros).subscribe(cuentas => { 
            this.cuentas = cuentas;
            this.loading = false;
            if(this.modalRef){
                this.modalRef.hide();
            }
        }, error => {this.alertService.error(error); this.loading = false;});
    }


    public openModal(template: TemplateRef<any>, cuenta:any) {
        this.cuenta = cuenta;
        this.alertService.modal = true;
        this.modalRef = this.modalService.show(template, {class: 'modal-lg', backdrop: 'static'});
    }


    public openFilter(template: TemplateRef<any>) {
        this.apiService.getAll('usuarios/list').subscribe(usuarios => { 
            this.usuarios = usuarios;
        }, error => {this.alertService.error(error); });
        this.alertService.modal = true;
        this.modalRef = this.modalService.show(template, {class: 'modal-lg', backdrop: 'static'});
    }


    public setEstado(cuenta:any){
        this.cuenta = cuenta;
        this.onSubmit();
    }

    public setPagination(event:any):void{
        this.loading = true;
        this.apiService.paginate(this.cuentas.path + '?page='+ event.page, this.filtros).subscribe(cuentas => { 
            this.cuentas = cuentas;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});
    }

    public delete(cuenta:any){

        Swal.fire({
          title: '¿Estás seguro?',
          text: '¡No podrás revertir esto!',
          icon: 'warning',
          showCancelButton: true,
          confirmButtonText: 'Sí, eliminarlo',
          cancelButtonText: 'Cancelar'
        }).then((result) => {
          if (result.isConfirmed) {
                this.apiService.delete('cuenta/', cuenta.id) .subscribe(data => {
                    for (let i = 0; i < this.cuentas.data.length; i++) { 
                        if (this.cuentas.data[i].id == data.id )
                            this.cuentas.data.splice(i, 1);
                    }
                }, error => {this.alertService.error(error); });4
          } else if (result.dismiss === Swal.DismissReason.cancel) {
            // Swal.fire('Cancelado', 'Tu archivo está seguro :)', 'info');
          }
        });

    }

    public onSubmit(){
        this.saving = true;
        this.apiService.store('cuenta', this.cuenta).subscribe(cuenta => {
            if (!this.cuenta.id) {
                this.loadAll();
                this.alertService.success('Paquete creada', 'El cuenta fue añadida exitosamente.');
            }else{
                this.alertService.success('Paquete guardada', 'El cuenta fue guardada exitosamente.');
            }
            this.saving = false;
            if(this.modalRef){
                this.modalRef.hide();
            }
            this.alertService.modal = false;
        }, error => {this.alertService.error(error); this.saving = false;});
    }

}
