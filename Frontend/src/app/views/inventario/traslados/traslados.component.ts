import { Component, OnInit, TemplateRef } from '@angular/core';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';

import { AlertService } from '../../../services/alert.service';
import { ApiService } from '../../../services/api.service';

declare var $:any;

@Component({
  selector: 'app-traslados',
  templateUrl: './traslados.component.html',
})
export class TrasladosComponent implements OnInit {

	public traslados:any = [];
    public buscador:any = '';

    public filtro:any = {};
    public filtrado:boolean = false;
    public usuarios:any = [];
    public loading:boolean = false;
    modalRef!: BsModalRef;

    constructor(public apiService: ApiService, private alertService: AlertService, 
        private modalService: BsModalService
    ){ }

    ngOnInit() {
        this.loadAll();
    }

    public loadAll() {
        this.loading = true;
        this.apiService.getAll('traslados').subscribe(traslados => { 
            this.traslados = traslados;
            this.loading = false;
        }, error => {this.alertService.error(error); });
    }

    public search(){
    	if(this.buscador && this.buscador.length > 2) {
	    	this.apiService.read('traslados/buscar/', this.buscador).subscribe(traslados => { 
	    	    this.traslados = traslados;

	    	}, error => {this.alertService.error(error); });
    	}
    }

    public delete(id:number) {
        if (confirm('¿Desea eliminar el Registro?')) {
            this.apiService.delete('traslado/', id) .subscribe(data => {
                for (let i = 0; i < this.traslados['data'].length; i++) { 
                    if (this.traslados['data'][i].id == data.id )
                        this.traslados['data'].splice(i, 1);
                }
            }, error => {this.alertService.error(error); });
                   
        }

    }

    public setPagination(event:any):void{
        this.loading = true;
        this.apiService.paginate(this.traslados.path + '?page='+ event.page).subscribe(traslados => { 
            this.traslados = traslados;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});
    }

    // Filtros
    openFilter(template: TemplateRef<any>) {

        if(!this.filtrado) {
            this.filtro.inicio = null;
            this.filtro.fin = null;
            this.filtro.usuario_id = '';
            this.filtro.estado = '';
            this.filtro.tipo = '';
        }
        if(!this.usuarios.data){
            this.apiService.getAll('usuarios/filtrar/tipo/Empleado').subscribe(usuarios => { 
                this.usuarios = usuarios.data;
            }, error => {this.alertService.error(error); });
        }
        this.modalRef = this.modalService.show(template);
    }

    onFiltrar(){
        this.loading = true;
        this.apiService.store('traslados/filtrar', this.filtro).subscribe(traslados => { 
            this.traslados = traslados;
            this.loading = false; this.filtrado = true;
            this.modalRef.hide();
        }, error => {this.alertService.error(error); this.loading = false;});

    }

}
