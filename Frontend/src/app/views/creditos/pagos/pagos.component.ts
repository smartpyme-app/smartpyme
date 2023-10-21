import { Component, OnInit, TemplateRef } from '@angular/core';

import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';

import { ApiService } from '../../../services/api.service';
import { AlertService } from '../../../services/alert.service';

@Component({
  selector: 'app-pagos',
  templateUrl: './pagos.component.html',
})
export class PagosComponent implements OnInit {

    public pagos:any;
    public loading:boolean = false;

    public filtro:any = {};
    public filtrado:boolean = false;
    public usuarios:any = [];
    public sucursales:any = [];
    
    modalRef?: BsModalRef;

    constructor( public apiService:ApiService, private alertService:AlertService, private modalService: BsModalService ){}

    ngOnInit() {
        this.loadAll();
    }

    public loadAll() {
        this.loading = true;
        this.apiService.getAll('pagos').subscribe(pagos => { 
            this.pagos = pagos;
            this.loading = false; this.filtrado = false;
        }, error => {this.alertService.error(error); this.loading = false;});
    }

    public search(buscador:any){
        if(buscador && buscador.length > 2) {
            this.loading = true;
            this.apiService.read('pagos/buscar/', buscador).subscribe(pagos => { 
                this.pagos = pagos;
                this.loading = false;
            }, error => {this.alertService.error(error); this.loading = false;});
        }else{
            this.loadAll();
        }
    }

    public delete(orden:any){
        if (confirm('¿Desea eliminar el Registro?')) {
            this.apiService.delete('orden/', orden.id) .subscribe(data => {
                for (let i = 0; i < this.pagos.data.length; i++) { 
                    if (this.pagos.data[i].id == data.id )
                        this.pagos.data.splice(i, 1);
                }
            }, error => {this.alertService.error(error); });
                   
        }
    }

    public setEstado(orden:any, estado:any):void{
        this.loading = true;
        orden.estado = estado;
        this.apiService.store('orden', orden).subscribe(data => {
            this.loading = false;
            orden = data;
            this.alertService.success('Guardado');
        },error => {this.alertService.error(error); this.loading = false; });
    }

    public setPagination(event:any):void{
        this.loading = true;
        this.apiService.paginate(this.pagos.path + '?page='+ event.page).subscribe(pagos => { 
            this.pagos = pagos;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});
    }


    // Filtros
    openFilter(template: TemplateRef<any>) {     

        if(!this.filtrado) {
            this.filtro.inicio = this.apiService.date();
            this.filtro.fin = this.apiService.date();
            this.filtro.sucursal_id = '';
            this.filtro.usuario_id = '';
            this.filtro.estado = '';
            this.filtro.metodo_pago = '';
            this.filtro.tipo_documento = '';
        }
        if(!this.sucursales.data){
            this.apiService.getAll('sucursales').subscribe(sucursales => { 
                this.sucursales = sucursales;
            }, error => {this.alertService.error(error); });
        }
        this.modalRef = this.modalService.show(template);
    }

    onFiltrar(){
        this.loading = true;
        this.apiService.store('pagos/filtrar', this.filtro).subscribe(pagos => { 
            this.pagos = pagos;
            this.loading = false; this.filtrado = true;
            this.modalRef!.hide();
        }, error => {this.alertService.error(error); this.loading = false;});

    }


}
