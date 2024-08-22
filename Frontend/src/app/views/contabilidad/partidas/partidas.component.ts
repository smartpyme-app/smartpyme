import { Component, OnInit, TemplateRef, ViewChild } from '@angular/core';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

import * as moment from 'moment';
import Swal from 'sweetalert2';

@Component({
  selector: 'app-partidas',
  templateUrl: './partidas.component.html',
  styles: ['.bn_mrgn { margin-left: 10px; }']
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

        this.filtrarPartidas();
    }

    public loadAll() {
        this.filtros.tipo = '';
        this.filtros.buscador = '';
        this.filtros.orden = 'id';
        this.filtros.direccion = 'desc';
        this.filtros.paginate = 10;
        this.filtros.estado = '';
        this.filtrarPartidas();
    }

    public filtrarPartidas(){
        this.loading = true;
        this.apiService.getAll('partidas', this.filtros).subscribe(partidas => { 
            this.partidas = partidas;
            this.loading = false;
            if(this.modalRef){
                this.modalRef.hide();
            }
        }, error => {this.alertService.error(error); this.loading = false;});
    }


    // public filtrarVentas(){
    //     this.loading = true;
    //     this.apiService.getAll('ventas', this.filtros).subscribe(ventas => { 
    //         this.ventas = ventas;
    //         this.loading = false;
    //         if(this.modalRef){
    //             this.modalRef.hide();
    //         }
    //     }, error => {this.alertService.error(error); this.loading = false;});
    // }


    public openModal(template: TemplateRef<any>, partida:any) {
        this.partida = partida;
        this.alertService.modal = true;
        this.modalRef = this.modalService.show(template, {class: 'modal-lg', backdrop: 'static'});
    }


    public openFilter(template: TemplateRef<any>) {
        this.alertService.modal = true;
        this.modalRef = this.modalService.show(template, {class: 'modal-lg', backdrop: 'static'});
    }


    public setEstado(partida:any, estado:any){
        this.partida = partida;
        this.partida.estado = estado;
        this.onSubmit();
    }

    public setEstadoChange(partida:any){
        this.apiService.store('partida', partida).subscribe(producto => { 
            this.alertService.success('Partida actualizada', 'El estado de la partida fue actualizado.');
        }, error => {this.alertService.error(error); });
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

    public imprimirDiarioAux(){
        window.open(this.apiService.baseUrl + '/api/reportes/diario/auxiliar' + '?token=' + this.apiService.auth_token());
    }

    public imprimirDiarioMayor(){
        window.open(this.apiService.baseUrl + '/api/reportes/diario/mayor' + '?token=' + this.apiService.auth_token());
    }

}
