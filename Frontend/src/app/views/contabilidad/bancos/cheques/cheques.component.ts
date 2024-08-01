import { Component, OnInit, TemplateRef, ViewChild } from '@angular/core';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

import * as moment from 'moment';
import Swal from 'sweetalert2';

@Component({
  selector: 'app-cheques',
  templateUrl: './cheques.component.html'
})

export class ChequesComponent implements OnInit {

    public cheques:any = [];
    public sucursales:any = [];
    public cuentas:any = [];
    public usuarios:any = [];
    public cheque:any = {};
    public loading:boolean = false;
    public saving:boolean = false;
    public downloading:boolean = false;
    public filtros:any = {};

    modalRef!: BsModalRef;

    constructor(public apiService: ApiService, private alertService: AlertService,
                private modalService: BsModalService
    ){}

    ngOnInit() {

        this.apiService.getAll('banco/cuentas/list').subscribe(cuentas => {
            this.cuentas = cuentas;
        }, error => {this.alertService.error(error);});

        this.loadAll();
    }

    public setOrden(columna: string) {
        if (this.filtros.orden === columna) {
          this.filtros.direccion = this.filtros.direccion === 'asc' ? 'desc' : 'asc';
        } else {
          this.filtros.orden = columna;
          this.filtros.direccion = 'asc';
        }

        this.filtrarCheques();
    }

    public loadAll() {
        this.filtros.id_cuenta = '';
        this.filtros.tipo = '';
        this.filtros.estado = '';
        this.filtros.buscador = '';
        this.filtros.orden = 'id';
        this.filtros.direccion = 'desc';
        this.filtros.paginate = 10;
        this.filtrarCheques();
    }

    public filtrarCheques(){
        this.loading = true;
        this.apiService.getAll('bancos/cheques', this.filtros).subscribe(cheques => { 
            this.cheques = cheques;
            this.loading = false;
            if(this.modalRef){
                this.modalRef.hide();
            }
        }, error => {this.alertService.error(error); this.loading = false;});
    }


    public openModal(template: TemplateRef<any>, cheque:any) {
        this.cheque = cheque;
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

    public setEstado(cheque:any, estado:any){
        Swal.fire({
          title: '¿Estás seguro?',
          text: '¡Se aprobará el cheque!',
          icon: 'warning',
          showCancelButton: true,
          confirmButtonText: 'Sí, aprobarlo',
          cancelButtonText: 'Cancelar'
        }).then((result) => {
          if (result.isConfirmed) {
                this.cheque = cheque;
                this.cheque.estado = estado;
                this.onSubmit();
          } else if (result.dismiss === Swal.DismissReason.cancel) {}
        });
    }

    public setPagination(event:any):void{
        this.loading = true;
        this.apiService.paginate(this.cheques.path + '?page='+ event.page, this.filtros).subscribe(cheques => { 
            this.cheques = cheques;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});
    }

    public delete(cheque:any){

        Swal.fire({
          title: '¿Estás seguro?',
          text: '¡No podrás revertir esto!',
          icon: 'warning',
          showCancelButton: true,
          confirmButtonText: 'Sí, eliminarlo',
          cancelButtonText: 'Cancelar'
        }).then((result) => {
          if (result.isConfirmed) {
                this.apiService.delete('banco/cheque/', cheque.id) .subscribe(data => {
                    for (let i = 0; i < this.cheques.data.length; i++) { 
                        if (this.cheques.data[i].id == data.id )
                            this.cheques.data.splice(i, 1);
                    }
                }, error => {this.alertService.error(error); });4
          } else if (result.dismiss === Swal.DismissReason.cancel) {
            // Swal.fire('Cancelado', 'Tu archivo está seguro :)', 'info');
          }
        });

    }

    public onSubmit(){
        this.saving = true;
        this.apiService.store('banco/cheque', this.cheque).subscribe(cheque => {
            if (!this.cheque.id) {
                this.loadAll();
                this.alertService.success('Cheque creado', 'El cheque fue añadido exitosamente.');
            }else{
                this.alertService.success('Cheque guardado', 'El cheque fue guardado exitosamente.');
            }
            this.saving = false;
            if(this.modalRef){
                this.modalRef.hide();
            }
            this.alertService.modal = false;
        }, error => {this.alertService.error(error); this.saving = false;});
    }

    public descargar(){
        this.downloading = true;
        this.apiService.export('bancos/cheques/exportar', this.filtros).subscribe((data:Blob) => {
            const blob = new Blob([data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'cheques.xlsx';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
            this.downloading = false;
          }, (error) => { this.alertService.error(error); this.downloading = false; }
        );
    }


}
