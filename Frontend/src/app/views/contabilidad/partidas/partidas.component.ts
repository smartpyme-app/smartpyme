import { Component, OnInit, TemplateRef, ViewChild } from '@angular/core';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

import * as moment from 'moment';
import Swal from 'sweetalert2';

@Component({
  selector: 'app-partidas',
  templateUrl: './partidas.component.html'
})

export class PartidasComponent implements OnInit {

    public partidas:any = [];
    public partida:any = {};
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
        this.apiService.getAll('partidas', this.filtros).subscribe(partidas => { 
            this.partidas = partidas;
            this.loading = false;
            if(this.modalRef){
                this.modalRef.hide();
            }
        }, error => {this.alertService.error(error); this.loading = false;});
    }


    public openModal(template: TemplateRef<any>, partida:any) {
        this.partida = partida;
        this.alertService.modal = true;
        this.modalRef = this.modalService.show(template, {class: 'modal-lg', backdrop: 'static'});
    }


    public openFilter(template: TemplateRef<any>) {
        this.alertService.modal = true;
        this.modalRef = this.modalService.show(template, {class: 'modal-lg', backdrop: 'static'});
    }


    public setEstado(partida:any){
        this.partida = partida;
        this.onSubmit();
    }

    public setPagination(event:any):void{
        this.loading = true;
        this.apiService.paginate(this.partidas.path + '?page='+ event.page, this.filtros).subscribe(partidas => { 
            this.partidas = partidas;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});
    }

    public delete(partida:any){

        Swal.fire({
          title: '¿Estás seguro?',
          text: '¡No podrás revertir esto!',
          icon: 'warning',
          showCancelButton: true,
          confirmButtonText: 'Sí, eliminarlo',
          cancelButtonText: 'Cancelar'
        }).then((result) => {
          if (result.isConfirmed) {
                this.apiService.delete('partida/', partida.id) .subscribe(data => {
                    for (let i = 0; i < this.partidas.data.length; i++) { 
                        if (this.partidas.data[i].id == data.id )
                            this.partidas.data.splice(i, 1);
                    }
                }, error => {this.alertService.error(error); });4
          } else if (result.dismiss === Swal.DismissReason.cancel) {
            // Swal.fire('Cancelado', 'Tu archivo está seguro :)', 'info');
          }
        });

    }

    public onSubmit(){
        this.saving = true;
        this.apiService.store('partida', this.partida).subscribe(partida => {
            if (!this.partida.id) {
                this.loadAll();
                this.alertService.success('Partida creada', 'El partida fue añadida exitosamente.');
            }else{
                this.alertService.success('Partida guardada', 'El partida fue guardada exitosamente.');
            }
            this.saving = false;
            if(this.modalRef){
                this.modalRef.hide();
            }
            this.alertService.modal = false;
        }, error => {this.alertService.error(error); this.saving = false;});
    }

}
