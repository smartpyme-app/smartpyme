import { Component, OnInit, TemplateRef, ViewChild } from '@angular/core';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

import * as moment from 'moment';
import Swal from 'sweetalert2';

@Component({
  selector: 'app-conciliaciones',
  templateUrl: './conciliaciones.component.html'
})

export class ConciliacionesComponent implements OnInit {

    public conciliaciones:any = [];
    public conciliacion:any = {};
    public cuentas:any = [];
    public usuarios:any = [];
    public loading:boolean = false;
    public saving:boolean = false;
    public downloading:boolean = false;
    public filtros:any = {};

    modalRef!: BsModalRef;

    constructor(public apiService: ApiService, private alertService: AlertService,
                private modalService: BsModalService
    ){}

    ngOnInit() {
        this.loadAll();

        this.apiService.getAll('banco/cuentas/list').subscribe(cuentas => {
            this.cuentas = cuentas;
        }, error => {this.alertService.error(error);});
    }

    public setOrden(columna: string) {
        if (this.filtros.orden === columna) {
          this.filtros.direccion = this.filtros.direccion === 'asc' ? 'desc' : 'asc';
        } else {
          this.filtros.orden = columna;
          this.filtros.direccion = 'asc';
        }

        this.filtrarConciliaciones();
    }

    public loadAll() {

        this.filtros.id_cuenta = '';
        this.filtros.inicio = '';
        this.filtros.fin = '';
        this.filtros.estado = '';
        this.filtros.buscador = '';
        this.filtros.id_usuario = '';
        this.filtros.orden = 'fecha';
        this.filtros.direccion = 'desc';
        this.filtros.paginate = 10;
        this.filtrarConciliaciones();
    }

    public filtrarConciliaciones(){
        this.loading = true;
        this.apiService.getAll('bancos/conciliaciones', this.filtros).subscribe(conciliaciones => { 
            this.conciliaciones = conciliaciones;
            this.loading = false;
            if(this.modalRef){
                this.modalRef.hide();
            }
        }, error => {this.alertService.error(error); this.loading = false;});
    }


    public openModal(template: TemplateRef<any>, conciliacion:any) {
        this.conciliacion = conciliacion;
        this.alertService.modal = true;
        this.modalRef = this.modalService.show(template, {class: 'modal-md', backdrop: 'static'});
    }


    public openFilter(template: TemplateRef<any>) {

        if(!this.usuarios.length){
            this.apiService.getAll('usuarios/list').subscribe(usuarios => { 
                this.usuarios = usuarios;
            }, error => {this.alertService.error(error); });
        }
        this.filtros.inicio = this.apiService.date();
        this.filtros.fin    = this.apiService.date();

        this.alertService.modal = true;
        this.modalRef = this.modalService.show(template, {class: 'modal-md', backdrop: 'static'});
    }


    public setEstado(conciliacion:any){
        this.conciliacion = conciliacion;
        this.onSubmit();
    }

    public setPagination(event:any):void{
        this.loading = true;
        this.apiService.paginate(this.conciliaciones.path + '?page='+ event.page, this.filtros).subscribe(conciliaciones => { 
            this.conciliaciones = conciliaciones;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});
    }

    public delete(conciliacion:any){

        Swal.fire({
          title: '¿Estás seguro?',
          text: '¡No podrás revertir esto!',
          icon: 'warning',
          showCancelButton: true,
          confirmButtonText: 'Sí, eliminarlo',
          cancelButtonText: 'Cancelar'
        }).then((result) => {
          if (result.isConfirmed) {
                this.apiService.delete('banco/conciliacion/', conciliacion.id) .subscribe(data => {
                    for (let i = 0; i < this.conciliaciones.data.length; i++) { 
                        if (this.conciliaciones.data[i].id == data.id )
                            this.conciliaciones.data.splice(i, 1);
                    }
                    this.alertService.success('Conciliación eliminada', 'La conciliación fue eliminada exitosamente.');
                }, error => {this.alertService.error(error); });4
          } else if (result.dismiss === Swal.DismissReason.cancel) {
            // Swal.fire('Cancelado', 'Tu archivo está seguro :)', 'info');
          }
        });

    }

    public onSubmit(){
        this.saving = true;
        this.apiService.store('banco/conciliacion', this.conciliacion).subscribe(conciliacion => {
            if (!this.conciliacion.id) {
                this.loadAll();
                this.alertService.success('Conciliación creada', 'La conciliación fue añadida exitosamente.');
            }else{
                this.alertService.success('Conciliación guardada', 'La conciliación fue guardada exitosamente.');
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
        this.apiService.export('bancos/conciliaciones/exportar', this.filtros).subscribe((data:Blob) => {
            const blob = new Blob([data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'conciliaciones.xlsx';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
            this.downloading = false;
          }, (error) => { this.alertService.error(error); this.downloading = false; }
        );
    }

}
