import { Component, OnInit, TemplateRef } from '@angular/core';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

@Component({
  selector: 'app-ajustes',
  templateUrl: './ajustes.component.html',
})
export class AjustesComponent implements OnInit {

	public ajustes:any = [];
    public buscador:any = '';
    public loading:boolean = false;
    public token:string = '';
    public filtro:any = {};
    public bodegas:any = [];
    public productos:any = [];
    modalRef!: BsModalRef;

    constructor(public apiService: ApiService, private alertService: AlertService,
                private modalService: BsModalService
    ){}

    ngOnInit() {
        this.token = this.apiService.auth_token();
        this.loadAll();
    }

    public loadAll() {
        this.loading = true;
        this.apiService.getAll('ajustes').subscribe(ajustes => { 
            this.ajustes = ajustes;
            this.loading = false;
        }, error => {this.alertService.error(error); });
    }

    public search(){
    	if(this.buscador && this.buscador.length > 2) {
	    	this.apiService.read('ajustes/buscar/', this.buscador).subscribe(ajustes => { 
	    	    this.ajustes = ajustes;

	    	}, error => {this.alertService.error(error); });
    	}
    }

    public delete(id:number) {
        if (confirm('¿Desea eliminar el Registro?')) {
            this.apiService.delete('ajuste/', id) .subscribe(data => {
                for (let i = 0; i < this.ajustes['data'].length; i++) { 
                    if (this.ajustes['data'][i].id == data.id )
                        this.ajustes['data'].splice(i, 1);
                }
            }, error => {this.alertService.error(error); });
                   
        }

    }

    public setPagination(event:any):void{
        this.loading = true;
        this.apiService.paginate(this.ajustes.path + '?page='+ event.page).subscribe(ajustes => { 
            this.ajustes = ajustes;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});
    }


    // Filtros
    openFilter(template: TemplateRef<any>) {
        this.filtro.categoria_id = '';
        if(!this.bodegas.length){
            this.apiService.getAll('bodegas').subscribe(bodegas => { 
                this.bodegas = bodegas;
            }, error => {this.alertService.error(error); });
        }
        if(!this.productos.length){
            this.apiService.getAll('productos/list').subscribe(productos => { 
                this.productos = productos;
            }, error => {this.alertService.error(error); });
        }
        this.modalRef = this.modalService.show(template);
    }

    onFiltrar(){
        this.loading = true;
        this.apiService.store('ajustes/filtrar', this.filtro).subscribe(ajustes => { 
            this.ajustes = ajustes;
            this.loading = false;
            this.modalRef.hide();
        }, error => {this.alertService.error(error); this.loading = false;});

    }


}
