import { Component, OnInit, TemplateRef } from '@angular/core';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';


@Component({
  selector: 'app-presupuestos',
  templateUrl: './presupuestos.component.html'
})

export class PresupuestosComponent implements OnInit {

    public presupuestos:any = [];
    public presupuesto:any = {};
    public buscador:any = '';
    public loading:boolean = false;

    public clientes:any = [];
    public usuarios:any = [];
    public sucursales:any = [];
    public documentos:any = [];
    public filtro:any = {};
    public filtrado:boolean = false;

    modalRef!: BsModalRef;

    constructor(public apiService: ApiService, private alertService: AlertService,
                private modalService: BsModalService
    ){}

    ngOnInit() {
        this.loadAll();

        this.apiService.getAll('clientes/list').subscribe(clientes => { 
            this.clientes = clientes;
        }, error => {this.alertService.error(error); });
    }

    public loadAll() {
        this.loading = true;
        this.filtro.estado = '';
        // this.filtro.inicio = this.apiService.date();
        // this.filtro.fin = this.apiService.date();

        this.apiService.getAll('presupuestos').subscribe(presupuestos => { 
            this.presupuestos = presupuestos;
            this.loading = false;this.filtrado = false;
        }, error => {this.alertService.error(error); });
    }

    public search(){
        if(this.buscador && this.buscador.length > 1) {
            this.loading = true;
            this.apiService.read('presupuestos/buscar/', this.buscador).subscribe(presupuestos => { 
                this.presupuestos = presupuestos;
                this.loading = false;this.filtrado = true;
            }, error => {this.alertService.error(error); this.loading = false;this.filtrado = false; });
        }
    }

    public setAnulacion(presupuesto:any, estado:any){
        presupuesto.enable = estado;
        if(confirm('Confirma realización la acción?')){
            this.apiService.store('presupuesto', presupuesto).subscribe(presupuesto => { 
                this.alertService.success('Presupuesto actualizado', 'El presupuesto fue actualizado exitosamente.');
            }, error => {this.alertService.error(error); });
        }
    }

    public filtrar(filtro:any, txt:any){
        this.loading = true;
        this.apiService.read('presupuestos/filtrar/' + filtro + '/', txt).subscribe(presupuestos => { 
            this.presupuestos = presupuestos;
            this.loading = false;
        }, error => {this.alertService.error(error); });

    }

    public setPagination(event:any):void{
        this.loading = true;
        this.apiService.paginate(this.presupuestos.path + '?page='+ event.page).subscribe(presupuestos => { 
            this.presupuestos = presupuestos;
            this.loading = false;
        }, error => {this.alertService.error(error); this.loading = false;});
    }

    public onFiltrar(){
        this.loading = true;
        this.apiService.store('presupuestos/filtrar', this.filtro).subscribe(presupuestos => { 
            this.presupuestos = presupuestos;
            this.loading = false;
            // this.modalRef.hide();
        }, error => {this.alertService.error(error); this.loading = false;});

    }

}
