import { Component, OnInit, TemplateRef, ViewChild } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

import * as moment from 'moment';
import Swal from 'sweetalert2';

@Component({
    selector: 'app-cuentas',
    templateUrl: './cuentas.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule],
    
})

export class CuentasComponent implements OnInit {

    public cuentas:any = [];
    public cuenta:any = {};
    public loading:boolean = false;
    public saving:boolean = false;
    public filtros:any = {};

    modalRef!: BsModalRef;

    constructor(public apiService: ApiService, private alertService: AlertService,
                private modalService: BsModalService
    ){}

    ngOnInit() {
        this.cuenta.del = this.apiService.date();
        this.cuenta.al  = this.apiService.date();

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
        this.filtros.direccion = 'desc';
        this.filtros.paginate = 10;
        this.filtrarCuentas();
    }

    public filtrarCuentas(){
        this.loading = true;
        this.apiService.getAll('bancos/cuentas', this.filtros).subscribe(cuentas => { 
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
        this.alertService.modal = true;
        this.modalRef = this.modalService.show(template, {class: 'modal-md', backdrop: 'static'});
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

    public imprimir(cuenta:any){
        window.open(this.apiService.baseUrl + '/api/banco/cuenta/libro/' + cuenta.id + '/' + cuenta.del + '/' + cuenta.al + '?token=' + this.apiService.auth_token());
    }

    public onSubmit(){
        this.saving = true;
        this.apiService.store('cuenta', this.cuenta).subscribe(cuenta => {
            if (!this.cuenta.id) {
                this.loadAll();
                this.alertService.success('Cuenta creada', 'El cuenta fue añadida exitosamente.');
            }else{
                this.alertService.success('Cuenta guardada', 'El cuenta fue guardada exitosamente.');
            }
            this.saving = false;
            if(this.modalRef){
                this.modalRef.hide();
            }
            this.alertService.modal = false;
        }, error => {this.alertService.error(error); this.saving = false;});
    }

}
