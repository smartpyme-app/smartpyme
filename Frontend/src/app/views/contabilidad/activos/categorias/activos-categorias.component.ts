import { Component, OnInit, TemplateRef } from '@angular/core';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '../../../../services/alert.service';
import { ApiService } from '../../../../services/api.service';


@Component({
  selector: 'app-activos-categorias',
  templateUrl: './activos-categorias.component.html'
})

export class ActivosCategoriasComponent implements OnInit {

    public categorias:any = [];
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
        this.apiService.getAll('activos/categorias').subscribe(categorias => { 
            this.categorias = categorias;
            this.loading = false;
        }, error => {this.alertService.error(error); });
    }

    public search(){
        if(this.buscador && this.buscador.length > 1) {
            this.loading = true;
            this.apiService.read('activos/categorias/buscar/', this.buscador).subscribe(categorias => { 
                this.categorias = categorias;
                this.loading = false;
            }, error => {this.alertService.error(error); this.loading = false; });
        }
    }


    public delete(id:number) {
        if (confirm('¿Desea eliminar el Registro?')) {
            this.apiService.delete('activos/categoria/', id) .subscribe(data => {
                for (let i = 0; i < this.categorias.length; i++) { 
                    if (this.categorias[i].id == data.id )
                        this.categorias.splice(i, 1);
                }
            }, error => {this.alertService.error(error); });
                   
        }

    }


}
