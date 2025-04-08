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
    public clientes:any = [];
    public guias:any = [];
    public usuarios:any = [];
    public paquete:any = {};
    public loading:boolean = false;
    public downloading:boolean = false;
    public saving:boolean = false;
    public filtros:any = {};

    modalRef!: BsModalRef;

    constructor(public apiService: ApiService, private alertService: AlertService,
                private modalService: BsModalService
    ){}

    ngOnInit() {
        this.apiService.getAll('clientes/list').subscribe(clientes => { 
            this.clientes = clientes;
        }, error => {this.alertService.error(error); });

        this.getGuias();

        this.loadAll();
    }

    private getGuias() {   
        this.apiService.getAll('paquetes/list/guias').subscribe(paquetes => { 
            this.guias = paquetes;
        }, error => {this.alertService.error(error); });

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
        this.filtros.id_sucursal = '';
        this.filtros.id_asesor = '';
        this.filtros.id_usuario = '';
        this.filtros.tipo = '';
        this.filtros.estado = '';
        this.filtros.num_guia = '';
        this.filtros.buscador = '';
        this.filtros.orden = 'id';
        this.filtros.direccion = 'asc';
        this.filtros.paginate = 10;

        if(this.apiService.auth_user().tipo != 'Administrador'){
            this.filtros.id_sucursal = this.apiService.auth_user().id_sucursal;
        }
        
        this.filtrarPaquetes();
    }

    public filtrarPaquetes(){
        this.loading = true;
        if(!this.filtros.id_cliente){
            this.filtros.id_cliente = '';
        }

        if(!this.filtros.num_guia){
            this.filtros.num_guia = '';
        }
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
        this.alertService.modal = true;
        this.modalRef = this.modalService.show(template, {class: 'modal-lg', backdrop: 'static'});
    }


    public openFilter(template: TemplateRef<any>) {
        this.apiService.getAll('usuarios/list').subscribe(usuarios => { 
            this.usuarios = usuarios;
        }, error => {this.alertService.error(error); });
        this.apiService.getAll('sucursales/list').subscribe(sucursales => { 
            this.sucursales = sucursales;
        }, error => {this.alertService.error(error); });
        this.alertService.modal = true;
        this.modalRef = this.modalService.show(template, {class: 'modal-lg', backdrop: 'static'});
    }


    public setEstado(paquete:any){
        this.paquete = paquete;
        this.onSubmit();
    }

    public setPagination(event:any):void{
        this.loading = true;
        this.apiService.paginate(this.paquetes.path + '?page='+ event.page, this.filtros).subscribe(paquetes => { 
            this.paquetes = paquetes;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});
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

    public descargar(){
        this.downloading = true;
        this.apiService.export('paquetes/exportar', this.filtros).subscribe((data:Blob) => {
            const blob = new Blob([data], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'paquetes.xlsx';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
            this.downloading = false;
          }, (error) => { this.alertService.error(error); this.downloading = false; }
        );
    }

    public onSubmit(){
        this.saving = true;
        this.apiService.store('paquete', this.paquete).subscribe(paquete => {
            if (!this.paquete.id) {
                this.loadAll();
                this.alertService.success('Paquete creada', 'El paquete fue añadida exitosamente.');
            }else{
                this.alertService.success('Paquete guardada', 'El paquete fue guardada exitosamente.');
            }
            this.saving = false;
            if(this.modalRef){
                this.modalRef.hide();
            }
            this.alertService.modal = false;
        }, error => {this.alertService.error(error); this.saving = false;});
    }

}
