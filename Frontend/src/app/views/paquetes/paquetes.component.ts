import { Component, OnInit, TemplateRef, ViewChild } from '@angular/core';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

import * as moment from 'moment';
import Swal from 'sweetalert2';

@Component({
  selector: 'app-paquetes',
  templateUrl: './paquetes.component.html'
})

export class PaquetesComponent implements OnInit {

    public paquetes:any = [];
    public sucursales:any = [];
    public paquete:any = {};
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

        this.filtrarPaquetes();
    }

    public loadAll() {
        this.filtros.id_cliente = '';
        this.filtros.id_usuario = '';
        this.filtros.tipo = '';
        this.filtros.estado = '';
        this.filtros.buscador = '';
        this.filtros.orden = 'id';
        this.filtros.direccion = 'asc';
        this.filtros.paginate = 10;
        this.filtrarPaquetes();
    }

    public filtrarPaquetes(){
        this.loading = true;
        this.apiService.getAll('paquetes', this.filtros).subscribe(paquetes => { 
            this.paquetes = paquetes;
            this.loading = false;
            if(this.modalRef){
                this.modalRef.hide();
            }
        }, error => {this.alertService.error(error); this.loading = false;});
    }


    public openModal(template: TemplateRef<any>, paquete:any) {
        this.paquete = paquete;

        if (!this.paquete.id) {
            this.paquete.id_empresa = this.apiService.auth_user().id_empresa;
            this.paquete.id_usuario = this.apiService.auth_user().id;
            this.paquete.frecuencia = '';
            this.paquete.tipo = 'Sin confirmar';
            this.paquete.duracion = "1 hora";
            this.paquete.estado = "Activo";
            this.paquete.id_cliente = '';
            this.paquete.id_servicio = '';
            this.paquete.id_sucursal = this.apiService.auth_user().id_sucursal;
            this.paquete.inicio =  moment().format('YYYY-MM-DD HH') + ':00';
        }
        this.alertService.modal = true;
        this.modalRef = this.modalService.show(template, {class: 'modal-lg', backdrop: 'static'});
    }


    public openFilter(template: TemplateRef<any>) {
        this.alertService.modal = true;
        this.modalRef = this.modalService.show(template, {class: 'modal-lg', backdrop: 'static'});
    }


    public setEstado(paquete:any, estado:any){
        this.paquete = paquete;
        this.paquete.tipo = estado;
        this.onSubmit();
    }

    public delete(paquete:any){

        Swal.fire({
          title: '¿Estás seguro?',
          text: '¡No podrás revertir esto!',
          icon: 'warning',
          showCancelButton: true,
          confirmButtonText: 'Sí, eliminarlo',
          cancelButtonText: 'Cancelar'
        }).then((result) => {
          if (result.isConfirmed) {
                this.apiService.delete('paquete/', paquete.id) .subscribe(data => {
                    for (let i = 0; i < this.paquetes.data.length; i++) { 
                        if (this.paquetes.data[i].id == data.id )
                            this.paquetes.data.splice(i, 1);
                    }
                }, error => {this.alertService.error(error); });4
          } else if (result.dismiss === Swal.DismissReason.cancel) {
            // Swal.fire('Cancelado', 'Tu archivo está seguro :)', 'info');
          }
        });

    }

    public onSubmit(){
        this.saving = true;
        this.apiService.store('paquete', this.paquete).subscribe(paquete => {
            if (!this.paquete.id) {
                this.loadAll();
                this.alertService.success('Cita creada', 'La cita fue añadida exitosamente.');
            }else{
                this.alertService.success('Cita guardada', 'La cita fue guardada exitosamente.');
            }
            this.saving = false;
            if(this.modalRef){
                this.modalRef.hide();
            }
            this.alertService.modal = false;
        }, error => {this.alertService.error(error); this.saving = false;});
    }

}
