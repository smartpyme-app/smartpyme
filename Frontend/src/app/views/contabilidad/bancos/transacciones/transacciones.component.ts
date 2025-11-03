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
    selector: 'app-transacciones',
    templateUrl: './transacciones.component.html',
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule],
    
})

export class TransaccionesComponent implements OnInit {

    public transacciones:any = [];
    public transaccion:any = {};
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
    }

    public setOrden(columna: string) {
        if (this.filtros.orden === columna) {
          this.filtros.direccion = this.filtros.direccion === 'asc' ? 'desc' : 'asc';
        } else {
          this.filtros.orden = columna;
          this.filtros.direccion = 'asc';
        }

        this.filtrarTransacciones();
    }

    public loadAll() {
        this.filtros.tipo = '';
        this.filtros.tipo_operacion = '';
        this.filtros.estado = '';
        this.filtros.buscador = '';
        this.filtros.orden = 'fecha';
        this.filtros.direccion = 'desc';
        this.filtros.paginate = 10;
        this.filtrarTransacciones();
    }

    public filtrarTransacciones(){
        this.loading = true;
        this.apiService.getAll('bancos/transacciones', this.filtros).subscribe(transacciones => { 
            this.transacciones = transacciones;
            this.loading = false;
            if(this.modalRef){
                this.modalRef.hide();
            }
        }, error => {this.alertService.error(error); this.loading = false;});
    }


    public openModal(template: TemplateRef<any>, transaccion:any) {
        this.transaccion = transaccion;
        this.alertService.modal = true;
        this.modalRef = this.modalService.show(template, {class: 'modal-lg', backdrop: 'static'});
    }


    public openFilter(template: TemplateRef<any>) {
        this.alertService.modal = true;
        this.modalRef = this.modalService.show(template, {class: 'modal-lg', backdrop: 'static'});
    }


    public setEstado(transaccion:any, estado:any){
        Swal.fire({
          title: '¿Estás seguro?',
          text: '¡Se aprobará la transacción!',
          icon: 'warning',
          showCancelButton: true,
          confirmButtonText: 'Sí, aprobarla',
          cancelButtonText: 'Cancelar'
        }).then((result) => {
          if (result.isConfirmed) {
                this.transaccion = transaccion;
                this.transaccion.estado = estado;
                this.onSubmit();
          } else if (result.dismiss === Swal.DismissReason.cancel) {}
        });
    }

    public setPagination(event:any):void{
        this.loading = true;
        this.apiService.paginate(this.transacciones.path + '?page='+ event.page, this.filtros).subscribe(transacciones => { 
            this.transacciones = transacciones;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});
    }

    public verDocumento(transaccion:any){
        var ventana = window.open(this.apiService.baseUrl + "/img/" + transaccion.url_referencia + "?token=" + this.apiService.auth_token(), "_new", "toolbar=yes, scrollbars=yes, resizable=yes, left=100, width=900, height=900");
    }

    public delete(transaccion:any){

        Swal.fire({
          title: '¿Estás seguro?',
          text: '¡No podrás revertir esto!',
          icon: 'warning',
          showCancelButton: true,
          confirmButtonText: 'Sí, eliminarlo',
          cancelButtonText: 'Cancelar'
        }).then((result) => {
          if (result.isConfirmed) {
                this.apiService.delete('banco/transaccion/', transaccion.id) .subscribe(data => {
                    for (let i = 0; i < this.transacciones.data.length; i++) { 
                        if (this.transacciones.data[i].id == data.id )
                            this.transacciones.data.splice(i, 1);
                    }
                }, error => {this.alertService.error(error); });4
          } else if (result.dismiss === Swal.DismissReason.cancel) {
            // Swal.fire('Cancelado', 'Tu archivo está seguro :)', 'info');
          }
        });

    }

    public onSubmit(){
        this.saving = true;
        this.apiService.store('banco/transaccion', this.transaccion).subscribe(transaccion => {
            if (!this.transaccion.id) {
                this.loadAll();
                this.alertService.success('Transacción creada', 'El transaccion fue añadida exitosamente.');
            }else{
                this.alertService.success('Transacción guardada', 'El transaccion fue guardada exitosamente.');
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
        this.apiService.export('bancos/transacciones/exportar', this.filtros).subscribe((data:Blob) => {
            const blob = new Blob([data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'transacciones.xlsx';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
            this.downloading = false;
          }, (error) => { this.alertService.error(error); this.downloading = false; }
        );
    }

    generarPartidaContable(transaccion:any){
        this.apiService.store('contabilidad/partida/transaccion', transaccion).subscribe(transaccion => {
            this.alertService.success('Partida generada.', 'La partida contable fue generada exitosamente.');
        },error => {this.alertService.error(error);});
    }

}
