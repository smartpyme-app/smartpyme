import { Component, OnInit, TemplateRef } from '@angular/core';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '../../../services/alert.service';
import { ApiService } from '../../../services/api.service';


@Component({
  selector: 'app-activos',
  templateUrl: './activos.component.html'
})

export class ActivosComponent implements OnInit {

    public activos:any = [];
    public buscador:any = '';
    public loading:boolean = false;


    constructor(public apiService: ApiService, private alertService: AlertService,
                private modalService: BsModalService
    ){}

    ngOnInit() {
        this.loadAll();
    }

    public loadAll() {
        this.loading = true;
        this.apiService.getAll('activos').subscribe(activos => { 
            this.activos = activos;
            this.loading = false;
        }, error => {this.alertService.error(error); });
    }

    public search(){
        if(this.buscador && this.buscador.length > 1) {
            this.loading = true;
            this.apiService.read('activos/buscar/', this.buscador).subscribe(activos => { 
                this.activos = activos;
                this.loading = false;
            }, error => {this.alertService.error(error); this.loading = false; });
        }
    }


    public delete(id:number) {
        if (confirm('¿Desea eliminar el Registro?')) {
            this.apiService.delete('activo/', id) .subscribe(data => {
                for (let i = 0; i < this.activos['data'].length; i++) { 
                    if (this.activos['data'][i].id == data.id )
                        this.activos['data'].splice(i, 1);
                }
            }, error => {this.alertService.error(error); });
                   
        }

    }

    public setPagination(event:any):void{
        this.loading = true;
        this.apiService.paginate(this.activos.path + '?page='+ event.page).subscribe(activos => { 
            this.activos = activos;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});
    }



}
